<?php
/**
 * Row definition for online payment transaction
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
 * @category VuFind2
 * @package  Db_Table
 * @author   Leszek Manicki <leszek.z.manicki@helsinki.fi>
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
namespace Finna\Db\Row;
use Finna\Db\Table\Fee,
    Finna\Db\Table\TransactionFees;

/**
 * Row definition for online payment transaction
 *
 * @category VuFind2
 * @package  Db_Table
 * @author   Leszek Manicki <leszek.z.manicki@helsinki.fi>
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
class Transaction extends \VuFind\Db\Row\RowGateway
{
    /**
     * Constructor
     *
     * @param \Zend\Db\Adapter\Adapter $adapter Database adapter
     */
    public function __construct($adapter)
    {
        parent::__construct('id', 'finna_transaction', $adapter);
    }

    /**
     * Add fee to the current transaction.
     *
     * @param array  $feeData  Fee data hash array
     * @param object $user     User (patron) object
     * @param string $currency Currency
     *
     * @return boolean success
     */
    public function addFee($feeData, $user, $currency)
    {
        $fee = new Fee();
        $fee->user_id = $user->id;
        $fee->title = $feeData['title'];
        $fee->type = $feeData['fine'];
        $fee->amount = $feeData['amount'];
        $fee->currency = $currency;
        if (!$fee->amount) {
            return false;
        }
        if (!$fee->save()) {
            return false;
        }
        $transaction_fee_obj = new TransactionFees();
        $transaction_fee_obj->transaction_id = $this->id;
        $transaction_fee_obj->fee_id = $fee->id;
        if (!$transaction_fee_obj->save()) {
            return false;
        }
        return true;
    }
}
