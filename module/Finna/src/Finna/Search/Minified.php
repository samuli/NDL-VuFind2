<?php
/**
 * VuFind Minified Search Object
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
 * @package  Search
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
namespace Finna\Search;

/**
 * A minified search object used exclusively for trimming a search object down to its
 * barest minimum size before storage in a cookie or database.
 *
 * It still contains enough data granularity to programmatically recreate search
 * URLs.
 *
 * This class isn't intended for general use, but simply a way of storing/retrieving
 * data from a search object:
 *
 * eg. Store
 * $searchHistory[] = serialize($this->minify());
 *
 * eg. Retrieve
 * $searchObject = unserialize($search);
 * $searchObject->deminify($manager);
 *
 * @category VuFind2
 * @package  Search
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
class Minified implements \Serializable
{
    // search_sdaterange_mvtype
    public $f_dty;

    protected $parentSO;
    /**
     * Constructor. Building minified object from the
     *    searchObject passed in. Needs to be kept
     *    up-to-date with the deminify() function on
     *    searchObject.
     *
     * @param object $searchObject Search Object to minify
     */
    public function __construct($searchObject)
    {
        $daterange = $searchObject->getParams()->getSpatialDateRangeFilter();
        if ($daterange && isset($daterange['type'])
        ) {
            $this->f_dty = $daterange['type'];
        }
    }
    
    public function setParentSO($so)
    {
        $this->parentSO = $so;
    }
    
    public function serialize()
    {
        $data = [];
        if ($this->f_dty) {
            $data['f_dty'] = $this->f_dty;
        }
        return serialize($data);        
    }

    public function unserialize($data)
    {
        $data = unserialize($data);
        if (isset($data['f_dty'])) {
            $this->f_dty = $data['f_dty'];
        }
        return $this;
    }

    /**
     * Turn the current object into search results.
     *
     * @param \VuFind\Search\Results\PluginManager $manager Search manager
     *
     * @return \VuFind\Search\Base\Results
     */
    public function deminify(\VuFind\Search\Results\PluginManager $manager)
    {
        $results = $this->parentSO->deminify($manager);
        $results->getParams()->deminifyFinnaSearch($this);
        return $results;


        //return parent::deminify($manager);

        /*
        $results = parent::deminify($manager);

        // Figure out the parameter and result classes based on the search class ID:
        $this->populateClassNames();

        // Deminify everything:
        $results = $manager->get($this->cl);
        $results->getParams()->deminify($this);
        $results->deminify($this);

        return $results;
        */
    }

}
