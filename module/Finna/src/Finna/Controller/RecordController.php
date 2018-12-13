<?php
/**
 * Record Controller
 *
 * PHP version 7
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
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @category VuFind
 * @package  Controller
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
namespace Finna\Controller;

use Zend\Mail as Mail;

/**
 * Record Controller
 *
 * @category VuFind
 * @package  Controller
 * @author   Samuli Sillanpää <samuli.sillanpaa@helsinki.fi>
 * @license  http://opensource.org/licenses/gpl-2.0.php GNU General Public License
 * @link     http://vufind.org   Main Site
 */
class RecordController extends \VuFind\Controller\RecordController
{
    use FinnaRecordControllerTrait;
    use CatalogLoginTrait;

    /**
     * Create record feedback form and send feedback to correct recipient.
     *
     * @return \Zend\View\Model\ViewModel
     * @throws \Exception
     */
    public function feedbackAction()
    {
        $view = $this->createViewModel();

        if ($this->formWasSubmitted('submitFeedback')) {
            $flashMsg = $this->flashMessenger();

            $message = $this->params()->fromPost('feedback_message');
            $replyToEmail = $this->params()->fromPost('from');
            $validator = new \Zend\Validator\EmailAddress();
            if (!$validator->isValid($replyToEmail)) {
                $flashMsg->setNamespace('error')
                    ->addMessage('Email address is invalid');
                return $view;
            }

            $driver = $this->loadRecord();
            $dataSource = $driver->getDataSource();
            $dataSources = $this->serviceLocator->get('VuFind\Config')
                ->get('datasources');

            $inst = $dataSources->$dataSource ?? null;
            $recipientEmail = isset($inst->feedbackEmail) ?
                $inst->feedbackEmail : null;
            if ($recipientEmail == null) {
                throw new \Exception(
                    'Feedback Module Error:'
                    . 'Recipient Email Unset (see datasources.ini)'
                );
            }

            $config = $this->getConfig();
            $feedback = isset($config->Feedback) ? $config->Feedback : null;
            $senderEmail = isset($feedback->sender_email)
                ? $feedback->sender_email : 'noreply@vufind.org';
            $senderName = isset($feedback->sender_name)
                ? $feedback->sender_name : 'VuFind Feedback';

            $emailSubject = $this->translate(
                'feedback_on_record',
                ['%%record%%' => $driver->getBreadcrumb()]
            );
            $serverUrl = $this->getRequest()->getServer('REQUEST_SCHEME');
            $serverUrl .= '://' . $this->getRequest()->getServer('HTTP_HOST');

            $emailMessage = "\n" . $this->translate('This email was sent from');
            $emailMessage .= ": " . $replyToEmail . "\n";
            $emailMessage .=
                "------------------------------------------------------------\n";
            // Use the record plugin to render the template for the correct driver
            $recordPlugin = $this->getViewRenderer()->plugin('record');
            $emailMessage .= $recordPlugin($driver)->getEmail();
            $emailMessage .=
                "\n\n------------------------------------------------------------\n";
            if (!empty($message)) {
                $emailMessage .= "\n" . $this
                    ->translate('Message From Sender') . ":\n";
                $emailMessage .= "\n" . $message . "\n\n";
            }

            // This sets up the email to be sent
            $mail = new Mail\Message();
            $mail->setEncoding('UTF-8');
            $mail->setBody($emailMessage);
            $mail->setFrom($senderEmail, $senderName);
            $mail->setReplyTo($replyToEmail);
            $mail->addTo($recipientEmail);
            try {
                $mail->setSubject($emailSubject);
            } catch (\Exception $e) {
                // Uhh.. PHP bug https://bugs.php.net/bug.php?id=53891 causes trouble
                // when trying to encode a subject containing non-ascii characters.
                // Try to convert the subject to ascii..
                // TODO: Remove this when PHP works properly..
                $emailSubject = iconv('UTF-8', 'ascii//TRANSLIT', $emailSubject);
                $mail->setSubject($emailSubject);
            }
            $headers = $mail->getHeaders();
            $headers->removeHeader('Content-Type');
            $headers->addHeaderLine('Content-Type', 'text/plain; charset=UTF-8');

            $this->serviceLocator->get('VuFind\Mailer')->getTransport()
                ->send($mail);

            $flashMsg->addSuccessMessage('Thank you for your feedback.');
            if ($this->getRequest()->getQuery('layout', 'no') !== 'lightbox'
                || 'layout/lightbox' != $this->layout()->getTemplate()
            ) {
                $this->redirectToRecord('');
            }
        }

        return $view;
    }

