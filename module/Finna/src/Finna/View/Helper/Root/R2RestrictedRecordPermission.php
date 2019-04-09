<?php
/**
 * Helper class for displaying REMS permission info and opening
 * REMS registration form.
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
 * @package  View_Helpers
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
namespace Finna\View\Helper\Root;

use Finna\RemsService\RemsService;

/**
 * Helper class for displaying REMS permission info and opening
 * REMS registration form.
 *
 * @category VuFind
 * @package  View_Helpers
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
class R2RestrictedRecordPermission extends \Zend\View\Helper\AbstractHelper
{
    protected $enabled;
    
    protected $rems;
    protected $authorized;

    /**
     * Constructor
     *
     * @param bool        $enabled    Is R2 enabled?
     * @param RemsService $rems       REMS service
     * @param bool        $authorized Is the user authorized to use REMS?
     */
    public function __construct(bool $enabled, RemsService $rems, bool $authorized
    ) {
        $this->enabled = $enabled;
        $this->rems = $rems;
        $this->authorized = $authorized;
    }

    /**
     * Render info box.
     *
     * @param RecordDriver $driver   Record driver
     * @param bool         $autoOpen Auto open registration form at page load.
     * @param User         $user     User
     *
     * @return null|html
     */
    public function __invoke($driver, $autoOpen = false, $user = null)
    {
        if (!$this->enabled || !$driver->hasRestrictedMetadata()) {
            return null;
        }

        $params = [
            'weakLogin' => $user && !$this->authorized,
            'user' => $user,
            'autoOpen' => $autoOpen,
            'id' => $driver->getUniqueID(),
            'collection' => $driver->isCollection()
        ];
        
        if ($user !== false) {
            $status = $this->rems->checkPermission(false);

            // TODO allow new submit if permission has been closed?
            $notSubmitted = in_array(
                $status,
                [null,
                 RemsService::STATUS_CLOSED,
                 RemsService::STATUS_NOT_SUBMITTED]
            );
            $params += [
                'status' => $status,
                'notSubmitted' => $notSubmitted,
                'callApi' => $status === null
             ];
        }
        
        return $this->getView()->render(
            'Helpers/R2RestrictedRecordPermission.phtml', $params
        );
    }
}
