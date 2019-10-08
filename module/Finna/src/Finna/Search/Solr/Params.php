<?php
/**
 * Solr Search Parameters
 *
 * PHP version 7
 *
 * Copyright (C) The National Library of Finland 2015-2016.
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
 * @package  Search_Solr
 * @author   Mika Hatakka <mika.hatakka@helsinki.fi>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
namespace Finna\Search\Solr;

use Finna\Search\Solr\AuthorityHelper;
use VuFind\Solr\Utils;

/**
 * Solr Search Parameters
 *
 * @category VuFind
 * @package  Search_Solr
 * @author   Mika Hatakka <mika.hatakka@helsinki.fi>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class Params extends \VuFind\Search\Solr\Params
{
    use \Finna\Search\FinnaParams;

    /**
     * Search handler for browse actions
     *
     * @var string
     */
    protected $browseHandler;

    /**
     * Date converter
     *
     * @var \Vufind\Date\Converter
     */
    protected $dateConverter;

    /**
     * New items facet configuration
     *
     * @var array
     */
    protected $newItemsFacets = [];

    /**
     * Query debug flag
     *
     * @var bool
     */
    protected $debugQuery = false;

    /**
     * Whether to request checkbox facet counts
     *
     * @var bool
     */
    protected $checkboxFacetCounts = false;

    // Date range index field (VuFind1)
    const SPATIAL_DATERANGE_FIELD_VF1 = 'search_sdaterange_mv';
    const SPATIAL_DATERANGE_FIELD_TYPE_VF1 = 'search_sdaterange_mvtype';

    /**
     * Hierarchical facet limit when facets are requested.
     *
     * @var int|null
     */
    protected $hierarchicalFacetLimit = null;

    /**
     * Search runner for performing authority index lookups.
     *
     * @var \VuFind\Search\SearchRunner
     */
    protected $searchRunner = null;

    /**
     * Helper for formatting authority id filter display texts.
     *
     * @var AuthorityHelper
     */
    protected $authorityHelper = null;

    protected $facetFilters = [];

    /**
     * Constructor
     *
     * @param \VuFind\Search\Base\Options  $options         Options to use
     * @param \VuFind\Config\PluginManager $configLoader    Config loader
     * @param HierarchicalFacetHelper      $facetHelper     Hierarchical
     * facet helper
     * @param AuthorityHelper              $authorityHelper Authority helper
     * @param \VuFind\Date\Converter       $dateConverter   Date converter
     * @param \VuFind\Search\SearchRunner  $searchRunner    Search runner
     */
    public function __construct($options,
        \VuFind\Config\PluginManager $configLoader,
        HierarchicalFacetHelper $facetHelper,
        AuthorityHelper $authorityHelper,
        \VuFind\Date\Converter $dateConverter,
        \VuFind\Search\SearchRunner $searchRunner
    ) {
        parent::__construct($options, $configLoader, $facetHelper);

        $this->dateConverter = $dateConverter;
        $config = $configLoader->get($options->getFacetsIni());

        // New items facets
        if (isset($config->SpecialFacets->newItems)) {
            $this->newItemsFacets = $config->SpecialFacets->newItems->toArray();
        }

        $this->searchRunner = $searchRunner;
        $this->authorityHelper = $authorityHelper;
    }

    /**
     * Restore settings from a minified object found in the database.
     *
     * @param \VuFind\Search\Minified $minified Minified Search Object
     *
     * @return void
     */
    public function deminify($minified)
    {
        parent::deminify($minified);
        $dateRangeField = $this->getDateRangeSearchField();
        if (!$dateRangeField) {
            return;
        }
        // Convert any VuFind 1 spatial date range filter
        if (isset($this->filterList[self::SPATIAL_DATERANGE_FIELD_VF1])) {
            $dateRangeFilters = $this->filterList[self::SPATIAL_DATERANGE_FIELD_VF1];
            unset($this->filterList[self::SPATIAL_DATERANGE_FIELD_VF1]);

            foreach ($dateRangeFilters as $filter) {
                if ($range = $this->parseDateRangeFilter($filter)) {
                    $from = $range['from'];
                    $to = $range['to'];
                    $type = $range['type'] ?? 'overlap';
                    $filter = "$dateRangeField:$type|[$from TO $to]";
                    parent::addFilter($filter);
                }
            }
        }
    }

    /**
     * Support method for initSearch() -- handle basic settings.
     *
     * @param \Zend\StdLib\Parameters $request Parameter object representing user
     * request.
     *
     * @return boolean True if search settings were found, false if not.
     */
    protected function initBasicSearch($request)
    {
        if ($handler = $request->get('browseHandler')) {
            $this->setBrowseHandler($handler);
        }
        return parent::initBasicSearch($request);
    }

    /**
     * Return the selected search handler (null for complex searches which have no
     * single handler)
     *
     * @return string|null
     */
    public function getSearchHandler()
    {
        return $this->browseHandler ?: parent::getSearchHandler();
    }

    /**
     * Set search handler for browse actions
     *
     * @param string $handler Hander
     *
     * @return string|null
     */
    public function setBrowseHandler($handler)
    {
        return $this->browseHandler = $handler;
    }

    /**
     * Does the object already contain the specified filter?
     *
     * @param string $filter A filter string from url : "field:value"
     *
     * @return bool
     */
    public function addFilter($filter)
    {
        // Extract field and value from URL string:
        list($field, $value) = $this->parseFilter($filter);

        if ($field == $this->getDateRangeSearchField()
            || $field == self::SPATIAL_DATERANGE_FIELD_VF1
        ) {
            // Date range filters are processed
            // separately (see initSpatialDateRangeFilter)
            return;
        }
        parent::addFilter($filter);
    }

    /**
     * Return current date range filter.
     *
     * @return mixed false|array Filter
     */
    public function getDateRangeFilter()
    {
        $filterList = $this->getFilterList();
        foreach ($filterList as $facet => $filters) {
            foreach ($filters as $filter) {
                if ($this->isDateRangeFilter($filter['field'])) {
                    return $filter;
                }
            }
        }
        return false;
    }

    /**
     * Return the current filters as an array of strings ['field:filter']
     *
     * @return array $filterQuery
     */
    public function getFilterSettings()
    {
        $result = parent::getFilterSettings();

        // Special processing for date range filters
        $dateRangeField = $this->getDateRangeSearchField();
        if ($dateRangeField) {
            foreach ($result as &$filter) {
                $dateRange = strncmp(
                    $filter, "$dateRangeField:", strlen($dateRangeField) + 1
                ) == 0;
                if ($dateRange) {
                    list($field, $value) = $this->parseFilter($filter);
                    list($op, $range) = explode('|', $value);
                    $op = $op == 'within' ? 'Within' : 'Intersects';
                    $filter = "{!field f=$dateRangeField op=$op}$range";
                }
            }
        }
        return $result;
    }

    /**
     * Create search backend parameters for advanced features.
     *
     * @return ParamBag
     */
    public function getBackendParameters()
    {
        $result = parent::getBackendParameters();

        if ($this->debugQuery) {
            $result->add('debugQuery', 'true');
        }

        // Restore original sort if we have geographic filters
        $sort = $this->normalizeSort($this->getSort());
        $newSort = $result->get('sort');
        if ($newSort && $newSort[0] != $sort) {
            $filters = $result->get('fq');
            if (null !== $filters) {
                foreach ($filters as $filter) {
                    if (strncmp($filter, '{!geofilt ', 10) == 0) {
                        $newSort[0] = $this->normalizeSort($sort);
                        $result->set('sort', $newSort);
                        break;
                    }
                }
            }
        }

        foreach ($this->facetFilters as $filter => $value) {
            $result->add($filter, $value);
        }

        return $result;
    }

    /**
     * Return current facet configurations.
     * Add checkbox facets to list.
     *
     * @return array $facetSet
     */
    public function getFacetSettings()
    {
        $facetSet = parent::getFacetSettings();
        if (!empty($facetSet)
            && null !== $this->hierarchicalFacetLimit
            && $this->facetLimit !== $this->hierarchicalFacetLimit
        ) {
            $hierarchicalFacets = $this->getOptions()->getHierarchicalFacets();
            foreach ($hierarchicalFacets as $field) {
                $facetSet["f.{$field}.facet.limit"] = $this->hierarchicalFacetLimit;
            }
        }

        // For checkbox counts
        if ($this->checkboxFacetCounts && !empty($this->checkboxFacets)) {
            foreach (array_keys($this->checkboxFacets) as $facetField) {
                $facetField = '{!ex=' . $facetField . '_filter}' . $facetField;
                $facetSet['field'][] = $facetField;
            }
        }

        return $facetSet;
    }

    /**
     * Pull the search parameters
     *
     * @param \Zend\StdLib\Parameters $request Parameter object representing user
     * request.
     *
     * @return void
     */
    public function initFromRequest($request)
    {
        // Check for advanced search from VuFind1 missing join and/or bool parameter:
        if (null === $request->get('lookfor')) {
            if (null === $request->get('join')) {
                $request->set('join', 'AND');
            }
            $bool0 = $request->get('bool0');
            if (!is_array($bool0) || empty(array_filter($bool0))) {
                $request->set('bool0', ['AND']);
            }
        }

        // Check for VuFind1 orfilters and convert them:
        if ($orFilters = $request->get('orfilter')) {
            $filters = $request->get('filter', []);
            foreach ($orFilters as $filter) {
                $filters[] = "~$filter";
            }
            $request->set('filter', $filters);
            $request->set('orfilter', null);
        }

        parent::initFromRequest($request);

        $this->setDebugQuery($request->get('debugSolrQuery', false));
    }

    /**
     * Initialize coordinate filter (coordinates, VuFind1)
     *
     * @param \Zend\StdLib\Parameters $request Parameter object representing user
     * request.
     *
     * @return void
     */
    public function initCoordinateFilter($request)
    {
        $coordinates = $request->get('coordinates');
        if (null === $coordinates) {
            return;
        }

        // Convert simple coordinates to a polygon
        $simple = preg_match(
            '/^([\d\.]+)\s+([\d\.]+)\s+([\d\.]+)\s+([\d\.]+)$/',
            $coordinates,
            $matches
        );
        if ($simple) {
            list(, $minX, $minY, $maxX, $maxY) = $matches;
            $coordinates = "POLYGON(($minX $maxY,$maxX $maxY,$maxX $minY"
                . ",$minX $minY,$minX $maxY))";
        }
        $this->addFilter(
            '{!score=none}location_geo:"Intersects('
            . str_replace('"', '\"', $coordinates) . ')"'
        );
    }

    /**
     * Initialize date range filter (search_daterange_mv)
     *
     * @param \Zend\StdLib\Parameters $request Parameter object representing user
     * request.
     *
     * @return void
     */
    public function initSpatialDateRangeFilter($request)
    {
        $dateRangeField = $this->getDateRangeSearchField();
        if (!$dateRangeField) {
            return;
        }
        $type = $request->get("{$dateRangeField}_type");
        if (!$type) {
            // VuFind 1
            $type = $request->get(self::SPATIAL_DATERANGE_FIELD_TYPE_VF1);
        }
        if (!$type) {
            $type = 'overlap';
        }

        $from = $to = null;
        $filters = $this->getFilters();
        $found = false;

        // Date range filter
        if (($reqFilters = $request->get('filter')) && is_array($reqFilters)) {
            foreach ($reqFilters as $f) {
                list($field, $value) = $this->parseFilter($f);
                if ($field == $dateRangeField
                    || $field == self::SPATIAL_DATERANGE_FIELD_VF1
                ) {
                    if ($range = $this->parseDateRangeFilter($f)) {
                        $from = $range['from'];
                        $to = $range['to'];
                        if (isset($range['type'])) {
                            $type = $range['type'];
                        }
                        $found = true;
                        break;
                    }
                }
            }
        }

        // Uninitialized VuFind1 date range query
        if (!$found && $request->get('sdaterange')) {
            // Search for VuFind1 search_sdaterange_mvfrom, search_sdaterange_mvto
            $from = $request->get('search_sdaterange_mvfrom');
            $to = $request->get('search_sdaterange_mvto');
            if (!empty($from) || !empty($to)) {
                if (empty($from)) {
                    $from = -9999;
                }
                if (empty($to)) {
                    $to = 9999;
                }
                $found = true;
            }
        }

        if (!$found) {
            return;
        }

        // Add filter. The final Solr filter is constructed in getFilterSettings.
        $filter = "$dateRangeField:$type|[$from TO $to]";
        parent::addFilter($filter);
    }

    /**
     * Get query debug flag status
     *
     * @return bool
     */
    public function getDebugQuery()
    {
        return $this->debugQuery;
    }

    /**
     * Enable or disable query debugging
     *
     * @param bool $value Whether to enable debugging
     *
     * @return void
     */
    public function setDebugQuery($value)
    {
        $this->debugQuery = $value;
    }

    /**
     * Whether to request checkbox facet counts
     *
     * @return bool
     */
    public function getCheckboxFacetCounts()
    {
        return $this->checkboxFacetCounts;
    }

    /**
     * Whether to request checkbox facet counts
     *
     * @param bool $value Enable or disable
     *
     * @return void
     */
    public function setCheckboxFacetCounts($value)
    {
        $this->checkboxFacetCounts = $value;
    }

    /**
     * Remove all hidden filters
     *
     * @return void
     */
    public function clearHiddenFilters()
    {
        $this->hiddenFilters = [];
    }

    /**
     * Get current limit for hierarchical facets
     *
     * @return int
     */
    public function getHierarchicalFacetLimit()
    {
        return $this->hierarchicalFacetLimit;
    }

    /**
     * Set limit for hierarchical facets
     *
     * @param int $limit New limit
     *
     * @return void
     */
    public function setHierarchicalFacetLimit($limit)
    {
        $this->hierarchicalFacetLimit = $limit;
    }

    public function addFacetFilter($field, $value, $prefix = true)
    {
        $key = $prefix ? 'prefix' : 'contains';
        $this->facetFilters["f.{$field}.facet.{$key}"] = $value;
    }

    /**
     * Return id of active author_id filter.
     *
     * @return mixed null|string
     */
    public function getAuthorIdFilter($all = false, $includeRole = false)
    {
        $result = [];
        foreach ($this->getFilterList() as $key => $val) {
            if (!$all && !empty($result)) {
                break;
            }

            $authorRoleFilter
                = in_array($key, ['Author', AuthorityHelper::AUTHOR_ID_FACET_LABEL]);
            $authorIdFilter = in_array($key, ['Author', 'authority_id_label']);
            if ($authorIdFilter || $authorRoleFilter) {
                foreach ($val as $filterItem) {
                    $filter = $filterItem['value'] ?? null;
                    if (!$filter) {
                        continue;
                    }
                    if ($authorIdFilter) {
                        $result[] = $filter;
                        /*
                        $parts = explode(' ', $filter);
                        list($key, $val) = explode(':', substr($parts[0], 1, -1), 2);
                        $result[] = substr(trim($val), 1, -1);
                        */
                    } else {
                        if ($includeRole) {
                            $result[] = $filter;
                            continue;
                        }
                        list($id, $role) = $this->authorityHelper->extractRole($filter);
                        $result[] = $id;
                    }
                }
            }
        }
        return !empty($result)
            ? $all ? $result : $result[0]
            : null;
    }

    /**
     * Format a single filter for use in getFilterList().
     *
     * @param string $field     Field name
     * @param string $value     Field value
     * @param string $operator  Operator (AND/OR/NOT)
     * @param bool   $translate Should we translate the label?
     *
     * @return array
     */
    protected function formatFilterListEntry($field, $value, $operator, $translate)
    {
        if (!in_array($field, $this->newItemsFacets)
            || !($range = Utils::parseRange($value))
        ) {
            if ($translate
                && in_array($field, $this->getOptions()->getHierarchicalFacets())
            ) {
                return $this->translateHierarchicalFacetFilter(
                    $field, $value, $operator
                );
            }
            $result = parent::formatFilterListEntry(
                $field, $value, $operator, $translate
            );
            $result = $this->formatDateRangeFilterListEntry($result, $field, $value);
            return $this->formatAuthorIdFilterListEntry($result, $field, $value);
        }

        $domain = $this->getOptions()->getTextDomainForTranslatedFacet($field);
        list($from, $fromDate) = $this->formatNewItemsDateForDisplay(
            $range['from'], $domain
        );
        list($to, $toDate) = $this->formatNewItemsDateForDisplay(
            $range['to'], $domain
        );
        $ndash = html_entity_decode('&#x2013;', ENT_NOQUOTES, 'UTF-8');
        if ($fromDate && $toDate) {
            $displayText = $from ? "$from $ndash" : $ndash;
            $displayText .= $to ? " $to" : '';
        } else {
            $displayText = $from;
            $displayText .= $to ? " $ndash $to" : '';
        }

        $displayText
            = $this->authorityHelper->formatFacet($field, $displayText);
        return compact('value', 'displayText', 'field', 'operator');
    }

    /**
     * Get a user-friendly string to describe the provided facet field.
     *
     * @param string $field   Facet field name.
     * @param string $value   Facet value.
     * @param string $default Default field name (null for default behavior).
     *
     * @return string         Human-readable description of field.
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function getFacetLabel($field, $value = null, $default = null)
    {
        if ($field === AuthorityHelper::AUTHOR2_ID_FACET
        ) {
            return 'authority_id_label';
        }
        return parent::getFacetLabel($field, $value, $default);
    }

    public function hasAuthorIdFilter()
    {
        foreach ($this->getFilterList() as $key => $val) {
            if (in_array(
                $key,
                ['authority_id_label', 'Author', AuthorityHelper::AUTHOR_ID_FACET_LABEL]
            )
            ) {
                return true;
            }
        }
        return false;
    }

    /**
     * Format display text for a author-id filter entry.
     *
     * @param array  $filter Filter
     * @param string $field  Filter field
     * @param string $value  Filter value
     *
     * @return array
     */
    protected function formatAuthorIdFilterListEntry($filter, $field, $value)
    {
        $displayText = $filter['displayText'];
        if ($id = $this->parseAuthorIdFilter($value, false)) {
            // Author id filter  (OR query with <field>:<author-id> pairs)
            $displayText = $this->authorityHelper->formatFacet($id);
        } elseif (in_array(
            $filter['field'],
            $this->authorityHelper->getAuthorIdFacets()
        )
        ) {
            $displayText = $this->authorityHelper->formatFacet($displayText);
        }
        $filter['displayText'] = $displayText;
        return $filter;
    }

    /**
     * Attempt to parse author id from a author-id filter.
     *
     * @param array   $filter       Filter
     * @param boolean $idRoleFilter Does the filter include author role
     * (true) or only author id (false)?
     *
     * @return mixed null|string
     */
    protected function parseAuthorIdFilter($filter, $idRoleFilter = false)
    {
        $field = $idRoleFilter
            ? AuthorityHelper::AUTHOR_ID_ROLE_FACET
            : AuthorityHelper::AUTHOR2_ID_FACET;

        //$pat = sprintf('/\(%s:"([a-z0-9_.:]*)"\) OR \(/', $field);
        $pat = sprintf('/%s:"([a-z0-9_.:]*)"/', $field);

        if (!preg_match($pat, $filter, $matches)) {
            return null;
        }
        return $matches[1];
    }

    /**
     * Translate a hierarchical facet filter
     *
     * Translates each facet level and concatenates the result
     *
     * @param string $field    Field name
     * @param string $value    Field value
     * @param string $operator Operator (AND/OR/NOT)
     *
     * @return array
     */
    protected function translateHierarchicalFacetFilter($field, $value, $operator)
    {
        $domain = $this->getOptions()->getTextDomainForTranslatedFacet($field);
        $parts = explode('/', $value);
        $result = [];
        for ($i = 0; $i <= $parts[0]; $i++) {
            $part = array_slice($parts, 1, $i + 1);
            $key = $i . '/' . implode('/', $part) . '/';
            $result[] = $this->translate($key, null, end($part));
        }
        $displayText = implode(' > ', $result);
        return compact('value', 'displayText', 'field', 'operator');
    }

    /**
     * Format a Solr date for display
     *
     * @param string $date   Date
     * @param string $domain Translation domain
     *
     * @return string
     */
    protected function formatNewItemsDateForDisplay($date, $domain)
    {
        if ($date == '' || $date == '*') {
            return ['', true];
        }
        if (preg_match('/^NOW-(\w+)/', $date, $matches)) {
            return [
                $this->translate("$domain::new_items_" . strtolower($matches[1])),
                false
            ];
        }
        $date = substr($date, 0, 10);
        return [
            $this->dateConverter->convertToDisplayDate('Y-m-d', $date),
            true
        ];
    }

    /**
     * Add filters to the object based on values found in the request object.
     *
     * @param \Zend\StdLib\Parameters $request Parameter object representing user
     * request.
     *
     * @return void
     */
    protected function initFilters($request)
    {
        parent::initFilters($request);
        $this->initSpatialDateRangeFilter($request);
        $this->initNewItemsFilter($request);
        $this->initCoordinateFilter($request);
    }

    /**
     * Initialize new items filter (first_indexed)
     *
     * @param \Zend\StdLib\Parameters $request Parameter object representing user
     * request.
     *
     * @return void
     */
    protected function initNewItemsFilter($request)
    {
        // first_indexed filter automatically included, no query param required
        // (compatible with Finna 1 implementation)
        $from = $request->get('first_indexedfrom');
        $from = $this->formatDateForFullDateRange($from);

        if ($from != '*') {
            $rangeFacet
                = $this->buildFullDateRangeFilter('first_indexed', $from, '*');
            $this->addFilter($rangeFacet);
        }
    }
}
