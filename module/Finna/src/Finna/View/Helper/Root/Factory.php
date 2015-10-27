<?php
/**
 * Factory for Root view helpers.
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
 * @package  View_Helpers
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
namespace Finna\View\Helper\Root;
use Zend\ServiceManager\ServiceManager;

/**
 * Factory for Root view helpers.
 *
 * @category VuFind2
 * @package  View_Helpers
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 *
 * @codeCoverageIgnore
 */
class Factory extends \VuFind\View\Helper\Root\Factory
{
    /**
     * Construct the LayoutClass helper.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return LayoutClass
     */
    public static function getLayoutClass(ServiceManager $sm)
    {
        $config = $sm->getServiceLocator()->get('VuFind\Config')->get('config');
        $left = !isset($config->Site->sidebarOnLeft)
            ? false : $config->Site->sidebarOnLeft;
        $offcanvas = !isset($config->Site->offcanvas)
            ? false : $config->Site->offcanvas;
        return new \Finna\View\Helper\Bootstrap3\LayoutClass($left, $offcanvas);
    }

    /**
     * Construct the Holdings Details Mode helper.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return HoldingsDetailsMode
     */
    public static function getHoldingsDetailsMode(ServiceManager $sm)
    {
        return new HoldingsDetailsMode(
            $sm->getServiceLocator()->get('VuFind\Config')->get('config')
        );
    }

    /**
     * Construct the Record helper.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return Record
     */
    public static function getRecord(ServiceManager $sm)
    {
        return new Record(
            $sm->getServiceLocator()->get('VuFind\Config')->get('config')
        );
    }

    /**
     * Construct the RecordLink helper.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return RecordLink
     */
    public static function getRecordLink(ServiceManager $sm)
    {
        return new RecordLink($sm->getServiceLocator()->get('VuFind\RecordRouter'));
    }

    /**
     * Construct the Navibar view helper.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return \Finna\View\Helper\Root\Navibar
     */
    public static function getNavibar(ServiceManager $sm)
    {
        $locator = $sm->getServiceLocator();
        $menuConfig = $locator->get('VuFind\Config')->get('navibar');

        return new Navibar($menuConfig);
    }

    /**
     * Construct combined results view helper.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return Combined
     */
    public static function getCombined(ServiceManager $sm)
    {
        $config = $sm->getServiceLocator()->get('VuFind\Config')->get('Combined');
        return new Combined($config);
    }

    /**
     * Construct content page view helper.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return Header
     */
    public static function getContent(ServiceManager $sm)
    {
        return new Content();
    }

    /**
     * Construct Primo view helper.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return Primo
     */
    public static function getPrimo(ServiceManager $sm)
    {
        $config = $sm->getServiceLocator()->get('VuFind\Config')->get('Primo');
        return new Primo($config);
    }

    /**
     * Construct record image view helper.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return Header
     */
    public static function getRecordImage(ServiceManager $sm)
    {
        return new RecordImage();
    }

    /**
     * Construct the SearchTabs helper.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return SearchTabs
     */
    public static function getSearchTabs(ServiceManager $sm)
    {
        $locator = $sm->getServiceLocator();
        $config = $locator->get('VuFind\Config')->get('config');
        $config = isset($config->SearchTabs)
            ? $config->SearchTabs->toArray() : [];
        return new SearchTabs(
            $locator->get('VuFind\SessionManager'),
            $locator->get('VuFind\DbTablePluginManager'),
            $locator->get('VuFind\SearchResultsPluginManager'),
            $config,
            $sm->get('url')
        );
    }
    /**
     * Construct Headtitle helper
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return HeadTitle
     */
    public static function getHeadTitle(ServiceManager $sm)
    {
        $config = $sm->getServiceLocator()->get('VuFind\Config')->get('config');
        return new HeadTitle($config);
    }

    /**
     * Construct MetaLib view helper.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return MetaLib
     */
    public static function getMetaLib(ServiceManager $sm)
    {
        $config = $sm->getServiceLocator()->get('VuFind\Config')->get('MetaLib');
        return new MetaLib($config);
    }

    /**
     * Construct the SearchTabs helper.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return SearchTabs
     */
    public static function getSearchTabsRecommendations(ServiceManager $sm)
    {
        $locator = $sm->getServiceLocator();
        $config = $locator->get('VuFind\Config')->get('config');
        $recommendationConfig = isset($config->SearchTabsRecommendations)
            ? $config->SearchTabsRecommendations->toArray() : [];
        return new SearchTabsRecommendations($recommendationConfig);
    }

