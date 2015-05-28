<?php

/**
 * NotificationController.php
 *
 * LICENCE
 *
 * L'ensemble de ce code relève de la législation française et internationale
 * sur le droit d'auteur et la propriété intellectuelle. Tous les droits de
 * reproduction sont réservés, y compris pour les documents téléchargeables et
 * les représentations iconographiques et photographiques. La reproduction de
 * tout ou partie de ce code sur quelque support que ce soit est formellement
 * interdite sauf autorisation écrite émanant de la société DIGITALEO.
 *
 * PHP Version 5.3
 *
 * @category Digitaleo
 * @package  Application.Module.Frontoffice.Controllers
 * @author   Digitaleo <developpement@digitaleo.com>
 * @license  http://www.digitaleo.net/licence.txt Digitaleo Licence
 * @link     http://www.digitaleo.net
 *
 */

/**
 * Description de la classe : NotificationController
 *
 * @category Digitaleo
 * @package  Application.Module.Frontoffice.Controllers
 * @author   Digitaleo <developpement@digitaleo.com>
 * @license  http://www.digitaleo.net/licence.txt Digitaleo Licence
 * @link     http://www.digitaleo.net
 */
class Frontoffice_NotificationController extends Zend_Controller_Action
{

    /**
     * Number of result to return in a search query
     *
     * @var int
     */
    protected $_searchLimit = 10;

    /* @var */
    protected $_notificationApi;

    /**
     * Initialisation du controller
     *
     * @return void
     */
    public function init()
    {
        $lib = Dm_Config::GetConfig('notifications', 'sms.rest');
        $connectedUser = Dm_Session::GetEntry(Dm_Session::CONNECTED_USER);
        $this->_notificationApi = Mk_Factory::getRestWrapper($lib, $connectedUser->userKey);

        // Jquery 1.8.2 pour le calendrier bootstrap 3
        $this->view->jQuery()->setLocalPath('/scripts/jquery/jquery-1.8.2.min.js');
        $hScript = $this->view->headScript();
        $hScript->appendFile('/scripts/bootstrap-ui/ui-bootstrap-tpls-0.10.0.js');
        $hScript->appendFile('/scripts/angularjs/tools/authentication/directive.js');
        $hScript->appendFile('/scripts/angularjs/notification/application/notification.js');
        $hScript->appendFile('/scripts/angularjs/notification/controllers/list.js');
        $hScript->appendFile('/scripts/angularjs/notification/controllers/editor.js');
        $hScript->appendFile('/scripts/angularjs/notification/services/notification.js');
        $hScript->appendFile('/scripts/angularjs/sms/services/customFields.js');
        $hScript->appendFile('/scripts/angularjs/notification/filters/list.js');
        $hScript->appendFile('/scripts/angularjs/notification-sms-settings/services/sms-template.js');
        $hScript->appendFile('/scripts/angularjs/sms/directives/counterSms.js');
        $hScript->appendFile('/scripts/bootstrap-daterangepicker/js/moment.min.js');
        $hScript->appendFile('/scripts/moment/moment-with-langs.min.js');
        $hScript->appendFile('/scripts/bootstrap-daterangepicker/js/daterangepicker.js');
        $hScript->appendFile('/scripts/bootstrap-datetimepicker/js/bootstrap-datetimepicker.js');
        $this->view->headLink()->appendStylesheet('/scripts/bootstrap-daterangepicker/css/daterangepicker-bs3.css');
        $this->view->headLink()
            ->appendStylesheet('/scripts/bootstrap-datetimepicker/css/bootstrap-datetimepicker.min.css');
        $this->view->headLink()->appendStylesheet('/scripts/angularjs/notification/views/notification.css');

        $this->view->searchLimit = $this->_searchLimit;

        // set json context
        $this->_helper->getHelper('contextSwitch')
            ->setActionContext('customfields', array('json'))
            ->setActionContext('tpoa', array('json'))
            ->setActionContext('getcontacts', array('json'))
            ->setActionContext('rest', array('json'))
            ->setActionContext('restResponse', array('json'))
            ->initContext('json');
    }

    /**
     * Retourne la liste des customFields
     *
     * @return json
     */
    public function customfieldsAction()
    {
        $translatedFields = array();
        $customFields = Service_Contact::GetColumns();
        foreach ($customFields as $customField => $trads) {
            $isAlwaysSearchable = in_array($customField, array("lastName", "mobile"));
            $searchable = in_array($customField, array("firstName", "lastName", "mobile"));
            $isActive = in_array($customField, array("lastName", "mobile"));
            $translatedFields[] = array(
                "customField" => $customField,
                "translation" => $trads,
                "isActive" => $isActive,
                "value" => "",
                "type" => "text",
                "maxLength" => "",
                "searchable" => $searchable,
                "isAlwaysSearchable" => $isAlwaysSearchable
            );
        }

        // Add personnal fields to custom fields
        $connectedUser = Dm_Session::GetEntry(Dm_Session::CONNECTED_USER);
        $personnalFields = $connectedUser->getParameterValue('personnalFieldsForSmsNotif');
        $return = array_merge($translatedFields, (array) json_decode($personnalFields, true));
        $this->_helper->json($return);
    }

