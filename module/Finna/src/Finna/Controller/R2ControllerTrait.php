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
     * Handles display and submit of R2 registration form.
     *
     * @return void
     */
    protected function processR2RegisterForm()
    {
        $formId = $this->params()->fromRoute('id', $this->params()->fromQuery('id'));
        if ($formId !== \Finna\Form\Form::R2_REGISTER_FORM) {
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

        $closeForm = function ($shibbolethAuthenticated = true) use (
            $session, $getRedirect
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
        if (!$this->isAuthorized()) {
            // Logged but not authorized (wrong login method etc), close form
            return $closeForm($shibbolethAuthenticated);
        }

        // Authorized. Check user permission from REMS and show
        // registration if needed.
        $rems = $this->serviceLocator->get('Finna\RemsService\RemsService');
        $permission = $rems->checkPermission(true);
        if (!$permission['success']) {
            if ($msg = $permission['status'] ?? null) {
                $this->flashMessenger()->addErrorMessage("REMS error: $msg");
            }
            return $getRedirect();
        }

        $showRegisterForm
            = RemsService::STATUS_NOT_SUBMITTED
            === $permission['status'];

        if (!$showRegisterForm) {
            // Registration has already been submitted, no need to show form.
            return $closeForm($shibbolethAuthenticated);
        }

        if ($this->formWasSubmitted('submit')) {
            // Handle submitted registration form
            $user = $this->getUser();

            $form = $this->serviceLocator->get('VuFind\Form\Form');
            $formId = \Finna\Form\Form::R2_REGISTER_FORM;
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
            foreach (['usage_purpose', 'usage_desc']
                as $param
            ) {
                $formParams[$param] = $this->translate($params[$param]) ?? null;
            }

            // Take firstname, lastname and email from profile if available
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
            $session->autoOpen = true;
            $id = $this->params()->fromRoute('id', $this->params()->fromQuery('id'));
            return $this->redirect()->toRoute(null, ['id' => $id]);
        }

        if (true === ($session->autoOpen ?? false)) {
            $view->autoOpenR2Registration = true;
            unset($session->autoOpen);
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
        if (!$auth->isGranted('access.R2Restricted')) {
            return false;
        }
        return true;
    }

    /**
     * Load record with restricted metadata.
     *
     * @return null|\VuFind\RecordDriver\AbstractBase
     */
    protected function loadRecordWithRestrictedData()
    {
        $params = [];
        if ($user = $this->getUser()) {
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
