<?php
/**
 * Restricted Solr (R2) record Controller
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
 * @link     http://vufind.org   Main Site
 */
namespace Finna\Controller;

use VuFindSearch\ParamBag;

/**
 * Restricted Solr (R2) record Controller
 *
 * @category VuFind
 * @package  Controller
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
class R2recordController extends RecordController
{
    use \Finna\Controller\R2ControllerTrait;

    /**
     * Type of record to display
     *
     * @var string
     */
    protected $searchClassId = 'R2';

    /**
     * Create a new ViewModel.
     *
     * @param array $params Parameters to pass to ViewModel constructor.
     *
     * @return \Laminas\View\Model\ViewModel
     */
    protected function createViewModel($params = null)
    {
        $view = parent::createViewModel($params);
        $this->layout()->searchClassId = $view->searchClassId = $this->searchClassId;
        return $view;
    }

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
            return $this->createViewModel()->setTemplate('r2record/missing.phtml');
        }
        return $result;
    }

    /**
     * Load the record requested by the user; note that this is not done in the
     * init() method since we don't want to perform an expensive search twice
     * when homeAction() forwards to another method.
     *
     * @param ParamBag $params Search backend parameters
     * @param bool     $force  Set to true to force a reload of the record, even if
     * already loaded (useful if loading a record using different parameters)
     *
     * @return AbstractRecordDriver
     */
    protected function loadRecord(ParamBag $params = null, bool $force = false)
    {
        try {
            return parent::loadRecord($params, $force);
        } catch (\VuFind\Exception\RecordMissing $e) {
            $id = $this->params()->fromRoute('id', $this->params()->fromQuery('id'));
            $driver = $this->serviceLocator
                ->get(\VuFind\RecordDriver\PluginManager::class)
                ->get('r2ead3missing');
            $driver->setRawData(['id' => $id]);
            $this->driver = $driver;
            return $driver;
        }
    }
}
