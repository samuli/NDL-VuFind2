<?php
/**
 * Additional functionality for Finna Solr records.
 *
 * PHP version 5
 *
 * Copyright (C) The National Library 2015.
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
 * @package  RecordDrivers
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:record_drivers Wiki
 */
namespace Finna\RecordDriver;

/**
 * Additional functionality for Finna Solr records.
 *
 * @category VuFind2
 * @package  RecordDrivers
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:record_drivers Wiki
 *
 * @SuppressWarnings(PHPMD.ExcessivePublicCount)
 */
trait SolrFinna
{
    /**
     * Return an associative array of image URLs associated with this record
     * (key = URL, value = description), if available; false otherwise.
     *
     * @param string $size Size of requested images
     *
     * @return mixed
     */
    public function getAllThumbnails($size = 'large')
    {
        return false;
    }

    /**
     * Return access restriction notes for the record.
     *
     * @return array
     */
    public function getAccessRestrictions()
    {
        // Not currently stored in the Solr index
        return [];
    }

    /**
     * Return type of access restriction for the record.
     *
     * @return mixed array with keys:
     *   'copyright'   Copyright (e.g. 'CC BY 4.0')
     *   'link'        Link to copyright info, see IndexRecord::getRightsLink
     *   or false if no access restriction type is defined.
     */
    public function getAccessRestrictionsType()
    {
        return false;
    }

    /**
     * Return building from index.
     *
     * @return string
     */
    public function getBuilding()
    {
        return $this->fields['building'];
    }

    /**
     * Get data source id
     *
     * @return string
     */
    public function getDataSource()
    {
        return isset($this->fields['datasource_str_mv'])
            ? $this->fields['datasource_str_mv'][0]
            : '';
    }

    /**
     * Return an external URL where a displayable description text
     * can be retrieved from, if available; false otherwise.
     *
     * @return mixed
     */
    public function getDescriptionURL()
    {
        return false;
    }

    /**
     * Return genres
     *
     * @return array
     */
    public function getGenres()
    {
        return isset($this->fields['genre'])
            ? $this->fields['genre'] : [];
    }

    /**
     * Get the hierarchy_parent_id(s) associated with this item (empty if none).
     *
     * @return array
     */
    public function getHierarchyParentID()
    {
        return isset($this->fields['hierarchy_parent_id'])
            ? $this->fields['hierarchy_parent_id'] : [];
    }

    /**
     * Get the parent title(s) associated with this item (empty if none).
     *
     * @return array
     */
    public function getHierarchyParentTitle()
    {
        return isset($this->fields['hierarchy_parent_title'])
            ? $this->fields['hierarchy_parent_title'] : [];
    }

    /**
     * Return image rights.
     *
     * @return mixed array with keys:
     *   'copyright'  Copyright (e.g. 'CC BY 4.0') (optional)
     *   'description Human readable description (array)
     *   'link'       Link to copyright info
     *   or false if the record contains no images
     */
    public function getImageRights()
    {
        return false;
    }

    /**
     * Return local record IDs (only works with dedup records)
     *
     * @return array
     */
    public function getLocalIds()
    {
        return isset($this->fields['local_ids_str_mv'])
            ? $this->fields['local_ids_str_mv'] : [];
    }

    /**
     * Get an array of dedup and link data associated with the record.
     *
     * @return array
     */
    public function getMergedRecordData()
    {
        // If local_ids_str_mv is set, we already have all
        if (isset($this->fields['local_ids_str_mv'])) {
            return [
                'records' => $this->createSourceIdArray(
                    $this->fields['local_ids_str_mv']
                ),
                'urls' => isset($this->fields['online_urls_str_mv'])
                    ? $this->mergeURLArray(
                        $this->fields['online_urls_str_mv'],
                        true
                    ) : []
            ];
        }

        // Find the dedup record
        if (null === $this->searchService) {
            return [];
        }

        $safeId = addcslashes($this->getUniqueID(), '"');
        $query = new \VuFindSearch\Query\Query(
            'local_ids_str_mv:"' . $safeId . '"'
        );
        $records = $this->searchService->search('Solr', $query, 0, 1)->getRecords();
        if (!isset($records[0])) {
            return [];
        }
        $results = [];
        $results['records'] = $this->createSourceIdArray($records[0]->getLocalIds());
        if ($onlineURLs = $records[0]->getOnlineURLs(true)) {
            $results['urls'] = $this->mergeURLArray(
                $onlineURLs,
                true
            );
        }
        return $results;
    }

    /**
     * Get all authors apart from presenters
     *
     * @return array
     */
    public function getNonPresenterAuthors()
    {
        $authors = [];
        if ($author = $this->getPrimaryAuthor()) {
            $authors[] = ['name' => $author];
        }
        if ($author = $this->getCorporateAuthor()) {
            $authors[] = ['name' => $author];
        }
        foreach ($this->getSecondaryAuthors() as $author) {
            $authors[] = ['name' => $author];
        }
        return $authors;
    }

