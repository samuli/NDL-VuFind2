<?php
/**
 * Suomi.fi authentication module.
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
 * @package  Authentication
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
namespace Finna\Auth;

/**
 * Suomi.fi authentication module.
 *
 * @category VuFind
 * @package  Authentication
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org Main Page
 */
class Suomifi extends Shibboleth
{
    /**
     * Get configuration (load automatically if not previously set).  Throw an
     * exception if the configuration is invalid.
     *
     * @throws AuthException
     * @return \Zend\Config\Config
     */
    public function getConfig()
    {
        if (!$this->configValidated) {
            // Replace Shibboleth config section with Shibboleth_suomifi
            $data = $this->config->toArray();
            $data['Shibboleth'] = $data['Shibboleth_suomifi'];
            $this->config = new \Zend\Config\Config($data);
        }

        return parent::getConfig();
    }

    /**
     * Get the URL to establish a session (needed when the internal VuFind login
     * form is inadequate).  Returns false when no session initiator is needed.
     *
     * @param string $target Full URL where external authentication method should
     * send user after login (some drivers may override this).
     *
     * @return bool|string
     */
    public function getSessionInitiator($target)
    {
        $url = parent::getSessionInitiator($target);
        if (!$url) {
            return $url;
        }
        
        // Set 'auth_method' query parameter within 'target'
        // query parameter to Suomifi

        $parsed = parse_url($url);
        parse_str($parsed['query'], $queryParams);
        $target = $queryParams['target'];
        $targetParsed = parse_url($target);
        parse_str($targetParsed['query'], $targetQueryParams);

        if (empty($targetParsed['scheme'])) {
            return $url;
        }

        $targetQueryParams['auth_method'] = 'Suomifi';

        $target
            = $targetParsed['scheme'] . '://' . $targetParsed['host']
            . $targetParsed['path'] . '?' . http_build_query($targetQueryParams);

        $queryParams['target'] = $target;

        return
            $parsed['scheme'] . '://' . $parsed['host']
            . $parsed['path'] . '?' . http_build_query($queryParams);
    }
}
