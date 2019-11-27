<?php

/**
 * Restricted Solr (R2) connector
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
 * @link     https://vufind.org
 */
namespace FinnaSearch\Backend\R2;

use VuFindSearch\ParamBag;
use Zend\Http\Client as HttpClient;

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
     * Set API user and password for authentication
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
     * @throws Exception             R2 search API key not configured
     * @throws RemoteErrorException  SOLR signaled a server error (HTTP 5xx)
     * @throws RequestErrorException SOLR signaled a client error (HTTP 4xx)
     */
    protected function send(HttpClient $client)
    {
        if (!$this->apiUser || !$this->apiPassword) {
            throw new \Exception('R2 search API username/password not configured');
        }

        $headers = $client->getRequest()->getHeaders();
        $headers->removeHeader(new \Zend\Http\Header\GenericHeader('x-user-id'));

        if ($this->username) {
            $headers->addHeaderLine('x-user-id', urldecode($this->username));
        }
        $client->setHeaders($headers);
        $client->setAuth($this->apiUser, $this->apiPassword);

        $this->debug(
            sprintf(
                '=> R2 Search headers: x-user-id: %s',
                $client->getHeader('x-user-id')
            )
        );

        return parent::send($client);
    }
}
