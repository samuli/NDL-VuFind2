<?php
/**
 * Service for querying Kirjastohakemisto database.
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
 * Service for querying Kirjastohakemisto database.
 * See: https://api.kirjastot.fi/
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
     * Language version
     *
     * @var string
     */
    protected $language;

    /**
     * Fallback language version
     *
     * @var string
     */
    protected $fallbackLanguage;

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
        $this->config = $config;
        $this->cacheManager = $cacheManager;
        $this->viewRenderer = $viewRenderer;
        $this->http = $http;
        $this->translator = $translator;

        $allLanguages = isset($config->General->languages)
            ? $config->General->languages->toArray() : [];

        $language = isset($config->General->language)
            ? $config->General->language
            : $this->translator->getLocale();

        $this->language = $this->validateLanguage($language, $allLanguages);

        if (isset($config->General->fallbackLanguage)) {
            $fallback = $config->General->fallbackLanguage;
            $fallback = $this->validateLanguage($fallback, $allLanguages);
            if ($fallback != $this->language) {
                $this->fallbackLanguage = $fallback;                
            }
        }
    }

    /**
     * Validate language version.
     *
     * @param string $language     Language version
     * @param array  $allLanguages List of valid languages.
     *
     * @return string Language version
     */
    protected function validateLanguage($language, $allLanguages)
    {
        $map = ['en-gb' => 'en'];
        if (isset($map[$language])) {
            $language = $map[$language];
        }

        if (!in_array($language, $allLanguages)) {
            $language = 'fi';
        }

        return $language;
    }

    /**
     * Convert building code to Kirjastohakemisto finna_id
     *
     * @param string|array $building Building
     *
     * @return string ID
     */
    public function getOrganisationInfoId($building)
    {
        if (is_array($building)) {
            $building = $building[0];
        }

        if (preg_match('/^0\/([a-zA-z0-9]*)\/$/', $building, $matches)) {
            // strip leading '0/' and trailing '/' from top-level building code
            return $matches[1];
        }
        return null;
    }

    /**
     * Check if organisation info is enabled.
     *
     * @return boolean
     */
    public function isAvailable()
    {
        return !empty($this->config->General->enabled);
    }

    /**
     * Perform query.
     *
     * @param string $parent   Parent organisation
     * @param array  $params   Query parameters
     *
     * @return mixed array of results or false on error.
     */
    public function query($parent, $params)
    {
        $id = null;
        if (isset($params['id'])) {
            $id = $params['id'];
        }

        if (!$this->isAvailable()) {
            $this->logError("Organisation info disabled ($parent)");
            return false;
        }

        if (!isset($this->config->General->url)) {
            $this->logError(
                "URL missing from organisation info configuration"
                . "($parent)"
            );
            return false;
        }

        if (empty($parent)) {
            $this->logError("Missing parent");
            return false;
        }

        $target = isset($params['target']) ? $params['target'] : 'widget';
        $action = isset($params['action']) ? $params['action'] : 'list';

        $id = null;
        if (isset($params['id'])) {
            $id = $params['id'];
        }
        $consortium
            = isset($params['consortium']) ? $params['consortium'] : null;

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

        $url = $this->config->General->url;
        if ($action == 'lookup') {
            // Check if consortium is found in Kirjastohakemisto
            $parents = explode(',', $parent);
            $url .= '/consortium';
            $params = [
                'finna:id' => $parent,
                'lang' => $this->language
            ];
            if (count($parents) > 1) {
                $params['with'] = 'finna';
            }

            $response = $this->fetchData($url, $params);
            if (!$response
            ) {
                $this->logError("Lookup error (url: $url)");
                return false;
            }
            error_log(var_export($response, true));

            if ($response['total'] == 0) {
                return false;
            }

            if (count($parents) == 1) {
                return $parents;
            } else {
                $result = [];
                foreach ($response['items'] as $item) {
                    $result[] = $item['finna']['finna_id'];
                }
                return $result;
            }
        } elseif ($action == 'consortium') {
            // Consortium info for a Finna-organisation
            $url .= '/consortium';
            $params = [
                'finna:id' => $parent,
                'with' => 'finna'
            ];
            if (!$this->fallbackLanguage) {
                $params['lang'] = $this->language;
            }

            $response = $this->fetchData($url, $params);
            if (!$response
                || !$response['total'] || !isset($response['items'][0]['id'])
            ) {
                $this->logError("Error reading consortium info (url: $url)");
                return false;
            }
            $response = $response['items'][0];
            $consortiumId = $response['id'];

            $consortium = [];
            if ($target == 'page') {
                foreach (
                    ['name', 'description', 'homepage'] as $field
                ) {
                    $val = $this->getField($response, $field);
                    if (!empty($val)) {
                        $consortium[$field] = $val;
                        if ($field == 'homepage') {
                            $parts = parse_url($val);
                            if (isset($parts['host'])) {
                                $val = $parts['host'];
                                $consortium['homepageLabel'] = $val;
                            }
                        }
                    }
                }
                if (!empty($response['logo'])) {
                    $consortium['logo'] = $response['logo'];
                }
                
                if (isset($response['finna'])) {
                    $finna = [];
                    foreach (['usage_info', 'notification'] as $field) {
                        $val = $this->getField($response['finna'], $field);
                        if (!empty($val)) {
                            $finna[$field] = $val;
                        }
                    }
                    
                    // fake data
                    $finna['usage_perc'] = rand()/getrandmax();
                    
                    $finna['links'] = [
                        ['name' => 'Yhteystiedot', 'value' => 'http://www.finna.fi'],
                        ['name' => 'Flickr', 'value' => 'http://www.finna.fi'],
                        ['name' => 'Karanot-tietokanta', 'value' => 'http://www.finna.fi']
                    ];

                    $finnaLink = $finnaLinkName = 'https://vaski.finna.fi';
                    $parts = parse_url($finnaLink);
                    if (isset($parts['host'])) {
                        $finnaLinkName = $parts['host'];
                    }
                    $finna['finnaLink'] 
                        = ['name' => $finnaLinkName, 'value' => $finnaLink]; 

                    if (!empty($finna)) {
                        $consortium['finna'] = $finna;
                    }
                }
            }
            $consortium['id'] = $consortiumId;

            $url = $this->config->General->url;

            // Organisation list for a consortium with schedules for the current week
            $url .= '/organisation';
            $params = [
                'consortium' => $consortiumId,
                'with' => 'schedules',
                'period.start' => $startDate,
                'period.end' => $endDate,
                'refs' => 'period'
            ];
            if (!$this->fallbackLanguage) {
                $params['lang'] = $this->language;
            }

            $response = $this->fetchData($url, $params);
            if (!$response) {
                $this->logError("Error reading organisation list (url: $url)");
                return false;
            }

            $result = ['id' => $id, 'consortium' => $consortium];
            $result['list'] = $this->parseList($target, $response);
            $result['weekNum'] = $weekNum;

            // References
            if (isset($response['references']['period'])) {
                $scheduleDescriptions = [];
                foreach ($response['references']['period'] as $key => $period) {
                    $id = $period['organisation'];
                    $scheduleDesc = $this->getField($period, 'description');
                    if (!empty($scheduleDesc)) {
                        if (!isset($scheduleDescriptions[$id])) {
                            $scheduleDescriptions[$id] = [];
                        }
                        $scheduleDescriptions[$id][] = $scheduleDesc;
                    }
                }
                foreach ($scheduleDescriptions as $id => $descriptions) {
                    foreach ($result['list'] as &$item) {
                        if ($item['id'] == $id) {
                            $item['schedule-descriptions']
                                = array_unique($descriptions);
                            continue;
                        }
                    }
                }
            }

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
                'with' => $with,
                'period.start' => $startDate,
                'period.end' => $endDate,
                'refs' => 'period'
            ];
            if (!$this->fallbackLanguage) {
                $params['lang'] = $this->language;
            }

            $response = $this->fetchData($url, $params);
            if (!$response) {
                $this->logError("Error reading organisation list (url: $url)");
                return false;
            }

            if (!$response['total']) {
                return false;
            }

            // Details
            $response = $response['items'][0];
            $result = $this->parseDetails(
                $target, $response, $schedules, $allServices
            );

            $result['id'] = $id;
            $result['periodStart'] = $startDate;
            $result['weekNum'] = $weekNum;

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
        $maxAge = isset($this->config->General->cachetime)
            ? $this->config->General->cachetime : 10;

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
     * @param string $target   page|widge
     * @param object $response JSON-object
     *
     * @return array
     */
    protected function parseList($target, $response)
    {
        $mapUrls = ['routeUrl', 'mapUrl'];
        $mapUrlConf = [];
        foreach ($mapUrls as $url) {
            if (isset($this->config->General[$url])) {
                $base = $this->config->General[$url];
                $conf = ['base' => $base];

                if (preg_match_all('/{([^}]*)}/', $base, $matches)) {
                    $conf['params'] = $matches[1];
                }
                $mapUrlConf[$url] = $conf;
            }
        }

        $result = [];
        foreach ($response['items'] as $item) {
            $name = $this->getField($item, 'name');
            if (empty($name)) {
                continue;
            }

            $data = [
                'id' => $item['id'],
                'name' => $name,
                'shortName' => $this->getField($item, 'short_name'),
                'slug' => $item['slug'],
                'type' => $item['type']
            ];

            if ($item['branch_type'] == 'mobile') {
                $data['mobile'] = 1;
            }

            $fields = ['homepage', 'email'];
            foreach ($fields as $field) {
                if ($val = $this->getField($item, $field)) {
                    $data[$field] = $val;
                }
            }

            if (!empty($item['address'])) {
                $address = [];
                foreach (['street', 'zipcode', 'city'] as $addressField) {
                    $address[$addressField] 
                        = $this->getField($item['address'], $addressField);
                }
                if (!empty($item['address']['coordinates'])) {
                    $coordinates = $item['address']['coordinates'];
                    $coordinates['lat'] = isset($coordinates['lat'])
                        ? (float)$coordinates['lat'] : null;
                    $coordinates['lon'] = isset($coordinates['lon'])
                        ? (float)$coordinates['lon'] : null;

                    $address['coordinates'] = $coordinates;
                }
                if (!empty($address)) {
                    $data['address'] = $address;
                }
            }

            if (!empty($item['address'])) {
                foreach ($mapUrlConf as $map => $mapConf) {
                    $mapUrl = $mapConf['base'];
                    if (!empty($mapConf['params'])) {
                        $replace = [];
                        foreach ($mapConf['params'] as $param) {
                            $val = $this->getField($item['address'], $param, 'fi');
                            if (!empty($val)) {
                                $replace[$param] = $val;
                            }
                        }
                    }
                    foreach ($replace as $param => $val) {
                        $mapUrl = str_replace(
                            '{' . $param . '}', rawurlencode($val), $mapUrl
                        );
                    }
                    $data[$map] = $mapUrl;
                }
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
     * @param string  $target             page|widge
     * @param object  $response           JSON-object
     * @param boolean $schedules          Include schedules in the response?
     * @param boolean $includeAllServices Include services in the response?
     *
     * @return array
     */
    protected function parseDetails(
        $target, $response, $schedules, $includeAllServices = false
    ) {
        $result = [];

        if ($schedules) {
            $result['openTimes'] = $this->parseSchedules($response['schedules']);
        }

        if (!empty($response['phone_numbers'])) {
            $phones = [];
            foreach ($response['phone_numbers'] as $phone) {
                $name = $this->getField($phone, 'name');
                if ($name) {
                    $phones[]
                        = ['name' => $name, 'number' => $phone['number']];
                }
            }
            try {
                $result['phone'] = $this->viewRenderer->partial(
                    "Helpers/organisation-info-phone-{$target}.phtml",
                    ['phones' => $phones]
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
                $name = $this->getField($link, 'name');
                $url = $this->getField($link, 'url');
                if ($name && $url) {
                    $links[] = ['name' => $name, 'url' => $url];
                }
            }
            $result['links'] = $links;
        }

        if (!empty($response['services'])
            && ($includeAllServices
            || !empty($this->config->OpeningTimesWidget->services))
        ) {
            $servicesMap = [];
            $servicesConf = $this->config->OpeningTimesWidget->services->toArray();
            foreach ($servicesConf as $key => $ids) {
                $servicesMap[$key] = explode(',', $ids);
            }
            $services = $allServices = [];
            foreach ($response['services'] as $service) {
                foreach ($servicesMap as $key => $ids) {
                    if (in_array($service['id'], $ids)) {
                        $services[] = $key;
                    }
                }
                if ($includeAllServices) {
                    $data = [$this->getField($service, 'name')];
                    $desc = $this->getField($service, 'short_description');
                    if ($desc) {
                        $data[] = $desc;
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

        $extra = $response['extra'];
        $desc = $this->getField($extra, 'description');
        if (!empty($desc)) {
            $result['description']
                = html_entity_decode($desc);
        }

        $slogan = $this->getField($extra, 'slogan');
        if (!empty($slogan)) {
            $result['slogan']
                = html_entity_decode($slogan);
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
     * @param object $data JSON data
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
            $closed
                = isset($day['sections']['selfservice']['closed']) ? true : false;

            $info = $this->getField($day, 'info');

            $staffTimes = $selfserviceTimes = [];
            // Self service times
            if (!empty($day['sections']['selfservice']['times'])) {
                foreach ($day['sections']['selfservice']['times'] as $time) {
                    $res = $this->extractDayTime($now, $time, $today, true);
                    if (!empty($res['openNow'])) {
                        $openNow = true;
                    }
                    if (empty($day['times'])) {
                        $res['result']['selfserviceOnly'] = true;
                    }
                    if (!empty($info)) {
                        $res['result']['info'] = $info;
                        $info = null;
                    }
                    $times[] = $res['result'];
                }
            }

            // Staff times
            foreach ($day['times'] as $time) {
                $res = $this->extractDayTime($now, $time, $today);
                if (!empty($res['openNow'])) {
                    $openNow = true;
                }
                if (!empty($info)) {
                    $res['result']['info'] = $info;
                    $info = null;
                }
                
                $times[] = $res['result'];
            }
            if ($today && !empty($times)) {
                $openToday = $times;
            }

            $scheduleData = [
               'date' => date('j.n.', $dayTime),
               'times' => $times,
               'day' => $weekDayName,
            ];
            
            $closed = $day['closed']
                && (!isset($day['sections']['selfservice']['closed'])
                    || $day['sections']['selfservice']['closed']);

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
    protected function extractDayTime($now, $time, $today, $selfService = false)
    {
        $opens = $this->formatTime($time['opens']);
        $closes = $this->formatTime($time['closes']);
        $result = [
           'opens' => $opens, 'closes' => $closes
        ];
        if ($selfService) {
            $result['selfservice'] = true;
        }
        
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

    /**
     * Format time string.
     *
     * @param string $time Time
     *
     * @return string
     */
    protected function formatTime($time)
    {
        $parts = explode(':', $time);
        if (substr($parts[0], 0, 1) == '0') {
            $parts[0] = substr($parts[0], 1);
        }
        if ($parts[1] == '00') {
            return $parts[0];
        }
        return $parts[0] . ':' . $parts[1];
    }

    /**
     * Return object field.
     *
     * @param array  $obj      Object
     * @param string $field    Field
     * @param string $language Language version. If not defined, 
     * the configured language versions is used.
     *
     * @return mixed
     */
    protected function getField($obj, $field, $language = false)
    {
        if (!isset($obj[$field])) {
            return null;
        }

        $data = $obj[$field];

        if (!is_array($data)) {
            return $data;
        }

        if ($language && !empty($data[$language])) {
            return $data[$language];
        }
        
        if (!empty($data[$this->language])) {
            return $data[$this->language];
        }

        if ($this->fallbackLanguage && !empty($data[$this->fallbackLanguage])) {
            return $data[$this->fallbackLanguage];
        }

        return null;
    }    
}
