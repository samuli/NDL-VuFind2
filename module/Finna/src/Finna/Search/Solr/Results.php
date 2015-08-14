<?php
/**
 * Solr aspect of the Search Multi-class (Results)
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
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://www.vufind.org  Main Page
 */
namespace Finna\Search\Solr;
use Finna\Search\Solr\Params;

/**
 * Solr Search Parameters
 *
 * @category VuFind2
 * @package  Search_Solr
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://www.vufind.org  Main Page
 */
class Results extends \VuFind\Search\Solr\Results
{
    use \Finna\Search\Results\SearchResultsTrait;

    /**
     * Returns the stored list of facets for the last search
     *
     * @param array $filter Array of field => on-screen description listing
     * all of the desired facet fields; set to null to get all configured values.
     *
     * @return array        Facets data arrays
     */
    public function getFacetList($filter = null)
    {
        $list = parent::getFacetList($filter);

        // Append date range facet to the list so that it gets
        // included even when facet counts are zero.
        if (!isset($list[Params::SPATIAL_DATERANGE_FIELD])
            && (is_null($filter) || isset($filter[Params::SPATIAL_DATERANGE_FIELD]))
        ) {
            // Resolve facet index in list
            $ind = 0;
            $filter = $filter ?: $this->getParams()->getFacetConfig();

            if (!isset($filter[Params::SPATIAL_DATERANGE_FIELD])) {
                return $list;
            }

            foreach (array_keys($filter) as $field) {
                if ($field == Params::SPATIAL_DATERANGE_FIELD) {
                    break;
                }
                $ind++;
            }

            $data = [];
            $filter = $filter[Params::SPATIAL_DATERANGE_FIELD];
            $data['label'] = $filter;
            $data['list'] = $filter;

            $list
                = array_slice($list, 0, $ind)
                + [Params::SPATIAL_DATERANGE_FIELD => $data]
                + array_slice($list, $ind);
        }
        return $list;
    }
}
