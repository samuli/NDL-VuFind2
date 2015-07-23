<?php
/**
 * Additional functionality for Finna Solr records.
 *
 * PHP version 5
 *
 * Copyright (C) The National Library 2015.
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
namespace Finna\Search;
use Finna\Solr\Utils;

/**
 * Additional functionality for Finna Solr records.
 *
 * @category VuFind2
 * @package  RecordDrivers
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:record_drivers Wiki
 *
 * @SuppressWarnings(PHPMD.ExcessivePublicCount)
 */
trait FinnaParams
{

    /**
     * TODO
     *
     * @var Query
     */
    protected $spatialDateRangeFilter;

    /**
     * Pull the search parameters
     *
     * @param \Zend\StdLib\Parameters $request Parameter object representing user
     * request.
     *
     * @return void
     */
    /*
    public function initFromRequest($request)
    {
        parent::initFromRequest($request);
        $this->initSpatialDateRangeFilter($request);
        }*/

    /**
     * Take a filter string and add it into the protected
     *   array checking for duplicates.
     *
     * @param string $newFilter A filter string from url : "field:value"
     *
     * @return void
     */
    public function addFilter($newFilter)
    {        
        list($field, $value) = $this->parseFilter($newFilter);
        echo("add: $newFilter, field: $field, val: $value");
        if ($field && $value && strpos($field, 'search_sdatarange_mv') !== false) {
            // VuFind1 daterange
            parent::addFilter($newFilter);
            return;
        }

        if (!$filter = $this->parseSpatialDaterangeFilter($newFilter)) {
            // Not a VuFind2 daterange filter
            parent::addFilter($newFilter);
            return;
        }

        list($field, $value, $options) = $filter;
        if (isset($this->filterList[$field])) {
            // Allow only one active spatial daterange filter
            return;
        }

        $rangeFilter = $field . ':"' . $value . '"';
        if (!$range = $this->convertSpatialDateRange($rangeFilter, $options['type'], true)) {
            parent::addFilter($newFilter);       
            return;
        }

        // VuFind2 daterange        
        $dateFilter = [
           'from' => $range['from'],
           'to' => $range['to'],
           'field' => self::SPATIAL_DATERANGE_FIELD,
           'val' => $value,
           'query' => $rangeFilter,
           'type' => $options['type']
        ];

        $dateFilter['solrQuery'] 
            = Utils::buildSpatialDateRangeQuery(
                $dateFilter['from'], 
                $dateFilter['to'], 
                $dateFilter['type'],
                $dateFilter['field']
        );

        echo("datef: " . var_export($dateFilter, true));

        $this->spatialDateRangeFilter = $dateFilter;
        //parent::addFilter($newFilter);
        parent::addFilter($dateFilter['query']);

    }

    public function getFilterSettings()    
    {
        $filters = parent::getFilterSettings();
        if ($this->spatialDateRangeFilter) {
            foreach ($filters as &$filter) {
                if (strpos($filter, self::SPATIAL_DATERANGE_FIELD) === 0) {
                    $type = $this->spatialDateRangeFilter['type'];
                    /*
                    // Map daterange type to Solr daterange query operator
                    $map = ['within' => 'Contains'];
                    // overlap => Intersects is default
                    $op = 'Intersects';
                    if (isset($map[$type])) {
                        $op = $map[$type];
                        }*/
                    $filter 
                        = '{!field f=' . self::SPATIAL_DATERANGE_FIELD 
                        . " op=$type}"
                        . $this->spatialDateRangeFilter['val']
                    ;
                    break;
                }
            }
        }
        echo("filters: " . var_export($filters, true));
        return $filters;
    }

    /**
     * Format a single filter for use in getFilterList().
     *
     * @param string $field     Field name
     * @param string $value     Field value
     * @param string $operator  Operator (AND/OR/NOT)
     * @param bool   $translate Should we translate the label?
     *
     * @return array
     */
    protected function formatFilterListEntry($field, $value, $operator, $translate)
    {
        //echo("formatfilter: " . $this->spatialDateRangeFilter['field'] . ", f2: $field");

        $res = parent::formatFilterListEntry($field, $value, $operator, $translate);
        if ($this->spatialDateRangeFilter
            && isset($this->spatialDateRangeFilter['field'])
            && $this->spatialDateRangeFilter['field'] == $field
        ) {
            $range = isset($this->spatialDateRangeFilter['type'])
                ? $this->convertSpatialDateRange(
                    $value, $this->spatialDateRangeFilter['type']
                )
                : $this->convertSpatialDateRange($value)
            ;
            if ($range) {
                $display = '';
                $from = $range['from'];
                $to = $range['to'];

                if ($from != '*') {
                    $display .= $from;
                }
                $display .= '–';
                if ($to != '*') {
                    $display .= $to;
                }
                $res['displayText'] = $display;
            }
        }
        return $res;
    }

    /**
     * TODO
     *
     * @return string
     */
    public function getSpatialDateRangeField()
    {
        return self::SPATIAL_DATERANGE_FIELD;
    }

    /**
     *  TODO
     *
     * @param \Zend\StdLib\Parameters $request Parameter object representing user
     * request.
     *
     * @return void
     */
    public function getSpatialDateRangeFilter() 
    {
        return $this->spatialDateRangeFilter;
    }


}
