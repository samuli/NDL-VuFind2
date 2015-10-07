<?php
/**
 * MyResearch Controller
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
 * @package  Controller
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
namespace Finna\Controller;

/**
 * Controller for the user account area.
 *
 * @category VuFind2
 * @package  Controller
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
class MyResearchController extends \VuFind\Controller\MyResearchController
{
    /**
     * Send list of checked out books to view.
     * Added profile to view, so borrow blocks can be shown.
     *
     * @return mixed
     */
    public function checkedoutAction()
    {
        // Stop now if the user does not have valid catalog credentials available:
        if (!is_array($patron = $this->catalogLogin())) {
            return $patron;
        }

        $view = $this->createViewIfUnsupported('getMyTransactions');
        if ($view === false) {
            $view = parent::checkedoutAction();
            $view->profile = $this->getCatalogProfile();
            $transactions = count($view->transactions);
            $renewResult = $view->renewResult;
            if (isset($renewResult) && is_array($renewResult)) {
                $renewedCount = 0;
                $renewErrorCount = 0;
                foreach ($renewResult as $renew) {
                    if ($renew['success']) {
                        $renewedCount++;
                    } else {
                        $renewErrorCount++;
                    }
                }
                $flashMsg = $this->flashMessenger();
                if ($renewedCount > 0) {
                    $msg = $this->translate(
                        'renew_ok', ['%%count%%' => $renewedCount,
                        '%%transactionscount%%' => $transactions]
                    );
                    $flashMsg->setNamespace('info')->addMessage($msg);
                }
                if ($renewErrorCount > 0) {
                    $msg = $this->translate(
                        'renew_failed',
                        ['%%count%%' => $renewErrorCount]
                    );
                    $flashMsg->setNamespace('error')->addMessage($msg);
                }
            }
        }
        return $view;
    }

    /**
     * Send user's saved favorites from a particular list to the view
     *
     * @return mixed
     */
    public function mylistAction()
    {
        $view = parent::mylistAction();
        $user = $this->getUser();

        if ($results = $view->results) {
            $list = $results->getListObject();

            // Redirect anonymous users and list visitors to public list URL
            if ($list && $list->isPublic()
                && (!$user || $user->id != $list->user_id)
            ) {
                return $this->redirect()->toRoute('list-page', ['lid' => $list->id]);
            }
        }

        if (!$user) {
            return $view;
        }

        $view->sortList = $this->createSortList();

        return $view;
    }

    /**
     * Gather user profile data
     *
     * @return mixed
     */
    public function profileAction()
    {
        $user = $this->getUser();
        if ($user == false) {
            return $this->forceLogin();
        }

        $values = $this->getRequest()->getPost();
        if ($this->formWasSubmitted('saveUserProfile')) {
            $validator = new \Zend\Validator\EmailAddress();
            if ($validator->isValid($values->email)) {
                $user->email = $values->email;
                if (isset($values->due_date_reminder)) {
                    $user->finna_due_date_reminder = $values->due_date_reminder;
                }
                $user->save();
                $this->flashMessenger()->setNamespace('info')
                    ->addMessage('profile_update');
            } else {
                $this->flashMessenger()->setNamespace('error')
                    ->addMessage('profile_update_failed');
            }
        }

        $view = parent::profileAction();
        $profile = $view->profile;

        if ($this->formWasSubmitted('saveLibraryProfile')) {
            $this->processLibraryDataUpdate($profile, $values);
        }

        $parentTemplate = $view->getTemplate();
        // If returned view is not profile view, show it below our profile part.
        if ($parentTemplate != '' && $parentTemplate != 'myresearch/profile') {
            $childView = $this->createViewModel();
            $childView->setTemplate('myresearch/profile');

            $compoundView = $this->createViewModel();
            $compoundView->addChild($childView, 'child');
            $compoundView->addChild($view, 'parent');

            return $compoundView;
        }

        return $view;
    }

    /**
     * Library information address change form
     *
     * @return mixed
     */
    public function changeProfileAddressAction()
    {
        if (!is_array($patron = $this->catalogLogin())) {
            return $patron;
        }
        $catalog = $this->getILS();
        $profile = $catalog->getMyProfile($patron);

        if ($this->formWasSubmitted('addess_change_request')) {
            // ToDo: address request sent
        }

        $view = $this->createViewModel();
        $view->profile = $profile;
        $view->setTemplate('myresearch/change-address-settings');
        return $view;
    }

    /**
     * Messaging settings change form
     *
     * @return mixed
     */
    public function changeMessagingSettingsAction()
    {
        if (!is_array($patron = $this->catalogLogin())) {
            return $patron;
        }
        $catalog = $this->getILS();
        $profile = $catalog->getMyProfile($patron);

        if ($this->formWasSubmitted('messaging_update_request')) {
            // ToDo: messaging update request send
        }

        $view = $this->createViewModel();

        if (isset($profile['messagingServices'])) {
            $view->services = $profile['messagingServices'];
            $emailDays = [];
            foreach ([1, 2, 3, 4, 5] as $day) {
                if ($day == 1) {
                    $label = $this->translate('messaging_settings_num_of_days');
                } else {
                    $label = $this->translate(
                        'messaging_settings_num_of_days_plural',
                        ['%%days%%' => $day]
                    );
                }
                $emailDays[] = $label;
            }

            $view->emailDays = $emailDays;
            $view->days = [1, 2, 3, 4, 5];
        }
        $view->setTemplate('myresearch/change-messaging-settings');
        return $view;
    }

    /**
     * Delete account form
     *
     * @return mixed
     */
    public function deleteAccountAction()
    {
        $user = $this->getUser();
        if ($user == false) {
            return $this->forceLogin();
        }

        $view = $this->createViewModel();
        if ($this->formWasSubmitted('submit')) {
            $view->success = $this->processDeleteAccount();
        } elseif ($this->formWasSubmitted('reset')) {
            return $this->redirect()->toRoute(
                'default', ['controller' => 'MyResearch', 'action' => 'Profile']
            );
        }
        $view->setTemplate('myresearch/delete-account');
        $view->token = $this->getSecret($this->getUser());
        return $view;
    }

    /**
     * Return the Favorites sort list options.
     *
     * @return array
     */
    public static function getFavoritesSortList()
    {
        return [
            'saved' => 'sort_saved',
            'title' => 'sort_title',
            'author' => 'sort_author',
            'date' => 'sort_year asc',
            'format' => 'sort_format',
        ];
    }

    /**
     * Send list of holds to view
     *
     * @return mixed
     */
    public function holdsAction()
    {
        // Stop now if the user does not have valid catalog credentials available:
        if (!is_array($patron = $this->catalogLogin())) {
            return $patron;
        }

        $view = $this->createViewIfUnsupported('getMyHolds');
        if ($view === false) {
            $view = parent::holdsAction();
            $view->recordList = $this->orderAvailability($view->recordList);
            $view->profile = $this->getCatalogProfile();
        }
        return $view;
    }

    /**
     * Logout Action
     *
     * @return mixed
     */
    public function logoutAction()
    {
        $config = $this->getConfig();
        if (isset($config->Site->logOutRoute)) {
            $logoutTarget = $this->getServerUrl($config->Site->logOutRoute);
        } else {
            $logoutTarget = $this->getRequest()->getServer()->get('HTTP_REFERER');
            if (empty($logoutTarget)) {
                $logoutTarget = $this->getServerUrl('home');
            }

            // If there is an auth_method parameter in the query, we should strip
            // it out. Otherwise, the user may get stuck in an infinite loop of
            // logging out and getting logged back in when using environment-based
            // authentication methods like Shibboleth.
            $logoutTarget = preg_replace(
                '/([?&])auth_method=[^&]*&?/', '$1', $logoutTarget
            );
        }
        // Append logout parameter to indicate user-initiated logout
        $logoutTarget = preg_replace(
            '/([?&])logout=[^&]*&?/', '$1', $logoutTarget
        );
        if (substr($logoutTarget, -1) == '?') {
            $logoutTarget .= 'logout=1';
        } elseif (strstr($logoutTarget, '?') === false) {
            $logoutTarget .= '?logout=1';
        } else {
            $logoutTarget .= '&logout=1';
        }

        return $this->redirect()
            ->toUrl($this->getAuthManager()->logout($logoutTarget));
    }

    /**
     * Save alert schedule for a saved search into DB
     *
     * @return mixed
     */
    public function savesearchAction()
    {
        $user = $this->getUser();
        if ($user == false) {
            return $this->forceLogin();
        }
        $schedule = $this->params()->fromQuery('schedule', false);
        $sid = $this->params()->fromQuery('searchid', false);

        if ($schedule !== false && $sid !== false) {
            $search = $this->getTable('Search');
            $baseurl = rtrim($this->getServerUrl('home'), '/');
            $row = $search->select(
                ['id' => $sid, 'user_id' => $user->id]
            )->current();
            if ($row) {
                $row->setSchedule($schedule, $baseurl);
            }
            return $this->redirect()->toRoute('search-history');
        } else {
            parent::savesearchAction();
        }
    }

    /**
     * Send list of storage retrieval requests to view
     *
     * @return mixed
     */
    public function storageRetrievalRequestsAction()
    {
        // Stop now if the user does not have valid catalog credentials available:
        if (!is_array($patron = $this->catalogLogin())) {
            return $patron;
        }

        $view = $this->createViewIfUnsupported('StorageRetrievalRequests', true);
        if ($view === false) {
            $view = parent::storageRetrievalRequestsAction();
            $view->recordList = $this->orderAvailability($view->recordList);
            $view->profile = $this->getCatalogProfile();
        }
        return $view;
    }

    /**
     * Send list of ill requests to view
     *
     * @return mixed
     */
    public function illRequestsAction()
    {
        // Stop now if the user does not have valid catalog credentials available:
        if (!is_array($patron = $this->catalogLogin())) {
            return $patron;
        }

        $view = $this->createViewIfUnsupported('ILLRequests', true);
        if ($view === false) {
            $view = parent::illRequestsAction();
            $view->recordList = $this->orderAvailability($view->recordList);
            $view->profile = $this->getCatalogProfile();
        }
        return $view;
    }

    /**
     * Send list of fines to view
     *
     * @return mixed
     */
    public function finesAction()
    {
        // Stop now if the user does not have valid catalog credentials available:
        if (!is_array($patron = $this->catalogLogin())) {
            return $patron;
        }

        $view = $this->createViewIfUnsupported('getMyFines');
        if ($view === false) {
            $view = parent::finesAction();
            $view->profile = $this->getCatalogProfile();
        }
        return $view;
    }

    /**
     * Unsubscribe a scheduled alert for a saved search.
     *
     * @return mixed
     */
    public function unsubscribeAction()
    {
        $id = $this->params()->fromQuery('id', false);
        $key = $this->params()->fromQuery('key', false);

        if ($id === false || $key === false) {
            throw new \Exception('Missing parameters.');
        }

        $view = $this->createViewModel();

        if ($this->params()->fromQuery('confirm', false) == 1) {
            $search = $this->getTable('Search')->select(['id' => $id])->current();
            if (!$search) {
                throw new \Exception('Invalid parameters.');
            }
            $user = $this->getTable('User')->getById($search->user_id);

            if ($key !== $search->getUnsubscribeSecret(
                $this->getServiceLocator()->get('VuFind\HMAC'), $user
            )) {
                throw new \Exception('Invalid parameters.');
            }
            $search->setSchedule(0);
            $view->success = true;
        } else {
            $view->unsubscribeUrl
                = $this->getRequest()->getRequestUri() . '&confirm=1';
        }
        return $view;
    }

    /**
     * Create sort list for public list page.
     * If no sort option selected, set first one from the list to default.
     *
     * @return array
     */
    protected function createSortList()
    {
        $sortOptions = self::getFavoritesSortList();
        $sort = isset($_GET['sort']) ? $_GET['sort'] : false;
        if (!$sort) {
            reset($sortOptions);
            $sort = key($sortOptions);
        }
        $sortList = [];
        foreach ($sortOptions as $key => $value) {
            $sortList[$key] = [
                'desc' => $value,
                'selected' => $key === $sort,
            ];
        }

        return $sortList;
    }

    /**
     * Check if current library card supports a function. If not supported, show
     * a message and a notice about the possibility to change library card.
     *
     * @param string  $function      Function to check
     * @param boolean $checkFunction Use checkFunction() if true,
     * checkCapability() otherwise
     *
     * @return mixed \Zend\View if the function is not supported, false otherwise
     */
    protected function createViewIfUnsupported($function, $checkFunction = false)
    {
        $params = ['patron' => $this->catalogLogin()];
        if ($checkFunction) {
            $supported = $this->getILS()->checkFunction($function, $params);
        } else {
            $supported = $this->getILS()->checkCapability($function, $params);
        }

        if (!$supported) {
            $view = $this->createViewModel();
            $view->noSupport = true;
            $this->flashMessenger()->setNamespace('error')
                ->addMessage('no_ils_support_for_' . strtolower($function));
            return $view;
        }
        return false;
    }

    /**
     * Order available records to beginning of the record list
     *
     * @param type $recordList list to order
     *
     * @return type
     */
    protected function orderAvailability($recordList)
    {
        if ($recordList === null) {
            return [];
        }

        $availableRecordList = [];
        $recordListBasic = [];
        foreach ($recordList as $item) {
            if (isset($item->getExtraDetail('ils_details')['available'])
                && $item->getExtraDetail('ils_details')['available']
            ) {
                $availableRecordList[] = $item;
            } else {
                $recordListBasic[] = $item;
            }
        }
        return array_merge($availableRecordList, $recordListBasic);
    }

    /**
     * Utility function for generating a token.
     *
     * @param object $user current user
     *
     * @return string token
     */
    protected function getSecret($user)
    {
        $data = [
            'id' => $user->id,
            'firstname' => $user->firstname,
            'lastname' => $user->lastname,
            'email' => $user->email,
            'created' => $user->created,
        ];
        $token = new \VuFind\Crypt\HMAC('usersecret');
        return $token->generate(array_keys($data), $data);
    }

    /**
     * Change phone number and email from library info.
     *
     * @param type $profile patron data
     * @param type $values  form values
     *
     * @return type
     */
    protected function processLibraryDataUpdate($profile, $values)
    {
        $validator = new \Zend\Validator\EmailAddress();
        if ($validator->isValid($values->profile_email)) {
            // ToDo: Save mail
        }
        // ToDo: Save phone $values->profile_tel
    }

    /**
     * Delete user account for MyResearch module
     *
     * @return boolean
     */
    protected function processDeleteAccount()
    {
        $user = $this->getUser();

        if (!$user) {
            $this->flashMessenger()->setNamespace('error')
                ->addMessage('You must be logged in first');
            return false;
        }

        $token = $this->getRequest()->getPost('token', null);
        if (empty($token)) {
            $this->flashMessenger()->setNamespace('error')
                ->addMessage('Missing token');
            return false;
        }
        if ($token !== $this->getSecret($user)) {
            $this->flashMessenger()->setNamespace('error')
                ->addMessage('Invalid token');
            return false;
        }

        $success = $user->anonymizeAccount();

        if (!$success) {
            $this->flashMessenger()->setNamespace('error')
                ->addMessage('delete_account_failure');
        }
        return $success;
    }

    /**
     * Get the current patron profile.
     *
     * @return mixed
     */
    protected function getCatalogProfile()
    {
        $patron = $this->catalogLogin();
        if (is_array($patron)) {
            $catalog = $this->getILS();
            $profile = $catalog->getMyProfile($patron);
            return $profile;
        }
        return null;
    }
}
