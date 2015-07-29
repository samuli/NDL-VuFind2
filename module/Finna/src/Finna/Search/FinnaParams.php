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
    protected $spatialDateRangeFilter = null;


    /**
     * Pull the search parameters
     *
     * @param \Zend\StdLib\Parameters $request Parameter object representing user
     * request.
     *
     * @return void
     */
    
    public function initFromRequest($request)
    {
        parent::initFromRequest($request);
        $this->initSpatialDateRangeFilter($request);
    }

    public function getFilterSettings()    
    {
        $filters = parent::getFilterSettings();
        if ($this->spatialDateRangeFilter) {
            foreach ($filters as &$filter) {
                if (strpos($filter, self::SPATIAL_DATERANGE_FIELD) === 0) {
                    $from = $this->spatialDateRangeFilter['from'];
                    $to = $this->spatialDateRangeFilter['to'];
                    $type = $this->spatialDateRangeFilter['type'];
                    $field = $this->spatialDateRangeFilter['field'];
                    $filter 
                        = Utils::buildSpatialDateRangeQuery(
                            $from, $to, $type, $field
                        );
                    break;
                }
            }
        }
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
            $filter = $this->spatialDateRangeFilter['val'];
            $type = isset($this->spatialDateRangeFilter['type'])
                ? $this->spatialDateRangeFilter['type']
                : null
            ;

            $range = Utils::parseSpatialDateRange($filter, $type, true);
            /*
            $range = isset($this->spatialDateRangeFilter['type'])
                ? $this->convertSpatialDateRange(
                    $value, $this->spatialDateRangeFilter['type']
                )
                : $this->convertSpatialDateRange($value)
                ;*/
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
