<?php
/**
 * "Search tabs" view helper
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
 * @package  View_Helpers
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
namespace Finna\View\Helper\Root;
use \Finna\Search\UrlQueryHelper,
    Zend\Session\Container as SessionContainer;

/**
 * "Search tabs" view helper
 *
 * @category VuFind2
 * @package  View_Helpers
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
class SearchTabs extends \VuFind\View\Helper\Root\SearchTabs
{
    /**
     * Database manager
     *
     * @var PluginManager
     */
    protected $table;

    /**
     * Session manager
     *
     * @var SessionManager
     */
    protected $session;

    /**
     * Active search class
     *
     * @var string
     */
    protected $activeSearchClass;

    /**
     * Constructor
     *
     * @param SessionManager $session Session manager
     * @param PluginManager  $table   Database manager
     * @param PluginManager  $results Search results plugin manager
     * @param array          $config  Tab configuration
     * @param Url            $url     URL helper
     */
    public function __construct(
        \Zend\Session\SessionManager $session,
        \VuFind\Db\Table\PluginManager $table,
        \VuFind\Search\Results\PluginManager $results,
        array $config, \Zend\View\Helper\Url $url
    ) {
        $this->session = $session;
        $this->table = $table;
        parent::__construct($results, $config, $url);
    }

    /**
     * Determine information about search tabs
     *
     * @param string $activeSearchClass The search class ID of the active search
     * @param string $query             The current search query
     * @param string $handler           The current search handler
     * @param string $type              The current search type (basic/advanced)
     * @param array  $savedSearches     Saved search ids from all search tabs
     *
     * @return array
     */
    public function __invoke(
        $activeSearchClass, $query, $handler, $type = 'basic', $savedSearches = []
    ) {
        $this->activeSearchClass = $activeSearchClass;
        $helper = $this->getView()->results->getUrlQuery();

        $tabs = parent::__invoke($activeSearchClass, $query, $handler, $type);

        foreach ($tabs as &$tab) {
            if (isset($tab['url'])) {
                $searchClass = $tab['class'];
                if (isset($savedSearches[$searchClass])) {
                    $searchId = $savedSearches[$tab['class']];
                    $searchSettings = $this->getSearchSettings($searchId);
                    $targetClass = $tab['class'];

                    // Make sure that tab url does not contain the
                    // search id for the same tab.
                    $parts = parse_url($tab['url']);
                    parse_str($parts['query'], $params);

                    if (isset($searchSettings['params'])) {
                        $params = array_merge($params, $searchSettings['params']);
                    }

                    if (isset($params['search'])) {
                        $filtered = [];
                        foreach ($params['search'] as $search) {
                            list($searchClass, $searchId) = explode(':', $search);
                            if ($searchClass !== $targetClass) {
                                $filtered[] = $search;
                            }
                        }
                        if (!empty($filtered)) {
                            $params['search'] = $filtered;
                        } else {
                            unset($params['search']);
                        }
                    }
                    
                    $url = $parts['path'] . '?' . http_build_query($params);
                    $tab['url'] = $url;
                    if (isset($searchSettings['filters'])) {
                        $tab['url'] .= '&' .
                            $helper->buildQueryString(
                                array('filter' => $searchSettings['filters']), false
                        );
                    }
                }
            }
        }

        return $tabs;
    }

    /**
     * Map a search query from one class to another.
     *
     * @param \VuFind\Search\Base\Options $activeOptions Search options for source
     * @param string                      $targetClass   Search class ID for target
     * @param string                      $query         Search query to map
     * @param string                      $handler       Search handler to map
     *
     * @return string
     */
    protected function remapBasicSearch($activeOptions, $targetClass, $query,
        $handler
    ) {
        // Set up results object for URL building:
        $results = $this->results->get($targetClass);
        $options = $results->getOptions();

        // Find matching handler for new query (and use default if no match):
        $targetHandler = $options->getHandlerForLabel(
            $activeOptions->getLabelForBasicHandler($handler)
        );

        // Clone helper so that we can remove active filters
        $urlQuery = $this->getView()->results->getUrlQuery();
        $urlQuery = clone($urlQuery);

        // Remove current filters
        $urlQuery->removeAllFilters();

        $filters = $this->getView()->results->getParams()->getFilters();
        if (!empty($filters)) {
            // Filters active, include current search id in the url
            $searchClass = $this->activeSearchClass;
            $searchId = $this->getView()->results->getSearchId();
            $query = $urlQuery->setSearchId($searchClass, $searchId);
        } else {
            $query = $urlQuery->getParams(false);
        }

        // Build new URL:
        $results->getParams()->setBasicSearch($query, $targetHandler);
        return $this->url->__invoke($options->getSearchAction())
            . $query;
    }

    /**
     * Return filters for a saved search.
     *
     * @param int $id Search id
     *
     * @return mixed array of filters or false if the given search has no filters.
     */
    protected function getSearchSettings($id)
    {
        if (!$search = $this->table->get('Search')->getRowById($id, false)) {
            return false;
        }

        $sessId = $this->session->getId();
        if ($search->session_id == $sessId) {
            $minSO = $search->getSearchObject();
            $savedSearch = $minSO->deminify($this->results);

            $params = $savedSearch->getUrlQuery()->getParamArray();

            $settings = [];
            if (isset($params['filter'])) {
                $settings['filters'] = $params['filter'];
            }
            
            $spatialDateRangeType = null;
            if (isset($params['search_sdaterange_mvtype'])) {
                $spatialDateRangeType = $params['search_sdaterange_mvtype'];
            } else {
                $session = new SessionContainer('spatialDateRangeType');
                if (!empty($session['type'])) {
                    $spatialDateRangeType = $session['type'];
                }
            }

            if ($spatialDateRangeType) {
                $settings['params'] = ['search_sdaterange_mvtype' => $spatialDateRangeType];
            }


            return $settings;


            foreach ($params as $key => $value) {
                if ($key == 'filter') {
                    return $value;
                }
            }
        }
        return false;
    }
}
