<?php
/**
 * R2 record controller trait.
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
 * @package  Controller
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:controllers Wiki
 */
namespace Finna\Controller;

/**
 * R2 record controller trait.
 *
 * @category VuFind
 * @package  Controller
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:controllers Wiki
 */
trait R2RecordControllerTrait
{
    /**
     * Home (default) action -- forward to requested (or default) tab.
     *
     * @return mixed
     */
    public function homeAction()
    {
        $result = parent::homeAction();
        if ($this->driver instanceof \Finna\RecordDriver\R2Ead3Missing) {
            // Show customized record not found page with register prompt if
            // REMS application was closed during session.
            $this->flashMessenger()->addMessage('Cannot find record', 'error');

            $r2 = $this->getViewRenderer()->plugin('R2');
            $rems = $this->serviceLocator->get(\Finna\Service\RemsService::class);

            $view = $this->createViewModel()->setTemplate('r2record/missing.phtml');
            $view->hasAccess = $r2->hasUserAccess();

            $warning = null;
            if ($rems->isSearchLimitExceeded('daily')) {
                $warning = 'R2_daily_limit_exceeded';
            } elseif ($rems->isSearchLimitExceeded('monthly')) {
                $warning = 'R2_monthly_limit_exceeded';
            }
            $view->warning = $warning;

            return $view;
        }
        return $result;
    }
}
