<?php
/**
 * Get dynamic list AJAX handler
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
 * @package  AJAX
 * @author   Juha Luoma <juha.luoma@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
namespace Finna\AjaxHandler;

use VuFind\Record\Loader;
use Vufind\ILS\Connection;
use Zend\View\Renderer\RendererInterface;
use Zend\Mvc\Controller\Plugin\Params;

/**
 * DynamicList AJAX handler
 *
 * @category VuFind
 * @package  AJAX
 * @author   Juha Luoma <juha.luoma@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class DynamicList extends \VuFind\AjaxHandler\AbstractBase
{
    /**
     * Record loader
     * 
     * @var Loader
     */
    protected $recordLoader;

    /**
     * Connection to ils
     * 
     * @var Connection
     */
    protected $ils;

    /**
     * View renderer
     *
     * @var RendererInterface
     */
    protected $renderer;

    /**
     * Constructor
     *
     * @param Loader            $loader    Record loader
     * @param Connection        $ils       Connection to the ils
     * @param RendererInterface $renderer  View renderer
     */
    public function __construct(Loader $loader, Connection $ils, RendererInterface $renderer) {
        $this->recordLoader = $loader;
        $this->renderer = $renderer;
        $this->ils = $ils;
    }

    /**
     * Handle a request.
     *
     * @param Params $params Parameter helper from controller
     *
     * @return array [response data, HTTP status code]
     */
    public function handleRequest(Params $params)
    {
        $type = $params->fromQuery('query', 'mostloaned');
        $amount = $params->fromQuery('amount', 10);
        $template = $params->fromQuery('template', 'carousel');
        $source = $params->fromQuery('source',  DEFAULT_SEARCH_BACKEND);
        $amount = $amount > 20 ? 20 : $amount;

        $result = $this->ils->checkFunction('getDynamicList', []);
        $records = [];
        $statusCode = 200;
        $html = '';
        if ($result) {
            $data = $this->ils->getDynamicList(['query' => $type, 'pageSize' => $amount]);
            foreach ($data['records'] ?? [] as $key => $obj) {
                $loadedRecord = $this->recordLoader->load($obj['id'], $source, true);
                $loadedRecord->setExtraDetail('ils_details', $obj);
                $records[] = $loadedRecord;
            }
            $html = $this->renderer->partial("ajax/dynamic-list-$template.phtml", compact('records', 'type'));
        } else {
            $html = "Could not load dynamic list $type";
            $statusCode = 500;
        }
        
        return $this->formatResponse(compact('html'), $statusCode);
    }
}