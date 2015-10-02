<?php

/**
 * MetaLib backend.
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
 * @package  Search
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org
 */
namespace FinnaSearch\Backend\Metalib;

use VuFindSearch\Query\AbstractQuery;

use VuFindSearch\ParamBag;

use VuFindSearch\Response\RecordCollectionInterface;
use VuFindSearch\Response\RecordCollectionFactoryInterface;

use VuFindSearch\Backend\AbstractBackend;
use VuFindSearch\Backend\Exception\BackendException;

/**
 * MetaLib backend.
 *
 * @category VuFind2
 * @package  Search
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org
 */
class Backend extends AbstractBackend
{
    /**
     * Connector.
     *
     * @var Connector
     */
    protected $connector;

    /**
     * Query builder.
     *
     * @var QueryBuilder
     */
    protected $queryBuilder = null;

    /**
     * Constructor.
     *
     * @param Connector                        $connector Primo connector
     * @param RecordCollectionFactoryInterface $factory   Record collection factory
     * (null for default)
     *
     * @return void
     */
    public function __construct(Connector $connector,
        RecordCollectionFactoryInterface $factory = null
    ) {
        if (null !== $factory) {
            $this->setRecordCollectionFactory($factory);
        }
        $this->connector    = $connector;
    }

    /**
     * Perform a search and return record collection.
     *
     * @param AbstractQuery $query  Search query
     * @param integer       $offset Search offset
     * @param integer       $limit  Search limit
     * @param ParamBag      $params Search backend parameters
     *
     * @return RecordCollectionInterface
     */
    public function search(AbstractQuery $query, $offset, $limit,
        ParamBag $params = null
    ) {
        $baseParams = $this->getQueryBuilder()->build($query);
        if (null !== $params) {
            $baseParams->mergeWith($params);
        }
        $baseParams->set('limit', $limit);
        $page = $limit > 0 ? floor($offset / $limit) + 1 : 1;
        $baseParams->set('pageNumber', $page);

        $query = $this->paramBagToQuery($baseParams);
        try {
            $response = $this->connector->query(
                $this->connector->getInstitutionCode(), $query['query'],           
                $query
            );
        } catch (\Exception $e) {
            throw new BackendException(
                $e->getMessage(),
                $e->getCode(),
                $e
            );
        }
        $collection = $this->createRecordCollection($response);
        //$this->injectSourceIdentifier($collection);

        return $collection;
    }

    /**
     * Retrieve a single document.
     *
     * @param string   $id     Document identifier
     * @param ParamBag $params Search backend parameters
     *
     * @return RecordCollectionInterface
     */
    public function retrieve($id, ParamBag $params = null)
    {
        try {
            $response   = $this->connector
                ->getRecord($id, $this->connector->getInstitutionCode());
        } catch (\Exception $e) {
            // TODO: check from database, this could be in a favorite list

            throw new BackendException(
                $e->getMessage(),
                $e->getCode(),
                $e
            );
        }
        $response['id'] = $id;
        $collection = $this->createRecordCollection(['documents' => [$response]]);

        

        //$this->injectSourceIdentifier($collection);
        return $collection;
    }

    /**
     * Set the query builder.
     *
     * @param QueryBuilder $queryBuilder Query builder
     *
     * @return void
     */
    public function setQueryBuilder(QueryBuilder $queryBuilder)
    {
        $this->queryBuilder = $queryBuilder;
    }

    /**
     * Return query builder.
     *
     * Lazy loads an empty QueryBuilder if none was set.
     *
     * @return QueryBuilder
     */
    public function getQueryBuilder()
    {
        if (!$this->queryBuilder) {
            $this->queryBuilder = new QueryBuilder();
        }
        return $this->queryBuilder;
    }

    /**
     * Return the record collection factory.
     *
     * Lazy loads a generic collection factory.
     *
     * @return RecordCollectionFactoryInterface
     */
    
    public function getRecordCollectionFactory()
    {
        if ($this->collectionFactory === null) {
            $this->collectionFactory = new Response\RecordCollectionFactory();
        }
        return $this->collectionFactory;
    }

    /**
     * Return the Primo connector.
     *
     * @return Connector
     */
    public function getConnector()
    {
        return $this->connector;
    }

    /// Internal API

    /**
     * Create record collection.
     *
     * @param array $records Records to process
     *
     * @return RecordCollectionInterface
     */
    protected function createRecordCollection($records)
    {
        return $this->getRecordCollectionFactory()->factory($records);
    }

    /**
     * Convert a ParamBag to a Primo query array.
     *
     * @param ParamBag $params ParamBag to convert
     *
     * @return array
     */
    protected function paramBagToQuery(ParamBag $params)
    {
        $params = $params->getArrayCopy();


        // Convert the options:
        $options = [];

        // Most parameters need to be flattened from array format, but a few
        // should remain as arrays:
        $arraySettings = [
            'query', 'facets', 'filterList', 'groupFilters', 'rangeFilters'
        ];
        foreach ($params as $key => $param) {
            $options[$key] = in_array($key, $arraySettings) ? $param : $param[0];
        }

        // Use special facet pcAvailabilty if it has been set
        if (isset($params['filterList']['pcAvailability'])) {
            unset($options['filterList']['pcAvailability']);
            $options['pcAvailability'] = true;
        }

        return $options;
    }
}