    /**
     * Load a normalized record from RecordManager for preview
     *
     * @param string $data   Record Metadata
     * @param string $format Metadata format
     * @param string $source Data source
     *
     * @return AbstractRecordDriver
     * @throw  \Exception
     */
    protected function loadPreviewRecord($data, $format, $source)
    {
        $config = $this->getConfig();
        if (empty($config->NormalizationPreview->url)) {
            throw new \Exception('Normalization preview URL not configured');
        }

        $httpService = $this->serviceLocator->get('VuFind\Http');
        $client = $httpService->createClient(
            $config->NormalizationPreview->url,
            \Zend\Http\Request::METHOD_POST
        );
        $client->setParameterPost(
            ['data' => $data, 'format' => $format, 'source' => $source]
        );
        $response = $client->send();
        if (!$response->isSuccess()) {
            throw new \Exception(
                'Failed to load preview: ' . $response->getStatusCode() . ' '
                . $response->getReasonPhrase()
            );
        }
        $metadata = json_decode($response->getBody(), true);
        $recordFactory = $this->serviceLocator
            ->get('VuFind\RecordDriverPluginManager');
        $this->driver = $recordFactory->getSolrRecord($metadata);
        return $this->driver;
    }

    /**
     * Load the record requested by the user; note that this is not done in the
     * init() method since we don't want to perform an expensive search twice
     * when homeAction() forwards to another method.
     *
     * @return AbstractRecordDriver
     */
    protected function loadRecord()
    {
        $id = $this->params()->fromRoute('id', $this->params()->fromQuery('id'));
        // 0 = preview record
        if ($id != '0') {
            return parent::loadRecord();
        }
        $data = $this->params()->fromPost(
            'data', $this->params()->fromQuery('data', '')
        );
        $format = $this->params()->fromPost(
            'format', $this->params()->fromQuery('format', '')
        );
        $source = $this->params()->fromPost(
            'source', $this->params()->fromQuery('source', '')
        );
        if (!$data) {
            // Support marc parameter for Voyager compatibility
            $format = 'marc';
            if (!$source) {
                $source = '_marc_preview';
            }
            $data = $this->params()->fromPost(
                'marc', $this->params()->fromQuery('marc')
            );
            // For some strange reason recent Voyager versions double-encode the data
            // with encodeURIComponent
            if (substr($data, -3) == '%1D') {
                $data = urldecode($data);
            }
            // Voyager doesn't tell the proper encoding, so it's up to the browser to
            // decide. Try to handle both UTF-8 and ISO-8859-1.
            $len = (int)substr($data, 0, 5);
            if (strlen($data) != $len) {
                $data = $this->decodeVoyagerUTF8($data);
            }
            $marc = new \File_MARC($data, \File_MARC::SOURCE_STRING);
            $record = $marc->next();
            if (false === $record) {
                throw new \Exception('Missing record data');
            }
            $data = $record->toXML();
            $data = preg_replace('/[\x00-\x09,\x11,\x12,\x14-\x1f]/', '', $data);
            $data = iconv('UTF-8', 'UTF-8//IGNORE', $data);
        }
        if (!$data || !$format || !$source) {
            throw new \Exception('Missing parameters');
        }

        return $this->loadPreviewRecord($data, $format, $source);
    }

    /**
     * Display a particular tab.
     *
     * @param string $tab  Name of tab to display
     * @param bool   $ajax Are we in AJAX mode?
     *
     * @return mixed
     */
    protected function showTab($tab, $ajax = false)
    {
        // Special case -- handle lightbox login request if login has already been
        // done
        if ($this->params()->fromQuery('layout', 'false') == 'lightbox'
            && $this->params()->fromQuery('catalogLogin', 'false') == 'true'
            && is_array($patron = $this->catalogLogin())
        ) {
            $response = $this->getResponse();
            $response->setStatusCode(205);
            return $response;
        }

        $view = parent::showTab($tab, $ajax);
        //$view->scrollData = $this->resultScroller()->getScrollData($driver);

        $this->getSearchMemory()->rememberScrollData($view->scrollData);
        return $view;
    }

