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
     * Translator
     *
     * @var VuFind\Tranlator
     */
    protected $translator;

    /**
     * Constructor.
     *
     * @param Zend\Config\Config             $config       Configuration
     * @param VuFind\CacheManager            $cacheManager Cache manager
     * @param VuFind\Http                    $http         HTTP service
     * @param Zend\View\Renderer\PhpRenderer $viewRenderer View renderer
     * @param VuFind\Translator              $translator   Translator
     */
    public function __construct(
        $config, $cacheManager, $http, $viewRenderer, $translator
    ) {
        $this->mainConfig = $config;
        $this->cacheManager = $cacheManager;
        $this->viewRenderer = $viewRenderer;
        $this->http = $http;
        $this->translator = $translator;
    }

    /**
     * Perform query.
     *
     * @param string $parent   Parent organisation
     * @param array  $params   Query parameters
     * @param string $language User language 
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

        $schedules = $action == 'list' || !empty($params['periodStart']);

        if ($action == 'details') {
            $dir = isset($params['dir']) && in_array($params['dir'], ['1', '-1'])
                ? $params['dir'] : 0;
            $startDate = strtotime("{$dir} Week", $startDate);
            $endDate = strtotime("{$dir} Week", $endDate);
        }
        
        $allServices = !empty($params['allServices']);

        $weekNum = date('W', $startDate);
        $startDate = date('Y-m-d', $startDate);
        $endDate = date('Y-m-d', $endDate);

        $url = $this->config['url'];


        if ($action == 'list') {
            // Organisation list with schedules for the current week
            $url .= '/library';
            $params = [
                'lang' => $language,
                $parentType => $parentId,
                'with' => 'schedules',
                'period.start' => $startDate,
                'period.end' => $endDate,
            ];
            
            $response = $this->fetchData($url, $params);
            if (!$response) {
                $this->logError("Error reading organisation list (url: $url)");
                return false;
            }

            $result = ['id' => $id];
            $result['list'] = $this->parseList($language, $target, $response);
            $result['weekNum'] = $weekNum;
            
            return $result;

        } else if ($action == 'details') {
            if (!$id) {
                $this->logError("Missing id");
                return false;
            }

            $url .= "/library";

            $with = $schedules ? 'schedules,' : '';
            if (!empty($params['fullDetails'])) {
                $with .= 'extra,phone_numbers,pictures,links,services';
            }

            $params = [
                'id' => $id,
                'lang' => $language,
                'with' => $with,
                'period.start' => $startDate,
                'period.end' => $endDate,
                'refs' => 'period'
            ];

            $response = $this->fetchData($url, $params);
            if (!$response) {
                $this->logError("Error reading organisation list (url: $url)");
                return false;
            }
            
            if (!$response['total']) {
                return false;
            }
            
            // References
            $scheduleDescriptions = [];
            if (isset($response['references']['period'])) {
                foreach ($response['references']['period'] as $key => $period) {
                    if (!empty($period['description'][$language])) {
                        $scheduleDescriptions[] = $period['description'][$language];
                    }
                }
            }
            
            // Details
            $response = $response['items'][0];
            $result = $this->parseDetails($language, $target, $response, $schedules, $allServices);

            $result['id'] = $id;
            $result['periodStart'] = $startDate;
            $result['weekNum'] = $weekNum;

            if (!empty($scheduleDescriptions)) {
                $result['schedule-descriptions'] = $scheduleDescriptions;
            }

            return $result;
        }
        
        $this->logError("Unknown action: $action");
        return false;
    }

    /**
     * Fetch data from cache or external API.
     *
     * @param string $url    URL
     * @param array  $params Query parameters
     *
     * @return mixed result or false on error.
     */
    protected function fetchData($url, $params)
    {
        $params['limit'] = 1000;
        $url .= '?' . http_build_query($params);
        
        error_log($url);

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
            if ($maxAge) {
                file_put_contents($localFile, $response);
            }
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
     * @param string  $language User language
     * @param string  $target   page|widge
     * @param object $response  JSON-object
     *
     * @return array
     */
    protected function parseList($language, $target, $response)
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

            if ($item['branch_type'] == 'mobile') {
                $data['mobile'] = 1;
            }

            $fields = ['homepage', 'email'];
            foreach ($fields as $field) {
                if (!empty($item[$field])) {
                    $data[$field] = $item[$field];
                }
            }

            $address = [];
            foreach (['street', 'zipcode', 'city', 'coordinates'] as $addressField) {
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

            $data['openTimes'] = $this->parseSchedules($item['schedules']);

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
     * @param string  $language           User language
     * @param string  $target             page|widge
     * @param object  $response           JSON-object
     * @param boolean $includeAllServices Include services in the response?
     *
     * @return array
     */
    protected function parseDetails(
        $language, $target, $response, $schedules, $includeAllServices = false
    ) {
        $result = [];
        
        if ($schedules) {
            $result['openTimes'] = $this->parseSchedules($response['schedules']);
            if (!empty($response['extra']['description'])) {
                $result['info'] = $response['extra']['description'];
            }
        }

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
                $links[] = ['name' => $link['name'], 'url' => $link['url']];
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
     * Parse schedules
     *
     * @param object  $data JSON data
     *
     * @return array
     */
    protected function parseSchedules($data)
    {
        $schedules = [];
        $periodStart = null;

        $dayNames = [
            'monday', 'tuesday', 'wednesday', 'thursday',
            'friday', 'saturday', 'sunday'
        ];

        $openNow = false;
        $openToday = false;
        $currentWeek = false;
        foreach ($data as $day) {
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
            $weekDayName = $this->translator->translate(
                'day-name-short-' . $dayNames[($day['day']) - 1]
            );

            $times = [];
            $now = time();
            $closed = isset($day['sections']['selfservice']['closed']) ? true : false;

            // Self service times
            $info = isset($day['sections']['selfservice']['info'])
                ? $day['sections']['selfservice']['info'] : null;

            if (!empty($day['sections']['selfservice']['times'])) {
                foreach ($day['sections']['selfservice']['times'] as $time) {
                    $res = $this->extractDayTime($now, $time, $today, true);
                    if (!empty($res['openNow'])) {
                        $openNow = true;
                    }
                    if ($info) {
                        $res['result']['info'] = $info;
                    }
                    $times[] = $res['result'];
                }
            }

            // Staff times
            $info = isset($day['info'])
                ? $day['info'] : null;

            foreach ($day['times'] as $time) {
                $res = $this->extractDayTime($now, $time, $today, false);
                if (!empty($res['openNow'])) {
                    $openNow = true;
                }
                if (!empty($info)) {
                    //$res['result']['info'] = $info;
                }

                $times[] = $res['result'];
            }

            if ($today && !empty($times)) {
                $openToday = $times;
            }

            $scheduleData = [
               'date' => date('d.m', $dayTime),
               'times' => $times,
               'day' => $weekDayName,
            ];

            if ($day['closed'] && !$closed) {
                $closed = true;
            }

            $closed = false;
            if (!empty($day['sections']['selfservice']['closed'])) {
                $closed = true;
            } else if ($day['closed']) {
                $closed = true;
            }

            if ($closed) {
                $scheduleData['closed'] = $closed;
            }

            if ($today) {
                $scheduleData['today'] = true;
            }

            $schedules[] = $scheduleData;

            if ($today) {
                $currentWeek = true;
            }
        }

        return compact('schedules', 'openNow', 'openToday', 'currentWeek');
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