    /**
     * Get online URLs
     *
     * @param bool $raw Whether to return raw data
     *
     * @return array
     */
    public function getOnlineURLs($raw = false)
    {
        if (!isset($this->fields['online_urls_str_mv'])) {
            return [];
        }
        return $raw ? $this->fields['online_urls_str_mv'] : $this->mergeURLArray(
            $this->fields['online_urls_str_mv'], true
        );
    }

    /**
     * Get all the original languages associated with the record
     *
     * @return array
     */
    public function getOriginalLanguages()
    {
        return isset($this->fields['original_lng_str_mv'])
            ? $this->fields['original_lng_str_mv'] : [];
    }

    /**
     * Get presenters
     *
     * @return array
     */
    public function getPresenters()
    {
        return [];
    }

    /**
     * Returns an array of parameter to send to Finna's cover generator.
     * Fallbacks to VuFind's getThumbnail if no record image with the
     * given index was found.
     *
     * @param string $size  Size of thumbnail
     * @param int    $index Image index
     *
     * @return array|bool
     */
    public function getRecordImage($size = 'small', $index = 0)
    {
        if ($urls = $this->getAllThumbnails($size)) {
            $urls = array_keys($urls);
            if ($index == 0) {
                $url = $urls[0];
            } else {
                if (isset($urls[$index])) {
                    $url = $urls[$index];
                }
            }
            if (!is_array($url)) {
                return array('id' => $this->getUniqueId(), 'url' => $url);
            }
        }
        $params = parent::getThumbnail($size);
        if ($params && !is_array($params)) {
            $params = ['url' => $params];
        }
        return $params;
    }

    /**
     * Return record format.
     *
     * @return string.
     */
    public function getRecordType()
    {
        return $this->fields['recordtype'];
    }

    /**
     * Return URL to copyright information.
     *
     * @param string $copyright Copyright
     * @param string $language  Language
     *
     * @return mixed URL or false if no URL for the given copyright
     */
    public function getRightsLink($copyright, $language)
    {
        if (isset($this->mainConfig['ImageRights'][$language][$copyright])) {
            return $this->mainConfig['ImageRights'][$language][$copyright];
        }
        return false;
    }

    /**
     * Return record source.
     *
     * @return string.
     */
    public function getSource()
    {
        return isset($this->fields['source']) ? $this->fields['source'] : false;
    }

    /**
     * Return main year.
     *
     * @return string|false
     */
    public function getYear()
    {
        return isset($this->fields['main_date_str'])
            ? $this->fields['main_date_str'] : false;
    }

    /**
     * Get a string representing the first date that the record was indexed.
     *
     * @return string
     */
    public function getFirstIndexed()
    {
        return isset($this->fields['first_indexed'])
            ? $this->fields['first_indexed'] : '';
    }

    /**
     * Extract sources from record IDs and create an array of sources and IDs
     *
     * @param array $ids Record ID's
     *
     * @return array Formatted array
     */
    protected function createSourceIdArray($ids)
    {
        $results = [];
        foreach ($ids as $id) {
            list($source) = explode('.', $id);
            $results[] = [
                'source' => $source,
                'id' => $id
            ];
        }
        return $results;
    }

    /**
     * A helper function that merges an array of JSON-encoded URLs
     *
     * @param array $urlArray Array of JSON-encoded URL attributes
     * @param bool  $sources  Whether to store data source of each URL
     *
     * @return array Array of URL information
     */
    protected function mergeURLArray($urlArray, $sources = true)
    {
        $urls = [];
        foreach ($urlArray as $url) {
            $newURL = json_decode($url, true);
            // If there's no dedup data, don't display sources either
            if (!$sources) {
                $newURL['source'] = '';
            }
            // Check for duplicates
            $found = false;
            foreach ($urls as &$existingUrl) {
                if ($newURL['url'] == $existingUrl['url']) {
                    $found = true;
                    if (is_array($existingUrl['source'])) {
                        $existingUrl['source'][] = $newURL['source'];
                    } else {
                        $existingUrl['source'] = [
                            $existingUrl['source'],
                            $newURL['source']
                        ];
                    }
                    if (!$existingUrl['text']) {
                        $existingUrl['text'] = $newURL['text'];
                    }
                    break;
                }
            }
            if (!$found) {
                $urls[] = $newURL;
            }
        }
        return $urls;
    }

    /**
     * Check if a URL (typically from getURLs()) is blacklisted based on the URL
     * itself and optionally its description.
     *
     * @param string $url  URL
     * @param string $desc Optional description of the URL
     *
     * @return boolean Whether the URL is blacklisted
     */
    protected function urlBlacklisted($url, $desc = '')
    {
        if (!isset($this->recordConfig->Record->url_blacklist)) {
            return false;
        }
        foreach ($this->recordConfig->Record->url_blacklist as $rule) {
            if (substr($rule, 0, 1) == '/' && substr($rule, -1, 1) == '/') {
                if (preg_match($rule, $url)
                    || ($desc !== '' && preg_match($rule, $desc))
                ) {
                    return true;
                }
            } elseif ($rule == $url || $rule == $desc) {
                return true;
            }
        }
        return false;
    }
}
