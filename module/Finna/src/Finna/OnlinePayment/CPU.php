<?php
/**
 * CPU payment handler
 *
 * PHP version 5
 *
 * Copyright (C) The National Library of Finland 2014.
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License version 2,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 *
 * @category VuFind
 * @package  OnlinePayment
 * @author   Leszek Manicki <leszek.z.manicki@helsinki.fi>
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 * @link     http://docs.paytrail.com/ Paytrail API documentation
 */
namespace Finna\OnlinePayment;

use Finna\OnlinePayment\BaseHandler;

require_once 'Cpu/Client.class.php';
require_once 'Cpu/Client/Payment.class.php';
require_once 'Cpu/Client/Product.class.php';

/**
 * Paytrail payment handler module.
 *
 * @category VuFind
 * @package  OnlinePayment
 * @author   Leszek Manicki <leszek.z.manicki@helsinki.fi>
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 * @link     http://docs.paytrail.com/ Paytrail API documentation
 */
class CPU extends BaseHandler
{
    const STATUS_SUCCESS = 1;
    const STATUS_CANCELLED = 0;
    const STATUS_PENDING = 2;
    const STATUS_ID_EXISTS = 97;
    const STATUS_ERROR = 98;
    const STATUS_INVALID_REQUEST = 99;

    const PAYMENT_NOTIFY = 'notify';

    /**
     * Start transaction.
     *
     * @param string             $finesUrl       Return URL to MyResearch/Fines
     * @param string             $ajaxUrl        Base URL for AJAX-actions
     * @param \Finna\Db\Row\User $user           User
     * @param string             $patronId       Patron's catalog username
     * (e.g. barcode)
     * @param string             $driver         Patron MultiBackend ILS source
     * @param int                $amount         Amount
     * (excluding transaction fee)
     * @param int                $transactionFee Transaction fee
     * @param array              $fines          Fines data
     * @param strin              $currency       Currency
     * @param string             $statusParam    Payment status URL parameter
     *
     * @return false on error, otherwise redirects to payment handler.
     */
    public function startPayment(
        $finesUrl, $ajaxUrl, $user, $patronId, $driver, $amount, $transactionFee,
        $fines, $currency, $statusParam
    ) {
        $orderNumber = $this->generateTransactionId($patronId);

        $returnUrl
            = "{$finesUrl}?driver={$driver}"
            . "&{$statusParam}=1";

        $notifyUrl
            = "{$ajaxUrl}/onlinePaymentNotify?driver={$driver}"
            . "&{$statusParam}=1";
        
        $totAmount = ($amount + $transactionFee);
        
        $payment = new \Cpu_Client_Payment($orderNumber);
        $payment->Email = $user->email;
        $payment->FirstName = $user->firstname;
        $payment->LastName  = $user->lastname;
        
        $payment->Description
            = isset($this->config->paymentDescription)
            ? $this->config->paymentDescription : '';

        $payment->ReturnAddress = $returnUrl;
        $payment->NotificationAddress = $notifyUrl;

        if (!isset($this->config->productCode)) {
            $this->handleCPUError('missing productCode configuration option');
            return false;
        }
        $productCode = $this->config->productCode;

        foreach ($fines as $fine) {
            $fineDesc = $fine['fine'];
            if (!empty($fine['title'])) {
                $fineDesc .= ' (' . $fine['title'] . ')';
            }
            $product = new \Cpu_Client_Product(
                $productCode, 1, $fine['amount'], $fineDesc
            );
            $payment = $payment->addProduct($product);   
        }
        if ($transactionFee) {
            $product = new \Cpu_Client_Product(
                $productCode, 1, $transactionFee,
                'Palvelumaksu / Serviceavgift / Transaction fee'
            );
            $payment = $payment->addProduct($product);               
        }

        if (!$module = $this->initCpu()) {
            $this->handleCPUError('error initing CPU online payment');
            return false;
        }

        $response = $module->sendPayment($payment);
        if (!$response) {
            $this->handleCPUError('error sending payment');
            return false;            
        }

        $response = json_decode($response);

        if (empty($response->Id) || empty($response->Status)) {
            $this->handleCPUError('error starting payment, no response');
            return false;            
        }

        $status = intval($response->Status);
        if (in_array($status, [self::STATUS_ERROR, self::STATUS_INVALID_REQUEST])) {
            // System error or Request failed.
            $this->handleCPUError('error starting transaction', $response);
            return false;            
        }

        $params = [
            $orderNumber, $status,
            $response->Reference, $response->PaymentAddress
        ];
        if (!$this->verifyHash($params, $response->Hash)) {
            $this->handleCPUError(
                'error starting transaction, invalid checksum', $response
            );
            return false;            
        }
        
        if ($status === self::STATUS_SUCCESS) {
            // Already processed
            $this->handleCPUError(
                'error starting transaction, transaction already processed',
                $response
            );
            return false;
        }

        if ($status === self::STATUS_ID_EXISTS) {
            // Order exists
            $this->handleCPUError(
                'error starting transaction, order exists',
                $response
            );
            return false;
        }

        if ($status === self::STATUS_CANCELLED) {
            // Cancelled
            $this->handleCPUError('error starting transaction, order cancelled', $response);
            return false;
        }

        if ($status === self::STATUS_PENDING) {
            // Pending

            if (!$this->createTransaction(
                $orderNumber,
                $driver,
                $user->id,
                $patronId,
                $amount,
                $transactionFee,
                $currency,
                $fines
            )) {
                return false;
            }
            $this->redirectToPayment($response->PaymentAddress);
        }
        return false;
    }

