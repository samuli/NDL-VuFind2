<?php
/**
 * Organisation page component view helper
 *
 * PHP version 5
 *
 * Copyright (C) The National Library of Finland 2016.
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
 * Organisation page component view helper
 *
 * @category VuFind
 * @package  View_Helpers
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
class OrganisationPage extends \Zend\View\Helper\AbstractHelper
{
    /**
     * Configuration
     *
     * @var \Zend\Config\Config
     */
    protected $config;

    /**
     * Constructor
     *
     * @param Zend\Config\Config $config Configuration
     */
    public function __construct(\Zend\Config\Config $config)
    {
        $this->config = $config;
    }

    /**
     * Returns HTML for embedding organisation page
     *
     * @param string $id Organisation Id
     *
     * @return mixed null|string
     */
    public function __invoke($id = null)
    {
        if (!$this->config->General->enabled) {
            return;
        }

        if (!$id) {
            if (!isset($this->config->General->defaultOrganisation)) {
                return;
            }
            $id = $this->config->General->defaultOrganisation;
        }

        $mapWidget = 'openlayers';
        if (isset($this->config->General->mapWidget)) {
            $widget = $this->config->General->mapWidget;
            if (in_array($widget, ['google', 'openlayers'])) {
                $mapWidget = $widget;
            }
        }
        $googleAPIKey = isset($this->config->General->googleAPIKey)
            ? $this->config->General->googleAPIKey : null;

        $consortiumInfo = isset($this->config->General->consortiumInfo)
            ? $this->config->General->consortiumInfo : false;

        $params = [
            'id' => $id,
            'consortiumInfo' => $consortiumInfo,
            'mapWidget' => $mapWidget,
            'googleAPIKey' => $googleAPIKey
        ];
        if (isset($this->config[$id]['default'])) {
            $params['library'] = $this->config[$id]['default'];
        }

        return $this->getView()->render(
            'Helpers/organisation-page.phtml', $params
        );
    }
}
