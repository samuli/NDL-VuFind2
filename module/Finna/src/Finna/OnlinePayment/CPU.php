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
    const PAYMENT_NOTIFY = 'notify';

    /**
     * Start transaction.
     *
     * @param string $finesUrl           Return URL to MyResearch/Fines
     * @param string $ajaxUrl            Base URL for AJAX-actions
     * @param int    $user             User ID
     * @param string $patronId           Patron's catalog username (e.g. barcode)
     * @param string $driver             Patron MultiBackend ILS source
     * @param int    $amount             Amount (excluding transaction fee)
     * @param int    $transactionFee     Transaction fee
     * @param array  $fines              Fines data
     * @param strin  $currency           Currency
     * @param string $statusParam        Payment status URL parameter
     * @param string $transactionIdParam Transaction Id URL parameter
     *
     * @return false on error, otherwise redirects to payment handler.
     */
    public function startPayment(
        $finesUrl, $ajaxUrl, $userId, $patronId, $driver, $amount, $transactionFee,
        $fines, $currency, $statusParam, $transactionIdParam
    ) {
        $orderNumber = $this->generateTransactionId($patronId);

        $returnUrl
            = "{$finesUrl}?{$transactionIdParam}=" . urlencode($orderNumber);

        $notifyUrl
            = "{$ajaxUrl}/cpuNotify?{$statusParam}=" . self::PAYMENT_NOTIFY
            . "&{$transactionIdParam}=" . urlencode($orderNumber);

        $totAmount = ($amount + $transactionFee);
        
        $payment = new \Cpu_Client_Payment($orderNumber);
        /*
          $payment->Email = '';
          $payment->FirstName = '';
          $payment->LastName  = '';
        */
        $payment->Description
            = isset($config->paymentDescription) ? $config->paymentDescription : '';

        $payment->ReturnAddress = $returnUrl;
        $payment->NotificationAddress = $notifyUrl;

        // TODO: check if defined in ini
        $productCode = $this->config->productCode;

        $productDescription = '';
        $payment = $payment->addProduct(
            new \Cpu_Client_Product($productCode, 1, $totAmount, $productDescription)
        );

        if (!$module = $this->initCpu()) {
            $this->logger->err('CPU: error starting payment processing.');
            return false;
        }

        $response = $module->sendPayment($payment);
        if (!$response) {
            $this->error('error sending payment');
            return false;            
        }

        $response = json_decode($response);

        //echo("payment: " . var_export($payment->convertToArray(), true));
        //echo("res: " . var_export($response, true));

        if (empty($response->Id) || empty($response->Status)) {
            $this->error('error starting payment, no response');
            return false;            
        }

        $status = $response->Status;
        if ($status === 98 || $status === 99) {
            // System error or Request failed.
            $this->error('error starting transaction', $response);
            return false;            
        }

        $params = [
            $orderNumber, $response->Status,
            $response->Reference, $response->PaymentAddress
        ];
        if (!$this->verifyHash($params, $response->Hash)) {
            $this->error('error starting transaction, invalid hash', $response);
            return false;            
        }
        
        if ($status === 1) {
            // Already processed
            $this->error('error starting transaction, transaction already processed', $response);
            return false;
        }

        if ($status === 97) {
            // Order exists
            $this->error('error starting transaction, order exists', $response);
            return false;
        }

        if ($status === 0) {
            // Cancelled
            $this->error('error starting transaction, order cancelled', $response);
            return false;
        }

        if ($status === 2) {
            // Pending
            header('Location: ' . $response->PaymentAddress, true, 302);
            return true;
        }

        return false;
        
    }

    /**
     * Process the response from payment service.
     *
     * @param array $params Response variables
     *
     * @return string error message (not translated)
     *   or associative array with keys:
     *     'markFeesAsPaid' (boolean) true if payment was successful and fees
     *     should be registered as paid.
     *     'transactionId' (string) Transaction ID.
     *     'amount' (int) Amount to be registered (does not include transaction fee).
     */
    public function processResponse($params)
    {
    
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

    protected function verifyHash($params, $hash)
    {
        $params[] = $this->config['secret'];
         return hash('sha256', implode('&', $params)) === $hash;
    }

    protected function error($msg, $response = '') {
        $this->logger->err(
            "CPU error: $msg (response: " . json_encode($response) . ')'
        );
    }
}
