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

use Finna\Search\Solr\AuthorityHelper;
use Zend\StdLib\Parameters;

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
    protected $authorId = null;
    protected $request;
    protected $roles = null;
    protected $authorityHelper = null;

    /**
     * Constructor
     *
     * @param \VuFind\Search\Results\PluginManager $results Results plugin manager
     */
    public function __construct(
        \VuFind\Search\Results\PluginManager $results,
        \Finna\Search\Solr\AuthorityHelper $authorityHelper
    ) {
        $this->resultsManager = $results;
        $this->authorityHelper = $authorityHelper;
    }

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
            $this->authorId = $id;
            $this->lookfor = "id:\"{$id}\"";
            $this->header = 'Author';
            $this->request = $request;
        } else {
            // Save user search query:
            $this->lookfor = $request->get('lookfor');
        }
    }

    /**
     * Called after the Search Results object has performed its main search.  This
     * may be used to extract necessary information from the Search Results object
     * or to perform completely unrelated processing.
     *
     * @param \VuFind\Search\Base\Results $results Search results object
     *
     * @return void
     */
    public function process($results)
    {
        parent::process($results);
        $this->addRoles();
    }

    public function getRoles()
    {
        return $this->roles;
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

    protected function addRoles()
    {
        if (!$this->authorId) {
            return;
        }

        try {
            $results = $this->resultsManager->get('Solr');
            $params = $results->getParams();
            $params->initFromRequest($this->request);

            // Remove existing author-id filter so that we get all
            // author roles when faceting.
            $authorIdFilters = $params->getAuthorIdFilter(true, true);
            if ($authorIdFilters) {
                foreach ($authorIdFilters as $filter) {
                    foreach ($this->authorityHelper->getAuthorIdFacets() as $authorIdField) {
                        $filterItem
                            = sprintf(
                                '%s:%s',
                                $authorIdField,
                                $filter
                            );
                        // Remove AND & OR filters
                        $params->removeFilter($filterItem);
                        $params->removeFilter("~$filterItem");
                    }
                }
            }

            $params->addFacet(AuthorityHelper::AUTHOR_ID_ROLE_FACET);
            $params->addFacetFilter(
                AuthorityHelper::AUTHOR_ID_ROLE_FACET,
                $this->authorityHelper->getAuthorIdRole($this->authorId),
                false
            );
            foreach ($this->filters as $filter) {
                $authParams->addHiddenFilter($filter);
            }
            $facets = $results->getFacetList();
            if (!isset($facets[AuthorityHelper::AUTHOR_ID_ROLE_FACET])) {
                return;
            }

            $roles = $facets[AuthorityHelper::AUTHOR_ID_ROLE_FACET]['list'] ?? [];
            if ($this->authorityHelper) {
                foreach ($roles as &$role) {
                    $authorityInfo = $this->authorityHelper->formatFacet($role['displayText'], true);
                    $role['displayText'] = $authorityInfo['displayText'];
                    $role['role'] = $authorityInfo['role'];
                    $role['enabled'] = in_array($role['value'], $authorIdFilters);
                }
            }
            $this->roles = $roles;
        } catch (RequestErrorException $e) {
            return;
        }
    }
}
