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
     * AJAX tab action -- render a tab without surrounding context.
     *
     * @return mixed
     */
    public function ajaxtabAction()
    {
        // Init a restricted record driver for RecordTabs
        $this->driver = $this->loadRecordWithRestrictedData();
        return parent::ajaxtabAction();
    }

    /**
     * Support method to load tab information from the RecordTab PluginManager.
     *
     * @return void
     */
    protected function loadTabDetails()
    {
        // Init a restricted record driver for RecordTabs
        $this->driver = $driver = $this->loadRecordWithRestrictedData();
        $request = $this->getRequest();
        $manager = $this->getRecordTabManager();
        $details = $manager
            ->getTabDetailsForRecord($driver, $request, $this->fallbackDefaultTab);
        $this->allTabs = $details['tabs'];
        $this->defaultTab = $details['default'] ? $details['default'] : false;
        $this->backgroundTabs = $manager->getBackgroundTabNames($driver);
    }
}
