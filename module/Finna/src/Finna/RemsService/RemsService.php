<?php
/**
 * Resource Entitlement Management System (REMS) service
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
 * @package  Content
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
namespace Finna\RemsService;

use VuFind\Auth\Manager;
use Zend\Config\Config;
use Zend\Session\Container;

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
    \Zend\Log\LoggerAwareInterface
{
    use \VuFind\Log\LoggerAwareTrait {
        logError as error;
    }
    use \VuFindHttp\HttpServiceAwareTrait;

    // REMS Application statuses
    const STATUS_APPROVED = 'approved';
    const STATUS_NOT_SUBMITTED = 'not-submitted';
    const STATUS_SUBMITTED = 'submitted';
    const STATUS_CLOSED = 'closed';
    const STATUS_DRAFT = 'draft';
    const STATUS_REVOKED = 'revoked';
    const STATUS_REJECTED = 'rejected';

    // Session keys
    const SESSION_IS_REMS_REGISTERED = 'is-rems-user';
    const SESSION_ACCESS_STATUS = 'access-status';
    const SESSION_BLACKLISTED = 'blacklisted';
    const SESSION_USAGE_PURPOSE = 'usage-purpose';
    const SESSION_ENTITLEMENTS_CHECKED = 'entitlements-checked';

    // REMS API user types
    const TYPE_ADMIN = 0;
    const TYPE_APPROVER = 1;
    const TYPE_USER = 2;

    /**
     * Configuration
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
     * National identification number of current user (encrypted).
     *
     * @var string
     */
    protected $userIdentificationNumber = null;

    /**
     * Constructor.
     *
     * @param Config         $config  Configuration
     * @param SessionManager $session Session container
     * @param String|null    $userId  National identification number of current user
     * @param Manager        $auth    Auth manager
     */
    public function __construct(
        Config $config,
        Container $session = null,
        $userId,
        Manager $auth
    ) {
        $this->config = $config;
        $this->session = $session;
        $this->auth = $auth;
        if ($userId) {
            $this->userIdentificationNumber
                = $this->encryptUserIdentificationNumber($userId);
        }
    }

    /**
     * Check if the current logged-in user is registerd to REMS
     * (not neccessariy during the current session).
     *
     * @throws Exception if user is not logged in
     * @return bool
     */
    public function isUserRegistered()
    {
        return !empty($this->getApplications());
    }

    /**
     * Check if the user is registered to REMS during the current session
     *
     * @return boolean
     */
    public function isUserRegisteredDuringSession()
    {
        if ($this->session->{RemsService::SESSION_IS_REMS_REGISTERED}) {
            // Registered during session
            return true;
        } else if (!($this->session->{RemsService::SESSION_ENTITLEMENTS_CHECKED} ?? false)) {
            // Check entitlements in case previous application did not get closed
            $this->session->{RemsService::SESSION_ENTITLEMENTS_CHECKED} = true;
            $entitlements = $this->getEntitlements();
            if (!empty($entitlements)) {
                // Entitlement found, fetch application and set its usage purpose
                // for this session
                $applicationId = $entitlements[0]['application-id'];
                if ($application = $this->getApplication($applicationId)) {
                    $fieldIds = $this->config->RegistrationForm->field;
                    $fields = $application['application/form']['form/fields'];
                    foreach ($fields as $field) {
                        if ($field['field/id'] === $fieldIds['usage_purpose']) {
                            $this->session->{RemsService::SESSION_USAGE_PURPOSE}
                                = 'R2_register_form_usage_' . $field['field/value'];
                            $this->session->{RemsService::SESSION_IS_REMS_REGISTERED}
                                = true;
                            return true;
                        }
                    }
                }
            }
            $this->session->{RemsService::SESSION_IS_REMS_REGISTERED} = false;
        }
        return $this->session->{RemsService::SESSION_IS_REMS_REGISTERED} ?? false;
    }

    /**
     * Check if the user is blacklisted.
     *
     * Returns the date when the user was blacklisted or
     * false if the user is not blacklisted.
     *
     * @param bool $ignoreCache Ignore cache?
     *
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
            'GET', RemsService::TYPE_ADMIN, null, false
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
        } catch (\Exception $e) {
            return false;
        }
        return $this->sendRequest(
            'entitlements',
            ['user' => $userId, 'resource' => $this->getResourceItemId()],
            'GET', RemsService::TYPE_ADMIN, null, false
        );
    }

    /**
     * Get access permission for the current session.
     *
     * @return string|null
     */
    public function hasUserSubmittedApplication()
    {
        $accessStatus = $this->getAccessPermission();
        return $accessStatus === RemsService::STATUS_SUBMITTED;
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
     * @return void
     */
    public function registerUser(
        string $email,
        $firstname = null,
        $lastname = null,
        $formParams = []
    ) {
        if (null === $this->userIdentificationNumber) {
            throw new \Exception('User national identification number not present');
        }
        if ($this->hasUserEntitlements()) {
            // User has an open approved application, abort.
            throw new \Exception('User already has entitlements');
        }

        $commonName = $firstname;
        if ($lastname) {
            $commonName = $commonName ? " $lastname" : $lastname;
        }

        // 1. Create user
        $params = [
            'userid' => $this->getUserId(),
            'email' => $email,
            'name' => $commonName
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
            return 'REMS: error creating draft application';
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
                 'value' => $this->userIdentificationNumber]
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
        if (!$ignoreCache) {
            $access = $this->session->{self::SESSION_ACCESS_STATUS} ?? null;
            if ($access) {
                return $access;
            }
        }
        if ($this->hasUserEntitlements()) {
            $access = RemsService::STATUS_APPROVED;
        } else if ($application = $this->getLastApplication()) {
            $access = $application['status'];
        }
        $this->session->{self::SESSION_ACCESS_STATUS} = $access;
        return $access;
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
        if (!$this->isUserRegisteredDuringSession()) {
            throw new \Exception(
                'Attempting to call REMS before the user has been registered'
                . ' during the session'
            );
        }

        try {
            $applications = $this->getApplications(
                [RemsService::STATUS_SUBMITTED, RemsService::STATUS_APPROVED]
            );
            foreach ($applications as $application) {
                if (! in_array(
                    $application['status'],
                    [RemsService::STATUS_SUBMITTED, RemsService::STATUS_APPROVED]
                )
                ) {
                    continue;
                }
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
     * Set access status of current user. This is called from Connector.
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
        case 'submitted':
            $status = self::STATUS_SUBMITTED;
            break;
        case 'manual-revoked':
            $status = self::STATUS_REVOKED;
            break;
        default:
            $status = self::STATUS_CLOSED;
        }
        $this->session->{self::SESSION_ACCESS_STATUS} = $status;
    }

    /**
     * Set blacklist status of current user. This is called from Connector.
     *
     * @param string|null $status Blacklist added date or
     * null if the user is not blacklisted.
     *
     * @return void
     */
    public function setBlacklistStatusFromConnector($status)
    {
        echo("from conn: " . var_export($status, true));
        $this->session->{self::SESSION_BLACKLISTED} = $status;
    }

    /**
     * Get application.
     *
     * @param int $id Id
     *
     * @return array|null
     */
    protected function getApplication($id)
    {
        try {
            return $this->sendRequest(
                "applications/$id", [], 'GET', RemsService::TYPE_USER, null, false
            );
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Get applications.
     *
     * @param array $statuses application statuses
     *
     * @return array
     */
    protected function getApplications($statuses = [])
    {
        $params = [];
        if ($statuses) {
            $params['query'] = implode(
                ' or ',
                array_map(
                    function ($status) {
                        return "application/state:application.state/{$status}";
                    },
                    $statuses
                )
            );
        }

        try {
            $result = $this->sendRequest(
                'my-applications',
                $params, 'GET', RemsService::TYPE_USER, null, false
            );
        } catch (\Exception $e) {
            return [];
        }

        $catalogItemId = $this->getCatalogItemId();

        $applications = [];
        foreach ($result as $application) {
            $id = $application['application/id'];
            $status = $application['application/state'] ?? null;
            $status = $this->mapRemsStatus($status);
            $created = $application['application/created'] ?? null;
            $modified = $application['application/modified'] ?? null;
            foreach ($application['application/resources'] as $catItem) {
                if ($catItem['catalogue-item/id'] === $catalogItemId) {
                    $titles = $catItem['catalogue-item/title'];
                    $title = $titles['fi'] ?? $titles['default'] ?? '';
                    break;
                }
            }
            $applications[]
                = compact(
                    'id', 'catalogItemId', 'status', 'created', 'modified'
                );
        }

        $sortFn = function ($a, $b) {
            return strcasecmp($b['created'], $a['created']);
        };
        usort($applications, $sortFn);

        return $applications;
    }

    protected function getLastApplication()
    {
        if ($applications = $this->getApplications()) {
            return $applications[0] ?? null;
        }
        return null;
    }

    /**
     * Return REMS user id (eppn) of the current authenticated user.
     *
     * @return string
     */
    protected function getUserId()
    {
        if (!$user = $this->auth->isLoggedIn()) {
            throw new \Exception('REMS: user not logged');
        }
        $id = $user->username;
        return self::prepareUserId($id);
    }

    /**
     * Prepare user id for saving to REMS.
     *
     * @param string $userId User id.
     *
     * @return string
     */
    public static function prepareUserId($userId)
    {
        // Strip domain from username
        if (false !== strpos($userId, ':')) {
            list($domain, $userId) = explode(':', $userId, 2);
        }
        return $userId;
    }

    /**
     * Encrypt user identification number.
     *
     * @param string $userId User identification number.
     *
     * @return string Encrypted
     */
    protected function encryptUserIdentificationNumber($userId)
    {
        $keyPath = $this->config->RegistrationForm->public_key ?? null;
        if (null === $keyPath) {
            throw new \Exception('Public key path not configured');
        }
        if (false === ($fp = fopen($keyPath, 'r'))) {
            throw new \Exception('Error opening public key');
        }
        if (false === ($key = fread($fp, 8192))) {
            throw new \Exception('Error reading public key');
        }
        fclose($fp);

        if (false === openssl_get_publickey($key)) {
            throw new \Exception('Error preparing public key');
        }

        if (!openssl_public_encrypt(
            $userId, $encrypted, $key, OPENSSL_PKCS1_OAEP_PADDING
        )
        ) {
            throw new \Exception('Error encrypting user id');
        }

        return base64_encode($encrypted);
    }

    /**
     * Send a request to REMS api.
     *
     * @param string      $url                 URL (relative)
     * @param array       $params              Request parameters
     * @param string      $method              GET|POST
     * @param int         $userType            Rems user type (see TYPE_ADMIN etc)
     * @param null|string $body                Request body
     * @param boolean     $requireRegistration Require that
     * the user has been registered to REMS during the session?
     *
     * @return string
     * @throws Exception
     */
    protected function sendRequest(
        $url,
        $params = [],
        $method = 'GET',
        $userType = RemsService::TYPE_USER,
        $body = null,
        $requireRegistration = true
    ) {
        if ($requireRegistration && !$this->isUserRegisteredDuringSession()) {
            throw new \Exception(
                'Attempting to call REMS before the user has been registered'
                . ' during the session'
            );
        }

        $userId = null;

        switch ($userType) {
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
            $err = "Invalid userType: $userType for url: $url";
            $this->error($err);
            throw new \Exception($err);
        }

        $contentType = $body['contentType'] ?? 'application/json';

        $client = $this->getClient($userId, $url, $method, $params, $contentType);
        if (isset($body['content'])) {
            $client->setRawBody($body['content']);
        }

        $formatError = function ($exception, $response) use ($client, $params) {
            $err = "REMS: request failed: " . $client->getRequest()->getUriString()
            . ', params: ' . var_export($params, true);
            if ($response !== null) {
                $err .= ', statusCode: ' . $response->getStatusCode() . ': '
                    . $response->getReasonPhrase()
                    . ', response content: ' . $response->getBody();
            }
            if ($exception !== null) {
                $err .= ', exception: ' . $exception->getMessage();
            }
            return $err;
        };

        try {
            $response = $client->send();
        } catch (\Exception $e) {
            $err = $formatError($e, null);
            $this->error($err);
            throw new \Exception($err);
        }

        $err = $formatError(null, $response);

        if (!$response->isSuccess() || $response->getStatusCode() !== 200) {
            $this->error($err);
            throw new \Exception($err);
        }

        $response = json_decode($response->getBody(), true);
        // Verify 'success' field for POST requests
        if ($method === 'POST'
            && (!isset($response['success']) || !$response['success'])
        ) {
            $this->error($err);
            throw new \Exception($err);
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
        $this->session->{self::SESSION_ACCESS_STATUS} = null;
        $this->session->{self::SESSION_BLACKLISTED} = null;
        $this->session->{self::SESSION_USAGE_PURPOSE} = null;
        $this->session->{self::SESSION_IS_REMS_REGISTERD} = null;

        if ($this->isUserRegisteredDuringSession()) {
            $this->closeOpenApplications();
        }
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
     * @return string|null
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
            'application.state/submitted'
                => RemsService::STATUS_SUBMITTED,
            'application.state/closed' => RemsService::STATUS_CLOSED,
            'application.state/draft' => RemsService::STATUS_DRAFT,
            'application.state/revoked' => RemsService::STATUS_REVOKED,
            'application.state/rejected' => RemsService::STATUS_REJECTED
        ];

        return $statusMap[$remsStatus] ?? 'unknown';
    }
}