    /**
     * Return payment response parameters.
     *
     * @param Zend\Http\Request $request Request
     *
     * @return array
     */
    public function getPaymentResponseParams($request)
    {
        $params = array_merge(
            $request->getQuery()->toArray(),
            $request->getPost()->toArray()
        );
        $payload = json_decode($request->getContent());

        $required = ['Id', 'Status', 'Reference', 'Hash'];
        $response = [];
        
        foreach ($required as $name) {
            if (isset($payload->$name)) {
                $response[$name] = $payload->$name;
                continue;
            }
            if (isset($params[$name])) {
                $response[$name] = $params[$name];
                continue;
            }

            $this->handleCPUError(
                "missing parameter $name in payment response", 
                ['params' => $params, 'payload' => $payload]
            );

            return false;
        }

        $result = array_merge($response, $params);
        $result['transaction'] = $result['Id'];
        
        return $result;
    }

    /**
     * Process the response from payment service.
     *
     * @param Zend\Http\Request $request Request
     *
     * @return string error message (not translated)
     *   or associative array with keys:
     *     'markFeesAsPaid' (boolean) true if payment was successful and fees
     *     should be registered as paid.
     *     'transactionId' (string) Transaction ID.
     *     'amount' (int) Amount to be registered (does not include transaction fee).
     */
    public function processResponse($request)
    {
        if (!$params = $this->getPaymentResponseParams($request)) {
            return 'online_payment_failed';
        }

        $id = $params['Id'];
        $status = intval($params['Status']);
        $reference = $params['Reference'];
        $orderNum = $params['transaction'];
        $hash = $params['Hash'];

        if (!$this->verifyHash([$id, $status, $reference], $hash)) {
            $this->handleCPUError(
                'error processing response: invalid checksum', $response
            );
            return 'online_payment_failed';
        }

        list($success, $data) = $this->getStartedTransaction($orderNum);
        if (!$success) {
            return $data;
        }

        if ($status === self::STATUS_SUCCESS) {
            $this->setTransactionPaid($orderNum);
            return [
               'markFeesAsPaid' => true,
               'transactionId' => $orderNum,
               'amount' => $data->amount
            ];
        } else if ($status === self::STATUS_CANCELLED) {
            $this->setTransactionCancelled($orderNum);
            return 'online_payment_canceled';
        } else {
            return 'online_payment_failed';
        }
    }

    /**
     * Init Paytrail module with configured merchantId, secret and URL.
     *
     * @return Paytrail_Module_Rest module.
     */
    protected function initCpu()
    {
        foreach (['merchantId', 'secret', 'url'] as $req) {
            if (!isset($this->config[$req])) {
                $this->logger->err("Paytrail: missing parameter $req");
                return false;
            }
        }

        return new \Cpu_Client(
            $this->config['url'],
            $this->config['merchantId'],
            $this->config['secret']
        );
    }

    /**
     * Verify transaction response hash.
     *
     * @param array  $params Parameters
     * @param string $hash   Hash
     *
     * @return boolean
     */
    protected function verifyHash($params, $hash)
    {
        $params[] = $this->config['secret'];
        return hash('sha256', implode('&', $params)) === $hash;
    }

    /**
     * Handle error.
     *
     * @param string $msg      Error message.
     * @param Object $response Response.
     *
     * @return void
     */
    protected function handleCPUError($msg, $response = '') {
        $this->logger->err(
            "CPU error: $msg (response: " . var_export($response, true) . ')'
        );
    }
}
