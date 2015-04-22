<?php
/**
 * Additional functionality for Finna database row objects.
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
namespace Finna\Db\Row;

/**
 * Additional functionality for Finna database row objects.
 *
 * @category VuFind2
 * @package  RecordDrivers
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:record_drivers Wiki
 *
 * @SuppressWarnings(PHPMD.ExcessivePublicCount)
 */
trait FinnaRowTrait
{
    protected $masterRow = null;
    protected $columns = null;

    /**
     * Must be called after the row object has been created.
     *
     * @param array                $columns   Column names for row object.
     * @param VuFind\Db\Row\Object $masterRow Master row object.
     *
     * @return mixed
     */
    public function postInitialize($columns, $masterRow)
    {
        $this->columns = $columns;
        $this->masterRow = $masterRow;
    }

    /**
     * Save
     *
     * @return int
     */
    public function save($saveMaster = true)
    {
        if ($this->masterRow && $saveMaster) {
            $this->masterRow->save();
        }
        return parent::save();
    }

    /**
     * Populate Data.
     *
     * @param array $rowData Data
     * @param bool  $rowExistsInDatabase
     *
     * @return AbstractRowGateway
     */
    public function populate(array $rowData, $rowExistsInDatabase = false)
    {
        if (!$this->masterRow) {
            parent::populate($rowData, $rowExistsInDatabase);
            return $this;
        }

        if (!$this->columns) {
            parent::populate($rowData, true);
            return $this;
        }

        $myData = array_intersect_key($rowData, array_flip($this->columns));
        parent::populate($myData, true);

        $masterData = $this->masterRow->toArray();
        $masterDataNew = array_diff($rowData, $myData);
        $masterData = array_replace($masterData, $masterDataNew);
        $masterData['id'] = $this->masterRow->id;

        $this->masterRow->populate($masterData, true);

        return $this;
    }

    /**
     * __set
     *
     * @param string $name  Field
     * @param mixed  $value Value
     *
     * @return void
     */
    public function __set($name, $value)
    {
        if ($this->masterRow && !in_array($name, $this->columns)) {
            $this->masterRow->__set($name, $value);
        } else {
            parent::__set($name, $value);
        }
    }

    /**
     * __get
     *
     * @param string $name Field
     *
     * @throws Exception\InvalidArgumentException
     * @return mixed
     */
    public function __get($name)
    {
        if ($this->masterRow && !in_array($name, $this->columns)) {
            return $this->masterRow->__get($name);
        }
        return parent::__get($name);
    }
}
