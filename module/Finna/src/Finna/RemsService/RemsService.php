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

    const STATUS_APPROVED = 'approved';
    const STATUS_NOT_SUBMITTED = 'not-submitted';
    const STATUS_SUBMITTED = 'submitted';
    const STATUS_CLOSED = 'closed';
    const STATUS_DRAFT = 'draft';

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
     * Constructor.
     *
     * @param Config         $config  Configuration
     * @param SessionManager $session Session container
     * @param Manager        $auth    Auth manager
     */
    public function __construct(
        Config $config,
        Container $session = null,
        Manager $auth
    ) {
        $this->config = $config;
        $this->session = $session;
        $this->auth = $auth;
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
        $commonName = $firstname;
        if ($lastname) {
            $commonName = $commonName ? " $lastname" : $lastname;
        }

        // 1. Create user
        $params = [
            'eppn' => $this->getUserId(),
            'mail' => $email,
            'commonName' => $commonName
        ];
        $this->sendRequest('users/create', $params, 'POST', RemsService::TYPE_ADMIN);
        
        // 2. Create draft application
        $catItemId = $this->getCatalogItemId('entitlement');
        $params = ['catalogue-item-ids' => [$catItemId]];

        $response = $this->sendRequest('applications/create', $params, 'POST');

        if (!isset($response['application-id'])) {
            return 'REMS: error creating draft application';
        }
        $applicationId = $response['application-id'];

        // 3. Save draft
        // TODO
        // Update this to save data to correct form fields
        // when REMS form is available
        $params =  [
            'application-id' => $applicationId,
            'field-values' =>  [
                ['field' => 58, 'value' => $firstname],
                ['field' => 59, 'value' => $lastname],
                ['field' => 60, 'value' => $email],
                ['field' => 61, 'value' => $formParams['usage_purpose']],
                ['field' => 62, 'value' => $formParams['usage_desc']]
            ]
        ];

        $response = $this->sendRequest('applications/save-draft', $params, 'POST');

        // 5. Submit application
        $params = [
             'application-id' => $applicationId
        ];
        $response = $this->sendRequest(
            'applications/submit',
            $params,
            'POST'
        );

        $this->savePermissionToSession(
            null,
            $this->getSessionKey($this->getCatalogItemId('entitlement'))
        );

        return true;
    }

    /**
     * Check permission
     * Returns an array with keys 'success' and 'status'.
     *
     * @param bool $callApi Call REMS API if permission is not already
     *                      checked and saved to session.
     *
     * @return array
     */
    public function checkPermission($callApi = false)
    {
        $catItemId = $this->getCatalogItemId('entitlement');
        $sessionKey = $this->getSessionKey($catItemId);

        if (!$callApi) {
            return $this->session->{$sessionKey} ?? null;
        }

        try {
            $applications = $this->getApplications(
                [RemsService::STATUS_SUBMITTED, RemsService::STATUS_APPROVED]
            );
        } catch (\Exception $e) {
            return ['success' => false, 'status' => $e->getMessage()];
        }

        $status = RemsService::STATUS_NOT_SUBMITTED;

        foreach ($applications as $application) {
            if ($application['catalogItemId'] !== $catItemId) {
                continue;
            }
            if (isset($application['status'])) {
                $appStatus = $application['status'];
                switch ($appStatus) {
                case RemsService::STATUS_SUBMITTED:
                    $status = $appStatus;
                    break;
                case RemsService::STATUS_APPROVED:
                    $status = $appStatus;
                    break 2;
                }
            }
        }

        $this->savePermissionToSession($status, $sessionKey);
        return ['success' => true, 'status' => $status];
    }

    /**
     * Return applications
     *
     * @param array $statuses application statuses
     *
     * @return array
     */
    public function getApplications($statuses = [])
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

        $result = $this->sendRequest('applications', $params);

        $catItemId = $this->getCatalogItemId('entitlement');

        $applications = [];
        foreach ($result as $application) {
            $data = ['id' => $catItemId];
            $catalogItemId = $catItemId;
            $id = $application['application/id'];
            $status = $application['application/state'] ?? null;
            $status = $this->mapRemsStatus($status);
            $created = $application['application/created'] ?? null;
            $modified = $application['application/modified'] ?? null;
            foreach ($application['application/resources'] as $catItem) {
                if ($catItem['catalogue-item/id'] === $catItemId) {
                    $titles = $catItem['catalogue-item/title'];
                    $title = $titles['fi'] ?? $titles['default'] ?? '';
                    break;
                }
            }
            $applications[]
                = compact('id', 'catalogItemId', 'status', 'created', 'modified');
        }

        return $applications;
    }

    /**
     * Close all open applications.
     *
     * @param string $comment Comment that is attached to the application.
     *
     * @return void
     */
    public function closeOpenApplications($comment = 'logout')
    {
        $applications = $this->getApplications([RemsService::STATUS_APPROVED]);
        foreach ($applications as $application) {
            if ($application['status'] !== RemsService::STATUS_APPROVED) {
                continue;
            }
            $params = [
                'application-id' => $application['id'],
                'comment' => $comment
            ];
            try {
                $this->sendRequest(
                    'applications/close',
                    null,
                    'POST',
                    RemsService::TYPE_APPROVER,
                    ['content' => json_encode($params)]
                );
            } catch (\Exception $e) {
                $this->error(
                    'Error closing open applications on logout: ' . $e->getMessage()
                );
            }
        }
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
        return $user->username;
    }

    /**
     * Send a request to REMS api.
     *
     * @param string      $url      URL (relative)
     * @param array       $params   Request parameters
     * @param string      $method   GET|POST
     * @param int         $userType Rems user type (see TYPE_ADMIN etc)
     * @param null|string $body     Request body
     *
     * @return bool
     */
    protected function sendRequest(
        $url,
        $params = [],
        $method = 'GET',
        $userType = RemsService::TYPE_USER,
        $body = null
    ) {
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
        $this->closeOpenApplications();
    }

    /**
     * Return HTTP client
     *
     * @param string $userId      User Id
     * @param string $url         URL (relative)
     * @param string $method      GET|POST
     * @param array  $bodyParams  Body parameters
     * @param string $contentType Content-Type
     *
     * @return string
     */
    protected function getClient(
        $userId,
        $url,
        $method = 'GET',
        $bodyParams = [],
        $contentType = 'application/json'
    ) {
        $url = $this->config->General->apiUrl . '/' . $url;

        $client = $this->httpService->createClient($url);
        $client->setOptions(['timeout' => 30, 'useragent' => 'Finna']);
        $headers = $client->getRequest()->getHeaders();
        $headers->addHeaderLine(
            'Accept',
            'application/json'
        );
        $headers->addHeaderLine('x-rems-api-key', $this->config->General->apiKey);
        $headers->addHeaderLine('x-rems-user-id', $userId);

        $body = json_encode($bodyParams);
        $client->setRawBody($body);
        $client->getRequest()->getHeaders()
            ->addHeaderLine('Content-Type', $contentType);

        $client->setMethod($method);
        return $client;
    }

    /**
     * Return session key for a permission
     *
     * @param int $permissionId Id
     *
     * @return string
     */
    protected function getSessionKey($permissionId)
    {
        return "permission-$permissionId";
    }

    /**
     * Save permission to session
     *
     * @param string $status     Permission status
     * @param string $sessionKey Session key
     *
     * @return void
     */
    protected function savePermissionToSession($status, $sessionKey)
    {
        if ($status === null) {
            unset($this->session->{$sessionKey});
        } else {
            $this->session->{$sessionKey} = $status;
        }
    }

    /**
     * Get REMS catalogue item id from configuration
     *
     * @param string $type Catalogue item type
     *
     * @return string|null
     */
    protected function getCatalogItemId($type = 'registration')
    {
        return (int)$this->config->General->catalogItem[$type] ?? null;
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
            'application.state/draft' => RemsService::STATUS_DRAFT
         ];

        return $statusMap[$remsStatus] ?? 'unknown';
    }
}
