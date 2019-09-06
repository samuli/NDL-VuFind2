<?php
/**
 * Helper for formatting display texts for authority id filters.
 *
 * PHP version 7
 *
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
 * @package  Search
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
namespace Finna\Search\Solr;

/**
 * Helper for formatting display texts for authority id filters.
 *
 * @category VuFind
 * @package  Search
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
class AuthorityIdFacetHelper
{
    /**
     * Facet label for author-id-role facet.
     *
     * @var string
     */
    public const AUTHOR_ID_FACET_LABEL = 'Author role';

    /**
     * Index field for author-ids.
     *
     * @var string
     */
    const AUTHOR_ID_FACET = 'author2_id_str_mv';

    /**
     * Index field for author id-role combinations
     *
     * @var string
     */
    const AUTHOR_ID_ROLE_FACET = 'author2_id_role_str_mv';
    
    /**
     * Results plugin manager
     *
     * @var \VuFind\Search\Results\PluginManager
     */
    protected $recordLoader;

    /**
     * Results plugin manager
     *
     * @var \VuFind\Translator
     */
    protected $translator;

    /**
     * Constructor
     *
     * @param \VuFind\Record\Loader              $recordLoader Record loader
     * @param \VuFind\View\Helper\Root\Translate $translator   Translator view helper
     */
    public function __construct(
        \VuFind\Record\Loader $recordLoader,
        \VuFind\View\Helper\Root\Translate $translator
    ) {
        $this->recordLoader = $recordLoader;
        $this->translator = $translator;
    }

    /**
     * Format displayTexts of a facet set.
     *
     * @param array $facetSet Facet set
     *
     * @return array
     */
    public function formatFacetSet($facetSet)
    {
        //die(var_export($facetSet, true));
        if (!isset($facetSet[AuthorityIdFacetHelper::AUTHOR_ID_ROLE_FACET])) {
            return $facetSet;
        }
        return $this->processFacets($facetSet);
    }

    /**
     * Format displayTexts of a facet list.
     *
     * @param string $field  Facet field
     * @param array  $facets Facets
     *
     * @return array
     */
    public function formatFacets($field, $facets)
    {
        if ($field !== AuthorityIdFacetHelper::AUTHOR_ID_ROLE_FACET) {
            return $facets;
        }
        $result = $this->processFacets([$field => ['list' => $facets]]);
        return $result[$field]['list'];
    }

    /**
     * Helper function for processing a facet set.
     *
     * @param array $facetSet Facet set
     *
     * @return array
     */
    protected function processFacets($facetSet)
    {
        $ids = [];
        $facetList
            = $facetSet[AuthorityIdFacetHelper::AUTHOR_ID_ROLE_FACET]['list'] ?? [];
        foreach ($facetList as $facet) {
            list($id, $role) = explode('###', $facet['displayText'], 2);
            $ids[] = $id;
        }

        $records = $this->recordLoader->loadBatchForSource($ids, 'SolrAuth', true);
        foreach ($facetList as &$facet) {
            list($id, $role) = $this->extractRole($facet['displayText']);
            foreach ($records as $record) {
                if ($record->getUniqueId() === $id) {
                    list($displayText, $role)
                        = $this->formatDisplayText($record, $role);
                    $facet['displayText'] = $displayText;
                    $facet['role'] = $role;
                    continue;
                }
            }
        }
        $facetSet[AuthorityIdFacetHelper::AUTHOR_ID_ROLE_FACET]['list'] = $facetList;
        return $facetSet;
    }

    /**
     * Format facet value (display text).
     *
     * @param string $value Facet value
     *
     * @return string
     */
    public function formatFacet($value, $extendedInfo = false)
    {
        $id = $value;
        $role = null;
        list($id, $role) = $this->extractRole($value);
        $record = $this->recordLoader->load($id, 'SolrAuth', true);
        list($displayText, $role) = $this->formatDisplayText($record, $role);
        return $extendedInfo
            ? ['id' => $id, 'displayText' => $displayText, 'role' => $role]
            : $displayText;
    }

    protected function extractRole($value)
    {
        $id = $value;
        $role = null;
        if (strpos($value, '###') !== false) {
            list($id, $role) = explode('###', $value, 2);
        }
        return [$id, $role];
    }

    /**
     * Helper function for formatting author-role display text.
     *
     * @param \Finna\RecordDriver\SolrDefault $record Record driver
     * @param string                          $role   Author role
     *
     * @return string
     */
    protected function formatDisplayText($record, $role = null)
    {
        $displayText = $record->getTitle();
        if ($role) {
            $role = strtolower(
                $this->translator->translate("CreatorRoles::$role")
            );
            $displayText .= " ($role)";
        }
        return [$displayText, $role];
    }
}
