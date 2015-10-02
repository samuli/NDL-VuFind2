<?php
/**
 * Primo Central Controller
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
 * @package  Controller
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
namespace Finna\Controller;
use VuFindSearch\ParamBag as ParamBag,
    Zend\Session\Container as SessionContainer;

/**
 * Primo Central Controller
 *
 * @category VuFind2
 * @package  Controller
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
class MetalibController extends \VuFind\Controller\AbstractSearch
{
    use SearchControllerTrait;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->searchClassId = 'Metalib';
        parent::__construct();
    }

    /**
     * Home action
     *
     * @return mixed
     */
    public function homeAction()
    {
        return $this->createViewModel();
    }

    /**
     * Search action -- call standard results action
     *
     * @return mixed
     */
    public function searchAction()
    {  
        if ($this->getRequest()->getQuery()->get('ajax')) {
            $view = parent::resultsAction();
        } else {
            $configLoader = $this->getServiceLocator()->get('VuFind\Config');
            $options = new \Finna\Search\Metalib\Options($configLoader);
            $params = new \Finna\Search\Metalib\Params($options, $configLoader);
            $params->initFromRequest($this->getRequest()->getQuery());
            $params->setIrds($this->getCurrentMetalibIrds());

            $results = new \Finna\Search\Metalib\Results($params);
            $results 
                = \Finna\Search\Results\Factory::initUrlQueryHelper(
                    $results, $this->getServiceLocator()
                );

            $view = $this->createViewModel();
            $view->qs = $this->getRequest()->getUriString();
            $view->params = $params;
            $view->results = $results;
            $view->disablePiwik = true;

            $allowedSets = $this->getMetalibSets();
            $sets = [];
            foreach ($allowedSets as $key => $set) {
                $sets[$key] = $set['name'];
            }
            $view->sets = $sets;
            list($isIrd, $set) = $this->getCurrentMetalibSet();

            $view->currentSet = $set;

            $session = new SessionContainer('Metalib');
            if ($isIrd) {
                //unset($session->recentSets);
                //die();

                $metalib = $this->getServiceLocator()->get('VuFind\Search');

                $backendParams = new ParamBag();
                $backendParams->add('irdInfo', explode(',', substr($set, 5)));
                $result = $metalib->search('Metalib', $params->getQuery(), false, false, $backendParams);
                $name = $result->getIRDInfo();
                if (!$name) {
                    $name = $set;
                }
                //die("res: " . var_export($result, true));

                if (!isset($session->recentSets)) {
                    $session->recentSets = [];
                }
                $session->recentSets[$set] = $isIrd ? $name : $sets[$set];
            }

            $view->recentSets 
                = isset($session->recentSets) ? $session->recentSets : [];
        }

        $this->initSavedTabs();

        return $view;
    }
}
