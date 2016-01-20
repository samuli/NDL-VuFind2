<?php
/**
 * Solr Autocomplete Module
 *
 * PHP version 5
 *
 * Copyright (C) Villanova University 2010.
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
 * @package  Autocomplete
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Chris Hallberg <challber@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:autosuggesters Wiki
 */
namespace Finna\Autocomplete;

/**
 * Solr Autocomplete Module
 *
 * This class provides suggestions by using the local Solr index.
 *
 * @category VuFind2
 * @package  Autocomplete
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:autosuggesters Wiki
 */
class Solr extends \VuFind\Autocomplete\Solr
{
    /**
     * TODO
     *
     * @var \VuFind\Search\Results\PluginManager
     */
    protected $facetSettings;
    protected $translator;
    protected $facetConfig;
    protected $searchConfig;
    protected $facetTranslations;

    /**
     * Constructor
     *
     * @param \VuFind\Search\Results\PluginManager $results Results plugin manager
     */
    public function __construct(\VuFind\Search\Results\PluginManager $results,
        $facetConfig, $searchConfig, $translator
    ) {
        $settings = [];
        $facets = isset($searchConfig->Autocomplete_Sections->facets)
            ? $searchConfig->Autocomplete_Sections->facets->toArray() : null;

        foreach ($facets as $data) {
            $data = explode(':', $data);
            
            $field = $data[0];
            $label = $data[1];
            $filters = isset($data[2]) ? $data[2] : null;
            $limit = isset($data[3]) ? $data[3] : null;

                            //list($field, $label, $filters, $limit)
                            //                = explode(':', $data);
            // Restrict facet values to top-level if no other filters are defined
            $filters = !empty($filters) ? explode(',', $filters) : ['^0/*.'];
            $settings[$field] = [
               'label' => $label, 'limit' => $limit, 'filters' => $filters
            ];
        }
        $this->facetConfig = $facetConfig;
        $this->searchConfig = $searchConfig;
        $this->facetSettings = $settings;
        $this->facetTranslations = $facetConfig->Results->toArray();
        foreach ($facetConfig->CheckboxFacets->toArray() as $field => $val) {
            list($field,) = explode(':', $field);
            $this->facetTranslations[$field] = $val;
        }
        $this->translator = $translator;
        parent::__construct($results);
    }

    /**
     * This method returns an array of strings matching the user's query for
     * display in the autocomplete box.
     *
     * @param string $query The user query
     *
     * @return array        The suggestions for the provided query
     */
    public function getSuggestions($query)
    {
        $facetLimit = 20;
        $params = $this->searchObject->getParams();
        $params->setFacetLimit($facetLimit);

        $allFacets = array_keys($this->facetSettings);
        $hierarchicalFacets = isset($this->facetConfig->SpecialFacets->hierarchical)
            ? $this->facetConfig->SpecialFacets->hierarchical->toArray() : null;

        $facets = array_diff($allFacets, $hierarchicalFacets);
        foreach ($facets as $facet) {
            $params->addFacet($facet);
        }
        $suggestionsLimit = 5;
        $suggestions = parent::getSuggestions($query);
        if (!empty($suggestions)) {
            $suggestions = array_splice($suggestions, 0, $suggestionsLimit);
        }

        $getFacetValues = function ($facet) {
            return [$facet['value'], $facet['count']];
        };
        $facetResults = [];

        // Facets
        foreach ($this->searchObject->getFacetList() as $field => $data) {
            $label = array_key_exists($field, $this->facetTranslations)
               ? $this->translator->translate($this->facetTranslations[$field])
               : $field;
            $values = $this->filterFacetValues($field, $data['list']);
            $values = $this->extractFacetData($field, $label, $values);
            $facetResults[$field] = ['label' => $label, 'values' => $values];
        }

        // Hierarchical facets
        $this->initSearchObject();
        $this->searchObject->getParams()->setBasicSearch(
            $this->mungeQuery($query), $this->handler
        );
        $this->searchObject->getParams()->setSort($this->sortField);
        foreach ($this->filters as $current) {
            $this->searchObject->getParams()->addFilter($current);
        }
        foreach ($hierarchicalFacets as $facet) {
            $params->addFacet($facet, null, false);
        }
        $hierachicalFacets = $this->searchObject->getFullFieldFacets(
            array_intersect($hierarchicalFacets, $allFacets),
            false, -1, 'count'
        );
        foreach ($hierachicalFacets as $field => $data) {
            $label = array_key_exists($field, $this->facetTranslations)
               ? $this->translator->translate($this->facetTranslations[$field])
               : $field;

            $values = $this->filterFacetValues($field, $data['data']['list']);
            $values = $this->extractFacetData($field, $label, $values, true);
            $facetResults[$field] = [
                'label' => $label,
                'values' => $values
            ];
        }
        $facets = $facetResults;

        // Static links
        
        // Search types
        /*
        $urlHelper = $this->searchObject->getUrlQuery();
        $searchTypes = [];
        foreach ($this->searchConfig->Basic_Searches as $handler => $label) {
            if ($handler == 'AllFields') {
                continue;
            }
            $searchTypes[] = [
                'label' => $this->translator->translate($label),
                'href' => $urlHelper->setHandler($handler, false),
                'type' => 'handler',
            ];
            }*/

        $result = compact('suggestions', 'facets');
        error_log(var_export($result, true));
        return $result;
    }

    protected function filterFacetValues($field, $values)
    {
        $result = [];
        if (isset($this->facetSettings[$field]['filters'])) {
            foreach ($values as $value) {
                foreach ($this->facetSettings[$field]['filters'] as $filter) {
                    $pattern = '/' . addcslashes($filter, '/') . '/';
                    if (preg_match($pattern, $value['value']) === 1) {
                        $result[] = $value;
                        continue;
                    }
                }
            }
        } else {
            $result = $values;
        }
        $limit = isset($this->facetSettings[$field]['limit'])
            ? $this->facetSettings[$field]['limit'] : 10;
        $result = array_splice($result, 0, $limit);

        return $result;
    }

    protected function extractFacetData($facet, $facetLabel, $values, $hierarchicalFacet = false)
    {
        $urlHelper = $this->searchObject->getUrlQuery();
        $paramArray = $urlHelper !== false ? $urlHelper->getParamArray() : null;

        $fn = function ($value) use (
            $facet, $facetLabel, $hierarchicalFacet, $urlHelper, $paramArray
        ) {
            $label = $hierarchicalFacet ? $value['displayText'] : $value['value'];
            $label = in_array($label, ['true','false']) ? $facetLabel : $label;
            $data = [$label, $value['count']];
            $data[] = $urlHelper->addFacet(
                $facet, $value['value'], $value['operator'], $paramArray
            );
            return $data;
        };
        return array_map($fn, $values);
    }
}
