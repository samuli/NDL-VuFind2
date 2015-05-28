<?php
/**
 * Solr Search Parameters
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
 * @package  Search_Solr
 * @author   Mika Hatakka <mika.hatakka@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://www.vufind.org  Main Page
 */
namespace Finna\Search\Solr;
use Finna\Solr\Utils;

/**
 * Solr Search Parameters
 *
 * @category VuFind2
 * @package  Search_Solr
 * @author   Mika Hatakka <mika.hatakka@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://www.vufind.org  Main Page
 */
class Params extends \VuFind\Search\Solr\Params
{
    /**
     * TODO
     *
     * @var Query
     */
    protected $spatialDateRangeFilter;


    /**
     * TODO
     *
     * @return string
     */
    public function getSpatialDateRangeField()
    {
        return 'search_sdaterange_mv';
    }

    /**
     * Return current facet configurations.
     * Add checkbox facets to list.
     *
     * @return array $facetSet
     */
    public function getFacetSettings()
    {
        $facetSet = parent::getFacetSettings();
        if (!empty($this->checkboxFacets)) {
            foreach (array_keys($this->checkboxFacets) as $facetField) {
                $facetField = '{!ex=' . $facetField . '_filter}' . $facetField;
                $facetSet['field'][] = $facetField;
            }
        }
        return $facetSet;
    }

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
        echo("format: $field:$value, spa: " . var_export($this->spatialDateRangeFilter, true));

