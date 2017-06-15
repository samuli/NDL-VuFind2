<?php
/**
 * Helper class for system messages
 *
 * PHP version 5
 *
 * Copyright (C) The National Library of Finland 2015-2017.
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
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
namespace Finna\View\Helper\Root;

/**
 * Helper class for system messages
 *
 * @category VuFind
 * @package  View_Helpers
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
class SystemMessages extends \Zend\View\Helper\AbstractHelper
{
    /**
     * Configuration
     *
     * @var array
     */
    protected $config;

    /**
     * System configuration
     *
     * @var array
     */
    protected $systemConfig;

    /**
     * Constructor
     *
     * @param array $config       Configuration
     * @param array $systemConfig System configuration
     */
    public function __construct($config, $systemConfig)
    {
        $this->config = $config;
        $this->systemConfig = $systemConfig;
    }

    /**
     * Return any system messages (translatable).
     *
     * @return array
     */
    public function __invoke()
    {
        $messages = !empty($this->config->Site->systemMessages)
            ? $this->config->Site->systemMessages->toArray() : [];

        if (!empty($this->systemConfig->Site->systemMessages)) {
            $messages
                = array_merge(
                    $messages,
                    $this->systemConfig->Site->systemMessages->toArray()
                );
        }
        return $messages;
    }
}
