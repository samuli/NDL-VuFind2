<?php
/**
 * Factory for various top-level VuFind services.
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
 * @package  Service
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
namespace Finna\Service;
use Zend\Console\Console,
    Zend\ServiceManager\ServiceManager;

/**
 * Factory for various top-level VuFind services.
 *
 * @category VuFind2
 * @package  Service
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
class Factory extends \VuFind\Service\Factory
{
    /**
     * Construct the cache manager.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return \Finna\Cache\Manager
     */
    public static function getCacheManager(ServiceManager $sm)
    {
        return new \Finna\Cache\Manager(
            $sm->get('VuFind\Config')->get('config'),
            $sm->get('VuFind\Config')->get('searches')
        );
    }

    /**
     * Construct the cookie manager.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return \VuFind\Cookie\CookieManager
     */
    public static function getCookieManager(ServiceManager $sm)
    {
        if (Console::isConsole()) {
            return false;
        }

        $config = $sm->get('VuFind\Config')->get('config');
        $path = '/';
        if (isset($config->Cookies->limit_by_path)
            && $config->Cookies->limit_by_path
        ) {
            $path = $sm->get('Request')->getBasePath();
        }
        $secure = isset($config->Cookies->only_secure)
            ? $config->Cookies->only_secure
            : false;
        $domain = isset($config->Cookies->domain)
            ? $config->Cookies->domain
            : null;
        return new \VuFind\Cookie\CookieManager($_COOKIE, $path, $domain, $secure);
    }

    /**
     * Construct the ILS connection.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return \Finna\ILS\Connection
     */
    public static function getILSConnection(ServiceManager $sm)
    {
        $catalog = new \Finna\ILS\Connection(
            $sm->get('VuFind\Config')->get('config')->Catalog,
            $sm->get('VuFind\ILSDriverPluginManager'),
            $sm->get('VuFind\Config')
        );
        return $catalog->setHoldConfig($sm->get('VuFind\ILSHoldSettings'));
    }

    /**
     * Generic plugin manager factory (support method).
     *
     * @param ServiceManager $sm Service manager.
     * @param string         $ns VuFind namespace containing plugin manager
     *
     * @return object
     */
    public static function getGenericPluginManager(ServiceManager $sm, $ns)
    {
        $className = 'Finna\\' . $ns . '\PluginManager';
        $configKey = strtolower(str_replace('\\', '_', $ns));
        $config = $sm->get('Config');
        return new $className(
            new \Zend\ServiceManager\Config(
                $config['vufind']['plugin_managers'][$configKey]
            )
        );
    }

    /**
     * Construct the Search\Results Plugin Manager.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return \VuFind\Search\Results\PluginManager
     */
    public static function getSearchResultsPluginManager(ServiceManager $sm)
    {
        return static::getGenericPluginManager($sm, 'Search\Results');
    }

}
