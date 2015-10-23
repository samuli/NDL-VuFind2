<?php
/**
 * Additional functionality for Finna SideFacets
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
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:record_drivers Wiki
 */
namespace Finna\Recommend;
use VuFind\Solr\Utils as SolrUtils;

/**
 * Additional functionality for Finna SideFacets
 *
 * @category VuFind2
 * @package  RecordDrivers
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:record_drivers Wiki
 *
 * @SuppressWarnings(PHPMD.ExcessivePublicCount)
 */
trait SideFacetsTrait
{
    /**
     * Get new items facets (facet titles)
     *
     * @return array
     */
    public function getNewItemsFacets()
    {
        if (!isset($this->newItemsFacets)) {
            return [];
        }

        $filters = $this->results->getParams()->getFilters();
        $result = [];
        foreach ($this->newItemsFacets as $current) {
            $from = '';
            if (isset($filters[$current])) {
                foreach ($filters[$current] as $filter) {
                    if ($range = SolrUtils::parseRange($filter)) {
                        $from = $range['from'] == '*' ? '' : $range['from'];
                        break;
                    }
                }
            }
            $translatable = '';
            if (preg_match('/^NOW-(\w+)/', $from, $matches)) {
                $translatable = 'new_items_' . strtolower($matches[1]);
            }
            $result[$current] = [
                'raw' => $from,
                'translatable' => $translatable,
                'date' => substr($from, 0, 10)
            ];
        }
        return $result;
    }
}
