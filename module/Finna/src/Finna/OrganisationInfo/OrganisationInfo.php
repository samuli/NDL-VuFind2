<?php
/**
 * Service for querying Kirjastohakemisto database.
 * See: https://api.kirjastot.fi/
 *
 * PHP version 7
 *
 * Copyright (C) The National Library of Finland 2016-2018.
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
 * @package  Content
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @author   Konsta Raunio <konsta.raunio@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:developer_manual Wiki
 */
namespace Finna\OrganisationInfo;

/**
 * Service for querying Kirjastohakemisto database.
 * See: https://api.kirjastot.fi/
 *
 * @category VuFind
 * @package  Content
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @author   Konsta Raunio <konsta.raunio@helsinki.fi>
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

        if (!empty($config->General->fallbackLanguage)) {
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

        if (preg_match('/^0\/([^\/]*)\/$/', $building, $matches)) {
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
     * @param string $parent    Parent organisation
     * @param array  $params    Query parameters
     * @param array  $buildings List of building id's to include in the
     * consortium-query
     *
     * @return mixed array of results or false on error.
     */
    public function query($parent, $params, $buildings = null)
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

        if ($params['orgType'] == 'library') {
            return $this->queryLibrary($parent, $params, $buildings);
        } elseif ($params['orgType'] == 'museum') {
            return $this->queryMuseum($parent, $params);
        }

        $this->logError("Unknown action: {$params['action']}");
        return false;
    }

    /**
     * Perform query for library data.
     *
     * @param string $parent    Parent organisation
     * @param array  $params    Query parameters
     * @param array  $buildings List of building id's to include in the
     * consortium-query
     *
     * @return mixed array of results or false on error.
     */
    protected function queryLibrary($parent, $params, $buildings = null)
    {
        $id = null;
        if (isset($params['id'])) {
            $id = $params['id'];
        }
        $consortium
            = $params['consortium'] ?? null;
        $target = $params['target'] ?? 'widget';

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

        $schedules = $params['action'] == 'list' || !empty($params['periodStart']);

        if ($params['action'] == 'details') {
            $dir = isset($params['dir']) && in_array($params['dir'], ['1', '-1'])
                ? $params['dir'] : 0;
            $startDate = strtotime("{$dir} Week", $startDate);
            $endDate = strtotime("{$dir} Week", $endDate);
        }

        $weekNum = date('W', $startDate);
        $startDate = date('Y-m-d', $startDate);
        $endDate = date('Y-m-d', $endDate);

        if ($params['action'] == 'lookup') {
            $link = $params['link'];
            $parentName = $params['parentName'];
            return $this->lookupLibraryAction($parent, $link, $parentName);
        } elseif ($params['action'] == 'consortium') {
            $response = $this->consortiumAction(
                $parent, $buildings, $target, $startDate, $endDate, $params
            );
            if ($response) {
                $response['id'] = $id;
                $response['weekNum'] = $weekNum;
            }
            return $response;
        } elseif ($params['action'] == 'details') {
            $allServices = !empty($params['allServices']);
            $fullDetails = !empty($params['fullDetails']);
            $response = $this->detailsAction(
                $id, $target, $schedules, $startDate, $endDate,
                $fullDetails, $allServices
            );

            if ($response) {
                $response['weekNum'] = $weekNum;
            }
            return $response;
        }
    }

    /**
     * Perform query for museum data.
     *
     * @param string $parent Parent organisation
     * @param array  $params Query parameters
     *
     * @return mixed array of results or false on error.
     */
    protected function queryMuseum($parent, $params)
    {
        if ($params['action'] == 'lookup') {
            $link = $params['link'];
            $parentName = $params['parentName'];
            return $this->lookupMuseumAction($parent, $link, $parentName);
        } else {
            $params['id'] = !empty($parent) ? $parent
                : $this->config->General->defaultOrganisation;
            $response = $this->museumAction($params);
            if ($response == null) {
                return false;
            } else {
                $response['id'] = $id;
                return $response;
            }
        }
        //TODO Consortium/Group handling, we need to get data for that first from
        //TODO Museoliitto and organisations
    }

    /**
     * Check if consortium is found in Kirjastohakemisto
     *
     * @param string  $parent     Consortium Finna ID in Kirjastohakemisto or
     * in Museoliitto. Use a comma delimited string to check multiple Finna IDs.
     * @param boolean $link       True to render the link as a html-snippet.
     * Oherwise only the link URL is outputted.
     * @param string  $parentName Translated consortium display name.
     *
     * @return mixed array of results or false on error.
     */
    protected function lookupLibraryAction($parent, $link = false, $parentName = null
    ) {
        // Check if consortium is found in Kirjastohakemisto
        $params = [
            'finna:id' => $parent,
            'lang' => $this->language
        ];
        $params['with'] = 'finna';
        $response = $this->fetchData('consortium', $params);

        if (!$response || $response['total'] == 0) {
            return false;
        }

        $urlHelper = $this->viewRenderer->plugin('url');
        $url = $urlHelper('organisationinfo-home');
        $result = ['success' => true, 'items' => []];
        foreach ($response['items'] as $item) {
            $id = $item['finna']['finna_id'];
            $data = "{$url}?" . http_build_query(['id' => $id]);
            if ($link) {
                $logo = null;
                if (isset($response['items'][0]['logo'])) {
                    $logos = $response['items'][0]['logo'];
                    foreach (['small', 'medium'] as $size) {
                        if (isset($logos[$size])) {
                            $logo = $logos[$size];
                            break;
                        }
                    }
                }
                $data = $this->viewRenderer->partial(
                    'Helpers/organisation-page-link.phtml', [
                       'url' => $data, 'label' => 'organisation_info_link',
                       'logo' => $logo, 'name' => $parentName
                    ]
                );
            }
            $result['items'][$id] = $data;
        }
        return $result;
    }

    /**
     * Check if consortium is found in Museoliitto API
     *
     * @param string  $parent     Consortium Finna ID in Kirjastohakemisto or
     * in Museoliitto. Use a comma delimited string to check multiple Finna IDs.
     * @param boolean $link       True to render the link as a html-snippet.
     * Oherwise only the link URL is outputted.
     * @param string  $parentName Translated consortium display name.
     *
     * @return mixed array of results or false on error.
     */
    protected function lookupMuseumAction($parent, $link = false,
        $parentName = null
    ) {
        $params['id'] = $parent;
        $response = $this->fetchData('consortium', $params, true);

        if (!$response || empty($response['museot'])) {
            return false;
        }

        $urlHelper = $this->viewRenderer->plugin('url');
        $url = $urlHelper('organisationinfo-home');
        $json = $response['museot'][0];
        $items = [];
        if ($json['finna_publish'] == 1) {
            $id = $json['finna_org_id'];
            $data = "{$url}?" . http_build_query(['id' => $id]);
            if ($link) {
                $logo = $json['image'] ?? null;
            }
            $name = isset($json['name'][$this->language])
                ? $json['name'][$this->language]
                    : $this->translator->translate("source_{$parent}");
            $data = $this->viewRenderer->partial(
                'Helpers/organisation-page-link.phtml', [
                   'url' => $data, 'label' => 'organisation_info_link',
                   'logo' => $logo, 'name' => $name
                ]
            );
            $items[$id] = $data;
        }
        return ['success' => true, 'items' => $items];
    }

    /**
     * Query consortium info.
     *
     * @param string $parent    Consortium Finna ID in Kirjastohakemisto.
     * Use a comma delimited string to check multiple Finna IDs.
     * @param array  $buildings List of building id's to include in the response
     * @param string $target    page|widget
     * @param string $startDate Start date (YYYY-MM-DD) of opening times
     * @param string $endDate   End date (YYYY-MM-DD) of opening times
     *
     * @return mixed array of results or false on error.
     */
    protected function consortiumAction(
        $parent, $buildings, $target, $startDate, $endDate
    ) {
        $params = [
            'finna:id' => $parent,
            'with' => 'finna,link_groups'
        ];
        if (!$this->fallbackLanguage) {
            $params['lang'] = $this->language;
        }

        $response = $this->fetchData('consortium', $params);
        if (!$response
            || !$response['total'] || !isset($response['items'][0]['id'])
        ) {
            $this->logError(
                'Error reading consortium info: ' .
                var_export($params, true)
            );
            return false;
        }
        $response = $response['items'][0];
        $consortiumId = $response['id'];

        $consortium = [];
        $finna = [];

        if (isset($response['finna'])) {
            $finna['service_point']
                = $this->getField($response['finna'], 'service_point');
        }

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
                $finnaFields = [
                   'usage_info', 'notification', 'finna_coverage',
                ];
                foreach ($finnaFields as $field) {
                    $val = $this->getField($response['finna'], $field);
                    if (!empty($val)) {
                        $finna[$field] = $val;
                    }
                }

                if (isset($finna['finna_coverage'])) {
                    $finna['usage_perc'] = $finna['finna_coverage'];
                }

                if (isset($response['link_groups'])) {
                    foreach ($response['link_groups'] as $field) {
                        $map = [
                                'finna_materials' => 'links',
                                'finna_usage_info' => 'finnaLink'
                                ];
                        foreach ($map as $from => $to) {
                            if (empty($field['identifier'])) {
                                continue;
                            }
                            if ($field['identifier'] == $from) {
                                foreach ($field['links'] as $field) {
                                    $name = $this->getField($field, 'name');
                                    $url =  $this->getField($field, 'url');
                                    $finna[$to][]
                                        = ['name' => $name, 'value' => $url];
                                }
                            }
                        }
                    }
                }
            }
        }
        if (!empty($finna)) {
            $consortium['finna'] = $finna;
        }
        $consortium['id'] = $consortiumId;

        // Organisation list for a consortium with schedules for the current week
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
        if (!empty($buildings)) {
            if (($buildings = implode(',', $buildings)) != '') {
                $params['id'] = $buildings;
            }
        }

        $response = $this->fetchData('organisation', $params);
        if (!$response) {
            return false;
        }

        $result = ['consortium' => $consortium];
        $result['list'] = $this->parseList($target, $response);

        return $result;
    }

    /**
     * Query organisation details.
     *
     * @param int     $id          Organisation
     * @param string  $target      page|widget
     * @param boolean $schedules   Include opening times
     * @param string  $startDate   Start date (YYYY-MM-DD) of opening times
     * @param string  $endDate     End date (YYYY-MM-DD) of opening times
     * @param boolean $fullDetails Include full details.
     * @param boolean $allServices Include full list of services.
     *
     * @return mixed array of results or false on error.
     */
    protected function detailsAction(
        $id, $target, $schedules, $startDate, $endDate, $fullDetails, $allServices
    ) {
        if (!$id) {
            $this->logError("Missing id");
            return false;
        }

        $with = 'schedules';
        if ($fullDetails) {
            $with .= ',extra,phone_numbers,pictures,links,services';
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

        $response = $this->fetchData('organisation', $params);
        if (!$response) {
            return false;
        }

        if (!$response['total']) {
            return false;
        }

        // References
        $scheduleDescriptions = null;
        if (isset($response['references']['period'])) {
            $scheduleDescriptions = [];
            foreach ($response['references']['period'] as $key => $period) {
                $scheduleDesc = $this->getField($period, 'description');
                if (!empty($scheduleDesc)) {
                    $scheduleDescriptions[] = $scheduleDesc;
                }
            }
        }

        // Details
        $response = $response['items'][0];
        $result = $this->parseDetails(
            $target, $response, $schedules, $allServices
        );

        $result['id'] = $id;
        $result['periodStart'] = $startDate;
        if ($scheduleDescriptions) {
            $result['scheduleDescriptions'] = $scheduleDescriptions;
        }

        return $result;
    }

    /**
     * Fetch data from cache or external API.
     *
     * @param string  $action Action
     * @param array   $params Query parameters
     * @param boolean $museum If organisation type is museum
     *
     * @return mixed array of results or false on error.
     */
    protected function fetchData($action, $params, $museum = false)
    {
        if ($museum) {
            if (empty($this->config->MuseumAPI->url)) {
                return false;
            }
            $url = $this->config->MuseumAPI->url . '/finna_org_perustiedot.php'
                . '?finna_org_id=' . urlencode($params['id']);
        } else {
            $params['limit'] = 1000;
            $url = $this->config->General->url . '/' . $action
                . '?' . http_build_query($params);
        }
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
            $client = $this->http->createClient(
                $url, \Zend\Http\Request::METHOD_GET, 10
            );
            $result = $client->send();
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

            $data['openNow'] = $data['openTimes']['openNow'] ?? false
            ;
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
                    $name = $this->getField($service, 'custom_name');
                    if (!$name) {
                        $name = $this->getField($service, 'name');
                    }
                    $data = [$name];
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

        if (isset($response['extra'])) {
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
                           'url' => $this->getField($link, 'value')
                        ];
                    }
                }
                if (!empty($rssLinks)) {
                    $result['rss'] = $rssLinks;
                }
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

        $openNow = null;
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
                    if (isset($res['openNow'])) {
                        $openNow = $res['openNow'];
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
                if (null === $openNow || !empty($res['openNow'])) {
                    $openNow = $res['openNow'];
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

            if ($info) {
                $scheduleData['info'] = $info;
            }

            $schedules[] = $scheduleData;

            if ($today) {
                $currentWeek = true;
            }
        }

        $result = compact('schedules', 'openToday', 'currentWeek');
        $result['openNow'] = $openNow ?: false;
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
        $result = ['result' => $result];
        if ($today) {
            $result['openNow'] = $openNow;
        }
        return $result;
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
        if (!isset($parts[1]) || $parts[1] == '00') {
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

    /**
     * Query museum info for organisation page
     *
     * @param array $params Query parameters
     *
     * @return mixed array of results or false on error.
     */
    protected function museumAction($params)
    {
        $response = $this->fetchData('consortium', $params, true);
        if ($response['museot'] == null) {
            $this->logError(
                'Error reading museum info: ' .
                var_export($params, true)
            );
            return false;
        }
        $language = $this->language;
        $json = $response['museot'][0];
        $consortium = $finna = [];
        $publish = $json['finna_publish'];
        if ($publish == 1) {
            //Consortium info
            $consortium = [
                'museum' => true,
                'name' =>  $json['name'][$language],
                'description' => $json['description'][$language],
                'finna' => [
                    'service_point' => $id,
                    'finna_coverage' => $json['coverage'],
                    'usage_perc' => $json['coverage'],
                    'usage_info' => $json['usage_rights'][$language]
                ],
            ];
            foreach ($json['links'] as $field => $key) {
                $consortium['finna']['finnaLink'][$field]['name']
                    = $key['link_info']['link_text_' . $language . ''];
                $consortium['finna']['finnaLink'][$field]['value']
                    = $key['link_info']['link_url_' . $language . ''];
            }
            if (!empty($json['image'])) {
                $consortium['logo']['small'] = $json['image'];
            }
            //Details info
            $details = [
                'name' => $json['name'][$language],
                'openNow' => false,
                'openTimes' => [
                    'museum' => true,
                    'currentWeek' => true,
                ],
                'address' => [
                    'coordinates' => [
                        'lat' => !empty($json['latitude']) ? $json['latitude'] : '',
                        'lon' => !empty($json['longitude']) ? $json['longitude'] : ''
                    ],
                    'street' => !empty($json['address']) ? $json['address'] : ''
                ],
                'id' => $params['id'],
                'email' => $json['email'] ?? '',
                'type' => 'museum',
            ];
            //Date handling
            $today = date('d.m');
            $currentHour = date('H:i');
            $days = [
                0 => 'monday', 1 => 'tuesday', 2 => 'wednesday',
                3 => 'thursday', 4 => 'friday', 5 => 'saturday', 6 => 'sunday'
            ];
            foreach ($days as $day => $key) {
                $details['openTimes']['schedules'][$day]
                    = $this->getMuseumDaySchedule($key, $json, $today, $currentHour);
                if ($details['openTimes']['schedules'][$day]['openNow'] == true) {
                    $details['openNow'] = true;
                    $details['openTimes']['openNow'] = true;
                }
            }
            //Address handling
            if (!empty($details['address'])) {
                $mapUrl = $this->config->General->mapUrl;
                $routeUrl = $this->config->General->routeUrl;
                $replace['street'] = $details['address']['street'];
                $replace['city'] = preg_replace(
                    '/[0-9,]+/', '', $json['post_office']
                );
                foreach ($replace as $param => $val) {
                    $mapUrl = str_replace(
                        '{' . $param . '}', rawurlencode($val), $mapUrl
                    );
                    $routeUrl = str_replace(
                        '{' . $param . '}', rawurlencode($val), $routeUrl
                    );
                }
                $details['mapUrl'] = $mapUrl;
                $details['routeUrl'] = $routeUrl;
                $details['address']['zipcode']
                    = preg_replace('/\D/', '', $json['post_office']);
                $details['address']['city'] = $replace['city'];
            }
            //Contactinfo handling
            $contactInfo = [];
            foreach ($json['contact_infos'] as $field => $key) {
                $contactInfo[]
                    = [
                        'name' =>
                            $key['contact_info']['place_' . $language . ''],
                        'contact' =>
                            $key['contact_info']['phone_email_' . $language . '']
                    ];
            }
            try {
                $contactInfoToResult = $this->viewRenderer->partial(
                    "Helpers/organisation-info-museum-page.phtml",
                    ['contactInfo' => $contactInfo]
                );
            } catch (\Exception $e) {
                $this->logError($e->getmessage());
            }
            //All data to view
            $result = [
                'museum' => true,
                'list' => [
                    0 => $details
                ],
                'weekNum' => date('W'),
                'consortium' => $consortium,
                'pictures' => [
                    0 => [
                        'url' =>
                        isset($json['image2']) && strlen($json['image2']) > 30
                         ? $json['image2'] : ''
                    ],
                    1 => [
                        'url' =>
                        isset($json['image3']) && strlen($json['image3']) > 30
                         ? $json['image3'] : ''
                    ],
                    2 => [
                        'url' =>
                        isset($json['image4']) && strlen($json['image4']) > 30
                         ? $json['image4'] : ''
                    ]
                ],
                'scheduleDescriptions' => [
                    0 => !empty($json['opening_info'][$language])
                        ? $json['opening_info'][$language] : ''
                ],
                'contactInfo' => $contactInfoToResult ?? ''
            ];
        } else {
            $result = false;
        }
        return $result;
    }

    /**
     * Date-data handling function for museums
     *
     * @param string $day         weekday
     * @param array  $json        data from museum api
     * @param string $today       current date
     * @param string $currentHour current hour
     *
     * @return array
     */
    protected function getMuseumDaySchedule($day, $json, $today, $currentHour)
    {
        $return = [];
        $dayShortcode = substr($day, 0, 3);
        if (empty($json['opening_time']["{$dayShortcode}_start"])
            && empty($json['opening_time']["{$dayShortcode}_end"])
        ) {
            $return['closed'] = true;
        } else {
            $return['times'][0]['closes']
                = $this->formatTime($json['opening_time']["{$dayShortcode}_end"]);
            $return['times'][0]['opens']
                = $this->formatTime($json['opening_time']["{$dayShortcode}_start"]);
        }
        $return['day'] = $this->translator->translate('day-name-short-' . $day);
        $return['date'] = date('d.m', strtotime("{$day} this week"));
        if ($today == $return['date']) {
            $return['today'] = true;
            if ($currentHour >= $json['opening_time']["{$dayShortcode}_start"]
                && $currentHour <= $json['opening_time']["{$dayShortcode}_end"]
            ) {
                $return['openNow'] = true;
            }
        }
        return $return;
    }
}