    /**
     * Retourne la liste des tpoa
     *
     * @return json
     */
    public function tpoaAction()
    {
        $tpoaList = array();
        $contract = Dm_Session::GetConnectedUserContract();
        $limitedCustomizeTpoa = $contract->getParameterValue('limitedCustomizeTpoa');
        if (strlen($limitedCustomizeTpoa) > 0) {
            foreach (explode(',', $limitedCustomizeTpoa) as $tpoa) {
                array_push($tpoaList, $tpoa);
            }
        }
        $this->_helper->json($tpoaList);
    }

    /**
     * Retourne la liste des contacts recherchés
     *
     * @return json
     */
    public function getcontactsAction()
    {
        $this->_helper->layout()->disableLayout();
        $this->_helper->viewRenderer->setNoRender(true);

        $objData = json_decode($this->getRequest()->getRawBody());
        $objDataSearch = $objData->keyword;
        $objDataLimit = (isset($objData->limit)) ? $objData->limit : 10;
        if (!$objDataSearch) {
            return null;
        }

        $lib = Dm_Config::GetConfig('mk', 'library.contact.rest');
        $connectedUser = Dm_Session::GetEntry(Dm_Session::CONNECTED_USER);
        $return = array();

        //Complete the result if needed by a search in main contact lib
        $wrapper = Mk_Factory::getRestWrapper($lib, $connectedUser->userKey);
        $params = array(
            'category' => 'NOTIFICATION',
            'properties' => array('id', 'email', 'phone', 'mobile', 'firstName', 'lastName', 'fax',
                'birthDate', 'company', 'reference', 'address1',
                'address2', 'zipcode', 'city', 'state', 'country', 'field01', 'field02', 'field03', 'field04',
                'field05', 'field06', 'field07', 'field08', 'field09', 'field10', 'field11', 'field12',
                'field13', 'field14', 'field15', 'smsOptout')
        );

        // recherche via un numéro de portable
        if (isset($objDataSearch->mobile)) {
            $phone = $objDataSearch->mobile;
            $operator = 'STARTSWITH';
            $num = array();
            if (preg_match('/^0([0-9]{9})$/', $phone, $num) === 1) {
                $phone = '+33' . $num[1];
            }

            if (preg_match('/^\+33([0-9]{9})$/', $phone, $num) === 1) {
                // +33 dans le numéro
                $operator = '=';
            }

            $params['complex'] = array(
                array(
                    'field' => 'mobile',
                    'operator' => $operator,
                    'value' => $phone,
                ));
        }

        if (isset($objDataSearch->firstName)) {
            $firstName = $objDataSearch->firstName;
            $operator = 'STARTSWITH';

            $params['complex'] = array(
                array(
                    'field' => 'firstName',
                    'operator' => $operator,
                    'value' => $firstName,
                ));
        }

        if (isset($objDataSearch->lastName)) {
            $lastName = $objDataSearch->lastName;
            $operator = 'STARTSWITH';

            $params['complex'] = array(
                array(
                    'field' => 'lastName',
                    'operator' => $operator,
                    'value' => $lastName,
                ));
        }

        $params['limit'] = $objDataLimit;
        $params['sort'] = 'id DESC'; // On force le tri du plus récent au plus ancien
        $params['offset'] = 0;
        $params['total'] = 1;

        $contacts = $wrapper->contactsRead($params);
        if ($contacts->total > 0) {
            $return = $contacts->list;
        }

        $this->_helper->json($return);

    }

    /**
     * Affiche la page de base des notifications
     *
     * @return string [html]
     */
    public function indexAction()
    {

    }

    /**
     * Retourne la liste des status
     *
     * @return string [html]
     */
    public function getStatusAction()
    {
        $statuses = Service_Api_Object_Message::$STATUS_BY_MEDIA[Service_Api_Object_Message::SMS];
        foreach ($statuses as $status) {
            $trsStatuses[$status] = ucfirst($this->view->translate($status . '.export'));
        }
        $params = array();
        $params['availableStatuses'] = $trsStatuses;
        $params['searchLimit'] = $this->_searchLimit;
        $params['editorEnabled'] = intval(!$this->_getParam('sent', 0));

        $this->_helper->json($params);
    }

