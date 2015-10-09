<?php
/**
 * Finna search controller trait.
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
 * @package  Controller
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
namespace Finna\Controller;

/**
 * Finna search controller trait.
 *
 * @category VuFind2
 * @package  Controller
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
trait SearchControllerTrait
{
    /**
     * Pass saved search ids from all tabs to layout.
     *
     * @return void
     */
    protected function initSavedTabs()
    {
        if ($savedTabs = $this->getRequest()->getQuery()->get('search')) {
            $saved = [];
            foreach ($savedTabs as $tab) {
                list($searchClass, $searchId) = explode(':', $tab);
                $saved[$searchClass] = $searchId;
            }
            $this->layout()->savedTabs = $saved;
        }
    }

    /**
     * Append search filters from a active search to the request object.
     * This is used in the combined results view.
     *
     * @return void
     */
    protected function initCombinedViewFilters()
    {
        $query = $this->getRequest()->getQuery();
        if (!(boolean)$query->get('combined')) {
            return;
        }

        $combined = $this->getCombinedSearches();
        if (!isset($combined[$this->searchClassId])) {
            // No active search with this search class
            return;
        }

        $searchId = $combined[$this->searchClassId];
        $manager
            = $this->getServiceLocator()->get('VuFind\SearchResultsPluginManager');

        if (!$filters = $this->getTable('Search')->getSearchFilters(
            $searchId, $manager
        )) {
            return;
        }

        $this->getRequest()->getQuery()->set('filter', $filters);
    }

    /**
     * Return active searches from the request object as
     * an array of searchClass => searchId elements.
     * This is used in the combined results view.
     *
     * @return array
     */
    protected function getCombinedSearches()
    {
        $query = $this->getRequest()->getQuery();
        if (!$saved = $query->get('search')) {
            return false;
        }

        $ids = [];
        foreach ($saved as $search) {
            list($backend, $searchId) = explode(':', $search, 2);
            $ids[$backend] = $searchId;
        }
        return $ids;
    }

    /**
     * Return configured MetaLib search sets that are searchable.
     *
     * @return array
     */
    protected function getMetalibSets()
    {
        $auth
            = $this->serviceLocator->get('ZfcRbac\Service\AuthorizationService');
        $configLoader = $this->getServiceLocator()->get('VuFind\Config');
        $access = $auth->isGranted('finna.authorized') ? 'authorized' : 'guest';
        $sets = $configLoader->get('MetaLibSets')->toArray();
        $allowedSets = [];
        foreach ($sets as $key => $set) {
            if (!isset($set['access']) || $set['access'] == $access) {
                $allowedSets[$key] = $set;
            }
        }
        return $allowedSets;
    }

    /**
     * Return current MetaLib search set.
     *
     * @return array
     */
    protected function getCurrentMetalibSet()
    {
        $allowedSets = $this->getMetalibSets();
        $currentSet = $this->getRequest()->getQuery()->get('set');
        if ($currentSet && strncmp($currentSet, '_ird:', 5) == 0) {
            $ird = substr($currentSet, 5);
            if (!preg_match('/\W/', $ird)) {
                return [true, $currentSet];
            }
        } else if ($currentSet) {
            if (array_key_exists($currentSet, $allowedSets)) {
                return [false, $currentSet];
            }
        }
        return [false, current(array_keys($allowedSets))];
    }

    /**
     * Return IRD's of the current MetaLib search set.
     *
     * @return array
     */
    protected function getCurrentMetalibIrds()
    {
        list($isIrd, $set) = $this->getCurrentMetalibSet();
        if (!$isIrd) {
            $allowedSets = $this->getMetalibSets();
            if (!isset($allowedSets[$set]['ird_list'])) {
                return false;
            }
            $set = $allowedSets[$set]['ird_list'];
        }
        return explode(',', $set);
    }
}
