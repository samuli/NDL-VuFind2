<?php
/**
 * Helper class for restricted Solr R2 search.
 *
 * PHP version 7
 *
 * Copyright (C) The National Library of Finland 2020.
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
 * @package  View_Helpers
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
namespace Finna\View\Helper\Root;

/**
 * Helper class for restricted Solr R2 search.
 *
 * @category VuFind
 * @package  View_Helpers
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
class R2 extends \Zend\View\Helper\AbstractHelper
{
    /**
     * Is R2 search enabled?
     *
     * @var bool
     */
    protected $enabled;

    /**
     * Is user authorized to use R2?
     *
     * @var bool
     */
    protected $authorized;

    /**
     * Is user registered to REMS during this session?
     *
     * @var bool
     */
    protected $registered;

    /**
     * Constructor
     *
     * @param bool $enabled    Is R2 enabled?
     * @param bool $authorized Is user suthorized to use R2?
     * @param bool $registered Is user registered to REMS during this session?
     */
    public function __construct(bool $enabled, bool $authorized, bool $registered)
    {
        $this->enabled = $enabled;
        $this->authorized = $authorized;
        $this->registered = $registered;
    }

    /**
     * Check if R2 is available
     *
     * @return bool
     */
    public function isAvailable()
    {
        return (bool)$this->enabled;
    }
    
    /**
     * Check if user is authorized to use R2.
     *
     * @return bool
     */
    public function isUserAuthorizedToUseR2()
    {
        return $this->authorized;
    }

    /**
     * Check if user is registered to REMS during this session.
     *
     * @return bool
     */
    public function isRegistered()
    {
        return $this->registered;
    }
}