    /**
     * Decode double-encoded UTF-8 received from Voyager
     *
     * @param string $str String to decode
     *
     * @return string
     */
    protected function decodeVoyagerUTF8($str)
    {
        $result = '';
        $len = strlen($str);
        for ($i = 0; $i < $len; $i++) {
            $c1 = ord($str[$i]);
            $c2 = $i + 1 < $len ? ord($str[$i + 1]) : 0;
            $c3 = $i + 2 < $len ? ord($str[$i + 2]) : 0;
            $c4 = $i + 3 < $len ? ord($str[$i + 3]) : 0;
            $c5 = $i + 4 < $len ? ord($str[$i + 4]) : 0;
            $c6 = $i + 5 < $len ? ord($str[$i + 5]) : 0;

            if ($c1 < 0x80) {
                $result .= chr($c1);
            } elseif ($c1 < 0xE0) {
                $c = (($c1 & 0x1F) << 6) + ($c2 & 0x3F);
                $result .= chr($c);
                $i += 1;
            } elseif ($c1 < 0xF0) {
                $c = (($c1 & 0x0F) << 12) + (($c2 & 0x3F) << 6) + ($c3 & 0x3F);
                $result .= chr($c);
                $i += 2;
            } elseif ($c1 < 0xF8) {
                $c = (($c1 & 0x07) << 18) + (($c2 & 0x3F) << 12)
                    + (($c3 & 0x3F) << 6) + ($c4 & 0x3F);
                $result .= chr($c);
                $i += 3;
            } elseif ($c1 < 0xFC) {
                $c = (($c1 & 0x03) << 24) + (($c2 & 0x3F) << 18)
                    + (($c3 & 0x3F) << 12) + (($c4 & 0x3F) << 6) + ($c5 & 0x3F);
                $result .= chr($c);
                $i += 4;
            } elseif ($c1 < 0xFE) {
                $c = (($c1 & 0x01) << 30) + (($c2 & 0x3F) << 24)
                    + (($c3 & 0x3F) << 18) + (($c4 & 0x3F) << 12)
                    + (($c5 & 0x3F) << 6) + ($c6 & 0x3F);
                $result .= chr($c);
                $i += 5;
            } else {
                $c = chr(0xFF);
            }
        }
        return $result;
    }

