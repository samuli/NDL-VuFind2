<?php
/**
 * R2 record controller trait.
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
 * @package  Controller
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:controllers Wiki
 */
namespace Finna\Controller;

use Finna\RemsService\RemsService;
use Zend\Session\Container as SessionContainer;

/**
 * R2 record controller trait.
 *
 * @category VuFind
 * @package  Controller
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:controllers Wiki
 */
trait R2ControllerTrait
{
    /**
     * Handle onDispatch event
     *
     * @param \Zend\Mvc\MvcEvent $e Event
     *
     * @return mixed
     */
    public function onDispatch(\Zend\Mvc\MvcEvent $e)
    {
        $helper = $this->getViewRenderer()->plugin('R2RestrictedRecord');
        if (!$helper->isAvailable()) {
            throw new \Exception('R2 is disabled');
        }

        return parent::onDispatch($e);
    }

    /**
     * Replace R2 new user registration form id with the id for returning
     * user registration form.
     *
     * @param string $formId Current form id
     *
     * @return null|string registration form id for returning user or null
     * if the form id was not modified.
     */
    protected function replaceR2RegisterFormId($formId)
    {
        if (! \Finna\Form\Form::isR2RegisterForm($formId, true)) {
            // Not a R2 registration form for new users
            return null;
        }

        // R2 registration form requested.
        // 1. If the user is not logged, default to new user form
        //    (this method gets called again after the login).
        // 2. For logged users, check if the user has been registered to REMS
        //    and replace form id with the id for returning users.
        if (!$user = $this->getUser()
        ) {
            return $formId;
        }

        $rems = $this->serviceLocator->get('Finna\RemsService\RemsService');
        $regId = \Finna\Form\Form::getR2RegisterFormId(!$rems->isUserRegistered());
        return $formId !== $regId ? $regId : null;
    }

