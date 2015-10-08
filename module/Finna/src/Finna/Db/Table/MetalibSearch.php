<?php
/**
 * Table Definition for search
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
 * @package  Db_Table
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://www.vufind.org  Main Page
 */
namespace Finna\Db\Table;

/**
 * Table Definition for Metalib search
 *
 * @category VuFind2
 * @package  Db_Table
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
class MetalibSearch extends \VuFind\Db\Table\Gateway
{
    /**
     * Constructor
     */
    public function __construct()
    {
        parent::__construct('finna_metalib_search', 'Finna\Db\Row\MetalibSearch');
    }

    /**
     * Add a search into the search table (history)
     *
     * @param \VuFind\Search\Results\PluginManager $manager       Search manager
     * @param \VuFind\Search\Base\Results          $newSearch     Search to save
     * @param string                               $sessionId     Current session ID
     * @param array                                $searchHistory Existing saved
     * searches (for deduplication purposes)
     *
     * @return void
     */
    public function saveMetalibSearch($results, $searchId)
    {
        if ($this->getRowBySearchHash($searchId)) {
            return;
        }
        $result = $this->createRow();
        $result->finna_search_id = $searchId;
        $result->search_object = serialize($results);
        $result->created = date('Y-m-d H:i:s');
        $result->save();
    }

    /**
     * Get a single row matching a primary key value.
     *
     * @param int  $id                 Primary key value
     * @param bool $exceptionIfMissing Should we throw an exception if the row is
     * missing?
     *
     * @throws \Exception
     * @return \VuFind\Db\Row\Search
     */
    public function getRowBySearchHash($hash)
    {
        return $this->select(['finna_search_id' => $hash])->current();
    }
}