    /**
     * Action for dealing with holds.
     *
     * @return mixed
     */
    public function holdAction()
    {
        $driver = $this->loadRecord();

        // Stop now if the user does not have valid catalog credentials available:
        if (!is_array($patron = $this->catalogLogin())) {
            return $patron;
        }

        // If we're not supposed to be here, give up now!
        $catalog = $this->getILS();
        $checkHolds = $catalog->checkFunction(
            'Holds',
            [
                'id' => $driver->getUniqueID(),
                'patron' => $patron
            ]
        );
        if (!$checkHolds) {
            return $this->redirectToRecord();
        }

        // Do we have valid information?
        // Sets $this->logonURL and $this->gatheredDetails
        $gatheredDetails = $this->holds()->validateRequest($checkHolds['HMACKeys']);
        if (!$gatheredDetails) {
            return $this->redirectToRecord();
        }

        // Block invalid requests:
        $validRequest = $catalog->checkRequestIsValid(
            $driver->getUniqueID(), $gatheredDetails, $patron
        );
        if ((is_array($validRequest) && !$validRequest['valid']) || !$validRequest) {
            $this->flashMessenger()->addErrorMessage(
                is_array($validRequest)
                    ? $validRequest['status'] : 'hold_error_blocked'
            );
            return $this->redirectToRecord('#top');
        }

        // Send various values to the view so we can build the form:
        $requestGroups = $catalog->checkCapability(
            'getRequestGroups', [$driver->getUniqueID(), $patron, $gatheredDetails]
        ) ? $catalog->getRequestGroups(
            $driver->getUniqueID(), $patron, $gatheredDetails
        ) : [];
        $extraHoldFields = isset($checkHolds['extraHoldFields'])
            ? explode(":", $checkHolds['extraHoldFields']) : [];

        $requestGroupNeeded = in_array('requestGroup', $extraHoldFields)
            && !empty($requestGroups)
            && (empty($gatheredDetails['level'])
                || ($gatheredDetails['level'] != 'copy'
                    || count($requestGroups) > 1));

        $pickupDetails = $gatheredDetails;
        if (!$requestGroupNeeded && !empty($requestGroups)
            && count($requestGroups) == 1
        ) {
            // Request group selection is not required, but we have a single request
            // group, so make sure pickup locations match with the group
            $pickupDetails['requestGroupId'] = $requestGroups[0]['id'];
        }
        $pickup = $catalog->getPickUpLocations($patron, $pickupDetails);

        // Process form submissions if necessary:
        if (null !== $this->params()->fromPost('placeHold')) {
            // If the form contained a pickup location or request group, make sure
            // they are valid:
            $validGroup = $this->holds()->validateRequestGroupInput(
                $gatheredDetails, $extraHoldFields, $requestGroups
            );
            $validPickup = $validGroup && $this->holds()->validatePickUpInput(
                $gatheredDetails['pickUpLocation'] ?? '', $extraHoldFields, $pickup
            );
            if (!$validGroup) {
                $this->flashMessenger()
                    ->addMessage('hold_invalid_request_group', 'error');
            } elseif (!$validPickup) {
                $this->flashMessenger()->addMessage('hold_invalid_pickup', 'error');
            } elseif (in_array('acceptTerms', $extraHoldFields)
                && empty($gatheredDetails['acceptTerms'])
            ) {
                $this->flashMessenger()->addMessage(
                    'must_accept_terms', 'error'
                );
            } else {
                // If we made it this far, we're ready to place the hold;
                // if successful, we will redirect and can stop here.

                // Add Patron Data to Submitted Data
                $holdDetails = $gatheredDetails + ['patron' => $patron];

                // Attempt to place the hold:
                $function = (string)$checkHolds['function'];
                $results = $catalog->$function($holdDetails);

                // Success: Go to Display Holds
                if (isset($results['success']) && $results['success'] == true) {
                    $msg = [
                        'html' => true,
                        'msg' => 'hold_place_success_html',
                        'tokens' => [
                            '%%url%%' => $this->url()->fromRoute('myresearch-holds')
                        ],
                    ];
                    $this->flashMessenger()->addMessage($msg, 'success');
                    return $this->redirectToRecord('#top');
                } else {
                    // Failure: use flash messenger to display messages, stay on
                    // the current form.
                    if (isset($results['status'])) {
                        $this->flashMessenger()
                            ->addMessage($results['status'], 'error');
                    }
                    if (isset($results['sysMessage'])) {
                        $this->flashMessenger()
                            ->addMessage($results['sysMessage'], 'error');
                    }
                }
            }
        }

        // Find and format the default required date:
        $defaultRequired = $this->holds()->getDefaultRequiredDate(
            $checkHolds, $catalog, $patron, $gatheredDetails
        );
        $defaultRequired = $this->serviceLocator->get('VuFind\DateConverter')
            ->convertToDisplayDate("U", $defaultRequired);
        try {
            $defaultPickup
                = $catalog->getDefaultPickUpLocation($patron, $gatheredDetails);
        } catch (\Exception $e) {
            $defaultPickup = false;
        }
        try {
            $defaultRequestGroup = empty($requestGroups)
                ? false
                : $catalog->getDefaultRequestGroup($patron, $gatheredDetails);
        } catch (\Exception $e) {
            $defaultRequestGroup = false;
        }

        $view = $this->createViewModel(
            [
                'gatheredDetails' => $gatheredDetails,
                'pickup' => $pickup,
                'defaultPickup' => $defaultPickup,
                'homeLibrary' => $this->getUser()->home_library,
                'extraHoldFields' => $extraHoldFields,
                'defaultRequiredDate' => $defaultRequired,
                'requestGroups' => $requestGroups,
                'defaultRequestGroup' => $defaultRequestGroup,
                'requestGroupNeeded' => $requestGroupNeeded,
                'helpText' => $checkHolds['helpText'] ?? null,
                'acceptTermsText' => $checkHolds['acceptTermsText'] ?? null
            ]
        );
        $view->setTemplate('record/hold');
        return $view;
    }

