<?php
/**
 * Solr Utility Functions
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
 * @package  Solr
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
namespace Finna\Solr;

/**
 * Solr Utility Functions
 *
 * This class is designed to hold Solr-related support methods that may
 * be called statically.  This allows sharing of some Solr-related logic
 * between the Solr and Summon classes.
 *
 * @category VuFind2
 * @package  Solr
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
class Utils extends \VuFind\Solr\Utils
{
    /**
     * Parse "from" and "to" values out of a spatial date range 
     * query (or return false if the query is not a range).
     *
     * @param string $query Solr query to parse.
     * @param string $type  Query type ('overlap' or 'within')
     *
     * @return array|bool   Array with 'from' and 'to' values extracted from range
     * or false if the provided query is not a range.
     */
    public static function parseSpatialDateRange($query, $type = 'overlap')
    {
        $regex = false;
        if ($type == 'overlap') {
            $regex = '/[\(\[]\"*[\d-]+\s+([\d-]+)\"*[\s\w]+\"*([\d-]+)\s+[\d-]+\"*[\)\]]/';
        } elseif ($type == 'within') {
            $regex = '/[\(\[]\"*([\d-]+\.?\d*)\s+[\d-]+\"*[\s\w]+\"*[\d-]+\s+([\d-]+\.?\d*)\"*[\)\]]/';
        }

        if (!$regex || !preg_match($regex, $query, $matches)) {
            return false;
        }

        $from = $matches[1];
        $to = $matches[2];

        if ($type == 'within') {
            // Adjust time range end points to match original search query
            // (see SearchObject/Base::buildSpatialDateRangeFilter)
            //$from += 0.5;
            //$to -= 0.5;
        }

        return array('from' => $from * 86400, 'to' => $to * 86400);
    }
}

