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
 * This controller handles Title lists from ILS
 *
 * @category VuFind
 * @package  Controller
 * @author   Juha Luoma <juha.luoma@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development:plugins:controllers Wiki
 */
class TitleListController extends \VuFind\Controller\AbstractBase
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
     * Function to fetch results and display them for certain title list
     * type
     *
     * @return ViewModel
     */
    public function resultsAction()
    {
        $catalog = $this->getILS();
        $params = $this->getRequest()->getQuery()->toArray();
        $query = $params['query'] ?? 'mostloaned';
        $page = $params['page'] ?? 1;
        $sourceId = $params['id'] ?? '';
        $id = $sourceId . '.123';
        $source = $params['source'] ?? DEFAULT_SEARCH_BACKEND;
        $noSupport = false;
        if ($config = $catalog->checkFunction('getTitleList', ['id' => $id])) {
            // Lets see if config is within the limitations
            $pageSize = $config['page_size'] ?? 20;
            $pageSize = $pageSize > 100 ? 100 : $pageSize;
            // Paging from ils starts from 0 instead of 1
            $result = $catalog->getTitleList(
                [
                    'query' => $query,
                    'pageSize' => $pageSize,
                    'page' => $page,
                    'id' => $id
                ]
            );
            $pageOptions = $this->getPaginationHelper()->getOptions(
                $page,
                $this->params()->fromQuery('sort'),
                $pageSize,
                $config
            );

            $paginator = $this->getPaginationHelper()->getPaginator(
                $pageOptions, $result['count'], $result['records']
            );
            $pageStart = $paginator->getAbsoluteItemNumber(1) - 1;
            $pageEnd = $paginator->getAbsoluteItemNumber($pageOptions['limit']) - 1;

            $records = [];
            $html = '';
            $loader = $this->serviceLocator->get(\VuFind\Record\Loader::class);
            foreach ($result['records'] ?? [] as $key => $obj) {
                $loadedRecord = $loader->load($obj['id'], $source, true);
                $loadedRecord->setExtraDetail('ils_details', $obj);
                $records[] = $loadedRecord;
            }

            $ilsParams = $pageOptions['ilsParams'];
            $ilsParams['query'] = $query;
            $ilsParams['id'] = $sourceId;
        } else {
            $this->flashMessenger()->addErrorMessage('An error has occured');
            $noSupport = true;
        }

        $view = $this->createViewModel(
            compact('records', 'paginator', 'ilsParams', 'query', 'noSupport')
        );
        $view->setTemplate('titlelist/results.phtml');
        return $view;
    }
}
