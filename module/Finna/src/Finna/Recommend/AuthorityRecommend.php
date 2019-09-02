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
     * Get recommendations (for use in the view).
     *
     * @return array
     */
    public function getRecommendations()
    {
        return array_unique($this->recommendations, SORT_REGULAR);
    }

    /**
     * Called at the end of the Search Params objects' initFromRequest() method.
     * This method is responsible for setting search parameters needed by the
     * recommendation module and for reading any existing search parameters that may
     * be needed.
     *
     * @param \VuFind\Search\Base\Params $params  Search parameter object
     * @param \Zend\StdLib\Parameters    $request Parameter object representing user
     * request.
     *
     * @return void
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function init($params, $request)
    {
        if ($id = $params->getAuthorIdFilter()) {
            $this->lookfor = 'id:' . $id;
            $this->header = 'Author';
        } else {
            // Save user search query:
            $this->lookfor = $request->get('lookfor');
        }
    }

    /**
     * Add main headings from records that match search terms on use_for/see_also.
     *
     * @return void
     */
    protected function addUseForHeadings()
    {
        $params = ['lookfor' => $this->lookfor, 'type' => 'MainHeading'];
        foreach ($this->performSearch($params) as $result) {
            $this->recommendations[] = $result;
        }
    }
}
