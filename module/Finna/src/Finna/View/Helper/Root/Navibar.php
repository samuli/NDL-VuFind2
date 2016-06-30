<?php
/**
 * Navibar view helper
 *
 * PHP version 5
 *
 * Copyright (C) The National Library of Finland 2014.
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
 * @category VuFind
 * @package  View_Helpers
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
namespace Finna\View\Helper\Root;

/**
 * Navibar view helper
 *
 * @category VuFind
 * @package  View_Helpers
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
class Navibar extends \Zend\View\Helper\AbstractHelper
{
    /**
     * Browse view helper
     *
     * @var Zend\View\Helper\Url
     */
    protected $browseHelper;

    /**
     * MetaLib view helper
     *
     * @var Zend\View\Helper\Url
     */
    protected $metaLibHelper;

    /**
     * Primo view helper
     *
     * @var Zend\View\Helper\Url
     */
    protected $primoHelper;

    /**
     * Url view helper
     *
     * @var Zend\View\Helper\Url
     */
    protected $urlHelper;

    /**
     * Menu configuration
     *
     * @var \Zend\Config\Config
     */
    protected $config;

    /**
     * Menu items
     *
     * @var Array
     */
    protected $menuItems;

    /**
     * Current language
     *
     * @var string
     */
    protected $language;

    /**
     * Constructor
     *
     * @param Zend\Config\Config $config Menu configuration
     * custom variables
     */
    public function __construct(\Zend\Config\Config $config)
    {
        $this->config = $config;
    }

    /**
     * Returns Navibar view helper.
     *
     * @return FInna\View\Helper\Root\Navibar
     */
    public function __invoke()
    {
        return $this;
    }

    /**
     * Returns rendered navibar layout.
     *
     * @return string
     */
    public function render()
    {
        return $this->getView()->render('navibar.phtml');
    }

    /**
     * Returns menu items as an associative array where each item consists of:
     *    string  $label       Label (untranslated)
     *    string  $url         Url
     *    boolean $route       True if url is a route name.
     *                         False if url is a literal link.
     *    array   $routeParams Route parameters as a key-value pairs.
     *
     * @param string $lng Language code
     *
     * @return Array
     */
    public function getMenuItems($lng)
    {
        if (!$this->menuItems || $lng != $this->language) {
            $this->browseHelper = $this->getView()->plugin('browse');
            $this->metaLibHelper = $this->getView()->plugin('metalib');
            $this->primoHelper = $this->getView()->plugin('primo');
            $this->urlHelper = $this->getView()->plugin('url');
            $this->language = $lng;
            $this->parseMenuConfig($lng);
        }
        return $this->menuItems;
    }

    /**
     * Constructs an url for a menu item that may be used in the template.
     *
     * @param array $data menu item configuration
     *
     * @return string
     */
    public function getMenuItemUrl(array $data)
    {
        if (!$data['route']) {
            return $data['url'];
        }

        if (isset($data['routeParams'])) {
            return $this->urlHelper->__invoke($data['url'], $data['routeParams']);
        } else {
            return $this->urlHelper->__invoke($data['url']);
        }
    }

    /**
     * Returns a url for changing the site language.
     *
     * The url is constructed by appending 'lng' query parameter
     * to the current page url.
     * Note: the returned url does not include possible hash (anchor),
     * which is inserted on the client-side.
     * /themes/finna/js/finna.js::initAnchorNavigationLinks
     *
     * @param string $lng Language code
     *
     * @return string
     */
    public function getLanguageUrl($lng)
    {
        $url = $this->view->serverUrl(true);
        $parts = parse_url($url);

        $params = [];
        if (isset($parts['query'])) {
            parse_str($parts['query'], $params);
            $url = substr($url, 0, strpos($url, '?'));
        }
        $params['lng'] = $lng;
        $url .= '?' . http_build_query($params);
        if (isset($parts['fragment'])) {
            $url .= '#' . $parts['fragment'];
        }
        return $url;
    }

    /**
     * Internal function for parsing menu configuration.
     *
     * @param string $lng Language code
     *
     * @return void
     */
    protected function parseMenuConfig($lng)
    {
        $translator = $this->getView()->plugin('translate');
        $translationEmpty = $this->getView()->plugin('translationEmpty');

        $parseUrl = function ($url) {
            $url = trim($url);

            $data = [];
            if (strpos($url, ',') !== false) {
                list($url, $target) = explode(',', $url, 2);
                $url = trim($url);
                $data['target'] = trim($target);
            }

            if (preg_match('/^(http|https):\/\//', $url)) {
                // external url
                $data['url'] = $url;
                $data['route'] = false;
                return $data;
            }

            $data['route'] = true;

            $needle = 'content-';
            if (($pos = strpos($url, $needle)) === 0) {
                // Content pages do not have static routes, so we
                // need to add required route parameters for url view helper.
                $page = substr($url, $pos + strlen($needle));
                $data['routeParams'] = [];
                $data['routeParams']['page'] = $page;
                $url = 'content-page';
            }

            $data['url'] = $url;
            return $data;
        };

        $result = [];
        $menuConfig = $this->getMenuData($this->config);
        $menuData = $menuConfig['menuData'];
        $sortData = $menuConfig['sortData'];

        foreach ($menuData as $menuKey => $items) {
            $item = [
                'id' => $menuKey, 'label' => "menu_$menuKey",
            ];

            $desc = 'menu_' . $menuKey . '_desc';
            if ($translator->translate($desc, null, false) !== false) {
                $item['desc'] = $desc;
            }

            $options = [];
            foreach ($items as $itemKey => $action) {
                if (!$action) {
                    continue;
                }
                if (!is_string($action)) {
                    if (!isset($action[$lng])) {
                        continue;
                    } else {
                        $action = $action[$lng];
                    }
                }

                $option = array_merge(
                    ['id' => $itemKey, 'label' => "menu_$itemKey"],
                    $parseUrl($action)
                );

                if ($option['route']) {
                    if (strpos('metalib-', $option['url']) === 0) {
                        if (!$this->metaLibHelper->isAvailable()) {
                            continue;
                        }
                    }
                    if (strpos('primo-', $option['url']) === 0) {
                        if (!$this->primoHelper->isAvailable()) {
                            continue;
                        }
                    }
                    if ($option['url'] === 'browse-database'
                        && !$this->browseHelper->isAvailable('Database')
                    ) {
                        continue;
                    }
                    if ($option['url'] === 'browse-journal'
                        && !$this->browseHelper->isAvailable('Journal')
                    ) {
                        continue;
                    }
                }

                $desc = 'menu_' . $itemKey . '_desc';
                if (!$translationEmpty($desc)) {
                    $option['desc'] = $desc;
                }
                $options[] = $option;
            }
            if (empty($options)) {
                continue;
            } else {
                $item['items'] = $options;
                $result[] = $item;
            }
        }
        $this->menuItems = $this->sortMenuItems($result, $sortData);
    }

    /**
     * Separate menu data from menu order data (__[menu]_sort__ sections).
     *
     * Returns an associative array with keys:
     *  'menuData' Menu items
     *  'sortData' Order data
     *
     * @param array $config Menu configuration
     *
     * @return array
     */
    protected function getMenuData($config)
    {
        $menuData = $sortDataOrder = $sortData = [];

        foreach ($config as $menuKey => $items) {
            if ($menuKey === 'Parent_Config') {
                continue;
            }

            if (!count($items)) {
                continue;
            }

            if (preg_match('/^__(.*)_sort__$/', $menuKey, $matches)) {
                // Sort section
                $menuKey = $matches[1];
                $items = $items->toArray();
                // Re-order menu-level sort entries in descending order
                asort($items);
                $sortData[$menuKey] = $items;

                if (isset($items['__MENU__'])) {
                    // Top-level menu position
                    $sortDataOrder[$items['__MENU__']] = $menuKey;
                }
                continue;
            }
            // Menu section
            $menuData[$menuKey] = $items;
        }

        // Re-order top-level sort entries in descending order
        $sortDataProcessed = [];
        ksort($sortDataOrder);

        foreach ($sortDataOrder as $index => $menuKey) {
            $sortDataProcessed[$menuKey] = $sortData[$menuKey];
            unset($sortData[$menuKey]);
        }
        $sortData = array_merge($sortDataProcessed, $sortData);

        return ['menuData' => $menuData, 'sortData' => $sortData];
    }

    /**
     * Sort menu items
     *
     * @param array $items Menu items
     * @param array $order Ordering
     *
     * @return array Sorted items
     */
    protected function sortMenuItems($items, $order)
    {
        foreach ($order as $menuKey => $order) {
            $menuPosition
                = $this->getItemIndex($items, $menuKey);
            if ($menuPosition === null) {
                continue;
            }
            if (isset($order['__MENU__'])) {
                // Re-position top-level menu
                $position = $order['__MENU__'];
                $items = $this->moveItem(
                    $items, $menuPosition, $position
                );
                $menuPosition = $position;
                unset($order['__MENU__']);
            }
            foreach ($order as $item => $position) {
                // Re-position single menu item
                if ($menuPosition === null) {
                    continue;
                }
                if (!isset($items[$menuPosition])) {
                    continue;
                }
                $currentPosition = $this->getItemIndex(
                    $items[$menuPosition]['items'], $item
                );
                if ($currentPosition === null) {
                    continue;
                }
                $items[$menuPosition]['items']
                    = $this->moveItem(
                        $items[$menuPosition]['items'],
                        $currentPosition, $position
                    );
            }
        }
        return $items;
    }

    /**
     * Get menu item index
     *
     * @param array  $items Menu items
     * @param string $id    Menu item id
     *
     * @return mixed null|int
     */
    protected function getItemIndex($items, $id)
    {
        $cnt = 0;
        foreach ($items as $item) {
            if ($item['id'] === $id) {
                return $cnt;
            }
            $cnt++;
        }
        return null;
    }

    /**
     * Move menu item
     *
     * @param array $items Menu items
     * @param int   $from  From (index)
     * @param int   $to    To (index)
     *
     * @return array Items
     */
    protected function moveItem($items, $from, $to)
    {
        if ($from < 0 || $to < 0) {
            return $items;
        }
        $move = array_splice($items, $from, 1);
        array_splice($items, $to, 0, $move);
        return $items;
    }
}
