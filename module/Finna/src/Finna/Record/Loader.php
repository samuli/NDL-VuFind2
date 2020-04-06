<?php
/**
 * Record loader
 *
 * PHP version 7
 *
 * Copyright (C) The National Library of Finland 2016-2019.
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
 * @package  Record
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
namespace Finna\Record;

use VuFind\Exception\RecordMissing as RecordMissingException;
use VuFindSearch\ParamBag;

/**
 * Record loader
 *
 * @category VuFind
 * @package  Record
 * @author   Demian Katz <demian.katz@villanova.edu>
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
class Loader extends \VuFind\Record\Loader
{
    /**
     * Default parameters that are passed to the backend when loading a record.
     *
     * @var array
     */
    protected $defaultParams = null;

    /**
     * Preferred language for display strings from RecordDriver
     *
     * @var string
     */
    protected $preferredLanguage;

    /**
     * Set preferred language for display strings from RecordDriver.
     *
     * @param string $language Language
     *
     * @return void
     */
    public function setPreferredLanguage($language)
    {
        $this->preferredLanguage = $language;
    }

    /**
     * Given an ID and record source, load the requested record object.
     *
     * @param string   $id              Record ID
     * @param string   $source          Record source
     * @param bool     $tolerateMissing Should we load a "Missing" placeholder
     * instead of throwing an exception if the record cannot be found?
     * @param ParamBag $params          Search backend parameters
     *
     * @throws \Exception
     * @return \VuFind\RecordDriver\AbstractBase
     */
    public function load($id, $source = DEFAULT_SEARCH_BACKEND,
        $tolerateMissing = false, ParamBag $params = null
    ) {
        if ($source == 'MetaLib') {
            if ($tolerateMissing) {
                $record = $this->recordFactory->get('Missing');
                $record->setRawData(['id' => $id]);
                $record->setSourceIdentifier($source);
                return $record;
            }
            throw new RecordMissingException(
                'Record ' . $source . ':' . $id . ' does not exist.'
            );
        }
        $missingException = false;
        try {
            if ($this->defaultParams) {
                $params = $params ?? new ParamBag();
                foreach ($this->defaultParams as $key => $val) {
                    $params->set($key, $val);
                }
            }
            $result = parent::load($id, $source, $tolerateMissing, $params);
        } catch (RecordMissingException $e) {
            $missingException = $e;
        }

        if ($missingException || $result instanceof \VuFind\RecordDriver\Missing
        ) {
            if ($source === 'Solr') {
                if (preg_match('/\.(FIN\d+)/', $id, $matches)) {
                    // Probably an old MetaLib record ID.
                    // Try to find the record using its old MetaLib ID
                    if ($mlRecord = $this->loadMetaLibRecord($matches[1])) {
                        return $mlRecord;
                    }
                } elseif (preg_match('/^musketti\..+?:(.+)/', $id, $matches)) {
                    // Old musketti record. Try to find the new record using the
                    // inventory number.
                    $newRecord = $this->loadRecordWithIdentifier(
                        $matches[1], 'museovirasto'
                    );
                    if ($newRecord) {
                        return $newRecord;
                    }
                }
            } elseif ($source === 'R2') {
                // Missing R2 record (probably non-existing restriction level)
                return $this->initMissingR2Record($id);
            }
        }
        if ($missingException) {
            throw $missingException;
        }

        if ($this->preferredLanguage) {
            $result->tryMethod('setPreferredLanguage', [$this->preferredLanguage]);
        }

        return $result;
    }

    /**
     * Given an array of associative arrays with id and source keys (or pipe-
     * separated source|id strings), load all of the requested records in the
     * requested order.
     *
     * @param array      $ids                       Array of associative arrays with
     * id/source keys or strings in source|id format.  In associative array formats,
     * there is also an optional "extra_fields" key which can be used to pass in data
     * formatted as if it belongs to the Solr schema; this is used to create
     * a mock driver object if the real data source is unavailable.
     * @param bool       $tolerateBackendExceptions Whether to tolerate backend
     * exceptions that may be caused by e.g. connection issues or changes in
     * subcscriptions
     * @param ParamBag[] $params                    Associative array of search
     * backend parameters keyed with source key
     *
     * @throws \Exception
     * @return array     Array of record drivers
     */
    public function loadBatch(
        $ids, $tolerateBackendExceptions = false, $params = []
    ) {
        if ($this->defaultParams) {
            $params = array_merge($this->defaultParams, $params);
        }
        // loadBatch needs source specific parameters.
        // Init them for R2 since we only need to pass 'R2Restricted' param.
        $sourceParams = ['R2' => new ParamBag()];
        foreach ($params as $key => $val) {
            $sourceParams['R2']->set($key, $val);
        }
        return parent::loadBatch($ids, $tolerateBackendExceptions, $sourceParams);
    }

    /**
     * Given an array of IDs and a record source, load a batch of records for
     * that source.
     *
     * @param array    $ids                       Record IDs
     * @param string   $source                    Record source
     * @param bool     $tolerateBackendExceptions Whether to tolerate backend
     * exceptions that may be caused by e.g. connection issues or changes in
     * subcscriptions
     * @param ParamBag $params                    Search backend parameters
     *
     * @throws \Exception
     * @return array
     */
    public function loadBatchForSource($ids, $source = DEFAULT_SEARCH_BACKEND,
        $tolerateBackendExceptions = false, ParamBag $params = null
    ) {
        if ('MetaLib' === $source) {
            $result = [];
            foreach ($ids as $recId) {
                $record = $this->recordFactory->get('Missing');
                $record->setRawData(['id' => $recId]);
                $record->setSourceIdentifier('MetaLib');
                $result[] = $record;
            }
            return $result;
        }

        $records = parent::loadBatchForSource(
            $ids, $source, $tolerateBackendExceptions, $params
        );

        if ($source === 'R2' && empty($records)) {
            // Init R2 specific Missing drivers.
            foreach ($ids as $id) {
                $records[] = $this->initMissingR2Record($id);
            }
            return $records;
        }

        // Check the results for missing MetaLib IRD records and try to load them
        // with their old MetaLib IDs.
        // Replace generic Missing drivers with R2 specific.
        foreach ($records as &$record) {
            if ($record instanceof \VuFind\RecordDriver\Missing) {
                $source = $record->getSourceIdentifier();
                $id = $record->getUniqueID();
                echo "missing: $id, $source";
                if ($source == 'Solr') {
                    if (preg_match('/\.(FIN\d+)/', $id, $matches)) {
                        if ($mlRecord = $this->loadMetaLibRecord($matches[1])) {
                            $record = $mlRecord;
                        }
                    } elseif (preg_match('/^musketti\..+?:(.+)/', $id, $matches)) {
                        // Old musketti record. Try to find the new record using the
                        // inventory number.
                        $newRecord = $this
                            ->loadRecordWithIdentifier($matches[1], 'museovirasto');
                        if ($newRecord) {
                            $record = $newRecord;
                        }
                    }
                } elseif ($source == 'R2') {
                    // Missing R2 record
                    // (probably non-existing restriction level)
                    $record = $this->initMissingR2Record($id);
                }
            }
        }

        return $records;
    }

    /**
     * Set default parameters that are passed to backend.
     *
     * @param array $params Parameters
     *
     * @return void
     */
    public function setDefaultParams($params = null)
    {
        $this->defaultParams = $params;
    }

    /**
     * Try to load a record using its old MetaLib ID
     *
     * @param string $id Record ID (e.g. FIN12345)
     *
     * @return \VuFind\RecordDriver\AbstractBase|bool Record or false if not found
     */
    protected function loadMetalibRecord($id)
    {
        $safeId = addcslashes($id, '"');
        $query = new \VuFindSearch\Query\Query(
            'original_id_str_mv:"' . $safeId . '"'
        );
        $params = new \VuFindSearch\ParamBag(
            ['hl' => 'false', 'spellcheck' => 'false']
        );
        $results = $this->searchService->search('Solr', $query, 0, 1, $params)
            ->getRecords();
        return !empty($results) ? $results[0] : false;
    }

    /**
     * Try to load a record using its identifier field
     *
     * @param string $identifier Identifier (e.g. SUK77:2)
     * @param string $dataSource Optional data source filter
     *
     * @return \VuFind\RecordDriver\AbstractBase|bool Record or false if not found
     */
    protected function loadRecordWithIdentifier($identifier, $dataSource = null)
    {
        $safeIdentifier = addcslashes($identifier, '"');
        $queryStr = 'identifier:"' . $safeIdentifier . '"';
        if (null !== $dataSource) {
            $queryStr .= ' AND datasource_str_mv:"' . addcslashes($dataSource, '"')
                . '"';
        }
        $query = new \VuFindSearch\Query\Query($queryStr);
        $params = new \VuFindSearch\ParamBag(
            ['hl' => 'false', 'spellcheck' => 'false']
        );
        $results = $this->searchService->search('Solr', $query, 0, 1, $params)
            ->getRecords();
        return !empty($results) ? $results[0] : false;
    }

    /**
     * Init missing R2Ead3 driver.
     *
     * @param string $id Record id.
     *
     * @return \VuFind\RecordDriver\AbstractBase
     */
    protected function initMissingR2Record($id)
    {
        $record = $this->recordFactory->get('R2Ead3Missing');
        $record->setRawData(['id' => $id, 'fullrecord' => '<xml></xml>']);
        $record->setSourceIdentifier('R2');
        return $record;
    }
}
