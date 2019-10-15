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
    protected $session = null;
    protected $cookieManager = null;
    
    /**
     * Constructor
     *
     * @param \VuFind\Search\Results\PluginManager $results Results plugin manager
     */
    public function __construct(
        \VuFind\Search\Results\PluginManager $results,
        \Finna\Search\Solr\AuthorityHelper $authorityHelper,
        $session,
        $cookieManager
    ) {
        $this->resultsManager = $results;
        $this->authorityHelper = $authorityHelper;
        $this->session = $session;
        $this->cookieManager = $cookieManager;
    }

    /**
     * Get recommendations (for use in the view).
     *
     * @return array
     */
    public function getRecommendations()
    {
        if (!$this->authorId) {
            return array_unique($this->recommendations, SORT_REGULAR);
        }

        // Make sure that authority records are sorted in the same order
        // as active filters
        $sorted = [];
        $rest = [];
        foreach ($this->recommendations as $r) {
            $pos = array_search($r->getUniqueID(), $this->authorId);
            if (false === $pos) {
                $rest[] = $r;
            } else {
                $sorted[$pos] = $r;
            }
        }
        ksort($sorted);
        return array_merge($sorted, $rest);
    }

    public function getActiveRecommendation()
    {
        return $this->session->activeId ?? null;
    }

    public function collapseAuthorityInfo()
    {
        return $this->cookieManager->get('collapseAuthorityInfo') === 'true';
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
        if ($ids = $params->getAuthorIdFilter(true)) {
            $this->authorId = $ids;
            if ($this->session->ids && $this->session->ids !== $ids) {
                // Reset active authority if filters have been changed.
                // This activated the last selected authority in the UI.
                $this->session->activeId = null;
            }
            $this->lookfor = implode(' OR ', array_map(function($id) { return "(id:\"{$id}\")"; }, $ids));
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
        // Override parent::process to allow advanced search
        
        $this->results = $results;

        // empty searches such as New Items will return blank
        if ($this->lookfor == null) {
            return;
        }

        // check result limit before proceeding...
        if ($this->resultLimit > 0
            && $this->resultLimit < $results->getResultTotal()
        ) {
            return;
        }

        // see if we can add main headings matching use_for/see_also fields...
        if ($this->isModeActive('usefor')) {
            $this->addUseForHeadings();
        }

        // see if we can add see-also references associated with main headings...
        if ($this->isModeActive('seealso')) {
            $this->addSeeAlsoReferences();
        }

        $this->addRoles();
    }

    public function getRoles($id)
    {
        return $this->roles[$id] ?? [];
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
            $resultsOrig = $this->results;

            foreach ($this->authorId as $id) {
                $results = clone $resultsOrig;
                $params = $results->getParams();

                $authorIdFilters = $params->getAuthorIdFilter(true, true);

                $params->addFacet(AuthorityHelper::AUTHOR_ID_ROLE_FACET);
                $paramsCopy = clone $params;
                
                $paramsCopy->addFacetFilter(
                    AuthorityHelper::AUTHOR_ID_ROLE_FACET,
                    $this->authorityHelper->getAuthorIdRole($id),
                    false
                );

                $results->setParams($paramsCopy);
                $results->performAndProcessSearch();
                $facets = $results->getFacetList();

                if (!isset($facets[AuthorityHelper::AUTHOR_ID_ROLE_FACET])) {
                    continue;
                }
                
                $roles
                    = $facets[AuthorityHelper::AUTHOR_ID_ROLE_FACET]['list'] ?? [];
                if ($this->authorityHelper) {
                    foreach ($roles as &$role) {
                        $authorityInfo = $this->authorityHelper->formatFacet(
                            $role['displayText'], true
                        );
                        $role['displayText'] = $authorityInfo['displayText'];
                        $role['role'] = $authorityInfo['role'];
                        $role['enabled']
                            = in_array($role['value'], $authorIdFilters);
                    }
                }
                $this->roles[$id] = $roles;
            }
        } catch (RequestErrorException $e) {
            return;
        }
    }
}