    /**
     * Action for dealing with storage retrieval requests.
     *
     * @return mixed
     */
    public function storageRetrievalRequestAction()
    {
        $driver = $this->loadRecord();

        // Stop now if the user does not have valid catalog credentials available:
        if (!is_array($patron = $this->catalogLogin())) {
            return $patron;
        }

        // If we're not supposed to be here, give up now!
        $catalog = $this->getILS();
        $checkRequests = $catalog->checkFunction(
            'StorageRetrievalRequests',
            [
                'id' => $driver->getUniqueID(),
                'patron' => $patron
            ]
        );
        if (!$checkRequests) {
            return $this->redirectToRecord();
        }

        // Do we have valid information?
        // Sets $this->logonURL and $this->gatheredDetails
        $gatheredDetails = $this->storageRetrievalRequests()->validateRequest(
            $checkRequests['HMACKeys']
        );
        if (!$gatheredDetails) {
            return $this->redirectToRecord();
        }

        // Block invalid requests:
        $validRequest = $catalog->checkStorageRetrievalRequestIsValid(
            $driver->getUniqueID(), $gatheredDetails, $patron
        );
        if ((is_array($validRequest) && !$validRequest['valid']) || !$validRequest) {
            $this->flashMessenger()->addErrorMessage(
                is_array($validRequest)
                    ? $validRequest['status']
                    : 'storage_retrieval_request_error_blocked'
            );
            return $this->redirectToRecord('#top');
        }

        // Send various values to the view so we can build the form:
        $pickup = $catalog->getPickUpLocations($patron, $gatheredDetails);
        $extraFields = isset($checkRequests['extraFields'])
            ? explode(":", $checkRequests['extraFields']) : [];

        // Process form submissions if necessary:
        if (null !== $this->params()->fromPost('placeStorageRetrievalRequest')) {
            if (in_array('acceptTerms', $extraFields)
                && empty($gatheredDetails['acceptTerms'])
            ) {
                $this->flashMessenger()->addMessage(
                    'must_accept_terms', 'error'
                );
            } else {
                // If we made it this far, we're ready to place the hold;
                // if successful, we will redirect and can stop here.

                // Add Patron Data to Submitted Data
                $details = $gatheredDetails + ['patron' => $patron];

                // Attempt to place the hold:
                $function = (string)$checkRequests['function'];
                $results = $catalog->$function($details);

                // Success: Go to Display Storage Retrieval Requests
                if (isset($results['success']) && $results['success'] == true) {
                    $msg = [
                        'html' => true,
                        'msg' => 'storage_retrieval_request_place_success_html',
                        'tokens' => [
                            '%%url%%' => $this->url()
                                ->fromRoute('myresearch-storageretrievalrequests')
                        ],
                    ];
                    $this->flashMessenger()->addMessage($msg, 'success');
                    return $this->redirectToRecord('#top');
                } else {
                    // Failure: use flash messenger to display messages, stay on
                    // the current form.
                    if (isset($results['status'])) {
                        $this->flashMessenger()->addMessage(
                            $results['status'], 'error'
                        );
                    }
                    if (isset($results['sysMessage'])) {
                        $this->flashMessenger()
                            ->addMessage($results['sysMessage'], 'error');
                    }
                }
            }
        }

        // Find and format the default required date:
        $defaultRequired = $this->storageRetrievalRequests()
            ->getDefaultRequiredDate($checkRequests);
        $defaultRequired = $this->serviceLocator->get('VuFind\DateConverter')
            ->convertToDisplayDate("U", $defaultRequired);
        try {
            $defaultPickup
                = $catalog->getDefaultPickUpLocation($patron, $gatheredDetails);
        } catch (\Exception $e) {
            $defaultPickup = false;
        }

        $view = $this->createViewModel(
            [
                'gatheredDetails' => $gatheredDetails,
                'pickup' => $pickup,
                'defaultPickup' => $defaultPickup,
                'homeLibrary' => $this->getUser()->home_library,
                'extraFields' => $extraFields,
                'defaultRequiredDate' => $defaultRequired,
                'helpText' => $checkRequests['helpText'] ?? null,
                'acceptTermsText' => $checkRequests['acceptTermsText'] ?? null
            ]
        );
        $view->setTemplate('record/storageretrievalrequest');
        return $view;
    }

