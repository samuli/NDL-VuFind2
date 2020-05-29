<?php
/**
 * Dynamic List Controller Module
 *
 * PHP version 7
 *
 * Copyright (C) The National Library of Finland 2015-2020.
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
 * @author   Juha Luoma <juha.luoma@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:controllers Wiki
 */
namespace Finna\Controller;

use VuFind\ILS\PaginationHelper;

/**
 * This controller handles Dynamic lists from ILS
 *
 * @category VuFind
 * @package  Controller
 * @author   Juha Luoma <juha.luoma@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:controllers Wiki
 */
class DynamicListController extends \VuFind\Controller\AbstractBase
{
    /**
     * ILS Pagination Helper
     *
     * @var PaginationHelper
     */
    protected $paginationHelper = null;

    /**
     * Get the ILS pagination helper
     *
     * @return PaginationHelper
     */
    protected function getPaginationHelper()
    {
        if (null === $this->paginationHelper) {
            $this->paginationHelper = new PaginationHelper();
        }
        return $this->paginationHelper;
    }

    /**
     * Function to fetch results and display them for certain dynamic list
     * type
     *
     * @return ViewModel
     */
    public function resultsAction()
    {
        $catalog = $this->getILS();
        $params = $this->getRequest()->getQuery()->toArray();
        $type = $params['query'] ?? 'mostloaned';
        $page = $params['page'] ?? 1;
        $source = $params['source'] ?? DEFAULT_SEARCH_BACKEND;

        $check = $catalog->checkFunction('getDynamicList', []);
        // Limit the amount of records to 20, for not too large requests
        $result = $catalog->getDynamicList(['query' => $type, 'pageSize' => 20, 'page' => $page - 1]);
        $pageOptions = $this->getPaginationHelper()->getOptions(
            $page,
            $this->params()->fromQuery('sort'),
            20,
            $check
        );
        // Build paginator if needed:
        $paginator = $this->getPaginationHelper()->getPaginator(
            $pageOptions, $result['count'], $result['records']
        );
        if ($paginator) {
            $pageStart = $paginator->getAbsoluteItemNumber(1) - 1;
            $pageEnd = $paginator->getAbsoluteItemNumber($pageOptions['limit']) - 1;
        } else {
            $pageStart = 0;
            $pageEnd = $result['count'];
        }

        $records = [];
        $html = '';
        if ($check) {
            $loader = $this->serviceLocator->get(\VuFind\Record\Loader::class);
            foreach ($result['records'] ?? [] as $key => $obj) {
                $loadedRecord = $loader->load($obj['id'], $source, true);
                $loadedRecord->setExtraDetail('ils_details', $obj);
                $records[] = $loadedRecord;
            }
        }
        $ilsParams = $pageOptions['ilsParams'];
        $ilsParams['query'] = $type;
        $view = $this->createViewModel(compact('records', 'paginator', 'ilsParams', 'type'));
        $view->setTemplate('dynamiclist/results.phtml');
        return $view;
    }
}
