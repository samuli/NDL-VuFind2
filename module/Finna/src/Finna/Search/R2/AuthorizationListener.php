<?php

/**
 * Restricted Solr (R2) Search authorization listener.
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
 * @link     https://vufind.org Main Site
 */
namespace Finna\Search\R2;

use FinnaSearch\Backend\R2\Connector;

use VuFind\Auth\Manager;
use VuFindSearch\Backend\BackendInterface;

use Zend\EventManager\EventInterface;
use Zend\EventManager\SharedEventManagerInterface;
use ZfcRbac\Service\AuthorizationService;

/**
 * Restricted Solr (R2) Search authorization listener.
 *
 * @category VuFind
 * @package  Search
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Site
 */
class AuthorizationListener
{
    /**
     * Backend.
     *
     * @var BackendInterface
     */
    protected $backend;

    /**
     * Authentication manager
     *
     * @var Manager
     */
    protected $authManager;

    /**
     * Authorization service
     *
     * @var ZfcRbac\Service\AuthorizationService
     */
    protected $authService;

    /**
     * Connector
     *
     * @var ZfcRbac\Service\AuthorizationService
     */
    protected $connector;

    /**
     * Constructor.
     *
     * @param BackendInterface     $backend     Search backend
     * @param Manager              $authManager Authentication manager
     * @param AuthorizationService $authService Authorization service
     * @param Connector            $connector   Backend connector
     *
     * @return void
     */
    public function __construct(
        BackendInterface $backend,
        Manager $authManager,
        AuthorizationService $authService,
        Connector $connector
    ) {
        $this->backend = $backend;
        $this->authManager = $authManager;
        $this->authService = $authService;
        $this->connector = $connector;
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
        $manager->attach('VuFind\Search', 'pre', [$this, 'onSearchPre']);
    }

    /**
     * Add username as a search parameter if the user is authorized.
     *
     * @param EventInterface $event Event
     *
     * @return EventInterface
     */
    public function onSearchPre(EventInterface $event)
    {
        $backend = $event->getTarget();
        if ($backend === $this->backend) {
            $params = $event->getParam('params');
            $context = $event->getParam('context');
            $this->connector->setUsername(null);
            // Pass the username of an authorized user to connector in order
            // to request restricted metadata.
            if ($context !== 'retrieve'
                || in_array(true, $params->get('R2Restricted') ?? [])
            ) {
                if ($this->authService->isGranted('access.R2Restricted')) {
                    $userId = \Finna\RemsService\RemsService::prepareUserId(
                        $this->authManager->isLoggedIn()->username
                    );
                    $this->connector->setUsername(urlencode($userId));
                }
            }
        }
        return $event;
    }
}
