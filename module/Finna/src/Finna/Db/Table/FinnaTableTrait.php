<?php
/**
 * Additional functionality for Finna database table objects.
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
 * @package  RecordDrivers
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:record_drivers Wiki
 */
namespace Finna\Db\Table;
use Zend\Db\Metadata\Metadata;

/**
 * Additional functionality for Finna database table objects.
 *
 * @category VuFind2
 * @package  RecordDrivers
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:record_drivers Wiki
 *
 * @SuppressWarnings(PHPMD.ExcessivePublicCount)
 */
trait FinnaTableTrait
{
    protected $tableColumns = null;

    /**
     * Prepare a query that joins rows from master and Finna tables.
     *
     * @param string $table     Master table name
     * @param string $refColumn Link column that is used to
     * join row objects from two tables.
     *
     * @return Zend\Db\Sql\Select Query.
     */
    protected function getRowsQuery($table, $refColumn)
    {
        if (!$this->tableColumns) {
            $metadata = new Metadata($this->getAdapter());
            $cols = [];
            foreach ($metadata->getTable("finna_{$table}")->getColumns() as $col) {
                $cols[] = $col->getName();
            }
            $this->tableColumns = array_flip($cols);
        }

        $select = $this->getSql()->select();
        $select->join("finna_{$table}", "{$table}.id = finna_{$table}.{$refColumn}");
        return $select;
    }

    /**
     * Create and populate a Finna row object.
     *
     * @param array  $data        Data.
     * @param string $rowClass    Class name of row object.
     * @param string $masterClass Class name of master row object.
     *
     * @return Finna\Db\Row\FinnaTableTrait Row object.
     */
    protected function getRowObject($data, $rowClass, $masterClass)
    {
        $rowData = array_intersect_key($data, $this->tableColumns);

        // Create and populate master row
        $masterData = array_diff($data, $rowData);
        $masterData['id'] = $data['search_id'];
        //$masterRow = new MasterSearchRow($this->getAdapter());
        $masterRow = new $masterClass($this->getAdapter());

        $masterRow->populate($masterData);

        // Create and populate row
        $row = new $rowClass($this->getAdapter());
        //$row = new SearchRow($this->getAdapter());
        $row->postInitialize(array_flip($this->tableColumns), $masterRow);
        $row->populate($data);

        $masterRow->id = $data['search_id'];
        $row->id = $data['id'];

        return $row;
    }
}