    /**
     * Construct the OpenUrl helper.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return OpenUrl
     */
    public static function getOpenUrl(ServiceManager $sm)
    {
        $config = $sm->getServiceLocator()->get('VuFind\Config')->get('config');
        $openUrlRules = json_decode(
            file_get_contents(
                \VuFind\Config\Locator::getConfigPath('OpenUrlRules.json')
            ),
            true
        );
        return new OpenUrl(
            $sm->get('context'),
            $openUrlRules,
            isset($config->OpenURL) ? $config->OpenURL : null
        );
    }

    /**
     * Construct the ProxyUrl helper.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return ProxyUrl
     */
    public static function getProxyUrl(ServiceManager $sm)
    {
        $config = $sm->getServiceLocator()->get('VuFind\Config');
        return new ProxyUrl(
            $sm->getServiceLocator()->get('VuFind\IpAddressUtils'),
            $config->get('permissions'),
            $config->get('config')
        );
    }

    /**
     * Construct the Total indexed count view helper.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return \Finna\View\Helper\Root\TotalIndexed
     */
    public static function getTotalIndexed(ServiceManager $sm)
    {
        $locator = $sm->getServiceLocator();
        return new TotalIndexed($locator);
    }

    /**
     * Construct the PersonaAuth view helper.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return \Finna\View\Helper\Root\PersonaAuth
     */
    public static function getPersonaAuth(ServiceManager $sm)
    {
        $locator = $sm->getServiceLocator();
        return new PersonaAuth($locator);
    }

    /**
     * Construct the Piwik helper.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return Piwik
     */
    public static function getPiwik(ServiceManager $sm)
    {
        $config = $sm->getServiceLocator()->get('VuFind\Config')->get('config');
        $url = isset($config->Piwik->url) ? $config->Piwik->url : false;
        $siteId = isset($config->Piwik->site_id) ? $config->Piwik->site_id : 1;
        $customVars = isset($config->Piwik->custom_variables)
            ? $config->Piwik->custom_variables
            : false;
        return new Piwik($url, $siteId, $customVars);
    }

    /**
     * Construct the Logout message view helper.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return \Finna\View\Helper\Root\LogoutMessage
     */
    public static function getLogoutMessage(ServiceManager $sm)
    {
        $authManager = $sm->getServiceLocator()->get('VuFind\AuthManager');
        $request = $sm->getServiceLocator()->get('Request');
        return new LogoutMessage($authManager, $request);
    }

    /**
     * Construct the Feed component helper.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return Record
     */
    public static function getFeed(ServiceManager $sm)
    {
        return new Feed(
            $sm->getServiceLocator()->get('VuFind\Config')->get('rss')
        );
    }

    /**
     * Construct the Organisations list view helper.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return \Finna\View\Helper\Root\OrganisationsList
     */
    public static function getOrganisationsList(ServiceManager $sm)
    {
        $locator = $sm->getServiceLocator();
        $cache = $locator->get('VuFind\CacheManager')->getCache('object');
        $facetHelper = $locator->get('VuFind\HierarchicalFacetHelper');
        $resultsManager = $locator->get('VuFind\SearchResultsPluginManager');

        return new OrganisationsList($cache, $facetHelper, $resultsManager);
    }

    /**
     * Construct the ImageSrc helper.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return ImageSrc
     */
    public static function getImageSrc(ServiceManager $sm)
    {
        return new ImageSrc(
            $sm->getServiceLocator()->get('VuFindTheme\ThemeInfo')
        );
    }

    /**
     * Construct the ScriptSrc helper.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return ImageSrc
     */
    public static function getScriptSrc(ServiceManager $sm)
    {
        return new ScriptSrc(
            $sm->getServiceLocator()->get('VuFindTheme\ThemeInfo')
        );
    }

    /**
     * Construct the SearchBox helper.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return SearchBox
     */
    public static function getSearchBox(ServiceManager $sm)
    {
        $config = $sm->getServiceLocator()->get('VuFind\Config');
        $searchbox = new SearchBox(
            $sm->getServiceLocator()->get('VuFind\SearchOptionsPluginManager'),
            $config->get('searchbox')->toArray()
        );
        $searchbox->setTabConfig($config->get('config'));
        return $searchbox;
    }

    /**
     * Construct the authorization notification helper.
     *
     * @param ServiceManager $sm Service manager.
     *
     * @return AuthorizationNotification
     */
    public static function getAuthorizationNote(ServiceManager $sm)
    {
        $authService
            = $sm->getServiceLocator()->get('ZfcRbac\Service\AuthorizationService');
        return new AuthorizationNotification($authService);
    }
}