    /**
     * List responses for a given sms
     *
     * @return json list of responses
     */
    public function restResponseAction()
    {
        Dm_Log::Error(__METHOD__);
        try {
            switch ($this->getRequest()->getMethod()) {
                case 'GET' :
                    $response = $this->_readResponse();
                    break;
                default:
                    throw new BadMethodCallException("Method not recognized");
            }
        } catch (BadMethodCallException $exc) {
            $this->getResponse()->setHttpResponseCode(405);
            $response = array("error" => $exc->getMessage());
        } catch (InvalidArgumentException $exc) {
            $this->getResponse()->setHttpResponseCode(401);
            $response = array("error" => $exc->getMessage());
        } catch (Exception $exc) {
            $this->getResponse()->setHttpResponseCode(500);
            $response = array("error" => $exc->getMessage());
        }

        $this->_helper->json($response, true);
    }

    /**
     * Retourne la liste des réponses
     *
     * @return array
     */
    private function _readResponse()
    {
        $this->_helper->layout()->disableLayout();

        $offset = intval($this->_getParam('offset', 1)) - 1;
        $perPage = $this->_searchLimit;
        $getNotifications = $this->_getParam('responses', true);

        $responsesParams = array(
            'limit' => $perPage,
            'offset' => $offset,
            'total' => false,
            'sort' => 'date DESC'
        );

        $responses = $this->_notificationApi->responseRead($responsesParams);

        $notificationIds = array();
        if (property_exists($responses, 'list') && is_array($responses->list)) {
            foreach ($responses->list as $listId => $response) {
                if (!array_key_exists($response->notificationId, $notificationIds)) {
                    $notificationIds[$response->notificationId] = array();
                }
                $notificationIds[$response->notificationId][] = $listId;
            }
        }

        // if there is responses, we have to get notifications informations
        if (count($notificationIds) > 0 && $getNotifications === true) {
            $params = array('notificationId' => array_keys($notificationIds),
                'properties' => array(
                    'id',
                    'civility',
                    'firstName',
                    'lastName',
                    'date',
                    'mobile',
                    'parts',
                    'tpoa',
                    'text',
                    'status',
                    'templateId'
                )
            );

            $notifications = $this->_notificationApi->notificationRead($params);

            if (property_exists($notifications, 'list') && is_array($notifications->list)) {
                foreach ($notifications->list as $notification) {
                    if (array_key_exists($notification->id, $notificationIds)) {
                        foreach ($notificationIds[$notification->id] as $listId) {
                            $responses->list[$listId]->notification = $notification;
                        }
                    }
                }
            }
        }
        return $responses;
    }

    /**
     * Rest access to controller
     *
     * @return JSON
     */
    public function restAction()
    {
        try {
            switch ($this->getRequest()->getMethod()) {
                case 'GET' :
                    $response = $this->_read();
                    break;
                case 'PUT' :
                    $response = $this->_send();
                    break;
                case 'DELETE' :
                    $response = $this->_delete();
                    break;
                default:
                    throw new BadMethodCallException("Method not recognized");
            }
        } catch (BadMethodCallException $exc) {
            $this->getResponse()->setHttpResponseCode(405);
            $response = array("error" => $exc->getMessage());
        } catch (InvalidArgumentException $exc) {
            $this->getResponse()->setHttpResponseCode(401);
            $response = array("error" => $exc->getMessage());
        } catch (Exception $exc) {
            $this->getResponse()->setHttpResponseCode(500);
            $response = array("error" => $exc->getMessage());
        }

        $this->_helper->json($response, true);
    }

