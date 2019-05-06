<?php
/**
 * Base class for Authority records record tabs.
 *
 * PHP version 7
 *
 * Copyright (C) The National Library of Finland 2019.
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
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category VuFind
 * @package  RecordTabs
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:record_tabs Wiki
 */
namespace Finna\RecordTab;

/**
 * Base class for Authority records record tabs.
 *
 * @category VuFind
 * @package  RecordTabs
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:record_tabs Wiki
 */
class AuthorityRecordsBase extends \VuFind\RecordTab\AbstractBase
{
    /**
     * Search runner
     *
     * @var \VuFind\Search\SearchRunner
     */
    protected $searchRunner;

    /**
     * Constructor
     *
     * @param \Zend\Config\Config         $config       Configuration
     * @param \VuFind\Search\SearchRunner $searchRunner Search runner
     */
    public function __construct(
        \Zend\Config\Config $config,
        $searchRunner
    ) {
        $this->searchRunner = $searchRunner;
    }

    /**
     * Is this tab active?
     *
     * @return bool
     */
    public function isActive()
    {
        return true;
    }

    /**
     * Get the on-screen description for this tab.
     *
     * @return string
     */
    public function getDescription()
    {
        return 'authority_records_' . $this->relation;
    }

    /**
     * Load records that are linked to this authority record.
     *
     * @param \VuFind\RecordDriver\DefaultRecord $driver Driver
     *
     * @return array
     */
    public function loadRecords($driver)
    {
        $id = $driver->getUniqueID();
        $query = ['lookfor' => $this->relation . "_id_str_mv:\"$id\""];
        return $this->searchRunner->run($query);
    }
}
