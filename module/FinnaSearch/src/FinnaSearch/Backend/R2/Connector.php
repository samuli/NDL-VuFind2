<?php

/**
 * Restricted Solr (R2) connector
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
 * @package  Search
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org
 */
namespace FinnaSearch\Backend\R2;

use Laminas\Http\Client as HttpClient;
use VuFindSearch\Backend\Exception\HttpErrorException;

use VuFindSearch\ParamBag;

/**
 * Restricted Solr (R2) connector
 *
 * @category VuFind
 * @package  Search
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org
 */
class Connector extends \VuFindSearch\Backend\Solr\Connector
{
    /**
     * Username
     *
     * @var string
     */
    protected $username;

    /**
     * API username
     *
     * @var string
     */
    protected $apiUser;

    /**
     * API password
     *
     * @var string
     */
    protected $apiPassword;

    /**
     * REMS service
     *
     * @var \Finna\RemsService\RemsService
     */
    protected $rems;

    /**
     * R2 field used to store unique identifier
     *
     * $uniqueKey field is not unique in R2 but is still treated as unique by VuFind.
     * (when uniqueness is not strictly required and doesn't cause a Solr error).
     * This field is unique in R2 and can be used together with cursorMark and sort.
     *
     * @var string
     */
    protected $R2uniqueKey = '_document_id';

    /**
     * HTTP configuration
     *
     * @var array
     */
    protected $httpConfig;

    /**
     * Set API user and password for authentication to index.
     *
     * @param string $user     User
     * @param string $password Password
     *
     * @return void
     */
    public function setApiAuthentication($user, $password)
    {
        $this->apiUser = $user;
        $this->apiPassword = $password;
    }

    /**
     * Set username
     *
     * @param string $username Username
     *
     * @return void
     */
    public function setUsername($username = null)
    {
        $this->username = $username;
    }

    /**
     * Set REMS service
     *
     * @param \Finna\RemsService\RemsService $rems REMS service
     *
     * @return void
     */
    public function setRems($rems)
    {
        $this->rems = $rems;
    }

    /**
     * Set HTTP configuration.
     *
     * @param Config $config Configuration
     *
     * @return void
     */
    public function setHttpConfig($config)
    {
        $this->httpConfig = $config;
    }

    /**
     * Execute a search.
     *
     * @param ParamBag $params Parameters
     *
     * @return string
     */
    public function search(ParamBag $params)
    {
        if ($params->get('cursorMark') && $sort = $params->get('sort')) {
            // Replace 'id' field with R2 unique identifier field
            $result = [];
            foreach ($sort as $s) {
                list($field, $order) = explode(' ', $s);
                $result[] = sprintf('%s %s', $this->R2uniqueKey, $order);
            }
            $params->set('sort', $result);
        }
        return parent::search($params);
    }

    /**
     * Send request the SOLR and return the response.
     *
     * @param HttpClient $client Prepared HTTP client
     *
     * @return string Response body
     *
     * @throws RemoteErrorException  SOLR signaled a server error (HTTP 5xx)
     * @throws RequestErrorException SOLR signaled a client error (HTTP 4xx)
     */
    protected function send(HttpClient $client)
    {
        if (!$this->apiUser || !$this->apiPassword) {
            throw new \Exception('R2 search API username/password not configured');
        }

        $headers = $client->getRequest()->getHeaders();
        $headers->removeHeader(new \Laminas\Http\Header\GenericHeader('x-user-id'));

        if ($this->username) {
            $headers->addHeaderLine('x-user-id', urldecode($this->username));
        }
        $client->setHeaders($headers);
        $client->setAuth($this->apiUser, $this->apiPassword);

        if ($this->httpConfig['ssl_allow_selfsigned'] ?? false) {
            $adapter = $client->getAdapter();
            if ($adapter instanceof \Laminas\Http\Client\Adapter\Socket) {
                $adapter->setOptions(['sslallowselfsigned' => true]);
            } elseif ($adapter instanceof \Laminas\Http\Client\Adapter\Curl) {
                $adapter->setOptions(
                    [
                        'curloptions' => [
                            CURLOPT_SSL_VERIFYPEER => false,
                            CURLOPT_SSL_VERIFYHOST => false
                        ]
                    ]
                );
            }
        }

        $this->debug(
            sprintf(
                '=> R2 Search headers: x-user-id: %s',
                $client->getHeader('x-user-id')
            )
        );

        if ($this->rems) {
            $this->debug(
                sprintf(
                    '=> R2 access status: %s',
                    $this->rems->getAccessPermission()
                )
            );
        }

        $this->debug(
            sprintf('=> %s %s', $client->getMethod(), $client->getUri())
        );

        $time     = microtime(true);
        $response = $client->send();
        $time     = microtime(true) - $time;

        $this->debug(
            sprintf(
                '<= %s %s', $response->getStatusCode(),
                $response->getReasonPhrase()
            ), ['time' => $time]
        );

        if ($this->rems) {
            $headers = $response->getHeaders();
            if ($accessStatus = $headers->get('x-user-access-status')) {
                $this->rems->setAccessStatusFromConnector(
                    $accessStatus->getFieldValue()
                );
            }
            if ($this->username) {
                $blacklisted = null;
                if ($blacklistedAt = $headers->get('x-user-blacklisted')) {
                    $blacklisted = $blacklistedAt->getFieldValue();
                }
                $this->rems->setBlacklistStatusFromConnector($blacklisted);
            }
        }

        if (!$response->isSuccess()) {
            throw HttpErrorException::createFromResponse($response);
        }
        return $response->getBody();
    }
}
