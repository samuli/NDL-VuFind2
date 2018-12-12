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

use VuFind\Record\Loader;
use VuFind\Session\Settings as SessionSettings;

use VuFind\I18n\Translator\TranslatorAwareInterface;

use Zend\Config\Config;
use Zend\Mvc\Controller\Plugin\Params;
use Zend\Mvc\Controller\Plugin\Url;
use Zend\View\Model\JsonModel;
use Zend\View\Renderer\RendererInterface;

//use wapmorgan\Mp3Info\Mp3Info;
//use getid3\getID3;

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
class GetIIIFManifest extends \VuFind\AjaxHandler\AbstractBase
    implements TranslatorAwareInterface
{
    use \VuFind\I18n\Translator\TranslatorAwareTrait;

    /**
     * Record loader
     *
     * @var Loader
     */
    protected $recordLoader;

    /**
     * Similar record handler
     *
     * @var Similar
     */
    protected $similar;

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
    public function __construct(SessionSettings $ss, Loader $loader, RendererInterface $renderer, Url $url) {
        $this->sessionSettings = $ss;
        $this->loader = $loader;
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

        $driver = $this->loader->load($id, 'Solr');

        $data = [
            'id' => $id,
            'type' => 'Manifest'
        ];

        $serverHelper = $this->renderer->plugin('serverurl');

        $datasource = $driver->getDataSource();
        
        $urls = $driver->getOnlineUrls($true);
        if (empty($urls)) {
            foreach ($driver->getURLs() as $url) {
                $found = false;
                foreach ($urls as $u) {
                    if ($u['url'] === $url['url']) {
                        $found = $true;
                        break;
                    }
                }
                if (!$found) {
                    $urls[] = $url;
                }
            }
        }

        $filterUrls = function($urls, $extensions) {
            $newUrls = [];
            foreach ($urls as $url) {
                if (preg_match('/^http(s)?:\/\/.*\.(' . implode('|', $extensions) . ')$/', $url['url'], $match)) {
                    $newUrls[] = $url;
                }
            }
            return $newUrls;
        };
        
        $annotateUrls = function ($urls, $type, $format = null) {
            return array_map(function ($url) use ($type, $format) {
                    $data = ['type' => $type, 'url' => $url['url']];
                    if ($format) {
                        $data['format'] = $format;
                    }
                    return $data;
            }, $urls);
        };

        $images = $driver->getAllImages();
        $images = $annotateUrls($images, 'Image', 'image/jpeg');
        
        $pdfs = $filterUrls($urls, ['pdf']);
        $pdfs = array_filter(
            $pdfs,
            function ($url) use ($datasource) {
                return $url['source'] === $datasource;
            }
        );
        $pdfs = $annotateUrls($pdfs, 'Pdf', 'application/pdf');

        $audio = $filterUrls($urls, ['mp3']);
        $audio = $annotateUrls($audio, 'Sound', 'audio/mp3');

        
        $urls = array_merge($images, $pdfs, $audio);
        
        //echo var_export($images, true);
        //die();

        $itemData = [];
        
        $cnt = 0;
        foreach ($urls as $urlData) {
            //echo var_export($urlData, true);
            
            $type = $urlData['type'];
            $format = $urlData['format'] ?? null;

            $thumbnail = null;
            $url = $itemUrl = $urlData['url'];
            switch($type) {
            case 'Image':
                $coverUrl = $serverHelper($this->url->fromRoute('cover-show'));
                $url = $coverUrl . "?id=${id}&index=${cnt}";
                $itemUrl = "$url&size=large";
                $thumbnail = "$url&size=small&rnd=" . md5(rand());
                break;
                
            case 'Pdf':
                $base = $serverHelper($this->url->fromRoute('record-resource', ['id' => $id]));
                $itemUrl = "${base}?url=" . urlencode($url);
                
                break;
            case 'Audio':
            case 'Sound':
                // $url = $itemUrl = 'http://files.blokdust.io/video/story-session1.mp4';
                // $type = 'Video';
                // $format = 'video/mp4';
            }

            $body = [
                'id' => $itemUrl,
                'type' => $type,
                'format' => $format,
                'label' => [
                    '@none' => [
                        $driver->getTitle()
                    ]
                ]
            ];
            if ($format) {
                $body['format'] = $format;
            }

            
            $result = [
                'id' => $url,
                'type' => 'Canvas',
                'items' => [
                    [
                        'id' => $url . "/annotationpage/$cnt",
                        'type' => 'AnnotationPage',
                        'items' => [
                           [
                               'id' => $id . "/annotation/$cnt",
                               'type' => 'Annotation',
                               'motivation' => 'painting',
                               'body' => $body,
                               'target' => $url
                           ]
                        ]
                    ]
                ],
                'metadata' => $this->getMetadata($driver)
            ];

            // TODO
            if ($type === 'Sound' || $type === 'Video') {
                /*
                if ($fp2 = fopen($tmp, 'wb')) {
                    $block = fread($fp, 32*1024);
                    die("block");
                    fwrite($fp2, $block);
                }
                fclose($fp);
                */
                /*
                $getID3 = new getID3;
                $info = $getID3->analyze($tmp);
                $result['duration'] = $info['playtime_string'];
                */
                
                /*
                $tmp = tempnam(sys_get_temp_dir(), 'mp3');
                $audioData = file_get_contents($url);
                file_put_contents($tmp, $audioData);
                
                $audio = new Mp3Info($tmp);
                $result['duration'] = $audio->duration;

                unlink($tmp);
                */

                $result['duration'] = 300;
            }
            
            if ($thumbnail) {
                $result['thumbnail'] = [
                   [
                       'id' => $thumbnail,
                       'type' => $type
                   ]
                ];
            }

            $itemData[] = $result;
            $cnt++;
        }
        //die();
        
        $data['items'] = $itemData;

        $data = json_encode($data);
        $data = str_replace("\\/", "/", $data);
        echo $data;
        die();
    }

    protected function getMetadata($driver)
    {
        $fields = [
            'Title' => 'getTitle',
            'Author' => 'getPrimaryAuthor',
            'Year' => 'getYear',
            'Description' => 'getDescription',
            'Summary' => 'getSummary',
            'Format' => 'getFormats',
            'License' => 'getUsageRights'

        ];
        $metadata = [];
        foreach ($fields as $field => $method) {
            if (! method_exists($driver, $method)) {
                continue;
            }
            
            $val = $driver->$method();
            if ($val) {
                $val = is_array($val) ? implode(', ', array_map(function ($v) { return $this->translate($v); }, $val)) : $this->translate($val);
                $metadata[] = ['label' => $this->translate($field), 'value' => $val];
            }
        }
        return $metadata;
                   
        
            
    }
}
