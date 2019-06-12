<?php
/**
 * AuthorityRecommend Recommendations Module
 *
 * PHP version 7
 *
 * Copyright (C) Villanova University 2012.
 * Copyright (C) The National Library of Finland 2019.
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
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category VuFind
 * @package  Recommendations
 * @author   Lutz Biedinger <vufind-tech@lists.sourceforge.net>
 * @author   Ronan McHugh <vufind-tech@lists.sourceforge.net>
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
namespace Finna\Recommend;

/**
 * AuthorityRecommend Module
 *
 * This class provides recommendations based on Authority records.
 * i.e. searches for a pseudonym will provide the user with a link
 * to the official name (according to the Authority index)
 *
 * Originally developed at the National Library of Ireland by Lutz
 * Biedinger and Ronan McHugh.
 *
 * @category VuFind
 * @package  Recommendations
 * @author   Lutz Biedinger <vufind-tech@lists.sourceforge.net>
 * @author   Ronan McHugh <vufind-tech@lists.sourceforge.net>
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class AuthorityRecommend extends \VuFind\Recommend\AuthorityRecommend
{
    /**
     * Perform a search of the authority index.
     *
     * @param array $params Array of request parameters.
     *
     * @return array
     */
    protected function performSearch($params)
    {
        if (empty($this->lookfor)) {
            // Do not show recommendations for empty search
            return [];
        }
        return parent::performSearch($params);
    }

    /**
     * Add main headings from records that match search terms on use_for/see_also.
     *
     * @return void
     */
    protected function addUseForHeadings()
    {

        // Build an advanced search request that prevents Solr from retrieving
        // records that would already have been retrieved by a search of the biblio
        // core, i.e. it only returns results where $lookfor IS found in in the
        // "Heading" search and IS NOT found in the "MainHeading" search defined
        // in authsearchspecs.yaml.
        $params = [
            'join' => 'AND',
            'bool0' => ['AND'],
            'lookfor0' => [$this->lookfor],
            'type0' => ['Heading'],
            'bool1' => ['NOT'],
            'lookfor1' => [$this->lookfor],
            'type1' => ['MainHeading']
        ];

        $this->recommendations = $this->performSearch($params);
    }

    /**
     * Get recommendations (for use in the view).
     *
     * @return array
     */
    public function getRecommendations()
    {
        return $this->recommendations;
    }
}
