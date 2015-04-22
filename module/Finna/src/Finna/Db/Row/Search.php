<?php
/**
 * Row Definition for search
 *
 * PHP version 5
 *
 * Copyright (C) Villanova University 2010.
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
 * @package  Db_Row
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
namespace Finna\Db\Row;
use VuFind\Db\Row\RowGateway,
    Zend\Db\Sql\Sql;

/**
 * Row Definition for search
 *
 * @category VuFind2
 * @package  Db_Row
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
class Search extends RowGateway
{
    use FinnaRowTrait;

    protected $table = 'finna_search';

    /**
     * Constructor.
     *
     * @param Zend\Db\Adapter\Adapter $adapter Database adapter.
     *
     * @return mixed
     */
    public function __construct($adapter)
    {
        parent::__construct('id', $this->table, $adapter);
        return $this;
    }

    /**
     * Set last executed time for scheduled alert.
     *
     * @param DateTime $time Time.
     *
     * @return mixed
     */
    public function setLastExecuted($time)
    {
        $this->last_executed = $time;
        return $this->save(false);
    }

}