    /**
     * Action for dealing with ILL requests.
     *
     * @return mixed
     */
    public function illRequestAction()
    {
        $driver = $this->loadRecord();

        // Stop now if the user does not have valid catalog credentials available:
        if (!is_array($patron = $this->catalogLogin())) {
            return $patron;
        }

        // If we're not supposed to be here, give up now!
        $catalog = $this->getILS();
        $checkRequests = $catalog->checkFunction(
            'ILLRequests',
            [
                'id' => $driver->getUniqueID(),
                'patron' => $patron
            ]
        );
        if (!$checkRequests) {
            return $this->redirectToRecord();
        }

        // Do we have valid information?
        // Sets $this->logonURL and $this->gatheredDetails
        $gatheredDetails = $this->ILLRequests()->validateRequest(
            $checkRequests['HMACKeys']
        );
        if (!$gatheredDetails) {
            return $this->redirectToRecord();
        }

        // Block invalid requests:
        $validRequest = $catalog->checkILLRequestIsValid(
            $driver->getUniqueID(), $gatheredDetails, $patron
        );
        if ((is_array($validRequest) && !$validRequest['valid']) || !$validRequest) {
            $this->flashMessenger()->addErrorMessage(
                is_array($validRequest)
                    ? $validRequest['status'] : 'ill_request_error_blocked'
            );
            return $this->redirectToRecord('#top');
        }

        // Send various values to the view so we can build the form:

        $extraFields = isset($checkRequests['extraFields'])
            ? explode(":", $checkRequests['extraFields']) : [];

        // Process form submissions if necessary:
        if (null !== $this->params()->fromPost('placeILLRequest')) {
            if (in_array('acceptTerms', $extraFields)
                && empty($gatheredDetails['acceptTerms'])
            ) {
                $this->flashMessenger()->addMessage(
                    'must_accept_terms', 'error'
                );
            } else {
                // If we made it this far, we're ready to place the hold;
                // if successful, we will redirect and can stop here.

                // Add Patron Data to Submitted Data
                $details = $gatheredDetails + ['patron' => $patron];

                // Attempt to place the hold:
                $function = (string)$checkRequests['function'];
                $results = $catalog->$function($details);

                // Success: Go to Display ILL Requests
                if (isset($results['success']) && $results['success'] == true) {
                    $msg = [
                        'html' => true,
                        'msg' => 'ill_request_place_success_html',
                        'tokens' => [
                            '%%url%%' => $this->url()
                                ->fromRoute('myresearch-illrequests')
                        ],
                    ];
                    $this->flashMessenger()->addMessage($msg, 'success');
                    return $this->redirectToRecord('#top');
                } else {
                    // Failure: use flash messenger to display messages, stay on
                    // the current form.
                    if (isset($results['status'])) {
                        $this->flashMessenger()
                            ->addMessage($results['status'], 'error');
                    }
                    if (isset($results['sysMessage'])) {
                        $this->flashMessenger()
                            ->addMessage($results['sysMessage'], 'error');
                    }
                }
            }
        }

        // Find and format the default required date:
        $defaultRequired = $this->ILLRequests()
            ->getDefaultRequiredDate($checkRequests);
        $defaultRequired = $this->serviceLocator->get('VuFind\DateConverter')
            ->convertToDisplayDate("U", $defaultRequired);

        // Get pickup libraries
        $pickupLibraries = $catalog->getILLPickUpLibraries(
            $driver->getUniqueID(), $patron, $gatheredDetails
        );

        // Get pickup locations. Note that these are independent of pickup library,
        // and library specific locations must be retrieved when a library is
        // selected.
        $pickupLocations = $catalog->getPickUpLocations($patron, $gatheredDetails);

        $view = $this->createViewModel(
            [
                'gatheredDetails' => $gatheredDetails,
                'pickupLibraries' => $pickupLibraries,
                'pickupLocations' => $pickupLocations,
                'homeLibrary' => $this->getUser()->home_library,
                'extraFields' => $extraFields,
                'defaultRequiredDate' => $defaultRequired,
                'helpText' => $checkRequests['helpText'] ?? null,
                'acceptTermsText' => $checkRequests['acceptTermsText'] ?? null
            ]
        );
        $view->setTemplate('record/illrequest');
        return $view;
    }

    public function resourceAction()
    {
        $url = $this->params()->fromQuery('url');

        // TODO: file_get_contents fails with redirects...
        $tmp = tempnam(sys_get_temp_dir(), 'resource');
        $fp = fopen($tmp, 'w+');
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_TIMEOUT, 50);
        curl_setopt($ch, CURLOPT_FILE, $fp); 
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_exec($ch); 
        curl_close($ch);
        fclose($fp);
        $content = file_get_contents($tmp);
        

        //$content = file_get_contents($url);

        $response = $this->getResponse();
        $response->setContent($content);
        $headers = $response->getHeaders();
        $headers->addHeaderLine('Content-Type', 'application/pdf')
            ->addHeaderLine('Content-Length', strlen($content));

        return $this->response;
    }
}
