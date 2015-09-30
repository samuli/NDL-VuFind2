<?php

/**
 * Factory for MetaLib backends.
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
 * @link     http://vufind.org   Main Site
 */
namespace Finna\Search\Factory;

use FinnaSearch\Backend\Metalib\Connector;
use VuFindSearch\Backend\BackendInterface;
use FinnaSearch\Backend\Metalib\Response\RecordCollectionFactory;
use FinnaSearch\Backend\Metalib\QueryBuilder;
use FinnaSearch\Backend\Metalib\Backend;

use Zend\ServiceManager\ServiceLocatorInterface;
use Zend\ServiceManager\FactoryInterface;

/**
 * Factory for MetaLib backends.
 *
 * @category VuFind2
 * @package  Search
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
class MetaLibBackendFactory implements FactoryInterface
{
    /**
     * Logger.
     *
     * @var Zend\Log\LoggerInterface
     */
    protected $logger;

    /**
     * Superior service manager.
     *
     * @var ServiceLocatorInterface
     */
    protected $serviceLocator;

    /**
     * MetaLib configuration
     *
     * @var \Zend\Config\Config
     */
    protected $config;

    /**
     * Create the backend.
     *
     * @param ServiceLocatorInterface $serviceLocator Superior service manager
     *
     * @return BackendInterface
     */
    public function createService(ServiceLocatorInterface $serviceLocator)
    {

        $this->serviceLocator = $serviceLocator;
        $configReader = $this->serviceLocator->get('VuFind\Config');
        $this->config = $configReader->get('MetaLib');
        if ($this->serviceLocator->has('VuFind\Logger')) {
            $this->logger = $this->serviceLocator->get('VuFind\Logger');
        }

        $connector = $this->createConnector();
        $backend   = $this->createBackend($connector);
        return $backend;
    }

    /**
     * Create the Primo Central backend.
     *
     * @param Connector $connector Connector
     *
     * @return Backend
     */
    protected function createBackend(Connector $connector)
    {
        $backend = new Backend($connector, $this->createRecordCollectionFactory());
        $backend->setLogger($this->logger);
        $backend->setQueryBuilder($this->createQueryBuilder());
        return $backend;
    }

    /**
     * Create the Primo Central connector.
     *
     * @return Connector
     */
    protected function createConnector()
    {
        $host = $this->config->General->url ?: null;
        $user = $this->config->General->x_user ?: null;
        $pass = $this->config->General->x_password ?: null;
        $port = null;
        $client = $this->serviceLocator->get('VuFind\Http')->createClient();
        $cacheManager = $this->serviceLocator->get('VuFind\CacheManager');
        $auth = $this->serviceLocator->get('ZfcRbac\Service\AuthorizationService');

        $timeout = isset($this->config->General->timeout)
            ? $this->config->General->timeout : 30;
        $client->setOptions(['timeout' => $timeout]);

        $connector = new Connector($host, $user, $pass, $client, $cacheManager, $auth, $port);
        $connector->setLogger($this->logger);
        return $connector;
    }

    /**
     * Determine the institution code
     *
     * @return string
     */
    protected function getInstCode()
    {
        return "foo";

        $codes = isset($this->primoConfig->Institutions->code)
            ? $this->primoConfig->Institutions->code : [];
        $regex = isset($this->primoConfig->Institutions->regex)
            ? $this->primoConfig->Institutions->regex : [];
        if (empty($codes) || empty($regex) || count($codes) != count($regex)) {
            throw new \Exception('Check [Institutions] settings in Primo.ini');
        }

        $request = $this->serviceLocator->get('Request');
        $ip = $request->getServer('REMOTE_ADDR');

        for ($i = 0; $i < count($codes); $i++) {
            if (preg_match($regex[$i], $ip)) {
                return $codes[$i];
            }
        }

        throw new \Exception(
            'Could not determine institution code. [Institutions] settings '
            . 'should include a catch-all rule at the end.'
        );
    }

    /**
     * Create the Primo query builder.
     *
     * @return QueryBuilder
     */
    protected function createQueryBuilder()
    {
        $builder = new QueryBuilder();
        return $builder;
    }

    /**
     * Create the record collection factory
     *
     * @return RecordCollectionFactory
     */
    protected function createRecordCollectionFactory()
    {
        $manager = $this->serviceLocator->get('VuFind\RecordDriverPluginManager');
        $callback = function ($data) use ($manager) {
            $driver = $manager->get('Metalib');
            //            die("createrec:" . var_export($data, true));
            $driver->setRawData($data);
            return $driver;
        };
        return new RecordCollectionFactory($callback);
    }
}