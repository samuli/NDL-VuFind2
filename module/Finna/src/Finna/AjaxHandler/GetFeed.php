<?php
/**
 * GetFeed AJAX handler
 *
 * PHP version 7
 *
 * Copyright (C) The National Library of Finland 2015-2018.
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
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @author   Konsta Raunio <konsta.raunio@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
namespace Finna\AjaxHandler;

use Finna\Feed\Feed as FeedService;
use Laminas\Config\Config;
use Laminas\Feed\Writer\Feed;
use Laminas\Mvc\Controller\Plugin\Params;
use Laminas\Mvc\Controller\Plugin\Url;
use Laminas\View\Renderer\RendererInterface;
use Vufind\ILS\Connection;
use VuFind\Record\Loader;
use VuFind\Session\Settings as SessionSettings;

/**
 * GetFeed AJAX handler
 *
 * @category VuFind
 * @package  AJAX
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @author   Ere Maijala <ere.maijala@helsinki.fi>
 * @author   Konsta Raunio <konsta.raunio@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     https://vufind.org/wiki/development Wiki
 */
class GetFeed extends \VuFind\AjaxHandler\AbstractBase
{
    use FeedTrait;

    /**
     * RSS configuration
     *
     * @var Config
     */
    protected $config;

    /**
     * Feed service
     *
     * @var FeedService
     */
    protected $feedService;

    protected $ils;
    protected $recordLoader;
    
    /**
     * View renderer
     *
     * @var RendererInterface
     */
    protected $renderer;

    /**
     * URL helper
     *
     * @var Url
     */
    protected $url;

    /**
     * Constructor
     *
     * @param SessionSettings   $ss       Session settings
     * @param Config            $config   RSS configuration
     * @param FeedService       $fs       Feed service
     * @param RendererInterface $renderer View renderer
     * @param Url               $url      URL helper
     */
    public function __construct(SessionSettings $ss, Config $config,
        FeedService $fs, Loader $recordLoader, Connection $ils, RendererInterface $renderer, Url $url
    ) {
        $this->sessionSettings = $ss;
        $this->config = $config;
        $this->feedService = $fs;
        $this->recordLoader = $recordLoader;
        $this->ils = $ils;
        $this->renderer = $renderer;
        $this->url = $url;
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
        if (!$id) {
            return $this->formatResponse('', self::STATUS_HTTP_BAD_REQUEST);
        }

        $touchDevice = $params->fromQuery('touch-device') === '1';

        try {
            $serverHelper = $this->renderer->plugin('serverurl');
            $homeUrl = $serverHelper($this->url->fromRoute('home'));
            
            if ($config = $this->feedService->getFeedConfig($id)) {
                $config = $config['result'];
            }

            if (null === ($ilsList = ($config['ilsList'] ?? null))) {
                // Normal feed
                $feed = $this->feedService->readFeed($id, $homeUrl);
            } else {
                // ILS list to be converted to a feed

                
                // TODO: read from params
                $query = $ilsList ?? 'new';
                $amount = 20;
                $type = 'carousel';
                $source = 'Solr';
                $ilsId = $config['ilsId'];

                // Create a fake patron id so ILS driver can be properly acquired

                // TODO: remove hard-coded
                $sourceId = 'satakirjastot';
                $patronId = !empty($sourceId) ? $sourceId . '.123' : '';
                $amount = $amount > 20 ? 20 : $amount;
                
                $result = $this->ils->checkFunction('getTitleList', ['id' => $patronId]);
                if (!$result) {
                    return $this->formatResponse('Missing configurations', 501);
                }
                
                $records = [];
                $data = $this->ils->getTitleList(
                    ['query' => $query, 'pageSize' => $amount, 'id' => $id]
                );

                //die(var_export($data['records'], true));
                
                foreach ($data['records'] ?? [] as $key => $obj) {
                    $loadedRecord = $this->recordLoader->load($ilsId . '.' . $obj['id'], $source, true);
                    $loadedRecord->setExtraDetail('ils_details', $obj);
                    $records[] = $loadedRecord;
                }

                // Create feed
                
                $serverUrl = $this->renderer->plugin('serverUrl');
                $recordHelper = $this->renderer->plugin('record');
                $recordImage = $this->renderer->plugin('recordImage');


                $feed = new Feed;
                $feed->setTitle($query);
                $feed->setLink('https://finna.fi');
                $feed->setDateModified(time());
                $feed->setId(' ');
                $feed->setDescription(' ');
                foreach ($records as $rec) {
                    $entry = $feed->createEntry();
                    $entry->setTitle($rec->getTitle());
                    $entry->setDateModified(time());
                    $entry->setDateCreated(time());
                    $entry->setId($rec->getUniqueID());
                    $entry->setLink('https://finna.fi'); // TODO proper link

                    // TODO: maybe not needed?
                    $summaries = array_filter($rec->tryMethod('getSummary'));
                    if (!empty($summaries)) {
                        $entry->setDescription(implode(' -- ', $summaries));

                    }

                    // TODO: tähän uusi sisältö, mahdollisesti htmlnä?
                    $entry->setContent('lorem ipsum');                    
                    

                    $imageUrl = $recordImage($recordHelper($rec))->getLargeImage()
                        . '&w=1024&h=1024&imgext=.jpeg';
                    $entry->setEnclosure(
                        [
                            'uri' => $serverUrl($imageUrl),
                            'type' => 'image/jpeg',
                            'length' => 0
                         ]
                    );
        
                    $feed->addEntry($entry);
                }

                $feed = $feed->export('rss', false);

                // TODO: check if feed could be passed to FeedService without export/import via string
                $feed = \Laminas\Feed\Reader\Reader::importString($feed);

                $config = $this->feedService->getFeedConfig($id);
                $feed = $this->feedService->parseFeed($feed, $config['result']);
            }
        } catch (\Exception $e) {
            return $this->formatResponse($e->getMessage(), self::STATUS_HTTP_ERROR);
        }

        if (!$feed) {
            return $this->formatResponse(
                'Error reading feed', self::STATUS_HTTP_ERROR
            );
        }

        return $this->formatResponse(
            $this->formatFeed(
                $feed,
                $this->config,
                $this->renderer,
                false,
                $touchDevice
            )
        );
    }
}
