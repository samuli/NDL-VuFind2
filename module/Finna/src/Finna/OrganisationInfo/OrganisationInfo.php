<?php
/**
 * Service for querying Kirjastohakemisto library database.
 * See: https://api.kirjastot.fi/
 *
 * PHP version 5
 *
 * Copyright (C) The National Library of Finland 2016.
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
 * @category VuFind
 * @package  Content
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
namespace Finna\OrganisationInfo;
use Zend\Config\Config;

/**
 * Service for querying Kirjastohakemisto library database.
 *
 * @category VuFind
 * @package  Content
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
class OrganisationInfo implements \Zend\Log\LoggerAwareInterface
{
    use \VuFind\Log\LoggerAwareTrait;

    /**
     * Organisation configuration.
     *
     * @var Zend\Config\Config
     */
    protected $config = null;

    /**
     * Configuration.
     *
     * @var Zend\Config\Config
     */
    protected $mainConfig = null;

    /**
     * Cache manager
     *
     * @var VuFind\CacheManager
     */
    protected $cacheManager;

    /**
     * View Renderer
     *
     * @var VuFind\CacheManager
     */
    protected $viewRenderer;

    /**
     * HTTP service
     *
     * @var VuFind\Http
     */
    protected $http;

    /**
     * Constructor.
     *
     * @param Zend\Config\Config             $config       Configuration
     * @param VuFind\CacheManager            $cacheManager Cache manager
     * @param VuFind\Http                    $http         HTTP service
     * @param Zend\View\Renderer\PhpRenderer $viewRenderer View renderer
     */
    public function __construct($config, $cacheManager, $http, $viewRenderer)
    {
        $this->mainConfig = $config;
        $this->cacheManager = $cacheManager;
        $this->viewRenderer = $viewRenderer;
        $this->http = $http;
    }

    /**
     * Perform query.
     *
     * @param string $parent Parent organisation
     * @param array  $params Query parameters
     *
     * @return mixed array of results or false on error.
     */
    public function query($parent, $params, $language)
    {
        $id = null;
        if (isset($params['id'])) {
            $id = $params['id'];
        }


        if (!isset($this->mainConfig[$parent])) {
            $this->logError("Missing configuration ($parent)");
            return false;
        }

        if (!$this->config) {
            $this->config = array_merge(
                $this->mainConfig->General->toArray(),
                $this->mainConfig[$parent]->toArray()
            );
        }

        if (!$this->config['enabled']) {
            $this->logError("Organisation info disabled ($parent)");
            return false;
        }

        if (!isset($this->config['url'])) {
            $this->logError(
                "URL missing from organisation info configuration"
                . "($parent)"
            );
            return false;
        }

        $target = isset($params['target']) ? $params['target'] : 'widget';
        $action = isset($params['action']) ? $params['action'] : 'list';
        $search = ($action == 'search' && !empty($params['query'])) 
            ? $params['query'] : null;

        $parentType = null;
        if (isset($this->config['consortium'])) {
            $parentType = 'consortium';
        } else if (isset($this->config['parent'])) {
            $parentType = 'parent';
        }

        if (!$parentType) {
            $this->logError(
                "Missing consortium/parent from organisation info configuration"
                . "($parent)"
            );
            return false;
        }

        $parentId = $this->config[$parentType];
        $id = null;
        if (isset($params['id'])) {
            $id = $params['id'];
        } else if (isset($this->config['default'])) {
            $id = $this->config['default'];
        }

        $this->logError("id: $id");

        $now = false;
        if (isset($params['periodStart'])) {
            $now = strtotime($params['periodStart']);
            if ($now === false) {
                $this->logError(
                    'Error parsing periodStart: ' . $params['periodStart']
                );
            }
        }
        if ($now === false) {
            $now = time();
        }

        $weekDay = date('N', $now);
        $startDate = $weekDay == 1
            ? $now : strtotime('last monday', $now);

        $endDate = $weekDay == 7
            ? $now : strtotime('next sunday', $now);

        if ($action == 'details'
            && isset($params['dir']) && in_array($params['dir'], ['1', '-1'])
        ) {
            $dir = $params['dir'];
            $startDate = strtotime("{$dir} Week", $startDate);
            $endDate = strtotime("{$dir} Week", $endDate);
        }
        
        $allServices = !empty($params['allServices']);

        $weekNum = date('W', $startDate);
        $startDate = date('Y-m-d', $startDate);
        $endDate = date('Y-m-d', $endDate);

        $url = $this->config['url'];


        if ($action == 'list') {
            $url .= '/library';
            $params = [
                'lang' => $language,
                $parentType => $parentId,
                'with' => 'schedules',
                'period.start' => 'today',
                'period.end' => 'today'
            ];
            
            if ($search) {
                $params['name'] = $search;
            }

            $response = $this->fetchData($url, $params);
            if (!$response) {
                $this->logError("Error reading organisation list (url: $url)");
                return false;
            }

            $result = ['id' => $id];
            $result['list'] = $this->parseList($target, $response);
            $result['weekNum'] = $weekNum;
            
            return $result;

        } else if ($action == 'details') {
            if (!$id) {
                $this->logError("Missing id");
                return false;
            }

            $url .= "/library/$id";

            $with = 'schedules';
            if (!empty($params['fullDetails'])) {
                $with .= ',extra,phone_numbers,pictures,links,services';
            }

            $params = [
                'lang' => $language,
                'with' => $with,
                'period.start' => $startDate,
                'period.end' => $endDate
            ];

            $response = $this->fetchData($url, $params);
            if (!$response) {
                $this->logError("Error reading organisation list (url: $url)");
                return false;
            }

            $result = $this->parseDetails($target, $response, $allServices);

            $result['id'] = $id;
            $result['periodStart'] = $startDate;
            $result['weekNum'] = $weekNum;

            return $result;

        } else if ($action == 'search') {
            $url .= '/library';
            $params = [
                'lang' => $language,
                $parentType => $parentId,
                'with' => 'schedules',
                'period.start' => 'today',
                'period.end' => 'today',
                'sort' => 'name'
            ];
            
            if ($search) {
                $params['name'] = $search;
            }

            $response = $this->fetchData($url, $params);
            if (!$response) {
                $this->logError("Error reading organisation list (url: $url)");
                return false;
            }
            
            $result = [];
            foreach ($response['items'] as $item) {
                $result[] = [
                    'id' => $item['id'],
                    'name' => $item['name'],
                ];
            }

            return $result;
        }
        
        $this->logError("Unknown action: $action");
        return false;
    }

    /**
     * Fetch data from cache or external API.
     *
     * @param string $url URL
     *
     * @return mixed result or false on error.
     */
    protected function fetchData($url, $params)
    {
        //$params['lang'] = 'fi';
        $params['limit'] = 1000;

        $url .= '?' . http_build_query($params);

        error_log("fetch: $url");

        $cacheDir = $this->cacheManager->getCache('organisation-info')
            ->getOptions()->getCacheDir();

        $localFile = "$cacheDir/" . md5($url) . '.json';
        $maxAge = isset($this->config['cachetime'])
            ? $this->config['cachetime'] : 10;

        $response = false;
        if ($maxAge) {
            if (is_readable($localFile)
                && time() - filemtime($localFile) < $maxAge * 60
            ) {
                $response = file_get_contents($localFile);
            }
        }
        if (!$response) {
            $client = $this->http->createClient($url);
            $result = $client->setMethod('GET')->send();
            if ($result->isSuccess()) {
                if ($result->getStatusCode() != 200) {
                    $this->logError(
                        'Error querying organisation info, response code '
                        . $result->getStatusCode() . ", url: $url"
                    );
                    return false;
                }
            } else {
                $this->logError(
                    'Error querying organisation info: '
                    . $result->getStatusCode() . ': ' . $result->getReasonPhrase()
                    . ", url: $url"
                );
                return false;
            }

            $response = $result->getBody();
            file_put_contents($localFile, $response);
        }

        if (!$response) {
            return false;
        }

        $response = json_decode($response, true);
        $jsonError = json_last_error();
        if ($jsonError !== JSON_ERROR_NONE) {
            $this->logError("Error decoding JSON: $jsonError (url: $url)");
            return false;
        }

        return $response;
    }

    /**
     * Parse organisation list.
     *
     * @param object $response JSON-object
     *
     * @return array
     */
    protected function parseList($target, $response)
    {
        $mapUrls = ['routeUrl', 'mapUrl'];
        $mapUrlConf = [];
        foreach ($mapUrls as $url) {
            if (isset($this->config[$url])) {
                $base = $this->config[$url];
                if (preg_match_all('/{([^}]*)}/', $base, $matches)) {
                    $params = $matches[1];
                }
                $conf = ['base' => $base];
                if ($params) {
                    $conf['params'] = $params;
                }
                $mapUrlConf[$url] = $conf;
            }
        }

        $result = [];
        foreach ($response['items'] as $item) {
            $data = [
                'id' => $item['id'],
                'name' => $item['name'],
                'slug' => $item['slug']
            ];

            $fields = ['homepage', 'email'];
            foreach ($fields as $field) {
                if (!empty($item[$field])) {
                    $data[$field] = $item[$field];
                }
            }

            $address = [];
            foreach (['street', 'zip', 'city', 'coordinates'] as $addressField) {
                if (!empty($item['address'][$addressField])) {
                    $address[$addressField] = $item['address'][$addressField];
                    if ($addressField == 'coordinates') {
                        $address[$addressField]['lat'] 
                            = (float)$address[$addressField]['lat'];
                        $address[$addressField]['lon'] 
                            = (float)$address[$addressField]['lon'];
                    }


                }
            }
            if (!empty($address)) {
                $data['address'] = $address;
            }

            foreach ($mapUrlConf as $map => $mapConf) {
                $mapUrl = $mapConf['base'];
                if (!empty($mapConf['params'])) {
                    $replace = [];
                    foreach ($mapConf['params'] as $param) {
                        if (!empty($item['address'][$param])) {
                            $replace[$param] = $item['address'][$param];
                        }
                    }
                    foreach ($replace as $param => $val) {
                        $mapUrl = str_replace(
                            '{' . $param . '}', rawurlencode($val), $mapUrl
                        );
                    }
                }
                $data[$map] = $mapUrl;
            }

            $schedules = $this->parseDetails($target, $item);
            if ($schedules && isset($schedules['openNow'])) {
                $data['openNow'] = !empty($schedules['openNow']);
            }

            $result[] = $data;
        }
        usort($result, [$this, 'sortList']);

        return $result;
    }

    /**
     * Sorting function for organisations.
     *
     * @param array $a Organisation data
     * @param array $b Organisation data
     *
     * @return int
     */
    protected function sortList($a, $b)
    {
        return strcasecmp($a['name'], $b['name']);
    }

    /**
     * Parse organisation details.
     *
     * @param object $response JSON-object
     *
     * @return array
     */
    protected function parseDetails($target, $response, $includeAllServices = false)
    {
        $result = [];
        if (!empty($response['extra']['description'])) {
            $result['info'] = $response['extra']['description'];
        }

        $schedules = [];
        $periodStart = null;

        $dayNames = [
            'monday', 'tuesday', 'wednesday', 'thursday',
            'friday', 'saturday', 'sunday'
        ];

        $openNow = false;
        $openToday = false;
        $currentWeek = false;
        foreach ($response['schedules'] as $day) {
            if (!isset($day['times'])
                && !isset($day['sections']['selfservice']['times'])
            ) {
                continue;
            }
            if (!$periodStart) {
                $periodStart = $day['date'];
            }

            $now = new \DateTime();
            $now->setTime(0, 0, 0);

            $date = new \DateTime($day['date']);
            $date->setTime(0, 0, 0);

            $today = $now == $date;

            $dayTime = strtotime($day['date']);
            if ($dayTime === false) {
                $this->logError("Error parsing date: " . $day['date']);
                continue;
            }

            $weekDay = date('N', $dayTime);
            $weekDayName = $dayNames[($day['day']) - 1];

            $times = [];
            $now = time();

            // Self service times
            if (!empty($day['sections']['selfservice']['times'])) {
                foreach ($day['sections']['selfservice']['times'] as $time) {
                    $result = $this->extractDayTime($now, $time, $today, true);
                    if (!empty($result['openNow'])) {
                        $openNow = true;
                    }
                    $times[] = $result['result'];
                }
            }

            // Staff times
            foreach ($day['times'] as $time) {
                $result = $this->extractDayTime($now, $time, $today, false);
                if (!empty($result['openNow'])) {
                    $openNow = true;
                }
                $times[] = $result['result'];
            }

            if ($today && !empty($times)) {
                $openToday = $times;
            }

            $schedules[] = [
               'date' => $dayTime,
               'closed' => $day['closed'],
               'times' => $times,
               'today' => $today,
               'dayName' => $weekDayName,
            ];
            if ($today) {
                $currentWeek = true;
            }
        }
        if (!empty($schedules)) {
            $result['html'] = $this->viewRenderer->partial(
                'Helpers/organisation-info-schedule.phtml',
                ['schedules' => $schedules]
            );
        }
        if ($currentWeek) {
            $result['openNow'] = $openNow;
            $result['openToday'] = $openToday;
        }

        $result['currentWeek'] = $currentWeek;

        if (!empty($response['phone_numbers'])) {
            $phones = [];
            foreach ($response['phone_numbers'] as $phone) {
                $phones[]
                    = ['name' => $phone['name'], 'number' => $phone['number']];
            }
            try {
                $result['phone'] = $this->viewRenderer->partial(
                    "Helpers/organisation-info-phone-{$target}.phtml", ['phones' => $phones]
                );
            } catch (\Exception $e) {
                $this->logError($e->getmessage());
            }
        }

        if (!empty($response['pictures'])) {
            $pics = [];
            foreach ($response['pictures'] as $pic) {
                $picResult = ['url' => $pic['files']['medium']];
                $pics[] = $picResult;
            }
            if (!empty($pics)) {
                $result['pictures'] = $pics;
            }
        }


        if (!empty($response['links'])) {
            $links = [];
            foreach ($response['links'] as $link) {
                if ($link['name'] != 'Facebook') {
                    continue;
                }
                $links[] = ['type' => $link['name'], 'url' => $link['url']];
            }
            $result['links'] = $links;
        }

        if (!empty($response['services']) 
            && ($includeAllServices || !empty($this->config['services']))
        ) {
            $servicesMap = $this->config['services'];
            $services = $allServices = [];
            foreach ($response['services'] as $service) {
                if (in_array($service['id'], array_keys($servicesMap))) {
                    $services[] = $servicesMap[$service['id']];
                }
                if ($includeAllServices) {
                    $data = [$service['name']];
                    if (!empty($service['short_description'])) {
                        $data[] = $service['short_description'];
                    }
                    $allServices[] = $data;
                }
            }
            if (!empty($services)) {
                $result['services'] = $services;
            }
            if (!empty($allServices)) {
                $result['allServices'] = $allServices;
            }
        }

        if (!empty($response['extra']['description'])) {
            $result['description']
                = html_entity_decode($response['extra']['description']);
        }

        if (!empty($response['extra']['building']['construction_year'])) {
            if ($year = $response['extra']['building']['construction_year']) {
                $result['buildingYear'] = $year;
            }
        }

        if (!empty($response['extra']['data'])) {
            $rssLinks = [];
            foreach ($response['extra']['data'] as $link) {
                if (in_array($link['id'], ['news', 'events'])) {
                    $rssLinks[] = [
                       'id' => $link['id'], 
                       'url' => $link['value']
                    ];
                }
            }
            if (!empty($rssLinks)) {
                $result['rss'] = $rssLinks;
            }
        }

        return $result;
    }

    /**
     * Augment a schedule (pair of opens/closes times) object.
     *
     * @param DateTime $now         Current time
     * @param array    $time        Schedule object
     * @param boolean  $today       Is the schedule object for today?
     * @param boolean  $selfService Is the schedule object a self service time?
     *
     * @return array
     */
    protected function extractDayTime($now, $time, $today, $selfService)
    {
        $opens = $time['opens'];
        $closes = $time['closes'];
        $result = [
           'opens' => $opens, 'closes' => $closes, 'selfservice' => $selfService
        ];
        $openNow = false;

        if ($today) {
            $opensTime = strtotime($time['opens']);
            $closesTime = strtotime($time['closes']);
            $openNow = $now >= $opensTime && $now <= $closesTime;
            if ($openNow) {
                $result['openNow'] = true;
            }
        }
        return compact('result', 'openNow');
    }
}
