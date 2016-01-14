<?php
/**
 * Axiell Web Services ILS Driver
 *
 * PHP version 5
 *
 * Copyright (C) The National Library of Finland 2015.
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
 * @category VuFind2
 * @package  ILS_Drivers
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:building_an_ils_driver Wiki
 */
namespace Finna\ILS\Driver;
use SoapClient, SoapFault, SoapHeader, File_MARC, PDO, PDOException, DOMDocument,
    VuFind\Exception\Date as DateException,
    VuFind\Exception\ILS as ILSException,
    VuFind\I18n\Translator\TranslatorAwareInterface as TranslatorAwareInterface,
    Zend\Validator\EmailAddress as EmailAddressValidator,
    Zend\Session\Container as SessionContainer;

/**
 * Axiell Web Services ILS Driver
 *
 * @category VuFind2
 * @package  ILS_Drivers
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org/wiki/vufind2:building_an_ils_driver Wiki
 */
class AxiellWebServices extends \VuFind\ILS\Driver\AbstractBase
    implements TranslatorAwareInterface, \Zend\Log\LoggerAwareInterface, \VuFindHttp\HttpServiceAwareInterface
{
    use \VuFindHttp\HttpServiceAwareTrait;
    use \VuFind\I18n\Translator\TranslatorAwareTrait;
    use \VuFind\Log\LoggerAwareTrait {
        logError as error;
    }

    /**
     * Configuration Reader
     *
     * @var \VuFind\Config\PluginManager
     */
    protected $configReader;

    /**
     * Date formatting object
     *
     * @var \VuFind\Date\Converter
     */
    protected $dateFormat;

    /**
     * Default pickup location
     *
     * @var string
     */
    protected $defaultPickUpLocation;

    protected $arenaMember = '';

    protected $catalogue_wsdl = '';
    protected $patron_wsdl = '';
    protected $loans_wsdl = '';
    protected $payments_wsdl = '';
    protected $reservations_wsdl = '';

    protected $logFile = '';
    protected $durationLogPrefix = '';
    protected $verbose = false;
    protected $holdingsOrganisationOrder;
    protected $holdingsBranchOrder;

    /**
     * Container for storing cached ILS data.
     *
     * @var SessionContainer
     */
    protected $session;

    protected $soapOptions = [
        'soap_version' => SOAP_1_1,
        'exceptions' => true,
        'trace' => 1,
        'connection_timeout' => 60,
        'typemap' => [
            [
                'type_ns' => 'http://www.w3.org/2001/XMLSchema',
                'type_name' => 'anyType',
                'from_xml' => ['\AxiellWebServices', 'anyTypeToString'],
                'to_xml' => ['\AxiellWebServices', 'stringToAnyType']
            ]
        ]
    ];

    /**
     * Constructor
     *
     * @param \VuFind\Date\Converter $dateConverter Date converter object
     */
    public function __construct(\VuFind\Date\Converter $dateConverter)
    {
        $this->dateFormat = $dateConverter;
    }

    /**
     * Get Patron Profile
     *
     * This is responsible for retrieving the profile for a specific patron.
     *
     * @param array $patron The patron array
     *
     * @throws ILSException
     * @return array        Array of the patron's profile data on success.
     */
    public function getMyProfile($patron)
    {
        $this->debugLog("getMyProfile called");

        return $patron;
    }

    /**
     * Check Account Blocks
     *
     * Checks if a user has any blocks against their account which may prevent them
     * performing certain operations
     *
     * @param string $patronId A Patron ID
     *
     * @return mixed           A boolean false if no blocks are in place and an array
     * of block reasons if blocks are in place
     */
    protected function checkAccountBlocks($patronId)
    {
        // There's currently not much that can be checked here with AWS.

        $blockReason = [];

        return empty($blockReason) ? false : $blockReason;
    }

    /**
     * Return configuration for the patron's active library card driver.
     *
     * @param array $patron Patron
     *
     * @return bool|array False if no driver configuration was found,
     * or configuration.
     */
    protected function getPatronDriverConfig($patron)
    {
        if (isset($patron['cat_username'])
            && ($pos = strpos($patron['cat_username'], '.')) > 0
        ) {
            $source = substr($patron['cat_username'], 0, $pos);

            $config = $this->configReader->get($source);
            return is_object($config) ? $config->toArray() : [];
        }

        return false;
    }

    /**
     * Write to debug log, if defined
     *
     * @param string $msg Message to write
     *
     * @return void
     */
    protected function debugLog($msg)
    {
        if (!$this->logFile) {
            return;
        }
        $msg = date('Y-m-d H:i:s') . ' [' . getmypid() . "] $msg\n";
        file_put_contents($this->logFile, $msg, FILE_APPEND);
    }

    /**
     * Initialize the driver.
     *
     * Validate configuration and perform all resource-intensive tasks needed to
     * make the driver active.
     *
     * @throws ILSException
     * @return void
     */
    public function init()
    {
        if (empty($this->config)) {
            throw new ILSException('Configuration needs to be set.');
        }

        //TODO: isset for all configuration files?

        $this->arenaMember = $this->config['Catalog']['arena_member'];
        $this->catalogue_wsdl = 'local/config/vufind/' . $this->config['Catalog']['catalogue_wsdl'];
        $this->patron_wsdl = 'local/config/vufind/' . $this->config['Catalog']['patron_wsdl'];
        $this->loans_wsdl = 'local/config/vufind/' . $this->config['Catalog']['loans_wsdl'];
        $this->payments_wsdl = 'local/config/vufind/' . $this->config['Catalog']['payments_wsdl'];
        $this->reservations_wsdl = 'local/config/vufind/' . $this->config['Catalog']['reservations_wsdl'];

        $this->defaultPickUpLocation
        = (isset($this->config['Holds']['defaultPickUpLocation']))
        ? $this->config['Holds']['defaultPickUpLocation'] : false;
        if ($this->defaultPickUpLocation == '0') {
            $this->defaultPickUpLocation = false;
        }

        if (isset($this->config['Debug']['durationLogPrefix'])) {
            $this->durationLogPrefix = $this->config['Debug']['durationLogPrefix'];
        }

        if (isset($this->config['Debug']['verbose'])) {
            $this->verbose = $this->config['Debug']['verbose'];
        }

        if (isset($this->config['Debug']['log'])) {
            $this->logFile = $this->config['Debug']['log'];
        }
        $this->holdingsOrganisationOrder = isset($this->config['Holdings']['holdingsOrganisationOrder']) ? explode(":", $this->config['Holdings']['holdingsOrganisationOrder']) : [];
        $this->holdingsOrganisationOrder = array_flip($this->holdingsOrganisationOrder);
        $this->holdingsBranchOrder = isset($this->config['Holdings']['holdingsBranchOrder']) ? explode(":", $this->config['Holdings']['holdingsBranchOrder']) : [];
        $this->holdingsBranchOrder = array_flip($this->holdingsBranchOrder);

        // Establish a namespace in the session for persisting cached data
        $this->session = new SessionContainer('AxiellWebServices_' . $this->arenaMember);
    }

    /**
     * Helper function for fetching cached data.
     * Data is cached for up to 30 seconds so that it would be faster to process
     * e.g. requests where multiple calls to the backend are made.
     *
     * @param string $id Cache entry id
     *
     * @return mixed|null Cached entry or null if not cached or expired
     */

    //TODO implement getCached Data for all functions
    protected function getCachedData($id)
    {
        if (isset($this->session->cache[$id])) {
            $item = $this->session->cache[$id];
            if (time() - $item['time'] < 30) {
                return $item['entry'];
            }
            unset($this->session->cache[$id]);
        }
        return null;
    }

    /**
     * Helper function for storing cached data.
     * Data is cached for up to 30 seconds so that it would be faster to process
     * e.g. requests where multiple calls to the backend are made.
     *
     * @param string $id    Cache entry id
     * @param mixed  $entry Entry to be cached
     *
     * @return void
     */
    //TODO implement putCachedData for all functions
    protected function putCachedData($id, $entry)
    {
        if (!isset($this->session->cache)) {
            $this->session->cache = [];
        }
        $this->session->cache[$id] = [
            'time' => time(),
            'entry' => $entry
        ];
    }

    /**
     * Get Pickup Locations
     *
     * This is responsible for retrieving pickup locations.
     *
     * @param array $user        The patron array from patronLogin
     * @param array $holdDetails Hold details
     *
     * @throws ILSException
     *
     * @return array      Array of the patron's fines on success
     */
    public function getPickUpLocations($user, $holdDetails)
    {
        $username = $user['cat_username'];
        $password = $user['cat_password'];

        $id = !empty($holdDetails['item_id'])
        ? $holdDetails['item_id'] : $holdDetails['id'];

        $function = 'getReservationBranches';
        $functionResult = 'getReservationBranchesResult';
        $conf = [
            'arenaMember' => $this->arenaMember,
            'user' => $username,
            'password' => $password,
            'language' => $this->getLanguage(),
            'country' => 'FI',
            'reservationEntities' => $id,
            'reservationType' => 'normal'
        ];

        $result = $this->doSOAPRequest($this->reservations_wsdl, $function, $functionResult, $username, ['getReservationBranchesParam' => $conf]);

        $locationsList = [];
        if (!isset($result->$functionResult->organisations->organisation)) {
            // If we didn't get any pickup locations for item_id, fall back to id
            // and try again... This seems to happen when there are only ordered
            // items in the branch
            if (!empty($holdDetails['item_id'])) {
                unset($holdDetails['item_id']);
                return $this->getPickUpLocations($user, $holdDetails);
            }
            return $locationsList;
        }
        $organisations = is_object($result->$functionResult->organisations->organisation)
        ? [$result->$functionResult->organisations->organisation]
        : $result->$functionResult->organisations->organisation;

        foreach ($organisations as $organisation) {
            if (!isset($organisation->branches->branch)) {
                continue;
            }

            $organisationID = $organisation->id;

            // TODO: Make it configurable whether organisation names should be included in the location name
            $branches = is_object($organisation->branches->branch)
            ? [$organisation->branches->branch]
            : $organisation->branches->branch;

            if (is_object($organisation->branches->branch)) {
                $locationsList[] = [
                    'locationID' => $organisationID . '.' .  $organisation->branches->branch->id,
                    'locationDisplay' => $organisation->branches->branch->name
                ];
            } else {
                foreach ($organisation->branches->branch as $branch) {
                    $locationsList[] = [
                        'locationID' => $organisationID . '.' . $branch->id,
                        'locationDisplay' => $branch->name
                    ];
                }
            }
        }

        // Sort pick up locations
        usort($locationsList, [$this, 'pickUpLocationsSortFunction']);

        return $locationsList;
    }

    /**
     * Get Default Pick Up Location
     *
     * Returns the default pick up location set in VoyagerRestful.ini
     *
     * @param array $patron      Patron information returned by the patronLogin
     * method.
     * @param array $holdDetails Optional array, only passed in when getting a list
     * in the context of placing a hold; contains most of the same values passed to
     * placeHold, minus the patron data.  May be used to limit the pickup options
     * or may be ignored.
     *
     * @return string       The default pickup location for the patron.
     */
    public function getDefaultPickUpLocation($patron = false, $holdDetails = null)
    {
        return $this->defaultPickUpLocation;
    }

    /**
     * Get Default Request Group
     *
     * Returns the default request group
     *
     * @param array $patron      Patron information returned by the patronLogin
     * method.
     * @param array $holdDetails Optional array, only passed in when getting a list
     * in the context of placing a hold; contains most of the same values passed to
     * placeHold, minus the patron data.  May be used to limit the request group options
     * or may be ignored.
     *
     * @return string       The default request group for the patron.
     */
    public function getDefaultRequestGroup($patron = false, $holdDetails = null)
    {
        $requestGroups = $this->getRequestGroups(0, 0);
        return $requestGroups[0]['id'];
    }

    /**
     * Get request groups
     *
     * @param integer $bibId    BIB ID
     * @param array   $patronId Patron information returned by the patronLogin
     * method.
     *
     * @return array  False if request groups not in use or an array of
     * associative arrays with id and name keys
     */
    public function getRequestGroups($bibId, $patronId)
    {
        // Request Groups are not used for reservations
        return false;
    }

    /**
     * Place Hold
     *
     * This is responsible for both placing holds as well as placing recalls.
     *
     * @param string $holdDetails The request details
     *
     * @throws ILSException
     *
     * @return mixed           True if successful, false if unsuccessful
     *
     */
    public function placeHold($holdDetails)
    {
        if (isset($holdDetails['item_id']) && $holdDetails['item_id']) {
            $entityId = $holdDetails['item_id'];
            $reservationSource = 'holdings';
        } else {
            $entityId = $holdDetails['id'];
            $reservationSource = 'catalogueRecordDetail';
        }

        $username = $holdDetails['patron']['cat_username'];
        $password = $holdDetails['patron']['cat_password'];

        try {
            $validFromDate = date('Y-m-d');

            if (!isset($holdDetails['requiredBy'])) {
                $validToDate = $this->getDefaultRequiredByDate();
            } else {
                $validToDate = $this->dateFormat->convertFromDisplayDate(
                    'Y-m-d', $holdDetails['requiredBy']
                );
            }
        } catch (DateException $e) {
            // Hold Date is invalid
            throw new ILSException('hold_date_invalid');
        }

        $pickUpLocation = $holdDetails['pickUpLocation'];
        list($organisation, $branch) = explode('.', $pickUpLocation, 2);

        $function = 'addReservation';
        $functionResult = 'addReservationResult';
        $functionParam = 'addReservationParam';

        $conf = [
            'arenaMember'  => $this->arenaMember,
            'user'         => $username,
            'password'     => $password,
            'language'     => 'en',
            'reservationEntities' => $entityId,
            'reservationSource' => $reservationSource,
            'reservationType' => 'normal',
            'organisationId' => $organisation,
            'pickUpBranchId' => $branch,
            'validFromDate' => $validFromDate,
            'validToDate' => $validToDate
        ];

        $result = $this->doSOAPRequest($this->reservations_wsdl, $function, $functionResult, $username, [$functionParam => $conf]);

        $statusAWS = $result->$functionResult->status;

        if ($statusAWS->type != 'ok') {
                $message = $this->handleError($function, $statusAWS->message, $username);
                if ($message == 'ils_connection_failed') {
                    throw new ILSException('ils_offline_status');
                }
                return [
                    'success' => false
                ];
            }

        return [
            'success' => true
        ];
    }

    /**
     * Cancel Holds
     *
     * This is responsible for canceling holds.
     *
     * @param string $cancelDetails The request details
     *
     * @throws ILSException
     *
     * @return array           Associative array of the results
     *
     */
    public function cancelHolds($cancelDetails)
    {
        $username = $cancelDetails['patron']['cat_username'];
        $password = $cancelDetails['patron']['cat_password'];
        $succeeded = 0;
        $results = [];

        $function = 'removeReservation';
        $functionResult = 'removeReservationResult';

        foreach ($cancelDetails['details'] as $details) {
            $result =  $this->doSOAPRequest($this->reservations_wsdl, $function, $functionResult, $username, ['removeReservationsParam' => ['arenaMember' => $this->arenaMember, 'user' => $username, 'password' => $password, 'language' => 'en', 'id' => $details]]);

            $statusAWS = $result->$functionResult->status;

            if ($statusAWS->type != 'ok') {
                $message = $this->handleError($function, $statusAWS->message, $username);
                if ($message == 'ils_connection_failed') {
                    throw new ILSException('ils_offline_status');
                }
                $results[$details] = [
                    'success' => false,
                    'status' => 'hold_cancel_fail',
                    'sysMessage' => $statusAWS->message
                ];
            } else {
                $results[$details] = [
                    'success' => true,
                    'status' => 'hold_cancel_success',
                    'sysMessage' => ''
                ];
            }

            ++$succeeded;
        }
        $results['count'] = $succeeded;
        return $results;
    }

    /**
     * Cancel Hold Details
     *
     * This is responsible for getting the details required for canceling holds.
     *
     * @param string $holdDetails The request details
     *
     * @return string           Required details passed to cancelHold
     *
     */
    public function getCancelHoldDetails($holdDetails)
    {
        return $holdDetails['reqnum'];
    }

    /**
     * Change pickup location
     *
     * This is responsible for changing the pickup location of a hold
     *
     * @param string $patron      Patron array
     * @param string $holdDetails The request details
     *
     * @return array Response
     */
    public function changePickupLocation($patron, $holdDetails)
    {
        global $configArray;
        $username = $patron['cat_username'];
        $password = $patron['cat_password'];
        $pickUpLocation = $holdDetails['pickup'];
        $created = $this->dateFormat->convertFromDisplayDate(
            "Y-m-d", $holdDetails['created']
        );
        $expires = $this->dateFormat->convertFromDisplayDate(
            "Y-m-d", $holdDetails['expires']
        );
        $reservationId = $holdDetails['reservationId'];
        list($organisation, $branch) = explode('.', $pickUpLocation, 2);

        $function = 'changeReservation';
        $functionResult = 'changeReservationResult';
        $conf = [
            'arenaMember' => $this->arenaMember,
            'user' => $username,
            'password' => $password,
            'language' => 'en',
            'id' => $reservationId,
            'pickUpBranchId' => $branch,
            'validFromDate' => $created,
            'validToDate' => $expires
        ];

        $result = $this->doSOAPRequest($this->reservations_wsdl, $function, $functionResult, $username, ['changeReservationsParam' => $conf]);

        $statusAWS = $result->$functionResult->status;

        if ($statusAWS->type != 'ok') {
            $message = $this->handleError($function, $statusAWS->message, $username);
            if ($message == 'ils_connection_failed') {
                throw new ILSException('ils_offline_status');
            }
            return [
                'success' => false,
                'sysMessage' => $statusAWS->message
            ];
        }

        return [
            'success' => true
        ];
    }

    /**
     * Get Status
     *
     * This is responsible for retrieving the status information of a certain
     * record.
     *
     * @param string $id The record id to retrieve the holdings for
     *
     * @throws ILSException
     * @return mixed     On success, an associative array with the following keys:
     * id, availability (boolean), status, location, reserve, callnumber.
     */
    public function getStatus($id)
    {
        return $this->getHolding($id);
    }

    /**
     * Get Statuses
     *
     * This is responsible for retrieving the status information for a
     * collection of records.
     *
     * @param array $idList The array of record ids to retrieve the status for
     *
     * @throws ILSException
     * @return array        An array of getStatus() return values on success.
     */
    public function getStatuses($idList)
    {
        $items = [];
        foreach ($ids as $id) {
            $items[] = $this->getHolding($id);
        }
        return $items;
    }

    /**
     * Get Holding
     *
     * This is responsible for retrieving the holding information of a certain
     * record.
     *
     * @param string $id     The record id to retrieve the holdings for
     * @param array  $patron Patron data
     *
     * @throws DateException
     * @throws ILSException
     * @return array         On success, an associative array with the following
     * keys: id, availability (boolean), status, location, reserve, callnumber,
     * duedate, number, barcode.
     */
    public function getHolding($id, array $patron = null)
    {
        $function = 'GetHoldings';
        $functionResult = 'GetHoldingResult';
        $conf = [
            'arenaMember' => $this->arenaMember,
            'id' => $id,
            'language' => 'fi'
        ];

        $result = $this->doSOAPRequest($this->catalogue_wsdl, $function, $functionResult, $id, ['GetHoldingsRequest' => $conf]);

        $statusAWS = $result->$functionResult->status;

        if ($statusAWS->type != 'ok') {
            $message = $this->handleError($function, $statusAWS->message, $id);
            if ($message == 'catalog_connection_failed') {
                throw new ILSException('ils_offline_holdings_message');
            }
            return array();
        }

        if (!isset($result->$functionResult->catalogueRecord->compositeHolding)) {
            return array();
        }

        $holdings 
            = is_object($result->$functionResult->catalogueRecord->compositeHolding)
            ? array($result->$functionResult->catalogueRecord->compositeHolding)
            : $result->$functionResult->catalogueRecord->compositeHolding;

        if (isset($holdings[0]->type) && $holdings[0]->type == 'year') {
            $result = [];
            foreach ($holdings as $holding) {
                $year = $holding->value;
                $holdingsEditions = is_object($holding->compositeHolding)
                    ? array($holding->compositeHolding)
                    : $holding->compositeHolding;
                foreach ($holdingsEditions as $holdingsEdition) {
                    $edition = $holdingsEdition->value;
                    $holdingsOrganisations 
                        = is_object($holdingsEdition->compositeHolding)
                        ? [$holdingsEdition->compositeHolding]
                        : $holdingsEdition->compositeHolding;

                    $journalInfo = [
                        'year' => $year,
                        'edition' => $edition                           
                    ];

                    $result = array_merge(
                        $result, 
                        $this->parseHoldings(
                            $holdingsOrganisations, $id, $journalInfo
                        )
                    );
                }
            }
        } else {
            $result = $this->parseHoldings($holdings, $id, '', '');
        }
        
        // Sort Organisations
        if (!empty($result)) {
            usort($result, [$this, 'holdingsSortFunction']);
            $result = $this->addHoldingsSummary($result);
        }

        return empty($result) ? false : $result;
    }

    /**
     * This is responsible for iterating the organisation holdings
     *
     * @param array  $organisationHoldings Organisation holdings
     * @param string $id                   The record id to retrieve the holdings
     * @param array  $journalInfo          Jornal information
     *
     * @return array
     */
    protected function parseHoldings($organisationHoldings, $id, $journalInfo = null)
    {
        if ($organisationHoldings[0]->status == 'noHolding') {
            return;
        }
        if ($organisationHoldings[0]->type != 'organisation') {
            return;
        }

        $result = [];
        foreach ($organisationHoldings as $organisation) {
            $organisationName = $group = $organisation->value;
            $organisationId = $organisation->id;
            $holdingsBranch = is_object($organisation->compositeHolding) ?
                array($organisation->compositeHolding) :
                $organisation->compositeHolding;
            if ($holdingsBranch[0]->type == 'branch') {
                foreach ($holdingsBranch as $branch) {
                    $branchName = $branch->value;
                    $branchId = $branch->id;
                    $reservableId = isset($branch->reservable)
                        ? $branch->reservable : '';
                    // This holding is only holdable if it has a reservable id
                    // different from the record id
                    $holdable = $branch->reservationButtonStatus == 'reservationOk';
                    //                         && $reservableId != $id;
                    $departments = is_object($branch->holdings->holding)
                        ? array($branch->holdings->holding)
                        : $branch->holdings->holding;

                    foreach ($departments as $department) {
                        // Get holding data
                        $dueDate = isset($department->firstLoanDueDate)
                            ? $this->dateFormat->convertToDisplayDate(
                                '* M d G:i:s e Y',
                                $department->firstLoanDueDate
                            ) : '';
                        $departmentName = $department->department;
                        $locationName = isset($department->location)
                            ? $department->location : '';

                        if (!empty($locationName)) {
                            $departmentName = "{$departmentName}, $locationName";
                        }

                        $nofAvailableForLoan
                            = isset($department->nofAvailableForLoan)
                            ? $department->nofAvailableForLoan : 0;
                        $nofTotal = isset($department->nofTotal)
                            ? $department->nofTotal : 0;
                        $nofOrdered = isset($department->nofOrdered)
                            ? $department->nofOrdered : 0;

                        // Group journals by issue number
                        if ($journalInfo) {
                            $year = isset($journalInfo['year']) ? $journalInfo['year'] : '';
                            $edition = isset($journalInfo['edition']) ? $journalInfo['edition'] : '';
                            if ($year !== '' && $edition !== '') {
                                if (strncmp($year, $edition, strlen($year)) == 0) {
                                    $group = $edition;
                                } else {
                                    $group = "$year, $edition";
                                }
                            } else {
                                $group = $year . $edition;
                            }
                            $journalInfo['location'] = $organisationName;
                        }

                        // Status & availability
                        $status = $department->status;
                        $available = false;
                        if ($status == 'availableForLoan'
                            || $status == 'returnedToday'
                        ) {
                            $available = true;
                        }

                        // Special status: On reference desk
                        if ($status == 'nonAvailableForLoan'
                            && isset($department->nofReference)
                            && $department->nofReference == 0
                        ) {
                            $status = 'onRefDesk';
                        }

                        // Status table
                        $statusArray = [
                           'availableForLoan' => 'Available',
                           'onLoan' => 'Charged',
                           //'nonAvailableForLoan' => 'Not Available',
                           'nonAvailableForLoan' => 'On Reference Desk',
                           'onRefDesk' => 'On Reference Desk',
                           'overdueLoan' => 'overdueLoan',
                           'ordered' => 'Ordered',
                           'returnedToday' => 'returnedToday'
                        ];

                        // Convert status text
                        if (isset($statusArray[$status])) {
                            $status = $statusArray[$status];
                        } else {
                            $this->debugLog(
                                'Unhandled status ' +
                                $department->status + " for $id"
                            );
                        }

                        $holding = [
                           'id' => $id,
                           'barcode' => $id,
                           'item_id' => $reservableId,
                           'holdings_id' => $group,
                           'availability' => $available || $status == 'On Reference Desk',
                           'availabilityInfo' => [
                               'available' => $nofAvailableForLoan,
                               'displayText' => $status,
                               'reservations' => isset($branch->nofReservations) 
                                  ? $branch->nofReservations : 0,
                               'ordered' => $nofOrdered,
                               'total' => $nofTotal,
                            ],
                           'status' => $status,
                           'location' => $group,
                           'organisation_id' => $organisationId,
                           'branch' => $branchName,
                           'branch_id' => $branchId,
                           'department' => $departmentName,
                           'duedate' => $dueDate,
                           'addLink' => $journalInfo,
                           'callnumber' => isset($department->shelfMark)
                              ? ($department->shelfMark) : '',
                           'is_holdable' 
                              => $branch->reservationButtonStatus == 'reservationOk',
                           'collapsed' => true
                        ];
                        if ($journalInfo) {
                            $holding['journalInfo'] = $journalInfo;
                        }
                        $result[] = $holding;
                    }
                }
            }
        }

        return $result;
    }

    /**
     * Annotate holdings items with summary counts.
     *
     * @param array $holdings Parsed holdings items
     *
     * @return array Annotated holdings
     */
    protected function addHoldingsSummary($holdings)
    {
        $availableTotal = $itemsTotal = $reservationsTotal = 0;
        $locations = [];
        
        foreach ($holdings as $item) {
            if (!empty($item['availability'])) {
                $availableTotal++;
            }
            if (isset($item['availabilityInfo']['total'])) {
                $itemsTotal += $item['availabilityInfo']['total'];
            } else {
                $itemsTotal++;
            }
            
            if (isset($item['availabilityInfo']['reservations'])) {
                $reservations = max(
                    $reservationsTotal, 
                    $item['availabilityInfo']['reservations']
                );
            }            
            $locations[$item['location']] = true;
        }
        
        foreach ($holdings as &$item) {
            $item['summaryCounts'] = [
                'available' => $availableTotal,
                'total' => $itemsTotal,
                'reservations' => $reservations,
                'locations' => count($locations)
            ];
        }
        return $holdings;
    }

    /**
     * Get Purchase History
     *
     * This is responsible for retrieving the acquisitions history data for the
     * specific record (usually recently received issues of a serial).
     *
     * @param string $id The record id to retrieve the info for
     *
     * @throws ILSException
     * @return array     An array with the acquisitions data on success.
     */
    public function getPurchaseHistory($id)
    {
        return [];
    }

    /**
     * Patron Login
     *
     * This is responsible for authenticating a patron against the catalog.
     *
     * @param string $username The patron barcode
     * @param string $password   The patron's last name or PIN (depending on config)
     *
     * @throws ILSException
     * @return mixed          Associative array of patron info on successful login,
     * null on unsuccessful login.
     */
    public function patronLogin($username, $password)
    {
        // Secondary login field not supported
        if (is_array($password)) {
            $password = $password[0];
        }

        $function = 'getPatronInformation';
        $functionResult = 'patronInformationResult';
        $conf = [
            'arenaMember' => $this->arenaMember,
            'user' => $username,
            'password' => $password,
            'language' => $this->getLanguage()

        ];

        $result = $this->doSOAPRequest($this->patron_wsdl, $function, $functionResult, $username, ['patronInformationParam' => $conf]);

        $statusAWS = $result->$functionResult->status;

        if ($statusAWS->type != 'ok') {
            $message = $this->handleError($function, $statusAWS->message, $username);
            if ($message == 'ils_connection_failed') {
                throw new ILSException('ils_offline_login_message');
            }
            return null;
        }

        $info = $result->$functionResult->patronInformation;

        $user = [];
        $user['id'] = $username;
        $user['cat_username'] = $username;
        $user['cat_password'] = $password;
        $names = explode(' ', $info->patronName);
        $user['lastname'] = array_pop($names);
        $user['firstname'] = implode(' ', $names);
        $user['email'] = '';
        $user['emailId'] = '';
        $user['address1'] = '';
        $user['zip'] = '';
        $user['phone'] = '';
        $user['phoneId'] = '';
        $user['phoneLocalCode'] = '';
        $user['phoneAreaCode'] = '';
        $user['major'] = null;
        $user['college'] = null;

        if (isset($info->emailAddresses) && $info->emailAddresses->emailAddress) {
            $emailAddresses = is_object($info->emailAddresses->emailAddress) ? [$info->emailAddresses->emailAddress] : $info->emailAddresses->emailAddress;
            foreach ($emailAddresses as $emailAddress) {
                if ($emailAddress->isActive == 'yes') {
                    $user['email'] = isset($emailAddress->address) ? $emailAddress->address : '';
                    $user['emailId'] = isset($emailAddress->id) ? $emailAddress->id : '';
                }
            }
        }

        if (isset($info->addresses)) {
            $addresses = is_object($info->addresses->address) ? [$info->addresses->address] : $info->addresses->address;
            foreach ($addresses as $address) {
                if ($address->isActive == 'yes') {
                    $user['address1'] = isset($address->streetAddress) ? $address->streetAddress : '';
                    $user['zip'] = isset($address->zipCode) ? $address->zipCode : '';
                    if (isset($address->city)) {
                        if ($user['zip']) {
                            $user['zip'] .= ', ';
                        }
                        $user['zip'] .= $address->city;
                    }
                    if (isset($address->country)) {
                        if ($user['zip']) {
                            $user['zip'] .= ', ';
                        }
                        $user['zip'] .= $address->country;
                    }
                }
            }
        }

        if (isset($info->phoneNumbers)) {
            $phoneNumbers = is_object($info->phoneNumbers->phoneNumber) ? [$info->phoneNumbers->phoneNumber] : $info->phoneNumbers->phoneNumber;
            foreach ($phoneNumbers as $phoneNumber) {
                if ($phoneNumber->sms->useForSms == 'yes') {
                    $user['phone'] = isset($phoneNumber->areaCode) ? $phoneNumber->areaCode : '';
                    $user['phoneAreaCode'] = $user['phone'];
                    if (isset($phoneNumber->localCode)) {
                        $user['phone'] .= $phoneNumber->localCode;
                        $user['phoneLocalCode'] = $phoneNumber->localCode;
                    }
                    if (isset($phoneNumber->id)) {
                        $user['phoneId'] = $phoneNumber->id;
                    }
                }
            }
        }

        return $user;
    }

    /**
     * Public Function which retrieves renew, hold and cancel settings from the
     * driver ini file.
     *
     * @param string $function The name of the feature to be checked
     *
     * @return array An array with key-value pairs.
     */
    public function getConfig($function)
    {
        if (isset($this->config[$function])) {
            $functionConfig = $this->config[$function];
        } else {
            $functionConfig = false;
        }
        return $functionConfig;
    }

    /**
     * Get Patron Transactions
     *
     * This is responsible for retrieving all transactions (i.e. checked out items)
     * by a specific patron.
     *
     * @param array $user The patron array from patronLogin
     *
     *
     * @throws DateException
     * @throws ILSException
     * @return array        Array of the patron's transactions on success.
     */
    public function getMyTransactions($user)
    {
        $username = $user['cat_username'];
        $password = $user['cat_password'];

        $function = 'GetLoans';
        $functionResult = 'loansResponse';
        $conf = [
            'arenaMember' => $this->arenaMember,
            'user' => $username,
            'password' => $password,
            'language' => $this->getLanguage()
        ];

        $result = $this->doSOAPRequest($this->loans_wsdl, $function, $functionResult, $username, ['loansRequest' => $conf]);

        $statusAWS = $result->$functionResult->status;

        if ($statusAWS->type != 'ok') {
            $message = $this->handleError($function, $statusAWS->message, $username);
            if ($message == 'catalog_connection_failed') {
                throw new ILSException($message);
            }
            return [];
        }

        $transList = [];
        if (!isset($result->$functionResult->loans->loan)) {
            return $transList;
        }
        $loans = is_object($result->$functionResult->loans->loan) ? [$result->$functionResult->loans->loan] : $result->$functionResult->loans->loan;

        foreach ($loans as $loan) {
            $title = $loan->catalogueRecord->title;
            if ($loan->note) {
                $title .= ' (' . $loan->note . ')';
            }

            $trans = [];
            $trans['id'] = $loan->catalogueRecord->id;
            $trans['item_id'] = $loan->id;
            $trans['title'] = $title;
            $trans['duedate'] = $loan->loanDueDate;
            $trans['renewable'] = (string)$loan->loanStatus->isRenewable == 'yes';
            $trans['message'] = $this->mapStatus($loan->loanStatus->status);
            $trans['barcode'] = $loan->id;
            $trans['renewalCount'] = max([0, $this->config['Loans']['renewalLimit'] - $loan->remainingRenewals]);
            $trans['renewalLimit'] = $this->config['Loans']['renewalLimit'];
            $transList[] = $trans;
        }

        // Sort the Loans
        $date = [];
        foreach ($transList as $key => $row) {
            $date[$key] = $row['duedate'];
        }
        array_multisort($date, SORT_ASC, $transList);

        // Convert Axiell format to display date format
        foreach ($transList as &$row) {
            $row['duedate'] = $this->formatDate($row['duedate']);
        }

        return $transList;
    }

    /**
     * Get Patron Fines
     *
     * This is responsible for retrieving all fines by a specific patron.
     *
     * @param array $user The patron array from patronLogin
     *
     * @throws DateException
     * @throws ILSException
     * @return array        Array of the patron's fines on success.
     */
    public function getMyFines($user)
    {
        $username = $user['cat_username'];
        $password = $user['cat_password'];

        $function = 'GetDebts';
        $functionResult = 'debtsResponse';
        $conf = [
            'arenaMember' => $this->arenaMember,
            'user' => $username,
            'password' => $password,
            'language' => $this->getLanguage(),
            'fromDate' => '1699-12-31',
            'toDate' => time()
        ];

        $result = $this->doSOAPRequest($this->payments_wsdl, $function, $functionResult, $username, ['debtsRequest' => $conf]);

        $statusAWS = $result->$functionResult->status;

        if ($statusAWS->type != 'ok') {
            $message = $this->handleError($function, $statusAWS->message, $username);
            if ($message == 'catalog_connection_failed') {
                throw new ILSException($message);
            }
            return [];
        }

        $finesList = [];
        if (!isset($result->$functionResult->debts->debt))
            return $finesList;
        $debts = is_object($result->$functionResult->debts->debt) ? [$result->$functionResult->debts->debt] : $result->$functionResult->debts->debt;

        foreach ($debts as $debt) {
            $fine = [];
            $fine['debt_id'] = $debt->id;
            $fine['amount'] = str_replace(',', '.', $debt->debtAmountFormatted) * 100;
            $fine['checkout'] = '';
            $fine['fine'] = $debt->debtType . ' - ' . $debt->debtNote;
            $fine['balance'] = str_replace(',', '.', $debt->debtAmountFormatted) * 100;
            // Convert Axiell format to display date format
            $fine['createdate'] = $this->formatDate($debt->debtDate);
            $fine['duedate'] = $this->formatDate($debt->debtDate);
            $finesList[] = $fine;
        }
        return $finesList;
     }

    /**
     * Get Patron Holds
     *
     * This is responsible for retrieving all holds by a specific patron.
     *
     * @param array $user The patron array from patronLogin
     *
     * @throws DateException
     * @throws ILSException
     * @return array        Array of the patron's holds on success.
     */
     public function getMyHolds($user)
     {
        $username = $user['cat_username'];
        $password = $user['cat_password'];

        $function = 'getReservations';
        $functionResult =  'getReservationsResult';

        $conf = [
            'arenaMember' => $this->arenaMember,
            'user' => $username,
            'password' => $password,
            'language' => $this->getLanguage()

        ];

        $result = $this->doSOAPRequest($this->reservations_wsdl, $function, $functionResult, $username, ['getReservationsParam' => $conf]);

        $statusAWS = $result->$functionResult->status;

        if ($statusAWS->type != 'ok') {
            $message = $this->handleError($function, $statusAWS->message, $username);
            if ($message == 'catalog_connection_failed') {
                throw new ILSException($message);
            }
            return [];
        }

        $holdsList = [];
        if (!isset($result->$functionResult->reservations->reservation))
            return $holdsList;
        $reservations = is_object($result->$functionResult->reservations->reservation) ? [$result->$functionResult->reservations->reservation] : $result->$functionResult->reservations->reservation;

        foreach ($reservations as $reservation) {
            $expireDate = $reservation->reservationStatus == 'fetchable' ? $reservation->pickUpExpireDate : $reservation->validToDate;
            $title = isset($reservation->catalogueRecord->title) ? $reservation->catalogueRecord->title : '';
            if (isset($reservation->note)) {
                $title .= ' (' . $reservation->note . ')';
            }

            $hold = [
                'id' => $reservation->catalogueRecord->id,
                'type' => $reservation->reservationStatus,
                'location' => $reservation->pickUpBranchId,
                'reqnum' => ($reservation->isDeletable == 'yes' && isset($reservation->id)) ? $reservation->id : '',
                'pickupnum' => isset($reservation->pickUpNo) ? $reservation->pickUpNo : '',
                'expire' => $this->formatDate($expireDate),
                'create' => $this->formatDate($reservation->validFromDate),
                'position' => isset($reservation->queueNo) ? $reservation->queueNo : '-',
                'available' => $reservation->reservationStatus == 'fetchable',
                'modifiable' => $reservation->reservationStatus == 'active',
                'item_id' => '',
                'reservation_id' => $reservation->id,
                'volume' => isset($reservation->catalogueRecord->volume) ? $reservation->catalogueRecord->volume : '',
                'publication_year' => isset($reservation->catalogueRecord->publicationYear) ? $reservation->catalogueRecord->publicationYear : '',
                'title' => $title
            ];
            $holdsList[] = $hold;
           }
        return $holdsList;
     }

     /**
      * Renew Details
      *
      * This is responsible for getting the details required for renewing loans.
      *
      * @param string $checkoutDetails The request details
      *
      * @throws ILSException
      *
      * @return string           Required details passed to renewMyItems
      *
      */
     public function getRenewDetails($checkoutDetails)
     {
         return $checkoutDetails['barcode'];
     }

     /**
      * Renew Items
      *
      * This is responsible for renewing items.
      *
      * @param string $renewDetails The request details
      * @throws ILSException
      *
      * @return array           Associative array of the results
      *
      */
     public function renewMyItems($renewDetails)
     {
         $succeeded = 0;
         $results = ['blocks' => [], 'details' => []];
         foreach ($renewDetails['details'] as $id) {
             $username = $renewDetails['patron']['cat_username'];
             $password = $renewDetails['patron']['cat_password'];

             $function = 'RenewLoans';
             $functionResult = 'renewLoansResponse';

             $conf = [
                 'arenaMember' => $this->arenaMember,
                 'user' => $username,
                 'password' => $password,
                 'language' => 'en',
                 'loans' => [$id]

             ];

             $result = $this->doSOAPRequest($this->loans_wsdl, $function, $functionResult, $username, ['renewLoansRequest' => $conf]);

             if ($statusAWS->type != 'ok') {
                $message = $this->handleError($function, $statusAWS->message, $username);
                if ($message == 'ils_connection_failed') {
                    throw new ILSException('ils_offline_status');
                }
            }

            $status = trim($result->$functionResult->loans->loan->loanStatus->status);
            $success = $status === 'isRenewedToday';

            $results['details'][$id] = [
                'success' => $success,
                'status' => $success ? 'Loan renewed' : 'Renewal failed',
                'sysMessage' => $status,
                'item_id' => $id,
                'new_date' => $this->formatDate($result->$functionResult->loans->loan->loanDueDate),
                'new_time' => ''
            ];
         }
         return $results;
     }

    /**
     * Update patron phone number
     *
     * @param array  $patron Patron array
     * @param string $phone  Phone number
     *
     * @throws ILSException
     *
     * @return array Response
     */
    public function updatePhone($patron, $phone)
    {
        $username = $patron['cat_username'];
        $password = $patron['cat_password'];
        $phoneCountry = isset($patron['phoneCountry']) ? $patron['phoneCountry'] : 'FI';
        $areaCode = '';
        $function = '';
        $functionResult = '';
        $functionParam = '';

        $conf = [
            'arenaMember'  => $this->arenaMember,
            'language'     => 'en',
            'user'         => $username,
            'password'     => $password,
            'areaCode'     => $areaCode,
            'country'      => $phoneCountry,
            'localCode'    => $phone,
            'useForSms'    => 'yes'
        ];

        if (!empty($patron['phoneId'])) {
            $conf['id'] = $patron['phoneId'];
            $function = 'changePhone';
            $functionResult = 'changePhoneNumberResult';
            $functionParam = 'changePhoneNumberParam';
        } else {
            $function = 'addPhone';
            $functionResult = 'addPhoneNumberResult';
            $functionParam = 'addPhoneNumberParam';
        }

        $result = $this->doSOAPRequest($this->patron_wsdl, $function, $functionResult, $username, [$functionParam => $conf]);

        $statusAWS = $result->$functionResult->status;

        if ($statusAWS->type != 'ok') {
            $message = $this->handleError($function, $statusAWS->message, $username);
            if ($message == 'ils_connection_failed') {
                throw new ILSException('ils_offline_status');
            }
            return  [
                'success' => false,
                'status' => 'Changing the phone number failed',
                'sys_message' => $statusAWS->message
            ];
        }

        return [
                'success' => true,
                'status' => 'Phone number changed',
                'sys_message' => ''
            ];
    }

    /**
     * Set patron email address
     *
     * @param array  $patron Patron array
     * @param String $email  User Email
     *
     * @throws ILSException
     *
     * @return array Response
     */
    public function updateEmail($patron, $email)
    {
        $username = $patron['cat_username'];
        $password = $patron['cat_password'];
        $function = '';
        $functionResult = '';
        $functionParam = '';

        $conf = [
            'arenaMember'  => $this->arenaMember,
            'language'     => 'en',
            'user'         => $username,
            'password'     => $password,
            'address'      => $email,
            'isActive'     => 'yes'
        ];

        if (!empty($patron['emailId'])) {
            $conf['id'] = $patron['emailId'];
            $function = 'changeEmail';
            $functionResult = 'changeEmailAddressResult';
            $functionParam = 'changeEmailAddressParam';
        } else {
            $function = 'addEmail';
            $functionResult = 'addEmailAddressResult';
            $functionParam = 'addEmailAddressParam';
        }

        $result = $this->doSOAPRequest($this->patron_wsdl, $function, $functionResult, $username, [$functionParam => $conf]);

        $statusAWS = $result->$functionResult->status;

        if ($statusAWS->type != 'ok') {
            $message = $this->handleError($function, $statusAWS->message, $username);
            if ($message == 'ils_connection_failed') {
                throw new ILSException('ils_offline_status');
            }
            return  [
                'success' => false,
                'status' => 'Changing the email address failed',
                'sys_message' => $statusAWS->message
            ];
        }

        return [
                'success' => true,
                'status' => 'Email address changed',
                'sys_message' => '',
            ];
    }

    /**
     * Change pin code
     *
     * @param String $cardDetails Patron card data
     *
     * @throws ILSException
     *
     * @return array Response
     */
    public function changePassword($cardDetails)
    {
        $username = $cardDetails['patron']['cat_username'];
        $password = $cardDetails['patron']['cat_password'];

        $function = 'changeCardPin';
        $functionResult = 'changeCardPinResult';

        $conf = [
            'arenaMember'  => $this->arenaMember,
            'cardNumber'   => $cardDetails['patron']['cat_username'],
            'cardPin'      => $cardDetails['oldPassword'],
            'newCardPin'   => $cardDetails['newPassword'],
        ];

        $result = $this->doSOAPRequest($this->patron_wsdl, $function, $functionResult, $username, ['changeCardPinParam' => $conf]);

        $statusAWS = $result->$functionResult->status;

        if ($statusAWS->type != 'ok') {
            $message = $this->handleError($function, $statusAWS->message, $username);
            if ($message == 'ils_connection_failed') {
                throw new ILSException('ils_offline_status');
            }
            return  [
                'success' => false,
                'status' => $statusAWS->message
            ];
        }

        return  [
                'success' => true,
                'status' => 'change_password_ok',
            ];
    }

    /**
     * Send a SOAP request
     *
     * @param string $wsdl           Name of the wsdl file
     * @param string $function       Name of the function
     * @param string $functionResult Name of the Result tag
     * @param string $id             Username or record id
     * @param array  $params         Parameters needed for the SOAP call
     *
     * @return object SOAP response
     */
    protected function doSOAPRequest($wsdl, $function, $functionResult, $id, $params)
    {
        $client = new SoapClient($wsdl, $this->soapOptions);

        $this->debugLog("$function Request for '$id'");

        $startTime = microtime(true);
        $result = $client->$function($params);

        if ($this->durationLogPrefix) {
            file_put_contents($this->durationLogPrefix . '_' . $function . '.log', date('Y-m-d H:i:s ') . round(microtime(true) - $startTime, 4) . "\n", FILE_APPEND);
        }

        if ($this->verbose) {
            $this->debugLog("$function Request: " . $this->formatXML($client->__getLastRequest()));
            $this->debugLog("$function Response: " . $this->formatXML($client->__getLastResponse()));
        }

        return $result;
    }

    /**
     * Format date
     *
     * @param string $dateString Date as a string
     *
     * @return string Formatted date
     */
    protected function formatDate($dateString)
    {
        // remove timezone from Axiell obscure dateformat
        $date = substr($dateString, 0, strpos("$dateString*", "+"));

        return $this->dateFormat->convertToDisplayDate("Y-m-d", $date);
    }

    /**
     * Pretty-print an XML string
     *
     * @param string $xml XML string
     *
     * @return string Pretty XML string
     */
    protected function formatXML($xml)
    {
        if (!$xml) {
            return $xml;
        }
        $dom = new DOMDocument('1.0');
        $dom->preserveWhiteSpace = false;
        $dom->formatOutput = true;
        $dom->loadXML($xml);
        return $dom->saveXML();
    }

    /**
     * Get the language to be used in the interface
     *
     * @return string Language as string
     */
    protected function getLanguage()
    {
        global $interface;
        //$language = $interface->getLanguage();
        $language = 'fi';
        if (!in_array($language, ['en', 'sv', 'fi'])) {
            $language = 'en';
        }
        return $language;
    }

    /**
     * Handle system status error messages from Axiell Web Services
     *
     * @param string     $function Function name
     * @param string     $message  Error message
     *
     * @return string    Error message as string
     */
    protected function handleError($function, $message, $id)
    {
        $status =  [
            // Axiell system status error messages
            'BackendError'           => 'ils_connection_failed',
            'LocalServiceTimeout'    => 'ils_connection_failed',
            'DatabaseError'          => 'ils_connection_failed',
        ];

        if (isset($status[$message])) {
            $this->debugLog("$function Request failed for '$id'");
            $this->debugLog("AWS error: '$message'");
            return $status[$message];
        } return $message;
    }

    /**
     * Sort function for sorting holdings locations according to organisation
     *
     * @param array $a Holdings location
     * @param array $b Holdings location
     *
     * @return number
     */
    protected function holdingsOrganisationSortFunction($a, $b)
    {
        if (isset($this->holdingsOrganisationOrder[$a['holdings'][0]['organisation_id']])) {
            if (isset($this->holdingsOrganisationOrder[$b['holdings'][0]['organisation_id']])) {

                return $this->holdingsOrganisationOrder[$a['holdings'][0]['organisation_id']] - $this->holdingsOrganisationOrder[$b['holdings'][0]['organisation_id']];
            }
            return -1;
        }
        if (isset($this->holdingsOrganisationOrder[$b['holdings'][0]['organisation_id']])) {
            return 1;
        }
        return strcasecmp($a['organisation'], $b['organisation']);
    }

    /**
     * Sort function for sorting holdings locations according to branch
     *
     * @param array $a Holdings location
     * @param array $b Holdings location
     *
     * @return number
     */
    protected function holdingsBranchSortFunction($a, $b)
    {
        $locationA = $a['branch'] . " " . $a['department'];
        $locationB = $b['branch'] . " " . $b['department'];

        if (isset($this->holdingsBranchOrder[$a['branch_id']])) {
            if (isset($this->holdingsBranchOrder[$b['branch_id']])) {
                $order = $this->holdingsBranchOrder[$a['branch_id']] - $this->holdingsBranchOrder[$b['branch_id']];
                if ($order == 0) {
                    return strcasecmp($locationA, $locationB);
                }
                return $order;
            }
            return -1;
        }
        if (isset($this->holdingsBranchOrder[$b['branch_id']])) {
            return 1;
        }

        return strcasecmp($locationA, $locationB);
    }

    /**
     * Sort function for sorting pickup locations
     *
     * @param array $a Pickup location
     * @param array $b Pickup location
     *
     * @return number
     */
    protected function pickUpLocationsSortFunction($a, $b)
    {
        $pickUpLocationOrder = isset($this->config['Holds']['pickUpLocationOrder']) ? explode(":", $this->config['Holds']['pickUpLocationOrder']) : [];
        $pickUpLocationOrder = array_flip($pickUpLocationOrder);
        if (isset($pickUpLocationOrder[$a['locationID']])) {
            if (isset($pickUpLocationOrder[$b['locationID']])) {
                return $pickUpLocationOrder[$a['locationID']] - $pickUpLocationOrder[$b['locationID']];
            }
            return -1;
        }
        if (isset($pickUpLocationOrder[$b['locationID']])) {
            return 1;
        }
        return strcasecmp($a['locationDisplay'], $b['locationDisplay']);
    }

    /**
     * Map statuses
     *
     * @param string $status as a string
     *
     * @return string Mapped status
     */
    protected function mapStatus($status)
    {
        $statuses =  [
            'isLoanedToday'         => 'Borrowed today',
            'isRenewedToday'        => 'Renewed today',
            'isOverdue'             => 'renew_item_overdue',
            'patronIsDeniedLoan'    => 'fine_limit_patron',
            'patronHasDebt'         => 'renew_item_patron_has_debt',
            'maxNofRenewals'        => 'renew_item_limit',
            'patronIsInvoiced'      => 'renew_item_patron_is_invoiced',
            'copyHasSpecialCircCat' => 'Copy has special circulation',
            'copyIsReserved'        => 'renew_item_requested',
            'renewalIsDenied'       => 'renew_denied'
        ];

        if (isset($statuses[$status])) {
            return $statuses[$status];
        }
        return $status;
    }

    /**
     * Helper method to determine whether or not a certain method can be
     * called on this driver.  Required method for any smart drivers.
     *
     * @param string $method The name of the called method.
     * @param array  $params Array of passed parameters
     *
     * @return bool True if the method can be called with the given parameters,
     * false otherwise.
     *
     * @SuppressWarnings(PHPMD.UnusedFormalParameter)
     */
    public function supportsMethod($method, $params)
    {
        //Special case: change password is only available if properly configured.
        if ($method == 'changePassword') {
            return isset($this->config['changePassword']);
        }
        return is_callable([$this, $method]);
    }
}
