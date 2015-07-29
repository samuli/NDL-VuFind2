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
use Finna\Search\FinnaParams,
    Finna\Solr\Utils;

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
    use FinnaParams;

    const SPATIAL_DATERANGE_FIELD = 'search_daterange_mv';
    const SPATIAL_DATERANGE_FIELD_VF1 = 'search_sdaterange_mv';



    /**
     * Does the object already contain the specified filter?
     *
     * @param string $filter A filter string from url : "field:value"
     *
     * @return bool
     */
    public function addFilter($filter)
    {
        // Extract field and value from URL string:
        list($field, $value) = $this->parseFilter($filter);
        list($operator, $field) = $this->parseOperatorAndFieldName($field);

        if ($field == self::SPATIAL_DATERANGE_FIELD_VF1 
            || $field == self::SPATIAL_DATERANGE_FIELD
        ) {
            // Timerange filters are processed 
            // separately (see initSpatialDateRangeFilter)
            return;
        } 
        parent::addFilter($filter);
    }

    /**
     * Parse apart the field and value from a URL filter string.
     *
     * @param string $filter A filter string from url : "field:value"
     *
     * @return array         Array with elements 0 = field, 1 = value.
     */
    public function parseSpatialDaterangeFilter($filter)
    {
        $regex 
            = '/^{\!field f=' . self::SPATIAL_DATERANGE_FIELD . ' op=(.+)}(.+)$/';
        if (!preg_match($regex, $filter, $matches)) {
            return false;
        }
        return [self::SPATIAL_DATERANGE_FIELD, $matches[2], ['type' => $matches[1]]];
    }

    /**
     * Restore settings from a minified object found in the database.
     *
     * @param \VuFind\Search\Minified $minified Minified Search Object
     *
     * @return void
     */
    public function deminifyFinnaSearch($minified)
    {
        $dateFilter = [];
        $dateFilter['type'] = $minified->f_dty;

        $this->spatialDateRangeFilter = $dateFilter;
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
     *  TODO
     *
     * @param \Zend\StdLib\Parameters $request Parameter object representing user
     * request.
     *
     * @return void
     */
    protected function initSpatialDateRangeFilter($request)
    {
        $type = $request->get('search_daterange_mv_type');
        if (!$type) {
            $type = $request->get('search_sdaterange_mvtype');
        }
        if (!$type) {
            $type = 'overlap';

        }
        $dateFilter = [];
        $dateFilter['type'] = $type;

        $from = $to = null;
        $filters = $this->getFilters();
        $found = false;

        // VuFind1/VuFind2 timerange filter
        if ($reqFilters = $request->get('filter')) {
            foreach ($reqFilters as $f) {
                list($field, $value) = $this->parseFilter($f);
                list($operator, $field) = $this->parseOperatorAndFieldName($field);
                
                $timerange_VF1 = $field == self::SPATIAL_DATERANGE_FIELD_VF1;
                $timerange = $field == self::SPATIAL_DATERANGE_FIELD;
                
                if ($timerange || $timerange_VF1) {
                    if ($range = Utils::parseSpatialDateRange(
                        $f, $type, $timerange
                    )) {
                        $from = $range['from'];
                        $to = $range['to'];
                        $found = true;                        
                    }
                }
            }        
        }

        // Uninitialized VuFind1 timerange query    
        if (!$found && $request->get('sdaterange')) {            
            // Search for VuFind1 search_sdaterange_mvfrom, search_sdaterange_mvto
            $from = $request->get('search_sdaterange_mvfrom');
            $to = $request->get('search_sdaterange_mvto');
            $vufind2Range = false;
            $found = true;
        }


        $this->spatialDateRangeFilter = $dateFilter;

        if (!$found) {
            return;        
        }

        $dateFilter['to'] = $to;
        $dateFilter['from'] = $from;
        $dateFilter['val'] = "[$from TO $to]";
        $dateFilter['field'] = self::SPATIAL_DATERANGE_FIELD;
        $dateFilter['query'] 
            = $dateFilter['field'] . ':"' . $dateFilter['val'] . '"';

        $this->spatialDateRangeFilter = $dateFilter;

        parent::addFilter($dateFilter['query']);

                    /*
                    if ($range = SolrUtils::parseRange($current)) {
                        $from = $range['from'] == '*' ? '' : $range['from'];
                        $to = $range['to'] == '*' ? '' : $range['to'];
                        $savedSearch->getParams()
                            ->removeFilter($field . ':' . $current);
                        break;
                        }*/

    }

}
