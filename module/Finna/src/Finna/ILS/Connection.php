<?php
/**
 * Catalog Connection Class
 *
 * This wrapper works with a driver class to pass information from the ILS to
 * VuFind.
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
 * @package  ILS_Drivers
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:building_an_ils_driver Wiki
 */
namespace Finna\ILS;
use VuFind\Exception\ILS as ILSException;

/**
 * Catalog Connection Class
 *
 * This wrapper works with a driver class to pass information from the ILS to
 * VuFind.
 *
 * @category VuFind2
 * @package  ILS_Drivers
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:building_an_ils_driver Wiki
 */
class Connection extends \VuFind\ILS\Connection
{
    /**
     * Change Password
     *
     * Attempts to change patron password (PIN code)
     *
     * @param array $details An array of patron id and old and new password
     *
     * @return mixed An array of data on the request including
     * whether or not it was successful and a system message (if available)
     */
    public function changePassword($details)
    {
        if (!$this->checkCapability('changePassword', compact('details'))) {
            throw new ILSException(
                'Cannot call method: ' . $this->getDriverClass() . '::patronLogin'
            );
        }

        // Remove old credentials from the cache regardless of whether the change
        // was successful
        $session = new \Zend\Session\Container('Finna\ILS\Connection\PatronCache');
        $hash = md5(
            $details['patron']['cat_username'] . "\t"
            . $details['oldPassword']
        );
        if (isset($session->$hash)) {
            unset($session->$hash);
        }

        return $this->getDriver()->changePassword($details);
    }

    /**
     * Patron Login with a cache
     *
     * This is a wrapper to ILS drivers' patronLogin() with a session-based cache
     *
     * @param string $username  The patron user id or barcode
     * @param string $password  The patron password
     * @param string $secondary Optional secondary login field
     *
     * @return mixed           Associative array of patron info on successful login,
     * null on unsuccessful login.
     */
    public function patronLogin($username, $password, $secondary = null)
    {
        $session = new \Zend\Session\Container('Finna\ILS\Connection\PatronCache');
        $hash = md5("$username\t$password");
        // Use a cached result if available and at most 60 seconds old
        if (isset($session->$hash)
            && $session->{$hash}['timestamp'] >= time() - 60
        ) {
            return $session->{$hash}['data'];
        }

        if ($this->checkCapability('patronLogin', compact('username', 'password'))) {
            $result = $this->getDriver()->patronLogin(
                $username, $password, $secondary
            );
            if (is_array($result)) {
                $session->$hash = [
                    'timestamp' => time(),
                    'data' => $result
                ];
            }
            return $result;
        }
        throw new ILSException(
            'Cannot call method: ' . $this->getDriverClass() . '::patronLogin'
        );
    }

    /**
     * Check Email Update
     *
     * A support method for checkFunction(). This is responsible for checking
     * the driver configuration to determine if the system supports updating
     * the email address of the ILS.
     *
     * @param array $functionConfig The email update configuration values
     * @param array $params         Patron data
     *
     * @return mixed On success, an associative array with specific function keys
     * and values of the configuration values.
     * on failure, false.
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    protected function checkMethodupdateEmail($functionConfig, $params)
    {
        if ($this->checkCapability('updateEmail', [$params ?: []])) {
            return $functionConfig;
        }
        return false;
    }

    /**
     * Check Phone Update
     *
     * A support method for checkFunction(). This is responsible for checking
     * the driver configuration to determine if the system supports updating
     * the phone number of the ILS.
     *
     * @param array $functionConfig The phone update configuration values
     * @param array $params         Patron data
     *
     * @return mixed On success, an associative array with specific function keys
     * and values of the configuration values.
     * on failure, false.
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    protected function checkMethodupdatePhone($functionConfig, $params)
    {
        if ($this->checkCapability('updatePhone', [$params ?: []])) {
            return $functionConfig;
        }
        return false;
    }

    /**
     * Check if catalog login is availale
     *
     * @return bool true if the login is available
     */
    public function loginAvailable()
    {
        $loginDrivers = $this->getLoginDrivers();
        return !$this->supportsMethod('getLoginDrivers', [])
            || !empty($loginDrivers);
    }

    /**
     * Check if online payment is supported.
     *
     * @param array $functionConfig Function configuration values
     * @param array $params         An array of function-specific params (or null)
     *
     * @return boolean
     */
    protected function checkMethodmarkFeesAsPaid($functionConfig, $params)
    {
        if ($this->checkCapability('markFeesAsPaid', [$params ?: []])) {
            return ['function' => 'markFeesAsPaid'];
        }
        return false;
    }
}