    /**
     * Retourne la liste des notifs
     *
     * @return array
     */
    private function _read()
    {
        $this->_helper->layout()->disableLayout();

        $offset = intval($this->_getParam('offset', 1)) - 1;
        $perPage = $this->_searchLimit;

        $params = array(
            'limit' => $perPage,
            'offset' => $offset * $perPage,
            'total' => true
        );
        $status = $this->_getParam('status');
        if ($status != null) {
            if (preg_match('#^\[#', $status) === 1) {
                $params['status'] = json_decode($status, true);
            } else {
                if ($status !== '""') {
                    $params['status'] = array($status);
                }
            }
        }
        $id = $this->_getParam('id');
        if ($id != null) {
            $params['id'] = $id;
        }
        $search = $this->_getParam('search');
        if ($search != null) {
            $params['search'] = $search;
        }
        $template = $this->_getParam('template');
        if (is_numeric($template) && $template > 0) {
            $params['templateId'] = $template;
        }

        $date = $this->_getParam('date');

        if (preg_match('#^\{#', $date)) {
            $dateObj = json_decode($date);
            if ($dateObj->start != '' && $dateObj->end != '') {
                $format_api = 'Y-m-d';
                $format_ihm = 'd-m-Y';
                $dateStart = date_format(DateTime::createFromFormat($format_ihm, $dateObj->start), $format_api);
                $dateEnd = date_format(DateTime::createFromFormat($format_ihm, $dateObj->end), $format_api);

                $a = array(
                    'logical' => 'AND',
                    array(
                        'operator' => '>=',
                        'value' => $dateStart,
                    ),
                    array(
                        'operator' => '<',
                        'value' => $dateEnd,
                    ),
                );
                $params['date'] = $a;
            }
        }

        $params['sort'] = 'date DESC';
        $params['properties'] = array(
            'id',
            'civility',
            'firstName',
            'lastName',
            'date',
            'mobile',
            'parts',
            'tpoa',
            'text',
            'status',
            'templateId'
        );


        $notifications = $this->_notificationApi->notificationRead($params);

        // POUR CHAQUE NOTIF :
        // préparation de la récupération des réponses et du stockage de celles ci
        $smsIds = array();
        if (property_exists($notifications, 'list') && is_array($notifications->list)) {
            foreach ($notifications->list as $listId => $notification) {
                if ($notification->status === Service_Api_Object_Message::STATUS_ANSWERED) {
                    $smsIds[$notification->id] = $listId;
                }
                $notification->responses = array();
            }
        }

        // READ RESPONSES :
        // récupération des réponses
        if (count($smsIds) > 0) {
            $responsesParams = array('notificationId' => array_keys($smsIds));
            $responses = $this->_notificationApi->responseRead($responsesParams);
            if (property_exists($responses, 'list') && is_array($responses->list)) {
                foreach ($responses->list as $response) {
                    // ajout de l'info de la réponse à la notification d'après l'id de notif
                    if (
                        array_key_exists($response->notificationId, $smsIds) &&
                        array_key_exists($smsIds[$response->notificationId], $notifications->list)
                    ) {
                        $notifications->list[$smsIds[$response->notificationId]]->responses[] = $response;
                    }
                }
            }
        }

        return $notifications;
    }

    /**
     * Supprime un message
     *
     * @return array
     */
    private
    function _delete()
    {
        $this->_helper->layout()->disableLayout();

        // Get the message
        $objMessage = json_decode($this->getRequest()->getRawBody());

        // Check the message
        if ($objMessage === null || !isset($objMessage->message)) {
            throw new InvalidArgumentException;
        }

        // Check message Status
        if ($objMessage->message->status !== Service_Api_Object_Message::STATUS_PROCESSING) {
            throw new InvalidArgumentException;
        }
        $ret = $this->_notificationApi->notificationDelete(array('id' => $objMessage->message->id));
        return $ret;
    }

    /**
     * Envoie une notif
     *
     * @return array
     */
    private function _send()
    {
        $objData = json_decode($this->getRequest()->getRawBody());
        if ($objData === null) {
            throw new InvalidArgumentException;
        }

        if (!property_exists($objData, 'contact')) {
            throw new InvalidArgumentException;
        }

        $lib = Dm_Config::GetConfig('mk', 'library.contact.rest');
        $connectedUser = Dm_Session::GetEntry(Dm_Session::CONNECTED_USER);
        $wrapper = Mk_Factory::getRestWrapper($lib, $connectedUser->userKey);
        $newContact = array();
        foreach ($objData->contact as $field => $value) {
            $newContact[$field] = $value;
        }
        $wrapper->contactsCreate(
            array(
                'category' => 'NOTIFICATION',
                'contacts' => array($newContact)
            ));
        // preparing contact data matching api spec
        $contact = array();
        $contact['recipient'] = $objData->contact->mobile;
        $contact['civility'] = $objData->contact->civility;
        $contact['firstName'] = $objData->contact->firstName;
        $contact['lastName'] = $objData->contact->lastName;

        if (!$objData->message || $objData->message == '') {
            throw new InvalidArgumentException;
        }
        $sms = $objData->message;

        if (!property_exists($objData, 'date')) {
            throw new InvalidArgumentException;
        }

        $format_api = 'Y-m-d H:i:s';
        $format_ihm = 'd-m-Y H:i';
        $dateOriginal = DateTime::createFromFormat($format_ihm, $objData->date);
        if ($dateOriginal) {
            $date = date_format($dateOriginal, $format_api);
        } else {
            $date = date($format_api);
        }

        $params = array(
            'text' => $sms,
            'response' => true,
            'date' => $date,
            'contacts' => array(
                $contact
            )
        );
        $templateId = $objData->templateId;
        if (is_numeric($templateId) && $templateId > 0) {
            $params['templateId'] = $templateId;
        }

        if (is_string($objData->tpoa) && strlen($objData->tpoa) > 0) {
            $params['tpoa'] = $objData->tpoa;
            $params['response'] = false;
        }
        return $this->_notificationApi->notificationCreate($params);
    }

}
