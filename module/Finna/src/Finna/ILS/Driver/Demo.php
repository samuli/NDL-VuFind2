<?php
/**
 * Advanced Dummy ILS Driver -- Returns sample values based on Solr index.
 *
 * Note that some sample values (holds, transactions, fines) are stored in
 * the session.  You can log out and log back in to get a different set of
 * values.
 *
 * PHP version 5
 *
 * Copyright (C) Villanova University 2007.
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
 * @category VuFind2
 * @package  ILS_Drivers
 * @author   Greg Pendlebury <vufind-tech@lists.sourceforge.net>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:building_an_ils_driver Wiki
 */
namespace Finna\ILS\Driver;
use VuFind\Exception\ILS as ILSException;

/**
 * Advanced Dummy ILS Driver -- Returns sample values based on Solr index.
 *
 * @category VuFind2
 * @package  ILS_Drivers
 * @author   Greg Pendlebury <vufind-tech@lists.sourceforge.net>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:building_an_ils_driver Wiki
 */
class Demo extends \VuFind\ILS\Driver\Demo
{
    /**
     * Extract source from the given ID
     *
     * @param string $id The id to be split
     *
     * @return string  Source
     */
    protected function getSource($id)
    {
        $pos = strpos($id, '.');
        if ($pos > 0) {
            return substr($id, 0, $pos);
        }

        return '';
    }

    /**
     * Public Function which retrieves renew, hold and cancel settings from the
     * driver ini file.
     *
     * @param string $function The name of the feature to be checked
     * @param array  $params   Optional feature-specific parameters (array)
     *
     * @return array An array with key-value pairs.
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function getConfig($function, $params = null)
    {
        if ($function == 'onlinePayment') {
            return $this->config['OnlinePayment'];
        }

        return parent::getConfig($function, $params);
    }

    /**
     * Get Patron Fines
     *
     * This is responsible for retrieving all fines by a specific patron.
     *
     * @param array $patron The patron array from patronLogin
     *
     * @return mixed        Array of the patron's fines on success.
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function getMyFines($patron)
    {
        $fines = parent::getMyFines($patron);
        if (!empty($fines)) {
            $fines[0]['fine'] = 'Accrued Fine';
        }
        $fines = $this->markOnlinePayableFines($fines);
        $this->session->fines = $fines;
        return $fines;
    }

    /**
     * Return total amount of fees that may be paid online.
     *
     * @param array $patron Patron
     *
     * @throws ILSException
     * @return array Associative array of payment info,
     * false if an ILSException occurred.
     */
    public function getOnlinePayableAmount($patron)
    {
        $fines = $this->getMyFines($patron);
        if (!empty($fines)) {
            $nonPayableReason = false;
            $amount = 0;
            foreach ($fines as $fine) {
                if (!$fine['payableOnline'] || $fine['accruedFine']) {
                    $nonPayableReason = 'online_payment_nonpayable_fees';
                } else {
                    $amount += $fine['balance'];
                }
            }
            $config = $this->getConfig('onlinePayment');
            if (!$nonPayableReason
                && isset($config['minimumFee']) && $amount < $config['minimumFee']
            ) {
                $nonPayableReason = 'online_payment_minumum_fee';
            }
            $res = ['payable' => !empty($nonPayableReason), 'amount' => $amount];
            if ($nonPayableReason) {
                $res['reeason'] = 'online_payment_minumum_fee';
            }
            return $res;
        }
    }

    /**
     * Support method for getMyFines.
     *
     * Appends booleans 'accruedFine' and 'payableOnline' to a fine.
     *
     * @param array $fines Processed fines.
     *
     * @return array $fines Fines.
     */
    protected function markOnlinePayableFines($fines)
    {
        $accruedType = 'Accrued Fine';

        $config = $this->config['OnlinePayment'];
        $nonPayable = isset($config['nonPayable'])
            ? $config['nonPayable'] : []
        ;
        $nonPayable[] = $accruedType;
        foreach ($fines as &$fine) {
            $payableOnline = true;
            if (isset($fine['fine'])) {
                if (in_array($fine['fine'], $nonPayable)) {
                    $payableOnline = false;
                }
            }
            $fine['accruedFine'] = ($fine['fine'] === $accruedType);
            $fine['payableOnline'] = $payableOnline;
        }

        return $fines;
    }

    /**
     * Mark fees as paid.
     *
     * This is called after a successful online payment.
     *
     * @param array $patron Patron.
     * @param int   $amount Amount to be registered as paid.
     *
     * @throws ILSException
     * @return boolean success
     */
    public function markFeesAsPaid($patron, $amount)
    {
        if (rand() % 2) {
            throw new ILSException('online_payment_registration_failed');
        }

        if (isset($this->session->fines)) {
            foreach ($this->session->fines as $key => $fine) {
                if ($fine['payableOnline']) {
                    unset($this->session->fines[$key]);
                }
            }
        }
        if (empty($this->sesion->fines)) {
            unset($this->session->fines);
        }

        return true;
    }

    /**
     * Helper method to determine whether or not a certain method can be
     * called on this driver.  Required method for any smart drivers.
     *
     * @param string $method The name of the called method.
     * @param array  $params Array of passed parameters
     *
     * @return bool True if the method can be called with the given parameters,
     * false otherwise.
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function supportsMethod($method, $params)
    {
        if ($method == 'markFeesAsPaid') {
            $required = ['enabled', 'registrationMethod', 'registrationParams'];

            foreach ($required as $req) {
                if (!isset($this->config['OnlinePayment'][$req])
                    || empty($this->config['OnlinePayment'][$req])
                ) {
                    return false;
                }
            }

            if (!$this->config['OnlinePayment']['enabled']) {
                return false;
            }

            $regParams = $this->config['OnlinePayment']['registrationParams'];
            $required = ['host', 'port', 'userId', 'password', 'locationCode'];
            foreach ($required as $req) {
                if (!isset($regParams[$req]) || empty($regParams[$req])) {
                    return false;
                }
            }
            return true;
        }
        return is_callable([$this, $method]);
    }
}
