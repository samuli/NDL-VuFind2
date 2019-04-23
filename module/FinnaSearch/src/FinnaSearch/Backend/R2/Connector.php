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
     * API key
     *
     * @var string
     */
    protected $apiKey;

    /**
     * Set API key
     *
     * @param string $key Key
     *
     * @return void
     */
    public function setApiKey($key)
    {
        $this->apiKey = $key;
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
     * Create the HTTP client.
     *
     * @param string $url    Target URL
     * @param string $method Request method
     *
     * @return HttpClient
     */
    protected function createClient($url, $method)
    {
        $client = parent::createClient($url, $method);
        if ($this->apiKey) {
            $client->getRequest()->getHeaders()
                ->addHeaderLine('api-key', $this->apiKey);
        }
        return $client;
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
        if (!$this->apiKey) {
            throw new \Exception('R2 search API key not configured');
        }

        $headers = $client->getRequest()->getHeaders();
        foreach (['apiKey' => 'api-key', 'username' => 'user-id']
                 as $key => $header
        ) {
            $headers->removeHeader(new \Zend\Http\Header\GenericHeader($header));
            if ($val = $this->{$key}) {
                $headers->addHeaderLine($header, $val);
            }
        }
        $client->setHeaders($headers);

        $this->debug(
            sprintf(
                '=> R2 Search headers: api-key: %s, user-id: %s',
                $client->getHeader('api-key'),
                $client->getHeader('user-id')
            )
        );

        return parent::send($client);
    }
}
