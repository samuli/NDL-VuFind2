<?php
/**
 * Table Definition for search
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
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://www.vufind.org  Main Page
 */
namespace Finna\Db\Table;
use fminSO;
/**
 * Table Definition for search
 *
 * @category VuFind2
 * @package  Db_Table
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
class Search extends \VuFind\Db\Table\Search
{
    /**
     * Constructor
     */
    public function __construct()
    {
        parent::__construct();
        $this->rowClass = 'Finna\Db\Row\Search';
    }

    /**
     * Get distinct view URLs with scheduled alerts.
     *
     * @return array URLs
     */
    public function getScheduleBaseUrls()
    {
        $sql
            = "SELECT distinct finna_schedule_base_url as url FROM {$this->table}"
            . " WHERE finna_schedule_base_url != '';";

        $result = $this->getAdapter()->query(
            $sql,
            \Zend\Db\Adapter\Adapter::QUERY_MODE_EXECUTE
        );
        $urls = [];
        foreach ($result as $res) {
            $urls[] = $res['url'];
        }
        return $urls;
    }

    /**
     * Get scheduled searches.
     *
     * @param string $baseUrl View URL
     *
     * @return array Array of Finna\Db\Row\Search objects.
     */
    public function getScheduledSearches($baseUrl)
    {
        $callback = function ($select) use ($baseUrl) {
            $select->columns(['*']);
            $select->where('finna_schedule > 0');
            $select->where->equalTo('finna_schedule_base_url', $baseUrl);
            $select->order('user_id');
        };

        return $this->select($callback);
    }

    public function saveSearch(\VuFind\Search\Results\PluginManager $manager,
        $newSearch, $sessionId, $searchHistory = []
    ) {
        parent::saveSearch($manager, $newSearch, $sessionId, $searchHistory);


        $callback = function ($select) use ($sessionId) {
            $select->columns(['*']);
            $select->where->equalTo('session_id', $sessionId);
            $select->order('id desc');
            $select->limit(1);
        };

        $row = $this->select($callback)->current();
        // todo: check for existing finnaSO?
        // $finnaSO = $row['finna_search_object'] ? $row['finna_search_object'] : [];
        
        $row['finna_search_object'] = serialize(new fminSO($newSearch));
        $row->save();

    }

}
