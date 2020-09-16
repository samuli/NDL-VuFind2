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

use Finna\Service\RemsService;
use Laminas\EventManager\EventManager;
use Laminas\EventManager\EventManagerInterface;
use VuFind\Exception\Auth as AuthException;

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
     * Logout event.
     *
     * @var string
     */
    const EVENT_LOGOUT = 'logout';

    /**
     * Event manager.
     *
     * @var EventManager
     */
    protected $events = null;

    /**
     * Constructor
     *
     * @param \Laminas\Session\ManagerInterface $sessionManager Session manager
     * @param EventManager                      $events         Event manager
     */
    public function __construct(
        \Laminas\Session\ManagerInterface $sessionManager,
        EventManager $events
    ) {
        $this->sessionManager = $sessionManager;

        $events->setIdentifiers(['Finna\Auth\Suomifi']);
        $this->events = $events;
    }

    /**
     * Attempt to authenticate the current user.  Throws exception if login fails.
     *
     * @param \Laminas\Http\PhpEnvironment\Request $request Request object containing
     * account credentials.
     *
     * @throws AuthException
     * @return \VuFind\Db\Row\User Object representing logged-in user.
     */
    public function authenticate($request)
    {
        $result = parent::authenticate($request);

        $config = $this->getConfig()->Shibboleth;
        if ($config->store_username_to_session ?? false) {
            // Store encrypted username to session
            $username = $this->encrypt(
                // parent method does not hash the username
                parent::getServerParam($request, $config->username)
            );
            $session = new \Laminas\Session\Container(
                'Shibboleth', $this->sessionManager
            );
            $session['identity_number'] = $username;
        }
        return $result;
    }

    /**
     * Perform cleanup at logout time.
     *
     * @param string $url URL to redirect user to after logging out.
     *
     * @return string     Redirect URL (usually same as $url, but modified in
     * some authentication modules).
     */
    public function logout($url)
    {
        $this->events->trigger(self::EVENT_LOGOUT, 'Finna\Auth\Suomifi', []);
        return parent::logout($url);
    }

    /**
     * Set configuration.
     *
     * @param \Laminas\Config\Config $config Configuration to set
     *
     * @return void
     */
    public function setConfig($config)
    {
        // Replace Shibboleth config section with Shibboleth_suomifi
        $data = $config->toArray();
        $data['Shibboleth'] = $data['Shibboleth_suomifi'];
        $config = new \Laminas\Config\Config($data);

        parent::setConfig($config);
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
        // Set 'auth_method' to Suomifi
        $url = str_replace(
            'auth_method%3DShibboleth', 'auth_method%3DSuomifi', $url
        );
        return $url;
    }

    /**
     * Get the route that is displayed in lightbox after the login has been
     * successfully performed and the page reloaded. Returns an array with
     * 'route' and 'params' keys.
     *
     * @return null|array
     */
    public function getPostLoginLightboxRoute()
    {
        if ($config = $this->getConfig()->Shibboleth ?? null) {
            $route = $config->post_login_lightbox_route ?? false;
            $routeParams = $config->post_login_lightbox_route_params ?? [];
            if ($route) {
                $params = [];
                foreach (explode(',', $routeParams) as $param) {
                    if (false === strpos($param, ':')) {
                        continue;
                    }
                    list($key, $val) = explode(':', $param, 2);
                    $params[$key] = $val;
                }
                return compact('route', 'params');
            }
        }
        return null;
    }

    /**
     * Get a server parameter taking into account any environment variables
     * redirected by Apache mod_rewrite.
     *
     * @param \Laminas\Http\PhpEnvironment\Request $request Request object containing
     * account credentials.
     * @param string                               $param   Parameter name
     *
     * @throws AuthException
     * @return mixed
     */
    protected function getServerParam($request, $param
    ) {
        $val = parent::getServerParam($request, $param);

        $config = $this->getConfig()->Shibboleth;
        if ($param === $config->username
        ) {
            $secret = $config->hash_secret ?? null;
            if (empty(trim($secret))) {
                throw new AuthException('hash_secret not configured');
            }
            $val = hash_hmac('sha256', $val, $secret, false);
            if ($val === false) {
                throw new AuthException('Error hashing username');
            }
        }
        return $val;
    }

    /**
     * Encrypt string using public key.
     *
     * @param string $string String.
     *
     * @return string Encrypted
     */
    protected function encrypt($string)
    {
        $config = $this->getConfig()->Shibboleth;
        $keyPath = $config->public_key ?? null;
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
            $string, $encrypted, $key, OPENSSL_PKCS1_OAEP_PADDING
        )
        ) {
            throw new \Exception('Error encrypting string');
        }

        return base64_encode($encrypted);
    }
}