    /**
     * Handles display and submit of R2 registration form.
     *
     * @return void
     */
    protected function processR2RegisterForm()
    {
        $formId = $this->params()->fromRoute('id', $this->params()->fromQuery('id'));
        if (!\Finna\Form\Form::isR2RegisterForm($formId)) {
            return null;
        }

        $recordId = $this->params()->fromQuery('recordId');
        $collection
            = (bool)$this->params()->fromQuery('collection', false);
        $session = $this->getR2Session();

        $inLightbox
            = $this->getRequest()->getQuery('layout', 'no') === 'lightbox'
               || 'layout/lightbox' == $this->layout()->getTemplate();

        $getRedirect = function () use ($recordId, $collection) {
            $recordId
                ? $this->redirect()->toRoute(
                    $collection
                    ? 'r2collection-home' : 'r2record-home',
                    ['id' => $recordId]
                )
                : $this->redirect()->toRoute('search-home');
        };

        // Verify that user is authorized to access restricted R2 data.
        if (!$user = $this->getUser()) {
            // Not logged, prompt login
            $session->inLightbox = $inLightbox;
            $session->recordId = $recordId;
            $session->collection = $collection;
            return $this->forceLogin();
        }

        $shibbolethAuthenticated
            = in_array($user->auth_method, ['suomifi', 'Shibboleth']);

        $closeForm = function () use (
            $session, $getRedirect, $shibbolethAuthenticated
        ) {
            $recordId = $session->recordId ?? null;
            $collection = $session->collection ?? false;
            unset($session->inLightbox);
            unset($session->recordId);
            unset($session->collection);

            if (!$shibbolethAuthenticated) {
                // Login completed inside lightbox: refresh page
                $response = $this->getResponse();
                $response->setStatusCode(205);
                return '';
            } else {
                // Login outside lightbox: redirect
                return $getRedirect();
            }
        };

        if (!$this->isAuthorized()) {
            // Logged but not authorized (wrong login method etc), close form
            return $closeForm();
        }

        // Authorized. Check user permission from REMS and show
        // registration if needed.
        $rems = $this->serviceLocator->get('Finna\RemsService\RemsService');

        $accessStatus = $rems->getAccessPermission();

        $showRegisterForm = !$accessStatus
            || in_array(
                $accessStatus,
                [RemsService::STATUS_CLOSED, RemsService::STATUS_NOT_SUBMITTED]
            );

        if (!$showRegisterForm) {
            // Registration has already been submitted, no need to show form.
            return $closeForm();
        }

        if ($this->formWasSubmitted('submit')) {
            // Handle submitted registration form
            $user = $this->getUser();

            $form = $this->serviceLocator->get('VuFind\Form\Form');
            $form->setFormId($formId);

            $view = $this->createViewModel(compact('form', 'formId', 'user'));
            $view->setTemplate('feedback/form');
            $params = $this->params()->fromPost();
            $form->setData($params);

            if (! $form->isValid()) {
                return $view;
            }

            $rems = $this->serviceLocator->get('Finna\RemsService\RemsService');

            // Collect submitted params required by REMS form
            $formParams = [];
            $formParams['usage_purpose']
                = (string)substr($params['usage_purpose'], -1);
            $formParams['usage_desc'] = $params['usage_desc'];

            // Take firstname and lastname from profile if available
            $firstname = !empty($user->firstname)
                ? $user->firstname : $params['firstname'];
            $lastname = !empty($user->lastname)
                ? $user->lastname : $params['lastname'];
            $email = $params['email'];

            try {
                $rems->registerUser(
                    $email,
                    $firstname,
                    $lastname,
                    $formParams
                );
            } catch (\Exception $e) {
                $this->flashMessenger()->addErrorMessage('R2_register_error');
                $this->flashMessenger()->addErrorMessage($e->getMessage());
            }

            if ($inLightbox) {
                // Request lightbox to refresh page
                $response = $this->getResponse();
                $response->setStatusCode(205);
                return '';
            } else {
                // Registration outside lightbox, redirect to record/collection
                if (isset($session->recordId)) {
                    $route = ($session->collection ?? false)
                        ? 'r2collection' : 'r2record';
                    return $this->redirect()->toRoute(
                        $route, ['id' => $session->recordId]
                    );
                } else {
                    return $this->redirect()->toRoute('search-home');
                }
            }
        }

        // User is authorized, let parent display the registration form
        return null;
    }

    /**
     * Handle request to auto open registration form at page load when
     * jumping from from local index record page to restricted index record page.
     *
     * @param View $view View
     *
     * @return View
     */
    protected function handleAutoOpenRegistration($view)
    {
        $session = $this->getR2Session();

        if ($this->getRequest()->getQuery()->get('register') === '1') {
            $view->autoOpenR2Registration = true;
        }

        return $view;
    }

    /**
     * Return session for REMS data.
     *
     * @return SesionContainer
     */
    public function getR2Session()
    {
        return new SessionContainer(
            'R2Registration',
            $this->serviceLocator->get('VuFind\SessionManager')
        );
    }

    /**
     * Is the user authorized to use R2?
     *
     * @return bool
     */
    public function isAuthorized()
    {
        $auth = $this->serviceLocator->get('ZfcRbac\Service\AuthorizationService');
        return $auth->isGranted('access.R2Restricted');
    }

    /**
     * Load record with restricted metadata.
     *
     * This tells RecordLoader to include the current user id in the request so
     * that R2 index can return data that the user is allowed to see.
     *
     * @return null|\VuFind\RecordDriver\AbstractBase
     */
    protected function loadRecordWithRestrictedData()
    {
        $params = [];
        if ($user = $this->getUser() && $this->isAuthorized()) {
            $params['R2Restricted'] = true;
        }

        $recordLoader
            = $this->serviceLocator->build('VuFind\Record\Loader', $params);

        return $recordLoader->load(
            $this->params()->fromRoute('id', $this->params()->fromQuery('id')),
            $this->searchClassId,
            false
        );
    }
}
