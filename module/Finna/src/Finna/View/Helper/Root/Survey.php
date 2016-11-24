<?php
/**
 * Finna survey view helper
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
 * Finna survey view helper
 *
 * @category VuFind
 * @package  View_Helpers
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
class Survey extends \Zend\View\Helper\AbstractHelper
{
    /**
     * Configuration
     *
     * @var array
     */
    protected $config;

    /**
     * Constructor
     *
     * @param array $config Configuration
     */
    public function __construct($config)
    {
        $this->config = $config;
    }

    /**
     * Render survey.
     *
     * @return string
     */
    public function render()
    {
        return $this->getView()->render(
            'Helpers/survey.phtml',
            ['url' => $this->config->Survey->url]
        );
    }

    /**
     * Check if survey is enabled
     *
     * @return bool
     */
    public function isEnabled()
    {
        return isset($this->config->Survey->enabled)
            && $this->config->Survey->enabled
            && !empty($this->config->Survey->url);
    }
}
