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
 * Table Definition for search
 *
 * @category VuFind2
 * @package  Db_Table
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
class Search extends \VuFind\Db\Table\Search
{
    use FinnaTableTrait;

    /**
     * Constructor
     */
    public function __construct()
    {
        parent::__construct();
        $this->rowClass = 'Finna\Db\Row\Search';
    }

    /**
     * Get scheduled searches.
     *
     * @return array Array of Finna\Db\Row\Search objects.
     */
    public function getScheduledSearches()
    {
        $select = $this->getRowsQuery('search', 'search_id');
        $select->where('schedule > 0');
        $select->order('user_id');

        $results = $this->selectWith($select);

        $rows = [];
        foreach ($results as $res) {
            $rows[] = $this->getRowObject(
                $res->toArray(), 'Finna\Db\Row\Search', 'VuFind\Db\Row\Search'
            );
        }

        return $rows;
    }
}
