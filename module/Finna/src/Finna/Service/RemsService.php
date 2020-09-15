<?php
/**
 * Resource Entitlement Management System (REMS) service
 *
 * PHP version 7
 *
 * Copyright (C) The National Library of Finland 2020.
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
 * @package  Content
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
namespace Finna\Service;

use Laminas\Config\Config;
use Laminas\Session\Container;
use VuFind\Auth\Manager;

/**
 * Resource Entitlement Management System (REMS) service
 *
 * @category VuFind
 * @package  Content
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
class RemsService implements
    \VuFindHttp\HttpServiceAwareInterface,
    \Laminas\Log\LoggerAwareInterface
{
    use \VuFind\Log\LoggerAwareTrait {
        logError as error;
    }
    use \VuFindHttp\HttpServiceAwareTrait;

    // REMS Application statuses
    const STATUS_APPROVED = 'approved';
    const STATUS_NOT_SUBMITTED = 'not-submitted';
    const STATUS_CLOSED = 'closed';
    const STATUS_DRAFT = 'draft';
    const STATUS_REVOKED = 'revoked';
    const STATUS_REJECTED = 'rejected';
    const STATUS_EXPIRED = 'expired';

    // Session keys
    const SESSION_IS_REMS_REGISTERED = 'is-rems-user';
    const SESSION_ACCESS_STATUS = 'access-status';
    const SESSION_BLACKLISTED = 'blacklisted';
    const SESSION_USAGE_PURPOSE = 'usage-purpose';

    // REMS API user types
    const TYPE_ADMIN = 0;
    const TYPE_APPROVER = 1;
    const TYPE_USER = 2;

    /**
     * REMS configuration
     *
     * @var Config
     */
    protected $config;

    /**
     * Session container
     *
     * @var Container
     */
    protected $session;

    /**
     * Auth manager
     *
     * @var Manager
     */
    protected $auth;

    /**
     * User id of the current user.
     *
     * @var string|null
     */
    protected $userId;

    /**
     * Is the user authenticated?
     *
     * @var bool
     */
    protected $authenticated;

    /**
     * National identification number of current user (encrypted).
     *
     * @var string
     */
    protected $userIdentityNumber = null;

    /**
     * Constructor.
     *
     * @param Config         $config             REMS configuration
     * @param SessionManager $session            Session container
     * @param String|null    $userIdentityNumber National identification number
     * of current user
     * @param string|null    $userId             ID of current user
     * @param bool           $authenticated      Is the user authenticated?
     */
    public function __construct(
        Config $config,
        Container $session = null,
        // $userIdentityNumber is null when the Suomifi authentication
        // module is created (before login, when displaying the login page)
        $userIdentityNumber,
        $userId,
        bool $authenticated
    ) {
        $this->config = $config;
        $this->session = $session;
        $this->userIdentityNumber = $userIdentityNumber;
        $this->userId = $userId;
        $this->authenticated = $authenticated;
    }

    /**
     * Check if the current logged-in user is registerd to REMS
     * (not neccessarily during the current session).
     *
     * @return bool
     */
    public function isUserRegistered()
    {
        return !empty($this->getApplications());
    }

    /**
     * Check if the user is registered to REMS during the current session
     *
     * @param bool $checkEntitlements Also check entitlements?
     *
     * @return boolean
     */
    public function isUserRegisteredDuringSession($checkEntitlements = false)
    {
        if (!$checkEntitlements
            && $this->session->{RemsService::SESSION_IS_REMS_REGISTERED}
        ) {
            // Registered during session
            return true;
        } else {
            // This fetches entitlements and updates sesssion variables
            $status = $this->getAccessPermission(true);
        }
        return $this->session->{RemsService::SESSION_IS_REMS_REGISTERED} ?? false;
    }

    /**
     * Is user allowed to see restricted metadata?
     *
     * @param bool $ignoreCache Ignore cache?
     * @param bool $throw       Throw exception?
     *
     * @return bool
     * @throws Exception
     */
    public function hasUserAccess($ignoreCache = false, $throw = false)
    {
        try {
            return $this->getAccessPermission($ignoreCache)
                === RemsService::STATUS_APPROVED;
        } catch (\Exception $e) {
            if ($throw) {
                throw $e;
            } else {
                return false;
            }
        }
    }

    /**
     * Is user session expired?
     *
     * @param bool $ignoreCache Ignore cache?
     *
     * @return bool
     */
    public function isSessionExpired($ignoreCache = false)
    {
        try {
            return $this->getAccessPermission($ignoreCache)
                === RemsService::STATUS_EXPIRED;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Check if the user is blacklisted.
     *
     * Returns the date when the user was blacklisted or
     * false if the user is not blacklisted.
     *
     * @param bool $ignoreCache Ignore cache?
     *
     * @throws Exception
     * @return string|false
     */
    public function isUserBlacklisted($ignoreCache = false)
    {
        if (!$ignoreCache) {
            $access = $this->session->{self::SESSION_BLACKLISTED} ?? null;
            if ($access) {
                return $access;
            }
        }
        $blacklist = $this->sendRequest(
            'blacklist',
            ['user' => $this->getUserId(), 'resource' => $this->getResourceItemId()],
            'GET', RemsService::TYPE_APPROVER, null, false
        );
        if (!empty($blacklist)) {
            $addedAt = $blacklist[0]['blacklist/added-at'];
            $this->session->{self::SESSION_BLACKLISTED} = $addedAt;
            return $addedAt;
        }
        return false;
    }

    /**
     * Check if the user has entitlements.
     *
     * @return boolean
     */
    public function hasUserEntitlements()
    {
        return !empty($this->getEntitlements());
    }

    /**
     * Get user entitlements
     *
     * @return array
     */
    protected function getEntitlements()
    {
        try {
            $userId = $this->getUserId();
            return $this->sendRequest(
                'entitlements',
                ['user' => $userId, 'resource' => $this->getResourceItemId()],
                'GET', RemsService::TYPE_APPROVER, null, false
            );
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Get application data (status and usagePurpose) from current entitlement.
     * Reurns null when user does not have entitlements.
     *
     * @param bool $throw Throw exception?
     *
     * @return array|null
     * @throws Exception
     */
    protected function getEntitlementApplication($throw = false)
    {
        $entitlements = $this->getEntitlements();
        if (empty($entitlements)) {
            return null;
        }

        // Fetch entitlement application and its usage purpose
        $applicationId = $entitlements[0]['application-id'];
        if ($application = $this->getApplication($applicationId, $throw)) {
            $status = $this->mapRemsStatus($application['application/state']);
            $usagePurpose = null;

            $fieldIds = $this->config->RegistrationForm->field;
            $fields = $application['application/form']['form/fields'];
            foreach ($fields as $field) {
                if ($field['field/id'] === $fieldIds['usage_purpose']) {
                    $usagePurpose = $field['field/value'];
                    break;
                }
            }
            return compact('status', 'usagePurpose');
        }
        return null;
    }

    /**
     * Register user to REMS
     *
     * @param string $email      Email
     * @param string $firstname  First name
     * @param string $lastname   Last name
     * @param array  $formParams Form parameters
     *
     * @throws Exception
     * @return bool
     */
    public function registerUser(
        string $email,
        string $firstname = '',
        string $lastname = '',
        array $formParams = []
    ) {
        if (empty($this->userIdentityNumber)) {
            throw new \Exception('User national identification number not present');
        }
        if ($this->hasUserEntitlements()) {
            // User has an open approved application, abort.
            throw new \Exception('User already has entitlements');
        }

        // 1. Create user
        $params = [
            'userid' => $this->getUserId(),
            'email' => $email,
            'name' => trim("$firstname $lastname")
        ];

        $this->sendRequest(
            'users/create',
            $params, 'POST', RemsService::TYPE_ADMIN, null, false
        );

        // 2. Create draft application
        $catItemId = $this->getCatalogItemId();
        $params = ['catalogue-item-ids' => [$catItemId]];

        $response = $this->sendRequest(
            'applications/create',
            $params, 'POST', RemsService::TYPE_USER, null, false
        );

        if (!isset($response['application-id'])) {
            throw new \Exception('REMS: error creating draft application');
        }
        $applicationId = $response['application-id'];

        $fieldIds = $this->config->RegistrationForm->field;

        // 3. Save draft
        $params =  [
            'application-id' => $applicationId,
            'field-values' =>  [
                ['field' => $fieldIds['firstname'], 'value' => $firstname],
                ['field' => $fieldIds['lastname'], 'value' => $lastname],
                ['field' => $fieldIds['email'], 'value' => $email],
                ['field' => $fieldIds['usage_purpose'],
                 'value' => $formParams['usage_purpose']],
                ['field' => $fieldIds['age'],
                 'value' => $formParams['age'] ?? null],
                ['field' => $fieldIds['license'],
                 'value' => $formParams['license'] ?? null],
                ['field' => $fieldIds['user_id'],
                 'value' => $this->userIdentityNumber]
            ]
        ];

        $response = $this->sendRequest(
            'applications/save-draft',
            $params, 'POST', RemsService::TYPE_USER, null, false
        );

        // 5. Submit application
        $params = [
             'application-id' => $applicationId
        ];
        $response = $this->sendRequest(
            'applications/submit',
            $params, 'POST', RemsService::TYPE_USER, null, false
        );

        $this->session->{RemsService::SESSION_IS_REMS_REGISTERED} = true;
        $this->session->{RemsService::SESSION_USAGE_PURPOSE}
            = $formParams['usage_purpose_text'];

        return true;
    }

    /**
     * Get access permission for the current session.
     *
     * @param bool $ignoreCache Ignore cache?
     *
     * @return string|null
     */
    public function getAccessPermission($ignoreCache = false)
    {
        $access = null;
        if (!$ignoreCache) {
            $access = $this->session->{self::SESSION_ACCESS_STATUS} ?? null;
            if ($access) {
                return $access;
            }
        }
        if ($entitlementApplication = $this->getEntitlementApplication()) {
            $status = $entitlementApplication['status'];
            $this->session->{self::SESSION_IS_REMS_REGISTERED}
                = $status === RemsService::STATUS_APPROVED;
            $this->session->{self::SESSION_ACCESS_STATUS} = $status;
            $this->session->{self::SESSION_USAGE_PURPOSE}
                = 'R2_register_form_usage_'
                  . $entitlementApplication['usagePurpose'];
        } else {
            return null;
        }

        return $this->session->{self::SESSION_ACCESS_STATUS} ?? null;
    }

    /**
     * Get usage purpose for the current session.
     * Returns an array with keys 'purpose' and 'details'.
     *
     * @return array|null
     */
    public function getUsagePurpose()
    {
        return $this->session->{self::SESSION_USAGE_PURPOSE} ?? null;
    }

    /**
     * Close all open applications.
     *
     * @return void
     */
    public function closeOpenApplications()
    {
        try {
            $applications = $this->getApplications(
                [RemsService::STATUS_APPROVED]
            );
            foreach ($applications as $application) {
                $params = [
                    'application-id' => $application['id'],
                    'comment' => 'ULOSKIRJAUTUMINEN'
                ];
                $this->sendRequest(
                    'applications/close',
                    null, 'POST', RemsService::TYPE_APPROVER,
                    ['content' => json_encode($params)]
                );
            }
        } catch (\Exception $e) {
            $this->error(
                'Error closing open applications on logout: ' . $e->getMessage()
            );
        }
    }

    /**
     * Set access status of current user.
     * This is called from R2 backend connector.
     *
     * @param string $status Status
     *
     * @return void
     */
    public function setAccessStatusFromConnector($status)
    {
        switch ($status) {
        case 'ok':
            $status = self::STATUS_APPROVED;
            break;
        case 'no-applications':
            $status = self::STATUS_NOT_SUBMITTED;
            break;
        case 'manual-revoked':
            $status = self::STATUS_REVOKED;
            break;
        case 'session-expired':
            $status = self::STATUS_EXPIRED;
            break;
        default:
            $status = self::STATUS_CLOSED;
        }
        $this->session->{self::SESSION_ACCESS_STATUS} = $status;
    }

    /**
     * Set blacklist status of current user.
     * This is called from R2 backend connector.
     *
     * @param string|null $status Blacklist added date or
     * null if the user is not blacklisted.
     *
     * @return void
     */
    public function setBlacklistStatusFromConnector($status)
    {
        $this->session->{self::SESSION_BLACKLISTED} = $status;
    }

    /**
     * Prepare user id for saving to REMS.
     *
     * @param string $userId User id.
     *
     * @return string
     */
    public function prepareUserId($userId)
    {
        // Strip configured prefix from username
        $parts = explode(':', $userId, 2);
        return $parts[1] ?? $userId;
    }

    /**
     * Get application.
     *
     * @param int  $id    Id
     * @param bool $throw Throw exception?
     *
     * @throws Exception
     * @return array|null
     */
    protected function getApplication($id, $throw = false)
    {
        return $this->sendRequest(
            "applications/$id", [], 'GET', RemsService::TYPE_USER,
            null, false, $throw
        );
    }

    /**
     * Get user application ids.
     *
     * @param array $statuses application statuses
     *
     * @return array
     */
    protected function getApplications($statuses = [])
    {
        // Fetching applications by query doesn't work with REMS api.
        // Therefore fetch all and filter by status manually.

        try {
            $result = $this->sendRequest(
                'my-applications',
                null, 'GET', RemsService::TYPE_USER, null, false
            );
        } catch (\Exception $e) {
            return [];
        }

        if ($statuses) {
            $statuses = array_map(
                function ($status) {
                    return "application.state/$status";
                },
                $statuses
            );

            $result = array_filter(
                $result,
                function ($application) use ($statuses) {
                    return in_array($application['application/state'], $statuses);
                }
            );
        }

        return array_map(
            function ($application) {
                return ['id' => $application['application/id']];
            },
            $result
        );
    }

    /**
     * Return REMS user id (eppn) of the current authenticated user.
     *
     * @return string
     * @throws Exception
     */
    protected function getUserId()
    {
        if (!$this->userId) {
            throw new \Exception('REMS: user not logged');
        }
        return $this->prepareUserId($this->userId);
    }

    /**
     * Send a request to REMS api.
     *
     * @param string      $url                 URL (relative)
     * @param array       $params              Request parameters
     * @param string      $method              GET|POST
     * @param int         $apiUser             Rems API user type (see TYPE_ADMIN etc)
     * @param null|string $body                Request body
     * @param boolean     $requireRegistration Require that
     * the user has been registered to REMS during the session?
     * @param boolean     $throw               Throw exception?
     *
     * @return string
     * @throws Exception
     */
    protected function sendRequest(
        $url,
        $params = [],
        $method = 'GET',
        $apiUser = RemsService::TYPE_USER,
        $body = null,
        $requireRegistration = true,
        $throw = false
    ) {
        $handleException = function ($err) use ($throw) {
            if ($throw) {
                throw new \Exception($err);
            } else {
                return '';
            }
        };

        if (!$this->authenticated) {
            if ($throw) {
                return $handleException(
                    'Attempting to call REMS before the user has been authenticated.'
                );
            } else {
                return '';
            }
        }
        if ($requireRegistration && !$this->isUserRegisteredDuringSession()) {
            if ($throw) {
                return $handleException(
                    'Attempting to call REMS before the user has been registered'
                    . ' during the session'
                );
            } else {
                return '';
            }
        }

        $userId = null;

        switch ($apiUser) {
        case RemsService::TYPE_USER:
            $userId = $this->getUserId();
            break;
        case RemsService::TYPE_APPROVER:
            $userId = $this->config->General->apiApproverUser;
            break;
        case RemsService::TYPE_ADMIN:
            $userId = $this->config->General->apiAdminUser;
            break;
        }

        if ($userId === null) {
            $err = "Invalid apiUser: $apiUser for url: $url";
            $this->error($err);
            return $handleException($err);
        }

        $contentType = $body['contentType'] ?? 'application/json';

        $client = $this->getClient($userId, $url, $method, $params, $contentType);
        if (isset($body['content'])) {
            $client->setRawBody($body['content']);
        }

        try {
            $response = $client->send();
        } catch (\Exception $e) {
            $err = 'REMS: request failed: '
                . $client->getRequest()->getUriString()
                . ', params: ' . var_export($params, true)
                . ', exception: ' . $exception->getMessage();
            $this->error($err);
            return $handleException('REMS request error');
        }

        if (!$response->isSuccess() || $response->getStatusCode() !== 200) {
            $err = 'REMS: request failed: '
                . $client->getRequest()->getUriString()
                . ', statusCode: ' . $response->getStatusCode() . ': '
                . $response->getReasonPhrase()
                . ', response content: ' . $response->getBody();
            $this->error($err);
            return $handleException('REMS request error');
        }

        $response = json_decode($response->getBody(), true);
        // Verify 'success' field for POST requests
        if ($method === 'POST'
            && (!isset($response['success']) || !$response['success'])
        ) {
            $err = 'REMS: POST request failed: '
                . $client->getRequest()->getUriString()
                . ', statusCode: ' . $response->getStatusCode() . ': '
                . $response->getReasonPhrase()
                . ', response content: ' . $response->getBody();
            $this->error($err);
            return $handleException('REMS request error');
        }

        return $response;
    }

    /**
     * Callback after logout has been requested.
     *
     * @return void
     */
    public function onLogoutPre()
    {
        if ($this->isUserRegisteredDuringSession()) {
            $this->closeOpenApplications();
        }
        $this->session->{self::SESSION_ACCESS_STATUS} = null;
        $this->session->{self::SESSION_BLACKLISTED} = null;
        $this->session->{self::SESSION_USAGE_PURPOSE} = null;
        $this->session->{self::SESSION_IS_REMS_REGISTERED} = null;
    }

    /**
     * Return HTTP client
     *
     * @param string $userId      User Id
     * @param string $url         URL (relative)
     * @param string $method      GET|POST
     * @param array  $params      Parameters
     * @param string $contentType Content-Type
     *
     * @return string
     */
    protected function getClient(
        $userId,
        $url,
        $method = 'GET',
        $params = [],
        $contentType = 'application/json'
    ) {
        $url = $this->config->General->apiUrl . '/' . $url;
        if ($method === 'GET') {
            $url .= '?' . http_build_query($params);
        }

        $client = $this->httpService->createClient($url);
        if ($method === 'POST') {
            $body = json_encode($params);
            $client->setRawBody($body);
        }

        $client->setOptions(['timeout' => 30, 'useragent' => 'Finna']);
        $headers = $client->getRequest()->getHeaders();
        $headers->addHeaderLine(
            'Accept',
            'application/json'
        );
        $headers->addHeaderLine('x-rems-api-key', $this->config->General->apiKey);
        $headers->addHeaderLine('x-rems-user-id', $userId);

        $client->getRequest()->getHeaders()
            ->addHeaderLine('Content-Type', $contentType);

        $client->setMethod($method);
        return $client;
    }

    /**
     * Get REMS catalogue item id from configuration
     *
     * @return int|null
     */
    protected function getCatalogItemId()
    {
        return (int)$this->config->General->catalogItem ?? null;
    }

    /**
     * Get REMS resource item id from configuration
     *
     * @return string|null
     */
    protected function getResourceItemId()
    {
        return $this->config->General->resourceItem ?? null;
    }

    /**
     * Map REMS application status
     *
     * @param string $remsStatus REMS status
     *
     * @return string
     */
    protected function mapRemsStatus($remsStatus)
    {
        $statusMap = [
            'application.state/approved' => RemsService::STATUS_APPROVED,
            'application.state/closed' => RemsService::STATUS_CLOSED,
            'application.state/draft' => RemsService::STATUS_DRAFT,
            'application.state/revoked' => RemsService::STATUS_REVOKED,
            'application.state/rejected' => RemsService::STATUS_REJECTED
        ];

        return $statusMap[$remsStatus] ?? 'unknown';
    }
}
