<?php
/**
 * Get "RecordDriverRelatedRecords" AJAX handler
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
 * @package  AJAX
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
namespace Finna\AjaxHandler;

use VuFind\Record\Loader;
use VuFind\Search\SearchRunner;
use VuFind\Session\Settings as SessionSettings;
use Zend\Mvc\Controller\Plugin\Params;
use Zend\View\Renderer\RendererInterface;

/**
 * Get "RecordDriverRelatedRecords" AJAX handler
 *
 * @category VuFind
 * @package  AJAX
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class GetRecordDriverRelatedRecords extends \VuFind\AjaxHandler\AbstractBase
{
    /**
     * Record loader
     *
     * @var Loader
     */
    protected $recordLoader;

    /**
     * SearchRunner
     *
     * @var SearchRunner
     */
    protected $searchRunner;

    /**
     * View renderer
     *
     * @var RendererInterface
     */
    protected $renderer;

    /**
     * Constructor
     *
     * @param SessionSettings   $ss           Session settings
     * @param Loader            $loader       Record loader
     * @param SearchRunner      $searchRunner Search runner
     * @param RendererInterface $renderer     View renderer
     */
    public function __construct(
        SessionSettings $ss, Loader $loader, SearchRunner $searchRunner,
        RendererInterface $renderer
    ) {
        $this->sessionSettings = $ss;
        $this->recordLoader = $loader;
        $this->searchRunner = $searchRunner;
        $this->renderer = $renderer;
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
        $this->disableSessionWrites();  // avoid session write timing bug

        $id = $params->fromPost('id', $params->fromQuery('id'));

        if (empty($id)) {
            return $this->formatResponse('', self::STATUS_HTTP_BAD_REQUEST);
        }

        $source = $params->fromPost(
            'source',
            $params->fromQuery('source', DEFAULT_SEARCH_BACKEND)
        );
        $driver = $this->recordLoader->load($id, $source);

        $html = '';
        if ($related = $driver->getRelatedRecords()) {
            $records = [];
            foreach ($related as $type => $ids) {
                $records[$type] = [];

                foreach ($ids as &$id) {
                    if (is_string($id)) {
                        try {
                            $records[$type][]
                                = $this->recordLoader->load($id, $source);
                        } catch (\Exception $e) {
                            // Ignore missing record
                        }
                    } elseif ($id = ($id['wildcard'] ?? null)) {
                        $results = $this->searchRunner->run(
                            ['lookfor' => 'id:' . addcslashes($id, '"')],
                            $source,
                            function ($runner, $params, $searchId) use ($driver) {
                                $params->setLimit(1);
                                $params->setPage(1);
                                $params->resetFacetConfig();
                                $options = $params->getOptions();
                                $options->disableHighlighting();
                                $options->spellcheckEnabled(false);
                            }
                        );
                        if (!$results instanceof \VuFind\Search\EmptySet\Results
                            && count($results->getResults())
                        ) {
                            $results = $results->getResults();
                            $records[$type][] = reset($results);
                        }
                    }
                }
            }
            if ($records) {
                $html = $this->renderer->partial(
                    'Related/RecordDriverRelatedRecordList.phtml',
                    ['results' => $records]
                );
            }
        }

        return $this->formatResponse(compact('html'));
    }
}
