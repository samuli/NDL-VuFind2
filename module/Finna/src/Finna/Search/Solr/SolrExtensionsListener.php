<?php

/**
 * Finna Solr extensions listener.
 *
 * PHP version 5
 *
 * Copyright (C) The National Library of Finland 2013-2015.
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
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */

namespace Finna\Search\Solr;

use \VuFindSearch\Backend\BackendInterface;

use Zend\EventManager\EventInterface;
use Zend\ServiceManager\ServiceLocatorInterface;
use Zend\EventManager\SharedEventManagerInterface;

/**
 * Finna Solr extensions listener.
 *
 * @category VuFind2
 * @package  Finna
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
class SolrExtensionsListener
{
    /**
     * Backend.
     *
     * @var BackendInterface
     */
    protected $backend;

    /**
     * Superior service manager.
     *
     * @var ServiceLocatorInterface
     */
    protected $serviceLocator;

    /**
     * Search configuration file identifier.
     *
     * @var string
     */
    protected $searchConfig;

    /**
     * Data source configuration file identifier.
     *
     * @var string
     */
    protected $dataSourceConfig;

    /**
     * Constructor.
     *
     * @param BackendInterface        $backend          Search backend
     * @param ServiceLocatorInterface $serviceLocator   Service locator
     * @param string                  $searchConfig     Search config file id
     * @param string                  $dataSourceConfig Data source file id
     *
     * @return void
     */
    public function __construct(
        BackendInterface $backend,
        ServiceLocatorInterface $serviceLocator,
        $searchConfig, $dataSourceConfig = 'datasources'
    ) {
        $this->backend = $backend;
        $this->serviceLocator = $serviceLocator;
        $this->searchConfig = $searchConfig;
        $this->dataSourceConfig = $dataSourceConfig;
    }

    /**
     * Attach listener to shared event manager.
     *
     * @param SharedEventManagerInterface $manager Shared event manager
     *
     * @return void
     */
    public function attach(
        SharedEventManagerInterface $manager
    ) {
        $manager->attach('VuFind\Search', 'pre', array($this, 'onSearchPre'));
        $manager->attach('VuFind\Search', 'post', array($this, 'onSearchPost'));
    }

    /**
     * Customize Solr request.
     *
     * @param EventInterface $event Event
     *
     * @return EventInterface
     */
    public function onSearchPre(EventInterface $event)
    {
        $backend = $event->getTarget();
        if ($backend === $this->backend) {
            $this->addDataSourceFilter($event);

            $params = $event->getParam('params');
            $context = $event->getParam('context');
            $query = $event->getParam('query');



            //echo("params: " . get_class($params) . ", " . var_export($params, true));
            //echo("q: " . var_export($query, true));

            
            

            if ($params && ($context == 'search' || $context == 'similar')
                && ($query === null || !is_callable(array($query, 'getHandler'))
                || $query->getHandler() !== 'ParentID')
            ) {
                $params->add('fq', '-hidden_component_boolean:TRUE');
            }
        }
        return $event;
    }

    /**
     * Post-search operations
     *
     * @param EventInterface $event Event
     *
     * @return EventInterface
     */
    public function onSearchPost(EventInterface $event)
    {
        return $event;
    }

    /**
     * Add data source filter per search config.
     *
     * @param EventInterface $event Event
     *
     * @return void
     */
    protected function addDataSourceFilter(EventInterface $event)
    {
        $config = $this->serviceLocator->get('VuFind\Config');
        $searchConfig = $config->get($this->searchConfig);
        if (isset($searchConfig->Records->sources)
            && $searchConfig->Records->sources
        ) {
            $sources = array_map(
                function ($input) {
                    return '"' . addcslashes($input, '"') . '"';
                },
                explode(',', $searchConfig->Records->sources)
            );
            $params = $event->getParam('params');
            if ($params) {
                $params->add(
                    'fq',
                    'source_str_mv:(' . implode(' OR ', $sources) . ')'
                );
            }
        }
    }
}