        $res = parent::formatFilterListEntry($field, $value, $operator, $translate);
        if ($this->spatialDateRangeFilter 
            && $this->spatialDateRangeFilter['field'] == $field
        ) {
            $type = 'overlap';
            
            echo("format2: $field:$value");

            if ($range = $this->convertSpatialDateRange($value, $type)) {
                $startDate = new \DateTime("@{$range['from']}");
                $endDate = new \DateTime("@{$range['to']}");
                $from = $startDate->format('Y');
                $to = $endDate->format('Y');
                $res['displayText'] = $range['from'] . '–' . $range['to'];
            }
        }
        return $res;
    }

    /**
     *  TODO
     *
     * @param \Zend\StdLib\Parameters $request Parameter object representing user
     * request.
     *
     * @return void
     */
    protected function initSpatialDateRangeFilter($request)
    {
        $type = $request->get('search_sdaterange_mvtype');
        /*
        if ($spatialRangeType) {
            $this->spatialDateRangeFilterType = $spatialRangeType;
            }*/

        $from = $to = null;

        if ($request->get('sdaterange')) {
            $from = $request->get('search_sdaterange_mvfrom');
            $to = $request->get('search_sdaterange_mvto');
        } else {
            $filters = $this->getFilters();
            if (isset($filters['search_sdaterange_mvtype'])) {
                
                $filters = $filters['search_sdaterange_mvtype'];
            }
        }

        if (!$ranges || empty($ranges)) {
            return;        
        }

        
        if ($ranges = $filters['search_sdaterange_mvtype']) {
            
            //            $range = Util::parseSpatialDateRange($rangequery, $type = 'overlap')
            if (!is_array($ranges)) {
                $ranges = [$ranges];
            }
            foreach ($ranges as $range) {
                $dateFrom = $request->get("{$range}from");
                $dateTo = $request->get("{$range}to");

                // Build filter only if necessary:
                if (!empty($range)) {
                    $dateFilter
                        = $this->buildSpatialDateRangeFilter(
                            $range, $dateFrom, $dateTo, $type
                        );

                    $this->addFilter($dateFilter['query']);


                    /*
                    if ($range = SolrUtils::parseRange($current)) {
                        $from = $range['from'] == '*' ? '' : $range['from'];
                        $to = $range['to'] == '*' ? '' : $range['to'];
                        $savedSearch->getParams()
                            ->removeFilter($field . ':' . $current);
                        break;
                        }*/

                    $dateFilter['to'] = $dateTo;
                    $dateFilter['from'] = $dateFrom;
                    $dateFilter['type'] = $type;

                    $this->spatialDateRangeFilter = $dateFilter;
                }
            }
        }
    }

    /**
     * Support method for initDateFilters() -- build a spatial filter query based on a range
     * of dates (expressed as days from unix epoch).
     * See the index schema definition for more information.
     *
     * @param string $field field to use for filtering.
     * @param string $from  year or date (yyyy-mm-dd) for start of range.
     * @param string $to    year or date (yyyy-mm-dd) for end of range.
     * @param string $type  'overlap'  = document duration overlaps query durration (default)
     *                      'within '  = document duration within query durration
     *
     * @return string       filter query.
     */
    protected function buildSpatialDateRangeFilter($field, $from, $to, $type = 'overlap')
    {
        $minFrom = -4371587;
        $maxTo = 2932896;

        $type = in_array($type, array('overlap', 'within')) ? $type : 'overlap';
        //        $this->spatialDateRangeFilterType = $type;

        $oldTZ = date_default_timezone_get();
        try {
            date_default_timezone_set('UTC');
            if ($from == '' || $from == '*') {
                $from = $minFrom;
            } else {
                // Make sure year has four digits
                if (preg_match('/^(-?)(\d+)(.*)/', $from, $matches)) {
                    $from = $matches[1] . str_pad($matches[2], 4, '0', STR_PAD_LEFT) . $matches[3];
                }
                // A crude check to see if this is a complete date to accommodate different years
                // (1990, -12 etc.)
                if (strlen($from) < 10) {
                    $from .= '-01-01';
                }
                $fromDate = new \DateTime("{$from}T00:00:00");
                // Need format instead of getTimestamp for dates before epoch
                $from = $fromDate->format('U') / 86400;
            }
            if ($to == '' || $to == '*') {
                $to = $maxTo;
            } else {
                // Make sure year has four digits
                if (preg_match('/^(-?)(\d+)(.*)/', $to, $matches)) {
                    $to = $matches[1] . str_pad($matches[2], 4, '0', STR_PAD_LEFT) . $matches[3];
                }
                // A crude check to see if this is a complete date to accommodate different years
                // (1990, -12 etc.)
                if (strlen($to) < 10) {
                    $to .= '-12-31';
                }

                $toDate = new \DateTime("{$to}T00:00:00");
                // Need format instead of getTimestamp for dates before epoch
                $to = $toDate->format('U') / 86400;    // days since epoch
            }
        } catch (Exception $e) {
            date_default_timezone_set($oldTZ);
            return '';
        }
        date_default_timezone_set($oldTZ);

        if ($from > $to) {
            PEAR::RaiseError(new PEAR_Error("Invalid date range specified."));
        }

        // Assume Solr syntax -- this should be overridden in child classes where
        // other indexing methodologies are used.

        $val =  null;
        if ($type == 'overlap') {
            // document duration overlaps query duration
            // q=fieldX:"Intersects(-∞ start end ∞)"
            $val = "[\"$minFrom $from\" TO \"$to $maxTo\"]";
            $query = "{$field}:$val";
        } else if ($type == 'within') {
            // document duration within query duration
            // q=fieldX:"Intersects(start -∞ ∞ end)"

            // Enlarge query range to match records with exactly the same time range as the original query
            $from -= 0.5;
            $to += 0.5;
            $val = "[\"$from $minFrom\" TO \"$maxTo $to\"]";
            $query = "{$field}:$val";
        }

        return [
           'query' => $query, 'field' => $field, 'val' => $val
        ];
    }

    protected function convertSpatialDateRange($value, $type)
    {
        if ($range = Utils::parseSpatialDateRange($value, $type)) {
            $startDate = new \DateTime("@{$range['from']}");
            $endDate = new \DateTime("@{$range['to']}");
            $from = $startDate->format('Y');
            $to = $endDate->format('Y');
            return ['from' => $from, 'to' => $to];
        }
        return null;
    }


}
