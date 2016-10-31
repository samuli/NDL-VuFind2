<?php
/**
 * OnlinePayment handler interface
 *
 * PHP version 5
 *
 * Copyright (C) The National Library of Finland 2015.
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
 */
namespace Finna\OnlinePayment;

/**
 * OnlinePayment handler interface.
 *
 * @category VuFind
 * @package  OnlinePayment
 * @author   Leszek Manicki <leszek.z.manicki@helsinki.fi>
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
interface OnlinePaymentHandlerInterface
{
    /**
     * Constructor
     *
     * @param array $config Configuration
     */
    public function __construct($config);

    /**
     * Return payment response parameters.
     *
     * @param Zend\Http\Request $request Request
     *
     * @return array
     */
    public function getPaymentResponseParams($request);

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
        $finesUrl, $ajaxUrl, $user, $patronId, $driver,
        $amount, $transactionFee, $fines, $currency,
        $statusParam
    );

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
    public function processResponse($request);
}
