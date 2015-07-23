<?php
/**
 * Class to help build URLs and forms in the view based on search settings.
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
 * @package  Search
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
namespace Finna\Search;

/**
 * Class to help build URLs and forms in the view based on search settings.
 *
 * @category VuFind2
 * @package  Search
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
class UrlQueryHelper extends \VuFind\Search\UrlQueryHelper
{
    /**
     * Copy constructor
     *
     * @return void
     */
    public function __clone()
    {
        $this->params = clone($this->params);
    }

    /**
     * Remove all filters.
     *
     * @return void
     */
    public function removeAllFilters()
    {
        $this->params->removeAllFilters();
    }

    /**
     * Expose parent method since we need to use from SearchTabs.
     *
     * @param array $a      Array of parameters to turn into a GET string
     * @param bool  $escape Should we escape the string for use in the view?
     *
     * @return string
     */
    public function buildQueryString($a, $escape = true)
    {
         return parent::buildQueryString($a, $escape);
    }

    /**
     * Generic case of parameter rebuilding.
     *
     * @param string $field     Field to update
     * @param string $value     Value to use (null to skip field entirely)
     * @param string $default   Default value (skip field if $value matches; null
     *                          for no default).
     * @param bool   $escape    Should we escape the string for use in the view?
     * @param bool   $clearPage Should we clear the page number, if any?
     *
     * @return string
     */
    public function updateQueryString($field, $value, $default = null,
        $escape = true, $clearPage = false
    ) {
        return parent::updateQueryString($field, $value, $default, $escape, $clearPage);
    }

    /**
     * Sets search id in the params and returns resulting query string.
     *
     * @param string $class Search class.
     * @param int    $id    Search id.
     *
     * @return string
     */
    public function setSearchId($class, $id)
    {
        $params = $this->getParamArray();
        $searches = isset($params['search']) ? $params['search'] : [];
        $res = [];
        $res[] = "$class:$id";

        foreach ($searches as $search) {
            list($searchClass, $searchId) = explode(':', $search);
            if ($searchClass !== $class) {
                $res[] = "$searchClass:$searchId";
            }
        }
        $params['search'] = $res;

        return '?' . $this->buildQueryString($params, false);
    }

    /**
     * Get an array of URL parameters.
     *
     * @return array
     */
    public function getParamArray()
    {
        $params = parent::getParamArray();
        //        $filter = $this->params->getSpatialDateRangeFilter();
        
        $filter = $this->params->getSpatialDateRangeFilter();
        if ($filter && isset($filter['field']) && isset($params['filter'])
        ) {
            foreach ($params['filter'] as &$param) {
                list($field, $value) = explode(':', $param, 2);
                //echo("spa: " . $filter['field'] . ", field: $field-$value");
                if ($field == $filter['field']) {
                    
                    $param = $filter['solrQuery'];
                    //die($param);
                }
            }
        }
        return $params;
    }




}
