<?php

/**
 * CampaignController.php
 *
 * PHP Version 5.3
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
 * @category Digitaleo
 * @package  Application.Module.Frontoffice.Controllers
 * @author   Digitaleo <developpement@digitaleo.com>
 * @license  http://www.digitaleo.net/licence.txt Digitaleo Licence
 * @link     http://www.digitaleo.net
 */

/**
 * Description de la classe : CampaignController
 *
 * @category Digitaleo
 * @package  Application.Module.Frontoffice.Controllers
 * @author   Digitaleo <developpement@digitaleo.com>
 * @license  http://www.digitaleo.net/licence.txt Digitaleo Licence
 * @link     http://www.digitaleo.net
 */
class Frontoffice_CampaignController extends Zend_Controller_Action
{
    /* @var $_pushChannels array */
    protected $_pushChannels = array(
        Service_Api_Object_Message::SMS,
        Service_Api_Object_Message::VOICE,
        Service_Api_Object_Message::EMAIL,
        Service_Api_Object_Message::VOICEMAIL
    );


    /* @var $_campaignService Service_Api_Handler_Campaign_Interface */
    protected $_campaignService;

    /* @var $_contactAdapter Mk_Contacts_Contact_Adapter_Interface */
    protected $_contactAdapter;

    /* @var $_contactListService Mk_Contacts_ContactList_Adapter_Interface */
    protected $_contactListService;
    protected $_excelContext;
    protected $_pdfContext;

    /* @var $_messengeoStatusFaked array */
    protected $_messengeoStatusFaked = array(
        Service_Api_Object_Campaign::STATUS_SCHEDULED,
        Service_Api_Object_Campaign::STATUS_RUNNING,
        Service_Api_Object_Campaign::STATUS_ENDED,
    );

    /* @var $_messengeoStatus array */
    protected $_messengeoStatus = array(
        Service_Api_Object_Campaign::STATUS_CREATED,
        Service_Api_Object_Campaign::STATUS_CANCELLED,
        Service_Api_Object_Campaign::STATUS_INTERRUPTED,
        Service_Api_Object_Campaign::STATUS_SENT,
        Service_Api_Object_Campaign::STATUS_ENDED,
        Service_Api_Object_Campaign::STATUS_ARCHIVED,
        Service_Api_Object_Campaign::STATUS_PENDING,
    );


    /**
     * Initialisation du helper contextSwitch
     *
     * @return void
     */
    public function init()
    {
        $this->_helper->ajaxContext->initContext();
        $this->_excelContext = $this->_helper->getHelper('ExcelContext');
        // Initialisation du context Excel
        $this->_excelContext
            ->addActionContext('stat-detail', 'xls')
            ->addActionContext('stat-detail', 'file-csv')
            ->initContext();

        // Initialisation du service de gestion de campagnes
        $this->_campaignService = Dm_Session::GetEntry(Dm_Session::SERVICE_FACTORY)->getCampaignService();

        // Initialisation du service de gestion de campagnes
        $this->_contactAdapter = Mk_Factory::GetContactAdapter();
        $this->_contactListService = Mk_Factory::GetContactListAdapter();

        $this->_pdfContext = $this->_helper->getHelper('PdfContext');
        $this->_pdfContext
            ->addActionContext('complete-stats', 'pdf')
            ->addActionContext('complete-stats', 'file-pdf')
            ->initContext();

        $this->_helper->getHelper('contextSwitch')
            ->addActionContext('confirm', 'json')
            ->addActionContext('ajax-save', 'json')
            ->addActionContext('ajax-save-stats-x-days', 'json')
            ->addActionContext('ajax-check-cost', 'json')
            ->addActionContext('get-cost', 'json')
            ->addActionContext('get-next-step', 'json')
            ->addActionContext('ajax-budget', 'json')
            ->initContext('json');
    }

    /**
     * Page d'acceuil
     * Cette page affiche la liste des campagane de l'utilisateur connecté dans des onglets
     * chargés en ajax
     *
     * Parametres :
     * status - (optionnel) etat des campagnes a afficher, si pas specifié on prendre l'etat EDITION
     *
     * @return void
     */
    public function indexAction()
    {
        // Construction de la liste
        $statusList = array();
        $statusList[Service_Api_Object_Campaign::STATUS_EDITING] = array(Service_Api_Object_Campaign::STATUS_EDITING);
        $statusList[Service_Api_Object_Campaign::STATUS_RUNNING] = array(
            Service_Api_Object_Campaign::STATUS_PENDING, Service_Api_Object_Campaign::STATUS_CONFIRMED,
            Service_Api_Object_Campaign::STATUS_CREATED,
            Service_Api_Object_Campaign::STATUS_RUNNING,
            Service_Api_Object_Campaign::STATUS_BUILD_DONE, Service_Api_Object_Campaign::STATUS_BUILD_IN_PROGRESS,
            Service_Api_Object_Campaign::STATUS_CONTACT_SERIALIZE);
        $statusList[Service_Api_Object_Campaign::STATUS_CLOSED] = array(
            Service_Api_Object_Campaign::STATUS_STOPPED, Service_Api_Object_Campaign::STATUS_CLOSED,
            Service_Api_Object_Campaign::STATUS_STOPPED_WHILE_RUNNING, Service_Api_Object_Campaign::STATUS_CANCELED);
        $this->view->statusList = $statusList;
        $this->view->status = $this->_getParam('status', Service_Api_Object_Campaign::STATUS_EDITING);
        $this->view->jQuery()->setLocalPath('/scripts/jquery/jquery-1.8.2.min.js');
//        $this->replaceJs('jquery-ui', '/scripts/jquery/jquery-ui-1.9.2.custom.min.js');

        $this->view->headLink()->appendStylesheet('/styles/ionicons.css');

//        $this->removeCss('jquery-form');
        $hScript = $this->view->headScript();

        $hScript->appendFile('/scripts/bootstrap-ui/ui-bootstrap-tpls-0.10.0.js');
        $hScript->appendFile('/scripts/angularjs/lib/angular-route/angular-route.min.js');
        $hScript->appendFile('/scripts/angularjs/lib/angular-resource/angular-resource.min.js');
        $hScript->appendFile('/scripts/angularjs/lib/angular-cookies/angular-cookies.min.js');

        $hScript->appendFile('/scripts/angularjs/lib/moment/min/moment-with-langs.min.js');
        $hScript->appendFile(
            '/scripts/angularjs/lib/eonasdan-bootstrap-datetimepicker/build/js/bootstrap-datetimepicker.min.js'
        );

        //================================================================================================
        // NG APP & DEPS
        //================================================================================================

        $hScript->appendFile('/scripts/angularjs/campaign/editor/app.js');

        $hScript->appendFile('/scripts/angularjs/campaign/editor/controllers/list.js');

        $hScript->appendFile('/scripts/angularjs/campaign/editor/filters/list-filter.js');
        $hScript->appendFile('/scripts/angularjs/campaign/editor/services/campaign.js');

        $hScript->appendFile('/scripts/angularjs/layout/login/login.js');

        $hScript->appendFile('/scripts/bootstrap-daterangepicker/js/daterangepicker.js');
        $hScript->appendFile('/scripts/bootstrap-daterangepicker/js/moment.min.js');
        $this->view->headLink()->appendStylesheet('/scripts/bootstrap-datetimepicker/css/bootstrap-datetimepicker.css');
        $this->view->headLink()->appendStylesheet('/scripts/bootstrap-daterangepicker/css/daterangepicker-bs3.css');

        //=======================
        // MODULES
        //=======================
        $hScript->appendFile('/scripts/angularjs/layout/infoBadge.js');
        $hScript->appendFile('/scripts/angularjs/tools/dynamic-loader.js');
        $hScript->appendFile('/scripts/angularjs/tools/datetimepicker/directive.js');

        $urlsCreation = array();
        $urlsCreation['classic'] = $this->view->href('campaign-add');

        if ($this->view->HasAccess('createAutomaticCampaign')) {
            $urlsCreation['automatic'] = $this->view->href('automatic-campaign-add');
        }

        // reading network templates
        $templateFilter = new Service_Api_Filter_Template();
        $templateFilter->properties = array('id');
        $readTemplatesResult = $this->_campaignService->templateRead($templateFilter);
        if ($this->view->pageAccess('list-templates') &&
            isset($readTemplatesResult) && $readTemplatesResult->size > 0
        ) {
//            $typeData[Service_Api_Object_Campaign::TYPE_NETWORK] = $this->view->href('list-templates',
//                array('listId' => $listId));
            $urlsCreation['network'] = $this->view->href('list-templates');
        }

        $this->view->urlsCreation = json_encode($urlsCreation);


        // Current month for budget
        $user = Dm_Session::GetConnectedUser();
        $datetime = new Zend_Date(Zend_Registry::get('Zend_Locale'));
        $datetime->setTimeZone($user->timezone);
        $month = $datetime->get(Zend_Date::MONTH_SHORT);

        $this->view->month = $month;
        $this->view->nbTemplates = $readTemplatesResult->size;

    }

    /**
     * Cette page retourne le contenu de la liste des campagnes de l'utilisateur connecté (sans layout)
     *
     * Parametres :
     * status - (optionnel) etat des campagnes a afficher, si pas specifié on prendre l'etat EDITING
     *
     * @return void
     */
    public function ngListAction()
    {
        $campaignId = $this->_getParam('campaignId');
        if (!is_null($campaignId) && !is_numeric($campaignId)) {
            $campaignId = null;
        }

        $rawParams = json_decode($this->getRequest()->getRawBody(), true); // put everything in assoc array
        if (!empty($rawParams)) {
            $this->getRequest()->setParams($rawParams); // request object is a nice params holder
            Dm_Session::setEntry('campaignListFilter', (object)$rawParams,
                Dm_Session::EXPOSED_SESSION); // cast to object to be easily retrieved in json object
        }

        $filterName = $this->_getParam('stringFilter', false);
        $filterDate = $this->_getParam('date', false);
        $filterLimit = $this->_getParam('limit', 10);
        $filterOffset = $this->_getParam('offset', 0);
        $filterType = $this->_getParam('type', null);
        $filterSort = "dateCreated " . $this->_getParam('sort', 'DESC');
        $filterSearch = $this->_getParam('search', null);
        // filtre d'affichage sur les campages créées avec le nouvel éditeur
        //todo : à enlever ultérieument
        //$filterExtSource = array(array('operator'=>"empty","field"=>"extSource"),"logical"=>"AND");
        $filterExtSource = array(array('operator'=>"empty"));
        // zero or null to get old editor
        $filterNewEditor = array(array('operator' => "=", "value" => 0), array('operator' => "empty"), 'logical' => 'OR');

        // Récupération de la campagne
        $status = $this->_getParam('status', $this->_messengeoStatus);

        // Génération du filtre status et complex en fonction des filtres demandés
        $getComplex = Service_Messengeo_Status::ComplexFilter($status);
        $filterComplex = $getComplex->filterComplex;
        $filterStatus = $getComplex->status;

        // Recuperation du status et affectation de la valeur dans le filtre
        // Interrogation de la couche service, on va chercher les infos chez Messengeo directement
        $identity = Zend_Auth::getInstance()->getIdentity();
        $handler = new Service_Api_Handler_Campaign_Messengeo($identity);
        $campaigns = $handler->campaignRead($campaignId, $filterName, $filterStatus, $filterType, $filterDate,
            $filterOffset, $filterLimit, $filterSort, $filterComplex, $filterSearch, null, null, null,$filterExtSource, $filterNewEditor);

        foreach ($campaigns as $campaign) {
            // Mise à jour des status
            $campaign->status = $this->_transformStatus($campaign);
            if (!isset($campaign->type)) {
                $campaign->type = 'STANDARD';
            }
            $campaign->typeTranslated = ucfirst($this->view->translate(strtolower($campaign->type)));
            if (!is_null($campaignId)) {
                $campaignTmp = $this->_campaignService->campaignReadByMessengeoExtId(array($campaign->id));
                if (!isset($campaignTmp->list[0]->id) || is_null($campaignTmp->list[0]->id)) {
                    $this->_helper->json(array(
                        "error" => $this->view->translate("Cannot load campaign") . ' ' .
                            $campaign->id),
                        true);
                }
                $campaignIdFromMessengeo = $campaignTmp->list[0]->id;

                // Récupération des steps
                // Récupération des étapes de la campagne
                $stepFilter = new Service_Api_Filter_Step();
                $stepFilter->campaignId = array($campaignIdFromMessengeo);
                $stepFilter->setSort(array(array('dateExecution', 'ASC')));
                $stepContainer = $this->_campaignService->stepRead($stepFilter);
                // variables used for confirmation control
                $campaignMessages = array();
                $steps = array();
                $stats = array();
                if ($stepContainer->size) {
                    $steps = $stepContainer->list;
                    $stats = $this->_getStatsResume($campaignIdFromMessengeo);
                }

                // Récupération de messages pour chaque étape
                foreach ($steps as $step) {
                    $campaignMessages = array();
                    $messageFilter = new Service_Api_Filter_Message();
                    $messageFilter->stepId = array($step->id);
                    $messageFilter->sort = array(array('priority', 'ASC'));

                    // Récupération de la liste de messages de la campagne
                    $messageList = $this->_campaignService->messageRead($messageFilter);
                    if ($messageList->size) {
                        // Vérification que l'étape contient au moins un message
                        $campaignMessages = $messageList->list;
                    }
                    $step->messages = $campaignMessages;
                }
                $campaign->steps = $steps;
                $campaign->stats = $stats;

            }
        }
        $readResult = $campaigns;

        //__________________________________________________________________________________________
        // BEGIN - PATCH - #14829 -  Probleme de remonté des campagnes de demo
        if (count($readResult) < $filterLimit &&
            Dm_Session::GetConnectedUserContract()->type === 'demonstration' &&
            ($status === Service_Api_Object_Campaign::STATUS_ENDED ||
                in_array(Service_Api_Object_Campaign::STATUS_ENDED, $status))
        ) {
            Dm_Log::Info('WE ASK TO CMPEO TO COMPLETE THE RESULT, BECAUSE WE WANT MORE RESULT');
            //Get STOPPED campaign from CMPEO
            $filter = new Service_Api_Filter_Campaign();
            $filter->status = array(Service_Api_Object_Campaign::STATUS_STOPPED);

            $sortByDate = false;
            if ($sortByDate) {
                $filter->sort = array(array('dateEnd', 'DESC'));
            } else {
                $filter->sort = array(array('id', 'DESC'));
            }

            if (is_string($filterDate) && preg_match('#^\{#', $filterDate)) {
                $dateObj = json_decode($filterDate);
            } elseif (is_array($filterDate)) {
                $dateObj = (object)$filterDate;
            }
            if (isset($dateObj) && $dateObj->start != '' && $dateObj->end != '') {
                $format_api = 'Y-m-d';
                $format_ihm = 'd-m-Y';
                $dateStart = date_format(DateTime::createFromFormat($format_ihm, $dateObj->start), $format_api);
                $dateEnd = date_format(DateTime::createFromFormat($format_ihm, $dateObj->end), $format_api);
//array(array('operator' => 'value'), array('operator' => 'value'))
                $filter->dateCreatedFilter = array(
                    array(
                        '>=' => $dateStart,
                    ),
                    array(
                        '=<' => $dateEnd,
                    ),
                );
            }

            $filter->properties = array(
                'id',
                'name',
                'timezone',
                'stepCount',
                'dateStart',
                'dateEnd',
                'dateCreated',
                'dateUpdated',
                'status',
                'contactListExtId',
                'contactListName',
                'contactCount',
                'isAutomatic'
            );
            if (is_numeric($filterOffset)) {
                $filter->offset = $filterOffset;
            }
            if (is_numeric($filterLimit)) {
                $filter->limit = $filterLimit;
            }
            if ($filterType) {
                switch (strtolower($filterType)) {
                    case 'standard' :
                        $filter->isAutomatic = '0';
                        $filter->_isAutomatic = '0';
                        break;
                    case 'automatic' :
                        $filter->isAutomatic = '1';
                        break;
                    default :
                        $filter->isAutomatic = null;
                        break;
                }
            }

            $campaigns = $this->_campaignService->campaignRead($filter);
            foreach ($campaigns as $campaign) {
                if ($campaign->contactListExtId) {
                    $filterList = new Mk_Contacts_ContactList_Filter($campaign->contactListExtId);
                    $listUsed = Mk_Factory::GetContactListAdapter()->listStatsRead($filterList)->detailList;
                    $campaign->contactListName = $listUsed->list[0]->name;
                    $campaign->contactCount = $listUsed->list[0]->contactNumber;
                }
                $campaign->type = $campaign->isAutomatic == 1 ? "TRIGGERED" : "STANDARD";
                $campaign->typeTranslated = ucfirst($this->view->translate(strtolower($campaign->type)));
            }

            foreach ($campaigns as $campaign) {
                // Mise à jour des status
                $campaign->status = $this->_transformStatus($campaign);
                $campaign->status['status'] = Service_Api_Object_Campaign::STATUS_ENDED;
                $campaign->listId = $campaign->contactListExtId;
                $campaign->listName = $campaign->contactListName;
                $campaign->reference = $campaign->id;
                if (!is_null($campaignId)) {
                    // Récupération des steps
                    // Récupération des étapes de la campagne
                    $stepFilter = new Service_Api_Filter_Step();
                    $stepFilter->campaignId = array($campaignId);
                    $stepFilter->properties = array(
                        'id',
                        'dateExecution',
                        'dateCreated',
                        'dateUpdated',
                        'mode',
                    );
                    $stepFilter->setSort(array(array('dateExecution', 'ASC')));
                    $stepContainer = $this->_campaignService->stepRead($stepFilter);
                    // variables used for confirmation control
                    $campaignMessages = array();
                    $steps = array();
                    $stats[] = array(
                        'status' => "draft",
                        'translation' => "",
                        'percent' => 100);

                    if ($stepContainer->size) {
                        $steps = $stepContainer->list;
                    }

                    // Récupération de messages pour chaque étape
                    foreach ($steps as $step) {
                        $campaignMessages = array();
                        $messageFilter = new Service_Api_Filter_Message();
                        $messageFilter->stepId = array($step->id);
                        $messageFilter->properties = array('id', 'media');
                        $messageFilter->sort = array(array('priority', 'ASC'));

                        // Récupération de la liste de messages de la campagne
                        $messageList = $this->_campaignService->messageRead($messageFilter);
                        if ($messageList->size) {
                            // Vérification que l'étape contient au moins un message
//                        $stats = array("draft" => $listUsed->list[0]->contactNumber);
                            $campaignMessages = $messageList->list;
                        }
                        $step->messages = $campaignMessages;
                    }
                    $campaign->steps = $steps;
                    $campaign->stats = $stats;
                }
            }
            $readResult->size++;
            $readResult->total++;
            $readResult->list[] = $campaigns->list[0];
        }
        // END - PATCH - #14829 - Probleme de remonté des campagnes de demo
        //__________________________________________________________________________________________


        $this->_helper->json($readResult, true);
    }

    /**
     * Cette page retourne le contenu de la liste des campagnes de l'utilisateur connecté (sans layout)
     *
     * Parametres :
     * status - (optionnel) etat des campagnes a afficher, si pas specifié on prendre l'etat EDITING
     *
     * @return void
     */
    public function    ngExportCampaignsAction()
    {
        $this->_helper->layout()->disableLayout();
        $this->_helper->viewRenderer->setNoRender(true);
        $campaignId = null;
        $csvSeparator = Service_Contact::GetCsvSeparator();
        $filterDate = $this->_getParam('date', false);
        $filterLimit = $this->_getParam('limit', 10);
        $filterOffset = $this->_getParam('offset', 0);
        $filterType = $this->_getParam('type', null);
        $filterSort = "id " . $this->_getParam('sort', 'DESC');
        // Récupération de la campagne

        $status = $this->_getParam('status', $this->_messengeoStatus);

        $getComplex = Service_Messengeo_Status::ComplexFilter($status);
        $filterComplex = $getComplex->filterComplex;
        $filterStatus = $getComplex->status;

        $columnsCampaign = array(
            'name' => ucfirst($this->view->translate('name')),
            'reference' => ucfirst($this->view->translate('reference')),
            'listName' => ucfirst($this->view->translate('list name')),
            'listCount' => ucfirst($this->view->translate('listCount')),
            'nbSteps' => ucfirst($this->view->translate('nbSteps')),
            'type' => ucfirst($this->view->translate('type')),
            'status' => ucfirst($this->view->translate('status')),
            'dateCreated' => ucfirst($this->view->translate('creation date')),
            'dateUpdated' => ucfirst($this->view->translate('update date')),
            'stats' => ucfirst($this->view->translate('statistics')),
        );

        // Ouverture d'un fichier temporaire pour generer le csv
        $time = explode(" ", microtime());
        $fname = $time[1] . $time[0] . '.csv';
        $tmpfname = Dm_Config::GetPath('tmp') . $fname;
        $handle = fopen($tmpfname, 'w');

        // Taille di fichier
        $filesize = 0;

        // Ecriture des entetes
        $writed = fputcsv($handle, $columnsCampaign, $csvSeparator);

        if (false !== $writed) {
            // Incremente le compteur de caracteres pour l'en-tete HTTP "Content-Length"
            $filesize += $writed;
        } else {
            // On arrete d'ecrire en cas d'erreur
            throw new Zend_Exception('Erreur d\'ecriture dans le fichier temporaire CSV');
        }

        // Recuperation du status et affectation de la valeur dans le filtre
        // Interrogation de la couche service
        // On va chercher les infos chez Messengeo directement
        $identity = Zend_Auth::getInstance()->getIdentity();
        $handler = new Service_Api_Handler_Campaign_Messengeo($identity);
        $campaigns = $handler->campaignRead($campaignId, null, $filterStatus, $filterType, $filterDate,
            $filterOffset, $filterLimit, $filterSort, $filterComplex);
        foreach ($campaigns as $campaign) {
            $campaignStatus = $this->_transformStatus($campaign);
            $campaign->status = $campaignStatus["translated"];
            $stats = array();
            // On écrit uniquement les infos necessaires
            $campaignData = array_intersect_key(get_object_vars($campaign), $columnsCampaign);
            // Si pas d'info on continue l'iteration
            if (!is_array($campaignData)) {
                continue;
            }

            if (isset($campaign->reference) && !is_null($campaign->reference)) {
                $campaignIdFromMessengeo = $campaign->reference;

                // Récupération des steps
                // Récupération des étapes de la campagne
                $stepFilter = new Service_Api_Filter_Step();
                $stepFilter->campaignId = array($campaignIdFromMessengeo);
                $stepFilter->setSort(array(array('dateExecution', 'ASC')));
                $stepContainer = $this->_campaignService->stepRead($stepFilter);
                // variables used for confirmation control
                $stats = array();
                $statsData = "";
                if ($stepContainer->size) {
                    $stats = $this->_getStatsResume($campaignIdFromMessengeo, true);
                    foreach ($stats as $statMedia => $nbContacts) {
                        $statsData .= strtoupper($statMedia) . " : " . $nbContacts . " ";
                    }
                }
                $campaignData['stats'] = $statsData;
            }
            // Ecriture de la ligne dans le fichier CSV
            $writed = fputcsv($handle, array_values($campaignData), $csvSeparator);

            if (false !== $writed) {
                // Incremente le compteur de caracteres pour l'en-tete HTTP "Content-Length"
                $filesize += $writed;
            } else {
                // On arrete d'ecrire en cas d'erreur
                throw new Zend_Exception('Erreur d\'ecriture dans le fichier temporaire CSV');
            }
        }
        fclose($handle);
        // Generate Filename
        $filename = "campagnes.csv";

        // Ecriture des entete http pour transmettre le fichier csv
        header('Content-Description: File Transfer');
        if (headers_sent()) {
            $this->Error('Some data has already been output to browser, can\'t send CSV file');
        }
        header('Content-type: application/octetstream; charset=utf-8');
        header('Content-Length: ' . $filesize);
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Transfer-Encoding: binary');

        // Lecture du fichier
        readfile($tmpfname);

        // Suppression du fichier temporaire
        unlink($tmpfname);
    }


    /**
     * Cette page retourne le contenu de la liste des campagnes de l'utilisateur connecté (sans layout)
     *
     * Parametres :
     * status - (optionnel) etat des campagnes a afficher, si pas specifié on prendre l'etat EDITING
     *
     * @return void
     */
    public function ngListEditingAction()
    {

        $campaignId = $this->_getParam('campaignId');
        if (!is_null($campaignId) && !is_numeric($campaignId)) {
            $campaignId = null;
        }

        $rawParams = json_decode($this->getRequest()->getRawBody(), true); // put everything in assoc array
        if (!empty($rawParams)) {
            $this->getRequest()->setParams($rawParams); // request object is a nice params holder
            Dm_Session::setEntry('campaignDraftListFilter', (object)$rawParams,
                Dm_Session::EXPOSED_SESSION); // cast to object to be easily retrieved in json object
        }

        $filterName = $this->_getParam('stringFilter', false);
        $date = $this->_getParam('date', false);
        $deleted = $this->_getParam('deleted', false);
        $limit = $this->_getParam('limit', 10);
        $offset = $this->_getParam('offset', 0);
        $type = $this->_getParam('type', 0);
        $search = $this->_getParam('search', '');
        $total = $this->_getParam('total', false) === 'true';

        // Récupération de la campagne

        $filter = new Service_Api_Filter_Campaign();

        $filter->status = array(Service_Api_Object_Campaign::STATUS_EDITING);

        $sortByDate = false;
        if ($sortByDate) {
            $filter->sort = array(array('dateEnd', 'DESC'));
        } else {
            $filter->sort = array(array('id', 'DESC'));
        }

        if (is_string($date) && preg_match('#^\{#', $date)) {
            $dateObj = json_decode($date);
        } elseif (is_array($date)) {
            $dateObj = (object)$date;
        }

        if (isset($dateObj) && $dateObj->start != '' && $dateObj->end != '') {
            $format_api = 'Y-m-d';
            $format_ihm = 'd-m-Y';
            $dateStart = date_format(DateTime::createFromFormat($format_ihm, $dateObj->start), $format_api);
            $dateEnd = date_format(DateTime::createFromFormat($format_ihm, $dateObj->end), $format_api);
//array(array('operator' => 'value'), array('operator' => 'value'))
            $filter->dateCreatedFilter = array(
                array(
                    '>=' => $dateStart,
                ),
                array(
                    '=<' => $dateEnd,
                ),
            );
        }

        $filter->properties = array(
            'id',
            'name',
            'timezone',
            'stepCount',
            'dateStart',
            'dateEnd',
            'dateCreated',
            'dateUpdated',
            'status',
            'contactListExtId',
            'contactListName',
            'contactCount',
            'isAutomatic'
        );
        if (is_numeric($offset)) {
            $filter->offset = $offset;
        }
        if (is_numeric($limit)) {
            $filter->limit = $limit;
        }
        if ($filterName) {
            $filter->name = $filterName;
            // $filter->listName = $string;
        }
        if ($type) {
            switch (strtolower($type)) {
                case 'standard' :
                    $filter->isAutomatic = '0';
                    $filter->_isAutomatic = '0';
                    break;
                case 'automatic' :
                    $filter->isAutomatic = '1';
                    break;
                default :
                    $filter->isAutomatic = null;
                    break;
            }
        }
        if ($total) {
            $filter->total = $total;
//            $filter->properties[] = 'total';
        }
        $filter->offset = $offset;

        if (!is_null($campaignId)) {
            $filter->campaignId = array($campaignId);
            $filter->limit = 1;
            $filter->offset = 0;
        }
        $filter->search = $search;

        $campaigns = $this->_campaignService->campaignEditingRead($filter);
        foreach ($campaigns as $campaign) {
            if ($campaign->contactListExtId) {
                $filterList = new Mk_Contacts_ContactList_Filter($campaign->contactListExtId);
                $listUsed = Mk_Factory::GetContactListAdapter()->listStatsRead($filterList)->detailList;
                if (isset($listUsed->list[0]->name)) {
                    $campaign->contactListName = $listUsed->list[0]->name;
                }
                if (isset($listUsed->list[0]->contactNumber)) {
                    $campaign->contactCount = $listUsed->list[0]->contactNumber;
                }
            }
            $campaign->type = $campaign->isAutomatic == 1 ? "TRIGGERED" : "STANDARD";
            $campaign->typeTranslated = ucfirst($this->view->translate(strtolower($campaign->type)));
        }

        foreach ($campaigns as $campaign) {
            // Mise à jour des status
            $campaign->status = $this->_transformStatus($campaign);
            if (!is_null($campaignId)) {
                // Récupération des steps
                // Récupération des étapes de la campagne
                $stepFilter = new Service_Api_Filter_Step();
                $stepFilter->campaignId = array($campaignId);
                $stepFilter->properties = array(
                    'id',
                    'dateExecution',
                    'dateCreated',
                    'dateUpdated',
                    'mode',
                );
                $stepFilter->setSort(array(array('dateExecution', 'ASC')));
                $stepContainer = $this->_campaignService->stepRead($stepFilter);
                // variables used for confirmation control
                $campaignMessages = array();
                $steps = array();
                $stats[] = array(
                    'status' => "draft",
                    'translation' => "",
                    'percent' => 100);

                if ($stepContainer->size) {
                    $steps = $stepContainer->list;
                }

                // Récupération de messages pour chaque étape
                foreach ($steps as $step) {
                    $campaignMessages = array();
                    $messageFilter = new Service_Api_Filter_Message();
                    $messageFilter->stepId = array($step->id);
                    $messageFilter->properties = array('id', 'media');
                    $messageFilter->sort = array(array('priority', 'ASC'));

                    // Récupération de la liste de messages de la campagne
                    $messageList = $this->_campaignService->messageRead($messageFilter);
                    if ($messageList->size) {
                        // Vérification que l'étape contient au moins un message
//                        $stats = array("draft" => $listUsed->list[0]->contactNumber);
                        $campaignMessages = $messageList->list;
                    }
                    $step->messages = $campaignMessages;
                }
                $campaign->steps = $steps;
                $campaign->stats = $stats;
            }
        }

        $readResult = $campaigns;
        $this->_helper->json($readResult, true);
    }

    /**
     * Renommage campagne
     *
     * Parametres :
     * - campaignId = Identifiant de la campagne à renommer
     * - renamed = nouveau nom de la campagne
     *
     * @return void
     */
    public function ngRenameAction()
    {
        $this->_helper->layout->disableLayout();
        $readResult = 0;
        $rawParams = json_decode($this->getRequest()->getRawBody());
        $status = $rawParams->status;
        $campaignId = $rawParams->id;
        $campaignName = $rawParams->renamed;
        Dm_Log::Info("Renaming campaign " . $rawParams->id . " => " . $campaignName);

        if ($status->status != "editing") {
            // Renommage côté messengeo
            $identity = Zend_Auth::getInstance()->getIdentity();
            $handler = new Service_Api_Handler_Campaign_Messengeo($identity);
            $readResult = $handler->campaignUpdate($campaignId, $campaignName);
        } else {
            if (!is_null($campaignId) && is_numeric($campaignId)) {
                $filter = new Service_Api_Filter_Campaign();
                $filter->campaignId = array($campaignId);

                $updateValues = array(
                    'name' => $campaignName
                );

                // removing null values so that only valid values will be updated
                $updateValues = array_filter($updateValues);
                $readResult = $this->_campaignService->campaignUpdate($filter, $updateValues);
            }
        }

        $this->_helper->json($readResult, true);
    }

    /**
     * Annulation d'une campagne
     *
     * Paramètres :
     * - campaignId = Identifiant de la campagne à supprimer
     *
     * @return void
     */
    public function ngCancelAction()
    {
        Dm_Log::Debug('Start ' . __METHOD__);
        $this->_helper->layout->disableLayout();
        $rawParams = json_decode($this->getRequest()->getRawBody());
        $campaignId = $rawParams->id;
        $readResult = "";

        if (!is_null($campaignId) && is_numeric($campaignId)) {
            // Récupération de la campagne
            $identity = Zend_Auth::getInstance()->getIdentity();
            $handler = new Service_Api_Handler_Campaign_Messengeo($identity);
            $campaigns = $handler->campaignRead($campaignId);

            if (!$campaigns->size) {
                $readResult = $this->view->translate("cannot cancel campaign, no campaign found");
            } else {
                $campaign = $campaigns->list[0];
                $canCancelCampaign = false;
                $isRunning = $campaign->status == 'created' || $campaign->status == 'pending' ? true : false;
                // On ne peut annuler une campagne seulement si elle a le statut 'created' côté messengeo
                if ($isRunning) {
                    $canCancelCampaign = true;
                }
                if ($canCancelCampaign) {
                    // Suppression de la campagne
                    $cancelCampaign = $handler->campaignCancel($campaignId);
                    if ($cancelCampaign === true) {
                        $readResult = array(
                            "message" => ucfirst($this->view->translate("campaign cancelled")),
                            "status" => $cancelCampaign
                        );
                    } else {
                        $readResult = (
                        array('error' => ucfirst(
                            $this->view->translate("impossible to cancel this campaign")))
                        );
                    }

                } else {
                    $readResult = (
                    array('error' => ucfirst(
                        $this->view->translate("impossible to cancel an ongoing campaign mailing")))
                    );
                }
            }
        } else {
            $readResult = (
            array('error' => ucfirst(
                $this->view->translate("cannot cancel campaign with invalid identifier")))
            );
        }

        $this->_helper->json($readResult, true);
    }

    /**
     * Action qui effectue la duplication d'une campagne
     *
     * Paramètres requis :
     * int $campaignId Identifiant de la campagne à dupliquer
     *
     * @return void
     */
    public function ngDuplicateAction()
    {
        $this->_helper->layout->disableLayout();
        $rawParams = json_decode($this->getRequest()->getRawBody());

        $status = $rawParams->status;
        $campaignId = $status->status == "editing" ? $rawParams->id : $rawParams->reference;
        $isTemplate = isset($rawParams->isTemplate) ? $rawParams->isTemplate : null;
        Dm_Log::Debug("Duplicating campaign " . $campaignId . " - status : " . $status->status);

        if (is_null($campaignId) || !is_numeric($campaignId)) {
            $readResult =
                array('error' => ucfirst($this->view->translate('cannot duplicate campaign with invalid identifier')));
        } else {

            // Duplication de la campagne
            // Lecture de campagnes dupliquées
            /* @var $duplicatedCampaignResponse Service_Api_Object_ObjectList */
            $filter = ($isTemplate) ? new Service_Api_Filter_Template() : new Service_Api_Filter_Campaign();
            $filter->campaignId = array($campaignId);

            if ($isTemplate) {
                $duplicatedCampaignResponse = $this->_campaignService->templateDuplicate($filter);
            } else {
                $duplicatedCampaignResponse = $this->_campaignService->campaignDuplicate($filter);
            }

            if ($duplicatedCampaignResponse->size) {
                $duplicatedCampaigns = $duplicatedCampaignResponse->list;

                foreach ($duplicatedCampaigns as $duplicatedCampaign) {
                    /* @var $duplicatedCampaign Service_Api_Object_Campaign */

                    // if listId or campaignName provided, update campaign with the new data
                    if (!is_null($this->_getParam('listId'))
                        || !is_null($this->_getParam('campaignName'))
                    ) {
                        $duplicatedCampaignFilter = new Service_Api_Filter_Campaign();
                        $duplicatedCampaignFilter->campaignId = array($duplicatedCampaign->id);
                        $this->_campaignService->campaignUpdate(
                            $duplicatedCampaignFilter,
                            // array_filter will remove null values
                            array_filter(array('contactListExtId' => $this->_getParam('listId'),
                                'name' => $this->_getParam('campaignName')))
                        );
                    }

                    // Lecture du tableau de mapping des identifiants des messages
                    if (!is_null($duplicatedCampaign->messageMapping)) {
                        $messageMapping = $duplicatedCampaign->messageMapping;
                        $messageHandler = new Editor_Model_Message_Table();

                        foreach ($messageMapping as $originalMessageId => $duplicatedMessageId) {
                            /* @var $originalEditorMessage Editor_Model_Message_Row */
                            // Lecture du message editeur original
                            $originalEditorMessage = $messageHandler->fetchRow('extId = ' . $originalMessageId);
                            // Duplication du message editeur original avec le extId qui pointe vers
                            // l'identifiant du message Cmpeo dupliqué
                            $originalEditorMessage->duplicate(
                                $duplicatedMessageId, Dm_Session::GetEntry(Dm_Session::CONNECTED_USER)->id);
                        }
                    }
                }
            } else {
                if ($this->view->hasAccess('template-creation')) {
                    $readResult =
                        array(
                            'error' => ucfirst(
                                $this->view->translate('cannot create template')
                            ),
                        );
                } else {
                    $readResult =
                        array(
                            'error' => ucfirst(
                                $this->view->translate('cannot create campaign')
                            ),
                        );
                }
            }
        }

        $message = "";
        if (!$isTemplate) {
            // redirection vers la liste des campagnes
            $message = ucfirst($this->view->translate("campaign was successfully duplicated"));
        } else {
            // duplication d'un modèle
            $params = array('campaignId' => $duplicatedCampaigns[0]->id);

            if ($duplicatedCampaigns[0]->isTemplate) {
                // redirection vers le résumé du template créé
                $message = ucfirst($this->view->translate("template was successfully duplicated"));
            } else {
                // redirection vers le résumé de la campagne créée
                $message = ucfirst($this->view->translate("campaign was successfully created"));
            }
        }
        Dm_Log::Debug("Success, new campaignId duplicated : " . $duplicatedCampaign->id);
        $readResult = array('succes' => $message, 'campaignId' => $duplicatedCampaign->id);
        $this->_helper->json($readResult, true);
    }


    /**
     * Suppression d'une campagne
     *
     * Paramètres :
     * - campaignId = Identifiant de la campagne à supprimer
     * - status     = Statut de la campagne pour pourvoir revenir à la même liste de campagnes
     *
     * @return void
     */
    public function ngDeleteAction()
    {
        Dm_Log::Debug('Start ' . __METHOD__);
        $this->_helper->layout->disableLayout();
        $rawParams = json_decode($this->getRequest()->getRawBody());
        $message = "";
        $campaignId = $rawParams->id;
        $status = $rawParams->status;

        if (!is_null($campaignId) && is_numeric($campaignId)) {
            // Récupération de la campagne
            $campaignFilter = new Service_Api_Filter_Campaign();
            $campaignFilter->campaignId = array($campaignId);
            $campaigns = $this->_campaignService->campaignRead($campaignFilter);

            if (!$campaigns->size) {
                $readResult =
                    array('error' => ucfirst($this->view->translate("cannot delete campaign")));
            } else {
                $campaign = $campaigns->list[0];

                // On peut supprimer une campagne non-automatisées seulement si elle n'a pas le statut 'running'
                // On peut supprimer une campagne automatisée à toute moment
                if ($campaign->isAutomatic ||
                    (!$campaign->isAutomatic && ($campaign->status == Service_Api_Object_Campaign::STATUS_EDITING ||
                            $campaign->status == Service_Api_Object_Campaign::STATUS_CONFIRMED))
                ) {
                    // Suppresion de la campagne
                    $this->_campaignService->campaignDelete($campaignFilter);
                    $readResult =
                        array('success' => ucfirst($this->view->translate("campaign deleted")));
                } else {
                    $readResult =
                        array('error' => $this->view->translate(sprintf("Cannot delete campaign with status : %s.",
                            $campaign->status)));
                }
            }
        } else {
            $readResult =
                array('error' => ucfirst($this->view->translate("cannot delete campaign with invalid identifier")));
        }

//        $this->_redirect($this->view->href('campaign-list', array('status' => $status)));

//        $readResult = array('succes' => $message, 'campaignId' => $campaignId);
        $this->_helper->json($readResult, true);
    }


    /**
     * Création d'une nouvell campagne
     *
     * @return void
     */
    public function ngSaveAction()
    {
        $this->_helper->layout->disableLayout();
        $form = new Frontoffice_Form_Campaign_Create();
        $this->view->form = $form;
        $rawParams = json_decode($this->getRequest()->getRawBody());
        if (!isset($rawParams)) {
            throw new Exception('cannot create campaign with invalid parameters');
        }
        $user = Dm_Session::GetConnectedUser();
        $datetime = new Zend_Date(Zend_Registry::get('Zend_Locale'));
        $datetime->setTimeZone($user->timezone);
        $name = ucfirst($this->view->translate('campaign')) . ' ' .
            $this->view->formatDate($datetime, 'dd-MM-yyyy HH:mm');
        $isAutomatic = 0;
        $listId = $rawParams->list;
        $campaignListId = (!is_null($listId) && is_numeric($listId)) ? $listId : null;
        if (isset($rawParams->name) && !empty($rawParams->name)) {
            $name = $rawParams->name;
        }
        if (isset($rawParams->type) && $rawParams->type == "automatic") {
            $isAutomatic = 1;
        } else {
            if ($rawParams->type == "network") {
                $readResult = array("urlToGo" => $this->view->href('list-templates',
                    array('listId' => $campaignListId, 'campaignName' => $name)));
                $this->_helper->json($readResult, true);
                exit;
            }
        }

        // Création d'une nouvelle campagne
        $campaignData = array(
            array(
                'name' => $name,
                'contactListExtId' => $campaignListId,
                'isAutomatic' => $isAutomatic,
            )
        );
        $campaignResult = $this->_campaignService->campaignCreate($campaignData); // 0.8s
        if (!$campaignResult->size) {
            $readResult =
                array('error' => ucfirst($this->view->translate('cannot create campaign'))
                );
        } else {
            $campaign = $campaignResult->list[0];
            $this->view->campaignId = $campaign->id;
            // Création d'une étape par default
            $stepData = array(
                array(
                    'name' => 'Etape',
                    'campaignId' => $campaign->id,
                    'isAutomatic' => $isAutomatic,
                )
            );
            $stepResult = $this->_campaignService->stepCreate($stepData);  // 0.6s
            if (!$stepResult->size) {
                $readResult =
                    array('error' => ucfirst($this->view->translate('cannot create step'))
                    );
            } else {
                $step = $stepResult->list[0];
                $stepId = $step->id;
                $this->view->stepId = $stepId;

                $form->populate(array(
                    'id' => $campaign->id, 'name' => $name, 'contactListExtId' => $campaignListId));

                $this->getHelper('viewRenderer')->setNoRender();

                Dm_Session::SetEntry('step_' . $step->id, array('stepIndex' => '0'), 'step-edit');
                Dm_Session::SetEntry('campaign_' . $campaign->id,
                    array('campaignName' => ucfirst($this->view->translate('new campaign'))),
                    'step-edit');
                $readResult = true;
                $readResult = array("urlToGo" => $this->view->href('step-list',
                    array('campaignId' => $campaign->id,
                        'stepId' => $stepId)));

            }
        }

        $this->_helper->json($readResult, true);
    }


    /**
     * Modification du status Created en fonction des paramètres de la campagne
     *
     * @param Mk_Campaigns_Campaign $campaign la campagne
     *
     * @return void
     */
    protected function _transformStatus($campaign)
    {
        if (!isset($campaign->status)) {
            return;
        }
        $user = Dm_Session::GetConnectedUser();

        $datetime = new Zend_Date(Zend_Registry::get('Zend_Locale'));
        $datetime->setTimeZone($user->timezone);

        $dateStart = new Zend_Date($campaign->dateStart);
        $dateStartDiff = $dateStart->compare($datetime);
        $dateEnd = new Zend_Date($campaign->dateEnd);
        $dateEndDiff = $dateEnd->compare($datetime);

        switch (strtolower($campaign->status)) {
            case 'created' :
                if ($dateStartDiff == 1) {
                    // DateStart dans le futur
                    $status = 'scheduled';
                } elseif ($dateEndDiff == -1) {
                    // DateEnd dans le passé
                    $status = 'ended';
                } else {
                    $status = 'running';
                }
                break;
            default :
                $status = $campaign->status;
                break;
        }
        return array(
            "status" => $status,
            "translated" => $this->view->translate($status)
        );
    }

    /**
     * Cette page retourne le contenu de la liste des campagane de l'utilisateur connecté (sans layout)
     *
     * Parametres :
     * status - (optionnel) etat des campagnes a afficher, si pas specifié on prendre l'etat EDITING
     *
     * @return void
     */
    public function ajaxListAction()
    {
        $this->_helper->layout->disableLayout();
        $filter = new Service_Api_Filter_Campaign();
        $filter->properties = array(
            'id',
            'name',
            'timezone',
            'stepCount',
            'dateStart',
            'dateEnd',
            'dateCreated',
            'dateUpdated',
            'status',
            'contactListExtId',
            'contactCount',
            'isAutomatic'
        );
        $filter->limit = $this->_getParam('perPage', Zend_Paginator::getDefaultItemCountPerPage());
        $page = $this->_getParam('page', 1);
        $offset = ($page - 1) * $filter->limit;
        $filter->offset = $offset;
        //Recuperation du status et affectation de la valeur dans le filtre
        $status = $this->_getParam('status', array(Service_Api_Object_Campaign::STATUS_EDITING));

        $this->view->activeCampaings = false;

        $sortByDate = false;
        if (!is_null($status)) {
            $filter->status = (is_array($status)) ? $status : array($status);

            $status_date_sortable = array(
                Service_Api_Object_Campaign::STATUS_STOPPED,
                Service_Api_Object_Campaign::STATUS_STOPPED_WHILE_RUNNING,
                Service_Api_Object_Campaign::STATUS_CLOSED,
                Service_Api_Object_Campaign::STATUS_CANCELED,
            );
            if (is_array($status)) {
                foreach ($status as $stat) {
                    // if all statuses are part of the array we have to sort campaigns by endDate
                    if (in_array($stat, $status_date_sortable)) {
                        $sortByDate = true;
                    } else {
                        $sortByDate = false;
                        break;
                    }
                }
            }
            if (is_array($status) && in_array(Service_Api_Object_Campaign::STATUS_CONFIRMED, $status)) {
                $this->view->activeCampaings = true;
            }
        }

        $statusList = array(
            Service_Api_Object_Campaign::STATUS_EDITING => Service_Api_Object_Campaign::STATUS_EDITING,
            Service_Api_Object_Campaign::STATUS_CONFIRMED => Service_Api_Object_Campaign::STATUS_RUNNING,
            Service_Api_Object_Campaign::STATUS_PENDING => Service_Api_Object_Campaign::STATUS_PENDING,
            Service_Api_Object_Campaign::STATUS_RUNNING => Service_Api_Object_Campaign::STATUS_RUNNING,
            Service_Api_Object_Campaign::STATUS_BUILD_DONE => Service_Api_Object_Campaign::STATUS_RUNNING,
            Service_Api_Object_Campaign::STATUS_BUILD_IN_PROGRESS => Service_Api_Object_Campaign::STATUS_RUNNING,
            Service_Api_Object_Campaign::STATUS_STOPPED => Service_Api_Object_Campaign::STATUS_STOPPED,
            Service_Api_Object_Campaign::STATUS_STOPPED_WHILE_RUNNING => Service_Api_Object_Campaign::STATUS_STOPPED,
            Service_Api_Object_Campaign::STATUS_CLOSED => Service_Api_Object_Campaign::STATUS_STOPPED,
            Service_Api_Object_Campaign::STATUS_CREATED => Service_Api_Object_Campaign::STATUS_CREATED,
            Service_Api_Object_Campaign::STATUS_CANCELLED => Service_Api_Object_Campaign::STATUS_CANCELLED,
            Service_Api_Object_Campaign::STATUS_CANCELED => Service_Api_Object_Campaign::STATUS_CANCELED,
            Service_Api_Object_Campaign::STATUS_INTERRUPTED => Service_Api_Object_Campaign::STATUS_INTERRUPTED,
            Service_Api_Object_Campaign::STATUS_ENDED => Service_Api_Object_Campaign::STATUS_ENDED
        );
        $this->view->statusList = $statusList;


        if ($sortByDate) {
            $filter->sort = array(array('dateEnd', 'DESC'));
        } else {
            $filter->sort = array(array('id', 'DESC'));
        }

        //Interrogation de la couche service
        $readResult = $this->_campaignService->campaignRead($filter);

        // Récupération du nombre de contacts par campagne
        if ($readResult->size) {
            $campaigns = $readResult->list;
            $campaignListIds = array();
            foreach ($campaigns as $campaign) {
                // Pour les campagne en édition, lecture de la liste de contacts
                if (strcasecmp($campaign->status, Service_Api_Object_Campaign::STATUS_EDITING) == 0) {
                    if (!is_null($campaign->contactListExtId)) {
                        $campaignListIds[] = $campaign->contactListExtId;
                    }
                }
            }

            // Lecture du nombre de contacts stocké dans Cmpeo
            $stats = $this->_contactListService->listStatsRead(
                new Mk_Contacts_ContactList_Filter($campaignListIds));
            /* @var $stats Mk_Contacts_ContactList_Output_Stats */
            $listResult = $stats->detailList;

            if ($listResult->size) {
                $lists = $listResult->list;
                $contract = Dm_Session::getConnectedUserContract();

                foreach ($campaigns as $campaign) {
                    foreach ($lists as $list) {
                        /* @var $list Mk_Contacts_ContactList */
                        if ($campaign->contactListExtId == $list->id) {
                            $campaign->contactCount = $list->contactNumber;
                            break;
                        }
                    }
                }
            }
        }

        /* @var $readResult Service_Api_Object_ObjectList */
        $paginator = Zend_Paginator::factory($readResult, 'ObjectList');
        $paginator->setCurrentPageNumber($page);
        $paginator->setDefaultItemCountPerPage($filter->limit);
        /* @var $paginator Zend_Paginator */
        $this->view->paginator = $paginator;

        $this->view->status = $status;
    }

    /**
     * Sauvegarde en AJAX d'une campagne
     *
     * Parametres :
     * campaignId       - Identifiant de la campagne
     * name             - (optionnel) Nom de la campagne
     * contactListExtId - (optionnel) Identifiant de la liste de contacts
     * comment          - (optionnel) Commentaire de la campagne
     *
     * @return void
     */
    public function ajaxSaveAction()
    {
        $this->_helper->layout->disableLayout();
        $this->view->status = 0;

        $campaignId = $this->_getParam('campaignId');

        if (!is_null($campaignId) && is_numeric($campaignId)) {
            $filter = new Service_Api_Filter_Campaign();
            $filter->campaignId = array($campaignId);

            $updateValues = array(
                'name' => $this->_getParam('name')
            );

            // removing null values so that only valid values will be updated
            $updateValues = array_filter($updateValues);
            $updateValues['comment'] = $this->_getParam('comment');

            $listId = $this->_getParam('contactListExtId');
            if (!is_null($listId) && is_numeric($listId) && $listId) {
                $updateValues['contactListExtId'] = $listId;
            }

            // mise en session pour l'édition des étapes de la campagne si le nom est défini
            if (array_key_exists('name', $updateValues)) {
                Dm_Session::SetEntry(
                    'campaign_' . $campaignId, array('campaignName' => $updateValues['name']), 'step-edit');
            }
            $this->view->status = $this->_campaignService->campaignUpdate($filter, $updateValues);
        }
    }

    /**
     * Sauvegarde en AJAX du paramètre d'envoi à J+X jours du mail de stats de la campagne
     *
     * Parametres :
     * campaignId         - (int)     Identifiant de la campagne
     * activeStatsXDays   - (boolean) paramètre définissant si l'utilisateur souhaite reçevoir un email avec un lien
     * vers les stats X jours après le début de la campagne.
     * daysCount          - (int)     nombre de jours
     *
     * @return void
     */
    public function ajaxSaveStatsXDaysAction()
    {
        $this->_helper->layout->disableLayout();
        $this->view->status = 0;

        $campaignId = $this->_getParam('campaignId');
        $activeStatsXDays = $this->_getParam('activeStatsXDays');
        $daysCount = 0;
        if ($activeStatsXDays === 'true') {
            $daysCount = intval($this->_getParam('daysCount'));
        }
        $filter["campaignId"] = $campaignId;

        $filter = new Service_Api_Filter_Campaign();
        $filter->campaignId = array($campaignId);
        $filter->status = array(Service_Api_Object_Campaign::STATUS_EDITING);

        // statsEmailDelay is in hours
        $updateValues['statsEmailDelay'] = $daysCount * 24;

        $this->view->status = $this->_campaignService->campaignUpdate($filter, $updateValues);

        $this->view->status = "success";
    }

    /**
     * Calcul du coût (ancien calcul uniquement côté slbeo).
     *
     * Parametres :
     * campaignId       - Identifiant de la campagne
     * contactListExtId - (optionnel) Identifiant de la liste de contacts
     *
     * @return void
     */
    public function ajaxCheckCostAction()
    {
        $campaignId = $this->_getParam('campaignId');
        $contactListExtId = $this->_getParam('contactListExtId');
        $this->_helper->json->sendJson($this->_getMessageCost(
            $campaignId, Dm_Session::GetEntry('CurrentMessages'), $contactListExtId));
    }

    /**
     * Calcul du coût, par baseo
     *
     * Parametres :
     * campaignId       - Identifiant de la campagne
     * contactListExtId - Identifiant de la liste de contacts
     *
     * @return json {cost: float}
     */
    public function getCostAction()
    {
        $listId = $this->_getParam('contactListExtId');
        $campaignMessages = Dm_Session::GetEntry('CurrentMessages');

        // Récupération des messages
        $mailings = array();
        if ($campaignMessages) {
            $messageHandler = new Editor_Model_Message_Table();
            foreach ($campaignMessages as $message) {
                $content = '';
                // On a besoin du contenu uniquement pour le média SMS
                if ($message->media == Editor_Model_Message_Row::SMS) {
                    $messageRow = $messageHandler->getMessageFromExtId($message->id);

                    // Utilisation du tpoa ou non
                    $tpoa = '';
                    $concreteEditorMessage = $messageRow->getConcreteMessage();
                    if ($concreteEditorMessage->tpoaEnabled == 1) {
                        $tpoaInfos = Editor_Service_Bridge::getMessageSmsStop();
                        $tpoa = ' ' . $tpoaInfos->stopMessage->message;
                    }

                    // Récupération du texte du message
                    $messageContent = $concreteEditorMessage->serialize();
                    $content = $messageContent->text . $tpoa;
                }

                $mailings[] = array(
                    'text' => $content,
                    'media' => $message->media
                );
            }
            // APPEL API MESSENGEO CALCUL COUT CAMPAGNE
            $config = Dm_Config::GetConfig('campaign', 'rest');
            $params = array('mailings' => $mailings, 'listId' => $listId);

            $messengeo = new Eo_Rest_Wrapper(
                $config, Dm_Session::GetConnectedUser()->userKey);
            $costs = $messengeo->campaigncostsCreate($params);
            if (property_exists($costs, 'size') && $costs->size === 1) {
                $cost = $costs->list[0]->cost;
            } else {
                Dm_Log::Error('Erreur lors de la récupération du cout');
                Dm_Log::Error($params);
                Dm_Log::Error($costs);
                $cost = '-';
            }
        } else {
            $cost = '-';
        }

        $this->_helper->json->sendJson(array('cost' => $cost));
    }

    /**
     * Cette page retourne le nombre de contacts d'une campagne,
     * optionnellement par type de message media
     *
     * @return void
     */
    public function ajaxContactCountAction()
    {

        $contactCount = 0;

        $campaignId = $this->getRequest()->getParam('campaignId');
        $stepId = $this->getRequest()->getParam('stepId');
        $messageMedia = $this->getRequest()->getParam('messageMedia');

        if (!is_null($campaignId) && is_numeric($campaignId) && !is_null($stepId) && is_numeric($stepId)) {
            $campaignMediaFilter = new Service_Api_Filter_CampaignMedia();
            $campaignMediaFilter->campaignId = $campaignId;
            $campaignMediaFilter->stepId = $stepId;
            $campaignMediaFilter->media = $messageMedia;
            $contactCount = $this->_campaignService->campaignContactCount($campaignMediaFilter);
        }

        $this->_helper->json->sendJson($contactCount);
    }

    /**
     * Page resumé d'une campagne
     *
     * @return void
     */
    public function editAction()
    {
        $campaignId = $this->_getParam('campaignId');

        if (is_null($campaignId) || !is_numeric($campaignId)) {
            $this->_helper->FlashMessenger->addMessage(
                array('error' => ucfirst($this->view->translate('cannot display campaign without a valid identifier')))
            );
            $this->_redirect($this->view->href('campaign-list'));
        } else {
            $this->view->campaignId = $campaignId;

            // Récupération de la campagne
            $campaignFilter = new Service_Api_Filter_Campaign();
            $campaignFilter->campaignId = array($campaignId);
            $campaignsContainer = $this->_campaignService->campaignRead($campaignFilter);

            if (!$campaignsContainer->size) {
                $this->_helper->FlashMessenger->addMessage(
                    array('error' => ucfirst($this->view->translate(sprintf('campaign not found'))))
                );

                $this->_redirect($this->view->href('campaign-list'));
            } else {
                /* @var $campaign Service_Api_Object_Campaign */
                $campaign = $campaignsContainer->list[0];

                Dm_Session::SetEntry(
                    'campaign_' . $campaign->id, array('campaignName' => $campaign->name), 'step-edit');

                // Redirection vers la page de stats si status de la campagne pas à "editing"
                if ($campaign->status != Service_Api_Object_Campaign::STATUS_EDITING) {
                    $this->_redirect($this->view->href('campaign-stat', array('campaignId' => $campaignId)));
                }

                // try to retrive the advice
                if ($campaign->sourceTemplateId > 0) {
                    $templateFilter = new Service_Api_Filter_Template();
                    $templateFilter->campaignId = array($campaign->sourceTemplateId);
                    $templatesContainer = $this->_campaignService->templateRead($templateFilter);
                    if ($templatesContainer->size > 0) {
                        $this->view->advice = $templatesContainer->list[0]->advice;
                    } else {
                        $this->view->advice = null;
                    }
                } else {
                    $this->view->advice = null;
                }

                $listId = $campaign->contactListExtId;

                // Récupération de listes de contacts de l'utilisateur connecté
                $listFilter = new Mk_Contacts_ContactList_Filter();
                $listFilter->properties = array('DEFAULT', 'stats', 'importStatus', true, false);
                /* @var $list Mk_Contacts_ContactList[] */
                $lists = $this->_contactListService->listStatsRead($listFilter)->detailList->list;

                if ($this->view->hasAccess('rentalContactList') && ($campaign->isAutomatic != 1)) {
                    $rentListFilter = new Mk_Contacts_ContactList_Filter();
                    $rentListFilter->category = 'RENTED';
                    $rentListFilter->importStatus = 'ok';
                    $rentListFilter->properties = array('DEFAULT',
                        'stats',
                        'importStatus',
                        'importErrors',
                        'dateCreated',
                        'expired',
                        'dateExpired',
                        'shootCount');
                    /* @var $list Mk_Contacts_ContactList[] */
                    $rentLists = $this->_contactListService
                        ->listStatsRead($rentListFilter, true, false)->detailList->list;
                } else {
                    $rentLists = array();
                }

                // Construction du formulaire d'edition de la campagne
                $form = $this->_getEditCampaignForm($lists, $campaign->contactListExtId, $rentLists);
                $form->populate(array(
                    'name' => $campaign->name,
                    'advice' => $campaign->advice,
                    'comment' => $campaign->comment
                ));
                $this->view->form = $form;

                // Mise à jour de la campagne avec les données envoyées en POST
                if ($this->getRequest()->isPost()) {
                    if ($form->isValid($this->getRequest()->getParams())) {
                        $this->_campaignService->campaignUpdate($campaignFilter, $form->getValues());

                        $campaignsContainer = $this->_campaignService->campaignRead($campaignFilter);
                        $campaign = $campaignsContainer->list[0];
                    }
                }

                $this->view->campaign = $campaign;

                // Construction d'un tableau (id liste, n° contacts) pour le passer à la vue
                $listRes = array();
                foreach ($lists as $list) {
                    $listRes[$list->id]['total'] = $list->contactNumber;
                    $listRes[$list->id]['emailNumber'] = $list->emailNumber;
                    $listRes[$list->id]['smsNumber'] = $list->smsNumber;
                    $listRes[$list->id]['voiceNumber'] = $list->voiceNumber;
                    $listRes[$list->id]['voicemailNumber'] = $list->voicemailNumber;
                    $listRes[$list->id]['plvNumber'] = $list->plvNumber;

                    if (Dm_Session::getConnectedUserContract()->type == "demonstration") {
                        $listRes[$list->id]['isDemo'] = $list->isMock;
                    }
                }
                foreach ($rentLists as $list) {
                    $listRes[$list->id]['total'] = $list->contactNumber;
                    $listRes[$list->id]['emailNumber'] = $list->emailNumber;
                    $listRes[$list->id]['smsNumber'] = $list->smsNumber;
                    $listRes[$list->id]['voiceNumber'] = $list->voiceNumber;
                    $listRes[$list->id]['voicemailNumber'] = $list->voicemailNumber;
                    $listRes[$list->id]['plvNumber'] = $list->plvNumber;

                    if (Dm_Session::getConnectedUserContract()->type == "demonstration") {
                        $listRes[$list->id]['isDemo'] = $list->isMock;
                    }
                }
                $this->view->lists = $listRes;
                $this->view->listId = $listId;

                // Récupération des étapes de la campagne
                $stepFilter = new Service_Api_Filter_Step();
                $stepFilter->campaignId = array($campaignId);
                $stepFilter->setSort(array(array('dateExecution', 'ASC')));
                $stepContainer = $this->_campaignService->stepRead($stepFilter);

                // variables used for confirmation control
                $campaignHasPushChannels = 0;
                $campaignHasValidDate = 1;
                $campaignHasValidReplyTo = 1;
                $campaignHasValidMessageSubject = 1;
                $campaignInvalidMessages = array();
                $campaignHasValidSms = 1;
                $campaignInvalidSmsParams = 0;
                $campaignFirstMessageDate = '';
                $campaignIsOnSunday = 0;

                $campaignMessages = array();
                if ($stepContainer->size) {
                    $steps = $stepContainer->list;
                    $this->view->steps = $steps;

                    if ($steps[0]->dateExecution !== null) {
                        $date = DateTime::createFromFormat('Y-m-d H:i:s', $steps[0]->dateExecution);
                        $campaignFirstMessageDate = $date->getTimestamp() . '000';
                        //added milliseconds to timestamp... to be able to compare it to javascript time.
                        $this->view->firstMessageDay = $date->format('d/m/Y');
                        $this->view->firstMessageTime = $date->format('H:i');
                    } else {
                        $this->view->firstMessageDay = '';
                        $this->view->firstMessageTime = '';
                        $date = new DateTime();
                    }
                    // On empeche l'envoi de campagne le dimanche
                    if ($date->format('N') == 7) {
                        $campaignIsOnSunday = 1;
                    }
                    unset($date);

                    // Récupération de messages pour chaque étape
                    $messageFilter = new Service_Api_Filter_Message();
                    $messageFilter->campaignId = array($campaign->id);
                    $messageFilter->sort = array(array('priority', 'ASC'));

                    // Récupération de la liste de messages de la campagne
                    $messageList = $this->_campaignService->messageRead($messageFilter);
                    if ($messageList->size) {
                        // Vérification que l'étape contient au moins un message
                        $campaignMessages = $messageList->list;
                    }

                    $stepCount = 1;

                    foreach ($steps as $step) {
                        $step->messages = array();

                        Dm_Session::SetEntry('step_' . $step->id, array('stepIndex' => $stepCount), 'step-edit');

                        foreach ($campaignMessages as $message) {
                            if ($message->stepId == $step->id) {
                                $step->messages[] = $message;

                                // verifying that message replyTo is not empty or invalid Email address
                                // verifying that message suject is not empty or 'Entrez votre objet ici'
                                // for Email messages
                                if (strcasecmp($message->media, Service_Api_Object_Message::EMAIL) == 0) {
                                    $messageHandler = new Editor_Model_Message_Table();
                                    $editorMessage = $messageHandler->fetchRow('extId = ' . $message->id);
                                    $concreteEditorMessage = $editorMessage->getConcreteMessage();

                                    $replyToIsValid = $this->_checkMessageReplyTo(
                                        $message->id, $concreteEditorMessage);

                                    if (!$replyToIsValid) {
                                        $campaignHasValidReplyTo = $replyToIsValid;
                                        $campaignInvalidMessages[] = $stepCount;
                                    }

                                    $subjectIsValid = $this->_checkMessageSubject(
                                        $message->id, 'Entrez votre objet ici', $concreteEditorMessage);

                                    if (!$subjectIsValid) {
                                        $campaignHasValidMessageSubject = $subjectIsValid;
                                        $campaignInvalidMessages[] = $stepCount;
                                    }
                                }

                                // Checking SMS message for empty or 'Votre texte ici' content
                                if (strcasecmp($message->media, Service_Api_Object_Message::SMS) == 0) {
                                    $messageHandler = new Editor_Model_Message_Table();
                                    $smsConcreteMessage = $messageHandler->fetchRow('extId = ' . $message->id)
                                        ->getConcreteMessage()->serialize();
                                    if (is_null($smsConcreteMessage->text) || empty($smsConcreteMessage->text) ||
                                        strcasecmp(trim($smsConcreteMessage->text),
                                            'votre texte ici') == 0
                                    ) {
                                        $campaignHasValidSms = 0;
                                    }

                                    $messageRow = $messageHandler->getMessageFromExtId($message->id);
                                    $concreteEditorMessage = $messageRow->getConcreteMessage();
                                    // test sur le TPOA ou récupération des sms
                                    if ($concreteEditorMessage->tpoaEnabled == 1 &&
                                        !($concreteEditorMessage->tpoaValue)
                                        || $concreteEditorMessage->tpoaEnabled == 1 &&
                                        $concreteEditorMessage->manageResponse
                                        == 1
                                    ) {
                                        $campaignInvalidSmsParams = 1;
                                    }
                                }

                                // if step has a PUSH media
                                if (in_array($message->media, Service_Api_Object_Message::$GROUP_PUSH)) {
                                    $campaignHasPushChannels = 1;
                                }
                            }
                        }

                        $stepCount++;
                    }

                    /**
                     * Validating campaign
                     */
                    // verifying that campaign can be confirmed
                    //
                    // - No PUSH media : Proceeding with the confirmation
                    // - At least one PUSH media per step : List of contacts is mandatory
                    //
                    $this->view->campaignHasPushChannels = $campaignHasPushChannels;

                    // verifying execution date
                    if ($campaign->dateStart &&
                        strtotime(date('Y-m-d', strtotime($campaign->dateStart))) < strtotime(date('Y-m-d'))
                    ) {
                        $campaignHasValidDate = 0;
                    }
                    $this->view->campaignHasValidDate = $campaignHasValidDate;

                    // message validation
                    $this->view->campaignHasValidReplyTo = $campaignHasValidReplyTo;
                    $this->view->campaignHasValidMessageSubject = $campaignHasValidMessageSubject;
                    $this->view->campaignInvalidMessages = $campaignInvalidMessages;
                    $this->view->campaignHasValidSms = $campaignHasValidSms;
                    $this->view->campaignInvalidSmsParams = $campaignInvalidSmsParams;
                    $this->view->campaignFirstMessageDate = $campaignFirstMessageDate;
                    $this->view->campaignIsOnSunday = $campaignIsOnSunday;
                }
                Dm_Session::SetEntry('CurrentMessages', $campaignMessages);

                // Récupération des coûts unitaires
                $contract = Dm_Session::getConnectedUserContract();
                try {
                    $mediaCosts = array(
                        Editor_Model_Message_Row::SMS => $contract->getCost('sms'),
                        Editor_Model_Message_Row::EMAIL => $contract->getCost('email'),
                        Editor_Model_Message_Row::VOICE => $contract->getCost('voice'),
                        Editor_Model_Message_Row::VOICEMAIL => $contract->getCost('voicemail'),
                    );
                } catch (Exception $e) {
                    Dm_Log::Error($e->getTraceAsString());
                    $mediaCosts = array();
                }
                $this->view->mediaCosts = $mediaCosts;
                $this->view->contractIsWeb = $contract->type === 'web';
                $this->view->contractIsDemo = $contract->type === 'demonstration' ? "true" : "false";
                $this->view->webEstimateUrl = $this->view->href('campaign-get-cost');
                $user = Dm_Session::GetConnectedUser();
                $this->view->contractIsComplete = $this->_webUserCanConfirmCampaigns($contract, $user);
                // définition pour l'interface si l'utilisateur dispose d'un numéro de mobile invalide
                $this->view->nextStepUrl = $this->_getNextStep($campaignId, $campaign->status, $contract, $user);
                $this->view->defaultDaysCountBeforeStats =
                    Dm_Config::GetConfig('mailStatsCampaign', 'defaultDaysCount');

                $this->view->daysCountBeforeStats = $this->view->defaultDaysCountBeforeStats;
                $delay = intval($campaign->statsEmailDelay);
                if ($delay > 0) {
                    $this->view->daysCountBeforeStats = intval($delay / 24);
                }
            }
        }
    }

    /**
     * Verifyes the subject of a message
     *
     * @param type $messageId Message identifier
     * @param Editor_Model_Email_Row $concreteEditorMessage is not mandatory, used if specified.
     *
     * @return mixed Description
     */
    protected function _checkMessageReplyTo($messageId, $concreteEditorMessage)
    {
        $replyToIsValid = 1;

        // reading editor message
        if (!$concreteEditorMessage) {
            $messageHandler = new Editor_Model_Message_Table();
            $editorMessage = $messageHandler->fetchRow('extId = ' . $messageId);
            $concreteEditorMessage = $editorMessage->getConcreteMessage();
        }

        // checking message subject against the string
        if ($concreteEditorMessage->replyTo != '' &&
            preg_match(Editor_Service_Message::EMAIL_VALIDATION_REGEX, $concreteEditorMessage->replyTo) != 1
        ) {
            $replyToIsValid = 0;
        }

        return $replyToIsValid;
    }

    /**
     * Verifyes the subject of a message
     *
     * @param type $messageId Message identifier
     * @param type $compareString String used for comparing against message subject
     * @param Editor_Model_Email_Row $concreteEditorMessage is not mandatory, used if specified.
     *
     * @return mixed Description
     */
    protected function _checkMessageSubject($messageId, $compareString, $concreteEditorMessage)
    {
        $subjectIsValid = 1;

        // reading editor message
        if (!$concreteEditorMessage) {
            $messageHandler = new Editor_Model_Message_Table();
            $editorMessage = $messageHandler->fetchRow('extId = ' . $messageId);
            $concreteEditorMessage = $editorMessage->getConcreteMessage();
        }

        // checking message subject against the string
        if (!$concreteEditorMessage->subject ||
            !strcasecmp($concreteEditorMessage->subject, strtolower($compareString))
        ) {
            $subjectIsValid = 0;
        }

        return $subjectIsValid;
    }

    /**
     * Coûts indicatifs des messages
     *
     * @param int $campaignId Campaign identifier
     * @param array $campaignMessages Campaign messages
     * @param int $listId Selected List Identifier (optimization to avoid a soap call without any list)
     *
     * @return array
     */
    protected function _getMessageCost($campaignId, $campaignMessages, $listId = null)
    {
        // Récupération des messages
        $messagesForCost = array();
        if ($campaignMessages) {
            $messageHandler = new Editor_Model_Message_Table();
            foreach ($campaignMessages as $message) {
                $content = '';
                $contactCount = Dm_Session::GetEntry('cost-' . $campaignId . '-' . $listId . '-' . $message->id);
                if (isset($listId)) {
                    // On a besoin du contenu uniquement pour le média SMS
                    if ($message->media == Editor_Model_Message_Row::SMS) {
                        $messageRow = $messageHandler->getMessageFromExtId($message->id);

                        // Utilisation du tpoa ou non
                        $tpoa = '';
                        $concreteEditorMessage = $messageRow->getConcreteMessage();
                        if ($concreteEditorMessage->tpoaEnabled == 1) {
                            $tpoaInfos = Editor_Service_Bridge::getMessageSmsStop();
                            $tpoa = ' ' . $tpoaInfos->stopMessage->message;
                        }

                        // Récupération du texte du message
                        $messageContent = $concreteEditorMessage->serialize();
                        $content = $messageContent->text . $tpoa;
                    }

                    if (!isset($contactCount)) {
                        $campaignMediaFilter = new Service_Api_Filter_CampaignMedia();
                        $campaignMediaFilter->campaignId = $campaignId;
                        $campaignMediaFilter->stepId = $message->stepId;
                        $campaignMediaFilter->media = $message->media;
                        $contactCount = $this->_campaignService->campaignContactCount($campaignMediaFilter);
                        Dm_Session::SetEntry('cost-' . $campaignId . '-' . $listId . '-' . $message->id, $contactCount);
                    }
                } else {
                    $contactCount = 0;
                }
                $messagesForCost[$message->id] = array(
                    'content' => $content,
                    'media' => $message->media,
                    'contactsNb' => $contactCount
                );
            }

            /**
             * Adding Fotolia cost to the total cost
             */
            $fotoliaCost = $this->_getFotoliaCost($this->_getFotoliaImages($campaignMessages));
            if ($fotoliaCost) {
                $messagesForCost['fotolia'] = $fotoliaCost['units'] * $fotoliaCost['unitPrice'];
            }
        }

        return $messagesForCost;
    }

    /**
     * Returns cost of Fotolia images
     *
     * @param array $images Collection of Fotolia images
     *
     * @return array
     */
    protected function _getFotoliaCost($images)
    {
        $cost = 0;
        $imagePrice = Dm_Session::GetEntry(Dm_Session::CONNECTED_USER_CONTRACT)->getCost('fotolia');
        if (!empty($images) && !is_null($imagePrice) && is_numeric($imagePrice)) {
            $cost = count($images) * $imagePrice;
        }
        return array(
            'units' => count($images),
            'unitPrice' => $imagePrice
        );
    }

    /**
     * Displays popup for choosing campaign type
     *
     * @return void
     */
    public function selectAction()
    {
        $this->_helper->layout->disableLayout();

        $listId = $this->_getParam('listId');
        $rental = $this->_getParam('rental', false);
        $typeData = array();

        /*
         * Standard campaign
         */
        $typeData[Service_Api_Object_Campaign::TYPE_STANDARD] = $this->view->href(
            'campaign-add', array('preselect' => $listId));

        /**
         * Automatic campaign
         */
        $typeData[Service_Api_Object_Campaign::TYPE_AUTOMATIC] = 0;
        if ($this->view->HasAccess('createAutomaticCampaign') && ($rental != true)) {
            $typeData[Service_Api_Object_Campaign::TYPE_AUTOMATIC] = $this->view->href(
                'automatic-campaign-add', array('listId' => $listId));
        }

        /**
         * Network campaign
         */
        $typeData[Service_Api_Object_Campaign::TYPE_NETWORK] = 0;
        // reading network templates
        $templateFilter = new Service_Api_Filter_Template();
        $templateFilter->properties = array('id');
        $readTemplatesResult = $this->_campaignService->templateRead($templateFilter);
        if ($this->view->pageAccess('list-templates') &&
            isset($readTemplatesResult) && $readTemplatesResult->size > 0
        ) {
            $typeData[Service_Api_Object_Campaign::TYPE_NETWORK] = $this->view->href('list-templates',
                array('listId' => $listId));
        }

        $this->view->typeData = $typeData;
    }

    /**
     * Creates an automatic campaing
     *
     * @return  void Description
     */
    public function addAutomaticAction()
    {
        // Récupération de listes de contacts de l'utilisateur connecté
        $listFilter = new Mk_Contacts_ContactList_Filter();
        $listFilter->orderCol = 'created';
        $listFilter->orderType = 'DESC';
        $lists = $this->_contactListService->listRead($listFilter)->list; // 0.8s
        // Construction du formulaire d'ajout d'une campagne
        $form = new Frontoffice_Form_Campaign_Create(array('contactLists' => $lists));
        $this->view->form = $form;

        $name = ucfirst($this->view->translate('automatic.campaign')) . ' ' . date('d/m/Y H:i');
        $campaignListId = (!is_null($this->_getParam('listId')) && is_numeric($this->_getParam('listId'))) ?
            $this->_getParam('listId') : null;
        // Création d'une nouvelle campagne
        $campaignData = array(
            array(
                'name' => $name,
                'contactListExtId' => $campaignListId,
                'isAutomatic' => 1
            )
        );

        $campaignResult = $this->_campaignService->campaignCreate($campaignData); // 0.8s
        if (!$campaignResult->size) {
            $this->_helper->FlashMessenger->addMessage(
                array('error' => ucfirst($this->view->translate('cannot create campaign')))
            );
        } else {
            $campaign = $campaignResult->list[0];
            $this->view->campaignId = $campaign->id;

            // Création d'une étape par default
            $stepData = array(
                array(
                    'name' => 'Etape',
                    'campaignId' => $campaign->id,
                    'isAutomatic' => 1
                )
            );
            $stepResult = $this->_campaignService->stepCreate($stepData);  // 0.6s

            if (!$stepResult->size) {
                $this->_helper->FlashMessenger->addMessage(
                    array('error' => ucfirst($this->view->translate('cannot create step')))
                );
            } else {
                $step = $stepResult->list[0];
                $stepId = $step->id;
                $this->view->stepId = $stepId;

                $form->populate(array(
                    'id' => $campaign->id, 'name' => $name, 'contactListExtId' => $campaignListId));

                $this->getHelper('viewRenderer')->setNoRender();

                Dm_Session::SetEntry('step_' . $step->id, array('stepIndex' => '0'), 'step-edit');
                Dm_Session::SetEntry('campaign_' . $campaign->id,
                    array('campaignName' => ucfirst($this->view->translate('new campaign'))),
                    'step-edit');

                $this->_redirect($this->view->href('step-list',
                    array('campaignId' => $campaign->id, 'stepId' => $stepId)));
            }
        }
    }

    /**
     * Returns HTML code for the automatic campaign options page
     *
     * @return void
     */
    public function ajaxAutomaticOptionsAction()
    {
        $this->_helper->layout->disableLayout();

        $this->view->customFields = Service_Contact::getAutomaticCustomFields();
        $this->view->campaignId = $this->_getParam('campaignId');
        $this->view->stepId = $this->_getParam('stepId');

        $sessionData = Dm_Session::GetEntry("campaignAuto_{$this->_getParam('campaignId')}");
        $this->view->automaticRecurrence = $sessionData['automaticRecurrence'];
        $this->view->automaticField = $sessionData['automaticField'];
        $this->view->automaticBeforeRecurrence = $sessionData['automaticBeforeRecurrence'];
        $this->view->automaticShiftRecurrence = $sessionData['automaticShiftRecurrence'];
        $this->view->automaticSendingHour = $sessionData['automaticSendingHour'];
    }

    /**
     * Saving auto options in session
     *
     * @param int $campaignId Campaign identifier
     * @param array $data Options of automatic campaign
     *
     * @return void
     */
    protected function _setAutoOptionsInSession($campaignId, $data)
    {
        if (is_null(Dm_Session::GetEntry("campaignAuto_{$campaignId}"))) {
            Dm_Session::SetEntry("campaignAuto_{$campaignId}", $data);
        } else {
            $sessionData = Dm_Session::GetEntry("campaignAuto_{$campaignId}");
            foreach ($sessionData as $key => $value) {
                if (isset($data[$key]) && $data[$key] != '') {
                    $sessionData[$key] = $data[$key];
                }
            }
            Dm_Session::SetEntry("campaignAuto_{$campaignId}", $sessionData);
        }
    }

    /**
     * AJAx saving automation data
     *
     * @return void
     */
    public function ajaxSaveAutomaticOptionsAction()
    {
        $campaignId = $this->_getParam('campaignId');
        $jsonResult = array('status' => true);
        $sessionData = array();

        if (is_null($campaignId) || !is_numeric($campaignId)) {
            $jsonResult['status'] = false;
        } else {
            if (!is_null($this->_getParam('campaignParams'))) {
                // updating campaign
                $updateFilter = new Service_Api_Filter_Campaign();
                $updateFilter->campaignId = array($campaignId);
                $updateResult = $this->_campaignService->campaignUpdate(
                    $updateFilter, $this->_getParam('campaignParams'));

                foreach ($this->_getParam('campaignParams') as $key => $value) {
                    $sessionData[$key] = $value;
                }

                if ($updateResult != 1) {
                    $jsonResult['status'] = false;
                    $jsonResult['message'] = ucfirst($this->view->translate('automatic.cannot save campaign data'));
                }
            }

            // updating step
            if (!is_null($this->_getParam('stepParams'))) {
                $stepParams = $this->_getParam('stepParams');
                // converting sending hour from user's timezone to system's timezone
                $sHour = Service_Api_Handler_Utils::ConvertHourToTimezone(
                    $stepParams['automaticSendingHour'], Dm_Session::GetEntry(Dm_Session::CONNECTED_USER)->timezone,
                    ini_get('date.timezone'));


                $stepFilter = new Service_Api_Filter_Step();
                $stepFilter->stepId = array($stepParams['stepId']);
                $stepUpdateResult = $this->_campaignService->stepUpdate(
                    $stepFilter, array('automaticSendingHour' => $sHour));

                $sessionData['automaticSendingHour'] = $sHour;

                if ($stepUpdateResult != 1) {
                    $jsonResult['status'] = false;
                    $jsonResult['message'] = ucfirst($this->view->translate('automatic.cannot save sending hour'));
                }
            }
        }

        $this->_setAutoOptionsInSession($campaignId, $sessionData);

        $this->_helper->json->sendJson($jsonResult);
    }

    /**
     * Création d'une nouvell campagne
     *
     * @return void
     */
    public function addAction()
    {
        $form = new Frontoffice_Form_Campaign_Create();
        $this->view->form = $form;

        $user = Dm_Session::GetConnectedUser();
        $datetime = new Zend_Date(Zend_Registry::get('Zend_Locale'));
        $datetime->setTimeZone($user->timezone);
        $name = ucfirst($this->view->translate('campaign')) . ' ' .
            $this->view->formatDate($datetime, 'dd/MM/yyyy HH:mm');

        $listId = $this->_getParam('preselect');
        $campaignListId = (!is_null($listId) && is_numeric($listId)) ? $listId : null;
        // Création d'une nouvelle campagne
        // on multiplie le nombre de jours par défaut pour le délai de stats car on veut stocker le nombre
        // d'heures et pas de jours
        $campaignData = array(
            array(
                'name' => $name,
                'contactListExtId' => $campaignListId,
                //'statsEmailDelay' => 24 * Dm_Config::GetConfig('mailStatsCampaign', 'defaultDaysCount')
            )
        );
        $campaignResult = $this->_campaignService->campaignCreate($campaignData); // 0.8s
        if (!$campaignResult->size) {
            $this->_helper->FlashMessenger->addMessage(
                array('error' => ucfirst($this->view->translate('cannot create campaign')))
            );
        } else {
            $campaign = $campaignResult->list[0];
            $this->view->campaignId = $campaign->id;

            // Création d'une étape par default
            $stepData = array(
                array(
                    'name' => 'Etape',
                    'campaignId' => $campaign->id,
                )
            );
            $stepResult = $this->_campaignService->stepCreate($stepData);  // 0.6s

            if (!$stepResult->size) {
                $this->_helper->FlashMessenger->addMessage(
                    array('error' => ucfirst($this->view->translate('cannot create step')))
                );
            } else {
                $step = $stepResult->list[0];
                $stepId = $step->id;
                $this->view->stepId = $stepId;

                $form->populate(array(
                    'id' => $campaign->id, 'name' => $name, 'contactListExtId' => $campaignListId));

                $this->getHelper('viewRenderer')->setNoRender();

                Dm_Session::SetEntry('step_' . $step->id, array('stepIndex' => '0'), 'step-edit');
                Dm_Session::SetEntry('campaign_' . $campaign->id,
                    array('campaignName' => ucfirst($this->view->translate('new campaign'))),
                    'step-edit');

                $this->_redirect($this->view->href('step-list',
                    array('campaignId' => $campaign->id, 'stepId' => $stepId)));
            }
        }
    }

    /**
     * Confirmation d'une campagne
     *
     * Paramètres :
     * - campaignId = Identifiant de la campagne à confirmer
     *
     * @return void
     */
    public function confirmAction()
    {
        Dm_Log::Debug('Start ' . __METHOD__);

        $jsonResult = array('status' => true);
        $campaignId = $this->_getParam('campaignId');
        try {
            if (is_null($campaignId) || !is_numeric($campaignId)) {
                throw new Exception('cannot confirm campaign with invalid identifier');
            }
            $purchaseResult = false;
            $campaign = Service_Api_Object_Campaign::LoadById($campaignId);
            // we got the campaign, we can verify the contacts list validity
            //
            // catch the list informations :
            $listFilter = new Mk_Contacts_ContactList_Filter();
            $listFilter->listId = array($campaign->contactListExtId);
            $listFilter->properties = array(
                'id', 'name', 'expired', 'importStatus', 'dateExpired', 'dateCreated', 'category',
                'stats');
            $listContainer = Mk_Factory::GetContactListAdapter()->listRead($listFilter);
            $list = $listContainer->list[0];
            unset($listContainer);
            unset($listFilter);
            // verifiy if list is rented :
            if ($list->category == 'RENTED') {

                // verifiy if user can use rented lists
                if (!$this->view->hasAccess('rentalContactList')) {
                    throw new Exception(
                        'cannot confirm campaign, unable to use a rented list');
                }

                // verify if campaign is automatic
                if ($campaign->isAutomatic == true) {
                    throw new Exception(
                        'cannot confirm campaign, you can\'t use a rented list for an automatic campaign');
                }

                $steps = $campaign->getSteps();

                // check if step count > 1, we can't use the rented list on multiple steps campaign
                if (count($steps) > 1) {
                    throw new Exception(
                        'cannot confirm campaign, you can\'t use a rented list for a multiple steps campaign');
                }

                $dateNow = date('Y-m-d H:i:s', time());
                $dateSending = date_create_from_format('Y-m-d H:i:s', $steps[0]->dateExecution);
                $dateListExpiration = date_create_from_format('Y-m-d H:i:s', $list->dateExpired);

                // check if list has expired
                if ($list->expired == true || ($list->dateExpired != null && $dateListExpiration < $dateNow)) {
                    throw new Exception(
                        'cannot confirm campaign, your list has expired, please select another one');
                }

                // check if list is always available at the programmed date of the first and only step
                if ($steps[0]->dateExecution != null && $list->dateExpired != null &&
                    $dateListExpiration < $dateSending
                ) {
                    throw new Exception(
                        'cannot confirm campaign, your list will expired before ' .
                        'your step will be sent, please select ' .
                        'another one');
                }
            }

            foreach ($campaign->getSteps() as $step) {
                $mobileSiteMessage = null;
                $messageSet = $step->getMessages();
                $messageTable = new Editor_Model_Message_Table();
                $extIds = array();
                foreach ($messageSet as $message) {
                    $extIds[] = $message->id;
                    if ($message->media === Service_Api_Object_Message::SITE_MOBILE) {
                        $mobileSiteMessage = $message;
                    }
                }
                if (count($extIds) >= 1) {
                    $editorMsgsRowset = $messageTable->fetchAll('extId IN (' . implode(',', $extIds) . ')');
                } else {
                    $editorMsgsRowset = array();
                    throw new Exception('your campaign does not contain any message.');
                }
                // creating new array from rowsets sorted by Editor_Message extId's
                $editorMessages = array();
                foreach ($editorMsgsRowset as $editorMsgRow) {
                    $editorMessages[$editorMsgRow->extId] = $editorMsgRow;
                }
                // Defining base mobile site address without source
                $siteUrl = '';
                if (!empty($mobileSiteMessage)) {
                    // Using Campaign>messageid (Editor>extId) instead of Editor>messageId
                    $siteUrl = Dm_Session::GetEntry(Dm_Session::BASE_URL)
                        . $this->view->Href('site_display', array('messageId' => $mobileSiteMessage->id));
                    // TODO il faut l'url en http !!
                    $site = $editorMessages[$mobileSiteMessage->id];
                    $concreteEditorMessage = $site->getConcreteMessage();
                    if (!is_null($concreteEditorMessage->scheduleStopDate)) {
                        $siteDate = date('Y-m-d H:i:s', strtotime($concreteEditorMessage->scheduleStopDate));
                        if (!is_null($step->dateExecution)) {
                            $startDate = date('Y-m-d H:i:s', strtotime($step->dateExecution));
                        } else {
                            $startDate = date('Y-m-d H:i:s');
                        }
                        if ($startDate > $siteDate) {
                            throw new Exception('cannot confirm the campaign if the site end date ' .
                                'is before the campaign start, please try again.');
                        }
                    }
                }

                /**
                 * CHECK MESSAGES DELAY (sequencing)
                 */
                foreach ($messageSet as $message) {
                    if (in_array($message->media, Service_Api_Object_Message::$GROUP_PUSH) &&
                        property_exists($list->stats, $message->media)
                    ) {
                        $media = $message->media;
                        if ($this->messageTimingIsValid(
                                $message, $editorMessages[$message->id], $list->stats->$media, $step) !== true
                        ) {
                            throw new Exception('Sequencing defined for ' . $media .
                                ' includes sending messages after 8PM, campaign cannot be confirmed. ' .
                                '<br /><br />Please check your sequencing options.');
                        }
                    }
                }

                foreach ($messageSet as $message) {
                    /**
                     * PURCHASING FOTOLIA IMAGES
                     */
                    $pResult = $this->_purchaseFotoliaImages($message);

                    if ($pResult) {
                        $purchaseResult = true;

                        $replacementTable = array();

                        // ----- REMPLACEMENTS
                        // ---      short mobile site url
                        $srcSiteShortUrl = '';
                        if ($siteUrl !== '') {
                            // Add source information to full sized url
                            $srcSiteUrl = $siteUrl . '/source/' . $message->media;
                            // create short url
                            $apiShortUrl = Dm_Api_Lsms::factory(Zend_Registry::get('lsms'));
                            $srcSiteShortUrl = $apiShortUrl->createShortUrl($srcSiteUrl);
                        }
                        $replacementTable['/#siteShortUrl#/'] = $srcSiteShortUrl;

                        // Serialize message content
                        $editorMessage = $editorMessages[$message->id];

                        /* @var $serializedMessage Editor_Model_MessageContent */
                        $serializedMessage = $editorMessage->serialize($replacementTable);
                        $this->saveSerializedMessage($message, $serializedMessage);
                    }
                }
            }

            /**
             * CONFIRMING CAMPAIGN
             */
            if ($purchaseResult) {
                Dm_Log::Debug("Purchased Fotolia images for campaign $campaignId");

                // CONFIRMING CAMPAIGN
                $campaignFilter = new Service_Api_Filter_Campaign();
                $campaignFilter->setCampaignId($campaignId);
                $confirmResult = $this->_campaignService->campaignConfirm($campaignFilter);
                if (isset($confirmResult['status']) && $confirmResult['status']) {
                    Dm_Log::info("campaign $campaignId confirmed");

                    $url = $this->_getNextStep($campaignId, Service_Api_Object_Campaign::STATUS_CONFIRMED);
                    $jsonResult['url'] = $url;
                } else {
                    throw new Exception($confirmResult['message']);
                }
            } else {
                throw new Exception('cannot confirm campaign, unable to purchase Fotolia images, please try again.');
            }
        } catch (Exception $e) {
            Dm_Log::Error($e->getMessage());
            Dm_Log::Error($e->getTraceAsString());
            $jsonResult['confirmMessage'] = ucfirst($this->view->translate($e->getMessage()));
            $jsonResult['status'] = false;
            $jsonResult['message'] = ucfirst($this->view->translate($e->getMessage()));
        }

        // on fournit un paramètre supplémentaire à la fonction,
        // si le paramètre ajax est fourni on retourne l'adresse de la page vers laquelle renvoyer l'utilisateru
        // sinon on redirige l'utilisateur vers la page suivante du flux de données
        if ($this->_getParam('ajax') == true) {
            $this->_helper->layout->disableLayout();
            $this->_helper->json->sendJson($jsonResult);
        } else {
            if ($jsonResult['status'] === true) {
                $this->_redirect($jsonResult['url']);
            } else {
                $this->_redirect($this->view->href('campaign-edit',
                    array('status' => $status, 'campaignId' => $campaignId))
                );
            }
        }

        Dm_Log::Debug('End ' . __METHOD__);
    }

    /**
     * Returns campaign next step page full url, depending on contract web or not, completed or not.
     * Gives campaign list for non web contracts, billing resume before payment or fill data page for web contracts
     *
     * @param int                  $campaignId campaign id
     * @param string               $status     status of campaign
     * @param Mk_Entities_Contract $contract   (optionnal) user contract
     * @param Mk_Entities_User     $user       (optionnal) user
     *
     * @return string page url where to send the user after confirmation
     */
    protected function _getNextStep(
        $campaignId, $status, $contract = null, $user = null, $addtionnalParameters = array())
    {
        // on retourne l'adresse de la page de prochaine étape de la campagne.
        // par défaut, liste des campagnes.
        // - si la campagne est en attente de paiement, deux solutions,
        // soit les données contrat et utilisateur sont complètes => page de paiement
        // soit les données contrat et utilisateur ne sont pas complètes => page de complétion d'infos
        // - si la campagne est liée à une offre fidélité on redirige vers le module Loyalty

        // récupération de l'offerId s'il est défini dans l'appel
        $offerId = "";
        if (!empty($addtionnalParameters["offerId"])) {
            $offerId = $addtionnalParameters["offerId"];
        }

        //test sur la version de l'éditeau pour changer le lien
        if ($addtionnalParameters['editor'] == 'new')
        {
            $pageName = 'campaign-beta';
            $addtionnalParameters = array();
            $params = array();
        }
        else{
            $pageName = 'campaign-list';
        }
        $params = array();
        if ($contract === null) {
            $contract = Dm_Session::GetConnectedUserContract();
        }
        if ($contract->type === 'web') { // CONTRAT EST WEB
            if ($user === null) {
                $user = Dm_Session::GetConnectedUser();
            }

            if (// DONNEES SONT BIEN REMPLIES et mobile validé
            $this->_webUserIsComplete($contract, $user)
            ) {
                // si les données de l'utilisateur sont complètes
                if ($this->_webUserCanConfirmCampaigns($contract, $user)) {
                    // si la campagne a été confirmée et poussée
                    if (in_array($status,
                                 array(
                                     Service_Api_Object_Campaign::STATUS_CONFIRMED,
                                     Service_Api_Object_Campaign::STATUS_PUSHED
                                 ))) {
                        // page de résumé et paiement de campagne
                        $pageName = 'billing-resume-before-payment';
                    } else {
                        // données complètes mais campagne non confirmée donc on doit la créer
                        $pageName = 'campaign-confirm';
                    }
                } else {
                    // les données sont complètes sauf mobile
                    $pageName = 'billing-complete-data';
                }
            } else {
                // données incomplètes on redirige vers la page de complétion / validation de mobile
                $pageName = 'billing-complete-data';
            }
            $params = array('campaignId' => $campaignId);
        } else {
            $params = array('status' => $status);

            // campaign loyalty
            if (!empty($offerId)) {
                $pageName = 'loyalty-offers';
                $addtionnalParameters = array();
                $params = array();
            }

        }

        $urlToReturn = $this->view->href($pageName, array_merge($addtionnalParameters, $params));
        if (!empty($offerId)) {
            $urlToReturn.="#/id/".$offerId."/camid/".$campaignId;
        }

        return $urlToReturn;
    }

    /**
     * Checks if user and his contract can be used for validate a campaign, this is useful for web users which needs
     * multiple informations to be set in contract and user properties, and needs to have a mobile validated before
     * we can confirm a campaign and make them pay.
     *
     * @param Mk_Entities_Contract $contract user contract
     * @param Mk_Entities_User $user user object
     *
     * @return boolean
     */
    private function _webUserCanConfirmCampaigns($contract, $user)
    {
        return $this->_webUserIsComplete($contract, $user) &&
        $user->mobileValidated == 1;
    }

    /**
     * Checks if user and his contract are completed with informations
     *
     * @param Mk_Entities_Contract $contract user contract
     * @param Mk_Entities_User $user user object
     *
     * @return boolean
     */
    private function _webUserIsComplete($contract, $user)
    {
        return $contract->city !== null
        && preg_match('#(web-).*@.*#', $contract->name) !== 1
        && $user->firstName !== null
        && $user->name !== null
        && $user->mobile !== null
        && $contract->postCode !== null
        && $contract->address !== null;
    }

    /**
     * Returns next step url for the campaign, specifically for web contracts & loyalty campaigns...
     *
     * @return json {url:string}
     */
    public function getNextStepAction()
    {
        $editorVersion = $this->_getParam('editor', 'old');
        $addtionnalParameters = array('editor' => $editorVersion);
        $campaignId = $this->_getParam('campaignId');
        $status = Service_Api_Object_Campaign::STATUS_EDITING;
        if ($editorVersion == 'new') {
            $campaign = Editor_Service_Api_Rest_Wrapper_Campaign::LoadById($campaignId);
            $status = $campaign->status;
            if ($campaign->type == "loyaltyoffer" && !empty($campaign->offerId)) {
                $addtionnalParameters["offerId"] = $campaign->offerId;
            }
        }
        $url = $this->_getNextStep($campaignId, $status, null, null, $addtionnalParameters);
        $this->_helper->json->sendJson(array('url' => $url));
    }

    /**
     * Purchase Fotolia image
     *
     * @param array $message Message object
     *
     * @return boolean
     */
    protected function _purchaseFotoliaImages($message)
    {
        $images = $this->_getFotoliaImages(array($message));
        $result = true;

        if (!empty($images)) {
            foreach ($images as $componentId => $image) {
                $response = Service_Api_Object_Content::GetService()->contentsPurchase($image['id'], 'FOTOLIA');

                if (is_null($response)) {
                    $result = false;
                } else {
                    $content = $response->list[0];

                    // updating image url with the new Baseo content url
                    $componentHandler = new Editor_Model_Component_Img_Table();
                    // On set l'extSource a mine pour les contenus fotolia achetés pour qu'il ne soit pas refacturé
                    // a la duplication de la campagne
                    // @see https://redmine.digitaleo.com/issues/15836
                    $res = $componentHandler->update(
                        array('src' => $content->url, 'extSource' => 'mine'), "componentId = {$componentId}");

                    if (!$res) {
                        $result = false;
                    }
                }
            }
        }

        return $result;
    }

    /**
     * Returns Fotolia images for a collection of messages
     *
     * @param array $messages Messages
     *
     * @return array Fotolia images
     */
    protected function _getFotoliaImages($messages)
    {
        $images = array();

        // reading editor messages
        $messageHandler = new Editor_Model_Message_Table();
        foreach ($messages as $message) {
            $editorMessage = $messageHandler->fetchRow("extId = {$message->id}");
            $concreteEditorMessage = $editorMessage->getConcreteMessage();

            // reading image components
            $componentHandler = new Editor_Model_Component_Table();
            $components = $componentHandler->fetchAll(
                "messageId = {$concreteEditorMessage['messageId']} AND type = 'img'");

            // reading Fotolia images
            foreach ($components as $component) {
                /* @var $component Editor_Model_Component_Row */
                $image = $component->getTypedComponent();
                if (strcasecmp($image['extSource'], 'fotolia') == 0) {
                    $images[$image['componentId']] = array(
                        'id' => $image['extId'],
                        'url' => $image['src']);
                }
            }
        }

        return $images;
    }

    /**
     * Returns true if message can be sent within timing (between datestart and 20h)
     *
     * @param Service_Api_Object_Message $message message cmpeo to validate
     * @param Editor_Model_Message_Row $editorMessage message editor to validate
     * @param integer $contacts number of contacts to send to this message
     * @param Service_Api_Object_Step $step step cmpeo (needed to have execution date
     *
     * @return boolean true or false
     * @throws Exception
     */
    protected function messageTimingIsValid($message, $editorMessage, $contacts, $step)
    {
        $media = $message->media;
        if (in_array($media, Service_Api_Object_Message::$GROUP_PUSH) &&
            $editorMessage->frequency > 0 && $editorMessage->quantity > 0
        ) {

            if (is_numeric($contacts)) {
                $nbContactsToSend = $contacts;
            } else {
                throw new Exception('cannot confirm campaign with 0 contacts');
            }

            $fq = $editorMessage->frequency;
            $qty = $editorMessage->quantity;
            // CHECK MESSAGE TIMING AVAILABILITY
            //
            // REGLE : La date d'envoi du dernier lot doit être avant 20h
            // ex : si 3 lots envoyés tous les 10 minutes, le 3ème lot part 20 minutes après le 1er
            // Si x lots envoyés tous les y unités de temps, (unités de temps après le 1er) = (x-1) * y
            // bug #14593 :
            // 8866 contacts, lots de 4433 (donc 2 lots), départ 10h.
            // date de fin = 10h + (2-1) * 390*60 = 16h30 (correct on autorise l'envoi)
            $numberOfBatches = ceil($nbContactsToSend / $qty); // arrondi superieur : au moins 1 lot si entre 0 et 1
            $submissionDuration = ($numberOfBatches - 1) * $fq; // minutes

            if ($step->dateExecution != null) {
                $timeSendStart = strtotime($step->dateExecution);
            } else {
                $timeSendStart = time();
            }

            $stepDateExecution = new DateTime(date('c', $timeSendStart));

            // date et heure limite d'envoi :
            $dateLimit = new DateTime($stepDateExecution->format('Y-m-d'));
            $dateLimit->add(new DateInterval('PT' . (20 * 60 * 60) . 'S'));

            $executionEndEstimated = new DateTime($stepDateExecution->format('c'));
            $executionEndEstimated->add(new DateInterval('PT' . ($submissionDuration * 60) . 'S'));

            Dm_Log::Debug("Sequencement :\n\rDate de début : " .
                $stepDateExecution->format('Y-m-d H:i') . "\n\rDate de fin estimée : " .
                $executionEndEstimated->format('Y-m-d H:i') . "\n\rDurée : " .
                $submissionDuration . " minutes");

            if ($executionEndEstimated > $dateLimit) {
                Dm_Log::Error("Execution de la campagne trop longue");
                return false;
            } else {
                Dm_Log::Debug('Sequencement OK');
            }
        }
        return true;
    }

    /**
     * Returns details for the Fotolia images
     *
     * @return array Json images data
     */
    public function ajaxFotoliaDetailAction()
    {
        $this->_helper->layout->disableLayout();
        $images = array();

        $campaignId = $this->_getParam('campaignId');
        if (!is_null($campaignId) && is_numeric($campaignId)) {
            // reading messages
            $messageFilter = new Service_Api_Filter_Message();
            $messageFilter->campaignId = array($campaignId);
            $messageFilter->properties = array('id');
            $messageContainer = Dm_Session::GetEntry(Dm_Session::SERVICE_FACTORY)
                ->getCampaignService()
                ->messageRead($messageFilter);
            if ($messageContainer->size) {
                // reading Fotolia images
                $images = $this->_getFotoliaImages($messageContainer->list);
            }
        }
        $this->view->images = $images;
        $this->view->totalCost = $this->_getFotoliaCost($images);
    }

    /**
     * Suppression d'une campagne
     *
     * Paramètres :
     * - campaignId = Identifiant de la campagne à supprimer
     * - status     = Statut de la campagne pour pourvoir revenir à la même liste de campagnes
     *
     * @return void
     */
    public function deleteAction()
    {
        Dm_Log::Debug('Start ' . __METHOD__);

        $status = $this->_getParam('status');
        $campaignId = $this->_getParam('campaignId');

        if (!is_null($campaignId) && is_numeric($campaignId)) {
            // Récupération de la campagne
            $campaignFilter = new Service_Api_Filter_Campaign();
            $campaignFilter->campaignId = array($campaignId);
            $campaigns = $this->_campaignService->campaignRead($campaignFilter);

            if (!$campaigns->size) {
                $this->_helper->FlashMessenger->addMessage(
                    array('error' => ucfirst($this->view->translate("cannot delete campaign")))
                );
            } else {
                $campaign = $campaigns->list[0];

                // On peut supprimer une campagne non-automatisées seulement si elle n'a pas le statut 'running'
                // On peut supprimer une campagne automatisée à toute moment
                if ($campaign->isAutomatic ||
                    (!$campaign->isAutomatic && ($campaign->status == Service_Api_Object_Campaign::STATUS_EDITING ||
                            $campaign->status == Service_Api_Object_Campaign::STATUS_CONFIRMED))
                ) {
                    // Suppresion de la campagne
                    $this->_campaignService->campaignDelete($campaignFilter);
                    $this->_helper->FlashMessenger->addMessage(
                        array('notice' => ucfirst($this->view->translate("campaign deleted")))
                    );
                } else {
                    $this->_helper->FlashMessenger->addMessage(
                        array('error' => $this->view->translate(sprintf("Cannot delete campaign with status : %s.",
                            $campaign->status)))
                    );
                }
            }
        } else {
            $this->_helper->FlashMessenger->addMessage(
                array('error' => ucfirst($this->view->translate("cannot delete campaign with invalid identifier")))
            );
        }

        if (!is_null($this->_getParam('ajax'))) {
            $this->view->layout()->disableLayout();
            $status = true;
            $messages = $this->_helper->FlashMessenger->getCurrentMessages();
            $messagesString = "";
            $this->_helper->FlashMessenger->clearCurrentMessages();
            foreach ($messages as $messageTbl) {
                $keys = array_keys($messageTbl);
                $msgTxt = $messageTbl[$keys[0]];
                if ($keys[0] == 'error') {
                    $status = false;
                    $messagesString .= $msgTxt . "<br />";
                }
            }
            $result = array(
                'status' => $status,
                'messages' => $messagesString);
            $this->_helper->json->sendJson($result);
        }

        $this->_redirect($this->view->href('campaign-list', array('status' => $status)));
    }

    /**
     * Annulation d'une campagne
     *
     * Paramètres :
     * - campaignId = Identifiant de la campagne à supprimer
     * - status     = Statut de la campagne pour pourvoir revenir à la même liste de campagnes
     *
     * @return void
     */
    public function cancelAction()
    {
        Dm_Log::Debug('Start ' . __METHOD__);

        $campaignId = $this->_getParam('campaignId');

        if (!is_null($campaignId) && is_numeric($campaignId)) {
            // Récupération de la campagne
            $campaignFilter = new Service_Api_Filter_Campaign();
            $campaignFilter->campaignId = array($campaignId);
            $campaigns = $this->_campaignService->campaignRead($campaignFilter);

            if (!$campaigns->size) {
                $this->_helper->FlashMessenger->addMessage(
                    array('error' => ucfirst($this->view->translate("cannot cancel campaign, no campaign found")))
                );
            } else {
                $campaign = $campaigns->list[0];
                $canCancelCampaign = false;
                if ($campaign->isAutomatic) {
                    $isRunning = in_array($campaign->status,
                        array(
                            Service_Api_Object_Campaign::STATUS_CONFIRMED,
                            Service_Api_Object_Campaign::STATUS_BUILD_IN_PROGRESS,
                            Service_Api_Object_Campaign::STATUS_BUILD_DONE,
                            Service_Api_Object_Campaign::STATUS_RUNNING,
                            Service_Api_Object_Campaign::STATUS_CONTACT_SERIALIZE,
                            Service_Api_Object_Campaign::STATUS_PUSHED));

                    // L'annulation d'une campagne automatisée est possible à tout moment (tant qu'elle est active)
                    if ($isRunning || isset($campaign->messengeoExtId) && !is_null($campaign->messengeoExtId)) {
                        // setting campaign's steps statuses to Confirmed and executionDate to NOW() + 1
                        // so the steps will not be processd by the daemon
                        $updateStepFilter = new Service_Api_Filter_Step();
                        $updateStepFilter->campaignId = array($campaignId);
                        $updateStepResult = $this->_campaignService->stepUpdate(
                            $updateStepFilter,
                            array(
                                'status' => Service_Api_Object_Step::STATUS_CONFIRMED,
                                'dateExecution' => date('Y-m-d H:i:s', strtotime('+1 day'))
                            ));

                        if ($updateStepResult) {
                            // setting campaign status to Confirmed and startDate to NOW() + 1
                            // so the campaign will not be processd by the daemon
                            $updateCampaignFilter = new Service_Api_Filter_Campaign();
                            $updateCampaignFilter->campaignId = array($campaignId);
                            $updateCampaignResult = $this->_campaignService->campaignUpdate(
                                $updateCampaignFilter,
                                array(
                                    'status' => Service_Api_Object_Campaign::STATUS_CONFIRMED,
                                    'dateStart' => date('Y-m-d H:i:s', strtotime('+1 day'))
                                ));

                            if ($updateCampaignResult) {
                                $canCancelCampaign = true;
                            }
                        }
                    }
                } else {
                    // On ne peut annuler une campagne seulement si elle a le statut 'confirmed', 'build_in_progress' ou
                    // 'build_done'
                    if ($campaign->status == Service_Api_Object_Campaign::STATUS_CONFIRMED ||
                        $campaign->status == Service_Api_Object_Campaign::STATUS_BUILD_DONE ||
                        $campaign->status == Service_Api_Object_Campaign::STATUS_CONTACT_SERIALIZE ||
                        $campaign->status == Service_Api_Object_Campaign::STATUS_BUILD_IN_PROGRESS ||
                        isset($campaign->messengeoExtId) && !is_null($campaign->messengeoExtId)
                    ) {
                        $canCancelCampaign = true;
                    }
                }

                if ($canCancelCampaign) {
                    // Suppresion de la campagne
                    $this->_campaignService->campaignCancel($campaignFilter);
                    $this->_helper->FlashMessenger->addMessage(
                        array('notice' => ucfirst($this->view->translate("campaign cancelled")))
                    );
                } else {
                    $this->_helper->FlashMessenger->addMessage(
                        array('error' => ucfirst(
                            $this->view->translate("impossible to cancel an ongoing campaign mailing")))
                    );
                    $this->_redirect($this->view->href(
                        'campaign-list', array('status' => $campaign->status)
                    )
                    );
                    $this->_redirect($this->view->href('campaign-list', array('status' => $campaign->status)));
                }
            }
        } else {
            $this->_helper->FlashMessenger->addMessage(
                array('error' => ucfirst($this->view->translate("cannot cancel campaign with invalid identifier")))
            );
        }
        $this->_redirect($this->view->href(
            'campaign-list', array('status' => Service_Api_Object_Campaign::STATUS_STOPPED)
        )
        );
    }

    /**
     * Genereate an archive with PDF and all message detail CSV
     *
     * Awaiting params :
     * - campaignId integer campaign identifier
     *
     * @return void => Download ZIP file
     */
    public function generateStatsArchiveAction()
    {
        $campaignId = $this->_getParam('campaignId');
        if (!$this->view->hasAccess('exportContact')) {
            $errorMessage = ucfirst($this->view->translate('cannot export your selection')) . ' <br> ' .
                $this->view->translate("You don't have the necessary credentials to access this resource");
            $this->_helper->FlashMessenger->addMessage(
                array('error' => $errorMessage)
            );
            $this->_redirect($this->view->href('campaign-stat', array('campaignId' => $campaignId)));
        }

        $sessionId = Dm_Session::GetEntry('id', 'current-campaign-stat');
        $sessionCampaign = Dm_Session::GetEntry('campaign', 'current-campaign-stat');
        //We check if the campaign is already load in session
        if (isset($sessionId) && $sessionId == $campaignId && isset($sessionCampaign)) {
            $campaign = $sessionCampaign;
        } else {
            //Load the campaign from its ID
            $campaign = Service_Api_Object_Campaign::LoadById($campaignId);
        }

        $json = $this->view->action(
            'complete-stats', 'campaign', 'frontoffice', array('campaignId' => $campaignId, 'format' => 'file-pdf'));
        $result = json_decode($json);
        $filesToArchive[$result->filename] = $result->file;
        $counter = 1;
        foreach ($campaign->getSteps() as $step) {
            foreach ($step->getMessages() as $message) {
                //If the message is a push media we can get a detail
                if (in_array($message->media, Service_Api_Object_Message::$GROUP_PUSH)) {
                    $statFilter = new Service_Api_Filter_Stat();
                    $statFilter->media = array($message->media);
                    $statFilter->stepId = array($step->id);
                    $statFilter->messageId = array($message->id);
                    $statFilter->restrictedAccess = !$this->view->hasAccess('contactManagement');
                    $fileName = Service_Contact::ExportContactsAndStatusFromStatfilter($statFilter);
                    unset($statFilter);

                    $filename = $this->view->translate('step') . '-' . $counter . '-' . $message->media . '.csv';
                    $filesToArchive[$filename] = $fileName;
                }
                if (in_array($message->media, Service_Api_Object_Message::$GROUP_SITEMOBILE)) {
                    $stats = $step->getStats();
                    $stat = $stats[$message->media];
                    if (isset($stat['stats']->form) && !empty($stat['stats']->form)) {
                        $formId = $stat['stats']->form[0]->formId;
                        $json = $this->view->action(
                            'form-detail', 'message', 'frontoffice',
                            array(
                                'messageId' => $message->id,
                                'formId' => $formId,
                                'format' => 'file-csv')
                        );
                        $result = json_decode($json);
                        $fileKey = $this->view->translate('step') . '-' . $counter . '-' . $message->media . '.csv';
                        $filesToArchive[$fileKey] = $result->file;
                    }
                }
            }
        }
        $name = preg_replace('/[\s]+/', '_', $campaign->name);
        $name = preg_replace('/[^a-zA-Z0-9_\.-]/', '', $name);
        $filename = Dm_Config::GetPath('tmp') . $name . '.zip';

        $zip = new ZipArchive();
        if ($zip->open($filename, ZIPARCHIVE::OVERWRITE) !== TRUE) {
            throw new Exception("Impossible d'ouvrir le fichier <$filename>\n");
        }
        foreach ($filesToArchive as $newfilename => $path) {
            $zip->addFile($path, $newfilename);
        }
        $zip->close();

        foreach ($filesToArchive as $newfilename => $path) {
            unlink($path);
        }
        header('Content-Description: File Transfer');
        if (headers_sent()) {
            $this->Error('Some data has already been output to browser, can\'t send ZIP file');
        }

        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="' . $name . '.zip"');
        header('Content-Transfer-Encoding: binary');
        header("Cache-Control: public");
        header("Pragma:");
        header("Expires: 0");
        readfile($filename);
        $this->view->layout()->disableLayout();
        $this->_helper->viewRenderer->setNoRender(true);
        die();
    }

    /**
     * Genereate an archive with PDF and all message detail CSV
     *
     * @param int $campaignId campaign identifier
     * @param boolean $export boolean export des stats
     *
     * @return void => Download ZIP file
     */
    private function _getStatsResume($campaignId, $export = false)
    {
        if (!$this->view->hasAccess('exportContact')) {
            $errorMessage = ucfirst($this->view->translate('cannot export your selection')) . ' <br> ' .
                $this->view->translate("You don't have the necessary credentials to access this resource");
            $this->_helper->FlashMessenger->addMessage(
                array('error' => $errorMessage)
            );
            $this->_redirect($this->view->href('ng-list'));
        }
        $return = array();

        $sessionId = Dm_Session::GetEntry('id', 'current-campaign-stat');
        $sessionCampaign = Dm_Session::GetEntry('campaign', 'current-campaign-stat');
        //We check if the campaign is already load in session
        if (isset($sessionId) && $sessionId == $campaignId && isset($sessionCampaign)) {
            $campaign = $sessionCampaign;
        } else {
            //Load the campaign from its ID
            $campaign = Service_Api_Object_Campaign::LoadById($campaignId);
        }

        $statsByMessage = array();
        foreach ($campaign->getSteps() as $step) {
            foreach ($step->getMessages() as $message) {
                $stats = $step->getStats();
                if ($export === true) {
                    foreach ($stats as $statMedia => $stat) {
                        $header = $stat['stats']->header;
                        $nbContacts = (isset($header['count']) ? $header['count'] : 0);
                        $return[$statMedia] = $nbContacts;
                    }
                } else {
                    if ($message->media !== Editor_Model_Message_Row::FACEBOOK &&
                        $message->media !== Editor_Model_Message_Row::TWITTER &&
                        $message->media !== Editor_Model_Message_Row::SITE_MOBILE
                    ) {
//
                        $messageId = $stats[$message->media]["id"];
                        $statsByMessage[$messageId] = $stats[$message->media]["stats"];
                    }
                }
            }
        }

        foreach ($statsByMessage as $msgId => $stat) {
            foreach ($stat->stats as $status => $data) {
                if (isset($data)) {
                    $nb = $data[0];
                    $prct = $data[1];
                    if ($status != "total" && $prct > 0) {
                        $return[$msgId][] = array(
                            'messageId' => $msgId,
                            'media' => $message->media,
                            'status' => $status,
                            'translation' => $this->view->translate($status),
                            'percent' => round($prct),
                            'nb' => $nb,
                        );
                    }
                }
            }
        }
        return $return;
    }

    /**
     * Genereate a file with detail CSV contacts with first column as stat column.
     *
     * Awaiting params :
     * - stepId       integer step identifier
     * - media        string  media identifier
     * - status       string  status identifier
     * - linkPosition integer link identifier, only when status = clicked.
     *
     * @return void => Download CSV file
     */
    public function generateContactsListWithStatusAction()
    {
        $statusList = array_merge(
            Service_Api_Object_Message::$SENDING_STATUS_LABELS, Service_Api_Object_Message::$PERF_STATUS_LABELS
        );

        $stepId = $this->_getParam('stepId');
        if (!$this->view->hasAccess('exportContact')) {
            $errorMessage = ucfirst($this->view->translate('cannot export your selection')) . ' <br> ' .
                $this->view->translate("You don't have the necessary credentials to access this resource");
            $this->_helper->FlashMessenger->addMessage(
                array('error' => $errorMessage)
            );
            $this->_redirect($this->view->href('campaign-list'));
        }
        if (in_array($this->_getParam('media'), Service_Api_Object_Message::$GROUP_PUSH)) {
            $mediaType = $this->_getParam('media');
        }

        if (array_key_exists($this->_getParam('status'), $statusList)) {
            $status = $this->_getParam('status');
        } else {
            $status = null;
        }

        if ($status === Service_Api_Object_Message::STATUS_CLICKED) {
            $linkPosition = $this->_getParam('linkPosition', null);
        } else {
            $linkPosition = null;
        }
        // Récupération de statistiques de l'étape
        $statFilter = new Service_Api_Filter_Stat();
        $statFilter->media = array($mediaType);
        $statFilter->stepId = array($stepId);
        $statFilter->status = array($status);
        $statFilter->linkPosition = $linkPosition;
        $statFilter->restrictedAccess = !$this->view->hasAccess('contactManagement');
        $fileName = Service_Contact::ExportContactsAndStatusFromStatfilter($statFilter);
        unset($statFilter);

        $userFileName = 'Contacts_' . $mediaType . '_' . $status . '.csv';
        header('Content-Type: application/csv');
        header('Content-Disposition: attachment; filename="' . $userFileName . '"');
        header('Content-Transfer-Encoding: binary');
        header("Cache-Control: public");
        header("Pragma:");
        header("Expires: 0");
        readfile($fileName);
        $this->view->layout()->disableLayout();
        $this->_helper->viewRenderer->setNoRender(true);
    }

    /**
     * Action to call to generate complete stats datas
     *
     * Awaiting params :
     * - campaignId integer campaign identifier
     * - format pdf or file-pdf
     * pdf => will generate a file, and will push it to download
     * file-pdf => will generate file and return a JSON stream which contain the path and the file name
     *
     * @return void => Download PDF file
     */
    public function completeStatsAction()
    {
        $campaignId = $this->_getParam('campaignId');
        $params = $this->_getAllParams();
        try {
            $sessionId = Dm_Session::GetEntry('id', 'current-campaign-stat');
            $sessionCampaign = Dm_Session::GetEntry('campaign', 'current-campaign-stat');
            //We check if the campaign is already loaded in session
            if (isset($sessionId) && $sessionId == $campaignId && isset($sessionCampaign)) {
                $campaign = $sessionCampaign;
            } else {
                //Load the campaign from its ID
                $campaign = Service_Api_Object_Campaign::LoadById($campaignId);
            }

            // Suffixe annulée si campagne annulée pour plus de visibilité
            if (Service_Api_Object_Campaign::STATUS_CANCELED == $campaign->status) {
                $campaign->name .= ' ' . $this->view->translate($campaign->status);
            }

            // nombre de contacts: listCount sur messengeo
            $this->view->listCount = $this->getMessengeoCampaign($campaign)->listCount;

            // Initialisation du service gestion de campagnes
            // Récupération de contacts de la campagne
            $contactCount = 0;
            if (!$this->_contratHasMessengeoCampaignApi()) {
                $contactFilter = new Service_Api_Filter_CampaignContact();
                $contactFilter->campaignId = array($campaignId);
                $contactFilter->limit = 1;
                /* @var $contactContainer Service_Api_Object_ObjectList */
                $contactCount = $this->_campaignService->contactRead($contactFilter)->total;
            } else {
                $filter = new Mk_Contacts_ContactList_Filter($campaign->contactListExtId);
                $list = Mk_Factory::GetContactListAdapter()->liststatsRead($filter)->detailList;
                if ($list->size) {
                    $contactCount = $list->list[0]->contactNumber;
                }
            }

            $this->view->campaign = $campaign;
            //Get the list name
            $this->view->listName = $campaign->getListName();
            //Get the total number of contact used for this campaign
            $this->view->listSize = $contactCount;
            //Get the step list
            $this->view->steps = $campaign->getSteps();

            // get messages to be able to display messages details into export
            $messagesBySteps = array();
            foreach ($this->view->steps as $step) {
                // Récupération de messages associés à l'étape
                $messageFilter = new Service_Api_Filter_Message();
                $messageFilter->setStepId($step->id);
                $messageFilter->properties = array('id', 'media', 'quantity', 'frequency');
                $messageContainer = $this->_campaignService->messageRead($messageFilter);

                if (!$messageContainer->size) {
                    $this->_helper->FlashMessenger->addMessage(
                        array('error' => $this->view->translate(sprintf('Cannot display stats for this step.')))
                    );
                } else {
                    $messagesBySteps[$step->id] = $messageContainer->list;
                }
            }
            $this->view->messagesBySteps = $messagesBySteps;

            //Get the SVG Graph datas
            $this->view->graphs = array();
            foreach ($params as $name => $value) {
                if (strpos($name, 'graph-') === 0) {
                    $this->view->graphs[$name] = '@' . $value;
                }
            }
        } catch (Exception $e) {
            $this->_helper->FlashMessenger->addMessage(
                array('error' => $this->view->translate($e->getMessage()))
            );
            $this->_redirect($this->view->href('campaign-list'));
        }
    }

    /**
     * campagne sur messengeo
     *
     * @param Service_Api_Object_Campaign $campaign campaign
     *
     * @return array ligne messengeo
     */
    public function getMessengeoCampaign($campaign)
    {
        $config = Dm_Config::GetConfig('campaign', 'rest');
        $key = $campaign->getUser()->userKey;
        $campaignFilter = new Mk_Campaigns_Campaign_Filter();
        $campaignFilter->id = $campaign->messengeoExtId;
        $campaignMessengeo = new Mk_Campaigns_Campaign_Reader($key, $config);
        $campaignMessengeoReader = $campaignMessengeo->read($campaignFilter);
        return $campaignMessengeoReader->current();
    }

    /**
     * Affichage des statistiques d'une campagne terminée
     *
     * Paramètres :
     * - campaignId = Identifiant de la campagne pour laquelle on récupé les statistiques
     *
     * @return void
     */
    public function statsAction()
    {
        $campaignId = $this->_getParam('campaignId');

        if (isset($campaignId)) {
            $campaign = Service_Api_Object_Campaign::LoadById($campaignId);
        } else {
            $messengeoId = $this->_getParam('messengeoId');
            $campaign = Service_Api_Object_Campaign::LoadByMessengeoId($messengeoId);
            $campaignId = $campaign->id;
        }

        $this->view->isRental = false;

        // nombre de contacts: listCount sur messengeo
        $this->view->listCount = $this->getMessengeoCampaign($campaign)->listCount;

        // Initialisation du service gestion de campagnes
        // Récupération de contacts de la campagne
        $contactCount = 0;
        if (!$this->_contratHasMessengeoCampaignApi()) {
            $contactFilter = new Service_Api_Filter_CampaignContact();
            $contactFilter->campaignId = array($campaignId);
            $contactFilter->limit = 1;
            /* @var $contactContainer Service_Api_Object_ObjectList */
            $contactCount = $this->_campaignService->contactRead($contactFilter)->total;
        } else {
            /**
             * @since Messengeo Campaign API
             */
            if (is_numeric($campaign->contactListExtId)) {
                $filter = new Mk_Contacts_ContactList_Filter($campaign->contactListExtId);
                $list = Mk_Factory::GetContactListAdapter()->liststatsRead($filter)->detailList;

                if ($list->size) {
                    $contactCount = $list->list[0]->contactNumber;
                    $this->view->isRental = ($list->list[0]->category == 'RENTED');
                }
            } else {
                $contactCount = '-';
            }
        }

        // try to retrieve the advice
        if ($campaign->sourceTemplateId > 0) {
            $templateFilter = new Service_Api_Filter_Template();
            $templateFilter->campaignId = array($campaign->sourceTemplateId);
            $templatesContainer = $this->_campaignService->templateRead($templateFilter);
            if ($templatesContainer->size > 0) {
                $this->view->advice = $templatesContainer->list[0]->advice;
            } else {
                $this->view->advice = null;
            }
        } else {
            $this->view->advice = null;
        }

        $contract = Dm_Session::getConnectedUserContract();
        if (isset($contract) && $contract->type == "demonstration") {
            $contactListAdapter = Mk_Factory::GetContactListAdapter();
            $filter = new Mk_Contacts_ContactList_Filter();
            $filter->id = $campaign->contactListExtId;
            $list = $contactListAdapter->liststatsRead($filter)->detailList;
            if ($list->size) {
                $campaign->contactListName = $list->list[0]->name;
                $contactContainer->total = $list->list[0]->contactNumber;
                $this->view->isMock = $list->list[0]->isMock;
                $contactCount = $list->list[0]->contactNumber;
                $this->view->listId = $list->list[0]->id;
            }
        }
        $this->view->campaign = $campaign;
        $this->view->listName = $campaign->contactListName;
        $this->view->listSize = $contactCount;
        $this->view->steps = $campaign->getSteps();

        Dm_Session::SetEntry('id', $campaignId, 'current-campaign-stat');
        Dm_Session::SetEntry('campaign', $campaign, 'current-campaign-stat');
    }

    /**
     * Affichage des details des envoi aux contacts d'une campagne terminée
     *
     * Paramètres :
     * - campaignId = Identifiant de la campagne
     * - messageId  = Identifiant du message pour lequel on récupé les details
     *
     * @return void
     */
    public function statDetailAction()
    {
        $campaignId = $this->_getParam('campaignId');
        $stepId = $this->_getParam('stepId');
        $mediaType = $this->_getParam('media');

        // si le statut est une chaine vide on le définit à null pour éviter de filtrer sur qqc de vide
        $givenStatus = $this->_getParam('status');
        $status = !empty($givenStatus) ? $givenStatus : null;

        if ($status === null && $givenStatus !== '') {
            $status = Dm_Session::GetEntry('status', 'campaignStatDetail_' . $stepId . $mediaType);
        } else {
            Dm_Session::SetEntry('status', $status, 'campaignStatDetail_' . $stepId . $mediaType);
        }
        unset($givenStatus);

        if ($status === Service_Api_Object_Message::STATUS_CLICKED) {
            $linkPositionParam = $this->_getParam('linkPosition');
            // on vérifie que linkPosition est un chiffre
            $linkPosition = (string)intval($linkPositionParam) === $linkPositionParam ? $linkPositionParam : null;
            unset($linkPositionParam);
            if ($linkPosition === null) {
                $linkPosition = Dm_Session::GetEntry('linkPosition', 'campaignStatDetail_' . $stepId . $mediaType);
            } else {
                Dm_Session::SetEntry('linkPosition', $linkPosition, 'campaignStatDetail_' . $stepId . $mediaType);
            }
        } else {
            $linkPosition = null;
        }

        if (is_null($campaignId) || is_null($stepId) || is_null($mediaType)) {
            $this->_redirect($this->view->href('campaign-list'));
        } else {
            if (!is_numeric($campaignId) || !is_numeric($stepId)) {
                $this->_helper->FlashMessenger->addMessage(
                    array('error' => ucfirst($this->view->translate('cannot display details with invalid identifiers')))
                );
                $this->_redirect($this->view->href('campaign-list'));
            } else {
                // Lecture de la campagne
                $campaign = Service_Api_Object_Campaign::LoadById($campaignId);

                if (!$campaign) {
                    $this->_helper->FlashMessenger->addMessage(
                        array('error' => ucfirst($this->view->translate('cannot display details')))
                    );
                    $this->_redirect($this->view->href('campaign-list'));
                } else {

                    $form = $this->_getContactStatForm();
                    $form->populate(array('status' => $status));
                    $form->populate(array('linkPosition' => $linkPosition));

                    $this->view->campaignId = $campaignId;
                    $this->view->campaignName = $campaign->name;
                    $this->view->stepId = $stepId;
                    $this->view->media = $mediaType;
                    $this->view->status = $status;
                    $this->view->form = $form;
                    $this->view->linkPosition = $linkPosition;

                    // Lien de retour
                    $this->view->backUrl = $this->view->href('campaign-stat', array('campaignId' => $campaignId));
                    // Lien d'export
                    $this->view->exportUrl = $this->view->href(
                        'generate-contacts-list-status',
                        array('stepId' => $stepId, 'media' => $mediaType, 'format' => 'xls', 'status' => $status,
                            'linkPosition' => $linkPosition)
                    );

                    // définition des bornes pour l'affichage
                    $page = $this->_getParam('page', 1);
                    $elemPerPage = $this->_getParam('perPage', 10);

                    // Récupération de statistiques de l'étape
                    $statFilter = new Service_Api_Filter_Stat();
                    $statFilter->stepId = array($stepId);
                    $statFilter->media = array($mediaType);
                    $statFilter->status = array($status);
                    $statFilter->linkPosition = $linkPosition;

                    // ici on affiche la pagination et une seule page de résultats pas de traitement par lot
                    $statFilter->limit = $elemPerPage;
                    $statFilter->offset = ($page - 1) * $elemPerPage;
                    $statFilter->total = true;

                    $stepStats = Service_Api_Object_Step::getCampaignContactsIdsWithStats($statFilter);

                    if ($stepStats['contacts']) {
                        // Recherche de correspondances entre le moyen de contact et les contacts
                        switch ($mediaType) {
                            case Service_Api_Object_Message::EMAIL:
                                $contactField = 'email';
                                break;
                            case Service_Api_Object_Message::SMS:
                                $contactField = 'mobile';
                                break;
                            case Service_Api_Object_Message::VOICE:
                                $contactField = 'phone';
                                break;
                            default:
                                $contactField = 'mobile';
                                break;
                        }

                        $this->view->contactField = $contactField;

                        // Récupération des infos de contacts à partir de leur moyen de contact
                        $contactStats = $this->_getContactInfo($stepStats['contacts'], $contactField);

                        // Traitement des contacts simulés
                        foreach ($contactStats as $key => $contact) {
                            if ($contact['isMock']) {
                                $contactStats[$key]['email'] = $this->_hideEmail($contact['email']);
                                $contactStats[$key]['phone'] = $this->_hidePhone($contact['phone']);
                                $contactStats[$key]['mobile'] = $this->_hidePhone($contact['mobile']);
                            }
                        }

                        // Gestion de la pagination
                        $paginator = Zend_Paginator::factory($stepStats['total']); //
                        $paginator->setCurrentPageNumber($page);
                        $paginator->setDefaultItemCountPerPage($elemPerPage);
                        $this->view->stats = $paginator;

                        $this->view->statsData = $contactStats;

                        // Consolidation des données pour l'export xls
                        // définition de la liste de champs de l'export excel
                        $contacts_fields = $this->_sessionGetColumns();
                        // Construction de la liste des colonnes à afficher en fonction des droits
                        if (!$this->view->hasAccess('contactManagement')) {
                            $restrictedContactsFields = array();
                            foreach (Dm_Config::GetConfig('contact', 'restricted.fields') as $field) {
                                $restrictedContactsFields[$field] = $contacts_fields[$field];
                            }
                            $contacts_fields = $restrictedContactsFields;
                        }

                        $this->view->meta = array_merge(array('status', 'id'), array_keys($contacts_fields));

                        $data = array();
                        foreach ($contactStats as $dataValue) {
                            if (isset($dataValue['id'])) {
                                $dataObj = new stdClass();
                                $dataValue['status'] =
                                    ucfirst($this->view->translate($dataValue['status'] . '.export'));
                                foreach ($this->view->meta as $key) {
                                    $dataObj->columns[] = $dataValue[$key];
                                }
                                $data[] = $dataObj;
                            }
                        }
                        $this->view->data = $data;
                    }
                }
            }
        }
    }

    /**
     * Visualisation d'un contact appartenant à une campagne confirmée
     *
     * Paramètres :
     * - campaignId = Identifiant de la campagne
     * - contactId  = Identifiant du contact à visualiser
     *
     * @return void
     */
    public function contactViewAction()
    {
        $campaignId = $this->_getParam('campaignId');
        $contactId = $this->_getParam('contactId');

        if (is_null($campaignId) || is_null($contactId)) {
            $this->_redirect($this->view->href('campaign-list'));
        } else {
            if (!is_numeric($campaignId) || !is_numeric($contactId)) {
                $this->_helper->FlashMessenger->addMessage(
                    array('error' => ucfirst($this->view->translate('cannot display contact with invalid identifiers')))
                );
                $this->_redirect($this->view->href('campaign-list'));
            } else {
                $this->view->campaignId = $campaignId;
                $this->view->stepId = $this->_getParam('stepId');
                $this->view->media = $this->_getParam('media');

                if ($this->_contratHasMessengeoCampaignApi()) {
                    /**
                     * MESSENGEO Campaign API
                     *
                     * @since Messengeo Campaign API
                     */
                    // Lecture de contacts avec l'API Baseo
                    $contactFilter = new Mk_Contacts_Contact_Filter($contactId);
                    $contactFilter->properties = array('DEFAULT', 'fax', 'birthDate', 'company', 'reference',
                        'address1',
                        'address2', 'zipcode', 'city', 'state', 'country',
                        'field01', 'field02', 'field03', 'field04', 'field05',
                        'field06', 'field07', 'field08', 'field09', 'field10',
                        'field11', 'field12', 'field13', 'field14', 'field15');
                    $contactContainer = Mk_Factory::GetContactAdapter()->contactRead($contactFilter);
                } else {
                    /**
                     * SLBEO Campaign API
                     */
                    // Lecture du contact dans Cmpeo
                    $contactFilter = new Service_Api_Filter_CampaignContact();
                    $contactFilter->contactId = array($contactId);
                    $contactContainer = $this->_campaignService->contactRead($contactFilter);
                }

                if ($contactContainer->size) {
                    $contact = $contactContainer->list[0];

                    // Formulaire de visualisation du contact
                    $contactForm = new Frontoffice_Form_Contact(array('contact' => $contact));
                    $this->view->form = $contactForm;
                }
            }
        }
    }

    /**
     * Budget sur un mois avec le mois N-1 par défaut
     *
     * @return Json
     */
    public function ajaxBudgetAction()
    {
        // Selected Month
        $month = $this->_getParam('month');
        $year = $this->_getParam('year', false);
        $export = $this->_getParam('export', false);

        // Get the budget datas
        $lines = $this->_getMonthBudget($month, $year);

        // Export case
        if ($export) {
            // Récupération du séparateur csv :
            $user = Dm_Session::GetEntry(Dm_Session::CONNECTED_USER);
            $csvSeparator = $user->csvSeparator;

            // Création du fichier vide avec les entêtes
            $fileName = Dm_Config::GetPath('tmp') . '/' . 'budget_' . microtime(true) . '.csv';
            $file = fopen($fileName, 'a');
            $fields = array(
                'id' => $this->view->translate('Id'),
                'date' => ucfirst($this->view->translate('execution date')),
                'campaign' => ucfirst($this->view->translate('campaign')),
                Service_Api_Object_Message::SMS =>
                    strtoupper($this->view->translate(Service_Api_Object_Message::SMS)),
                Service_Api_Object_Message::EMAIL =>
                    ucfirst($this->view->translate(Service_Api_Object_Message::EMAIL)),
                Service_Api_Object_Message::VOICE =>
                    ucfirst($this->view->translate(Service_Api_Object_Message::VOICE)),
                Service_Api_Object_Message::VOICEMAIL =>
                    ucfirst($this->view->translate(Service_Api_Object_Message::VOICEMAIL)),
            );
            fputcsv($file, $fields, $csvSeparator, '"');

            foreach ($lines as $line) {
                fputcsv($file, $line, $csvSeparator, '"');
            }

            // Current month for budget
            $user = Dm_Session::GetConnectedUser();
            $datetime = new Zend_Date(Zend_Registry::get('Zend_Locale'));
            $datetime->setTimeZone($user->timezone);
            if (!empty($month)) {
                $datetime->setMonth($month);
            }
            $month = $datetime->get(Zend_Date::MONTH_NAME);

            // Export du budget
            $userFileName = 'budget-' . $month . '-' . $year . '.csv';
            header('Content-Type: application/csv');
            header('Content-Disposition: attachment; filename="' . $userFileName . '"');
            header('Content-Transfer-Encoding: binary');
            readfile($fileName);

            $this->view->layout()->disableLayout();
            $this->_helper->viewRenderer->setNoRender(true);
            die();
        }
        $this->_helper->json->sendJson($lines);
    }

    /**
     * Get the budget for all the campaigns in a month
     *
     * @param int $month Month to get Budget
     * @param int $year Year to get Budget
     *
     * @return Array
     *
     * @todo To delete when fonctionnality is available in Messengeo API
     */
    private function _getMonthBudget($month, $year)
    {
        // Return session for month if exists
        if (Dm_Session::hasEntry('budget' . $month . $year)) {
            return Dm_Session::GetEntry('budget' . $month . $year);
        }

        // Campaign filter
        $filter = new Service_Api_Filter_Campaign();

        // Set begin date complex filter part 1
        $filter->dateStartFilter = array();
        $dateStart = new Zend_Date(Zend_Registry::get('Zend_Locale'));
        $dateStop = new Zend_Date(Zend_Registry::get('Zend_Locale'));

        // Set User timezone
        $user = Dm_Session::GetConnectedUser();
        $dateStart->setTimeZone($user->timezone);
        $dateStop->setTimeZone($user->timezone);

        // Set Month
        if (!empty($month)) {
            $dateStart->setMonth($month);
            $dateStop->setMonth($month);
        }

        // Set Year
        if (!empty($month)) {
            $dateStart->setYear($year);
            $dateStop->setYear($year);
        }

        // Sub one month if in january for start date
        if ($dateStart->get(Zend_Date::MONTH_SHORT) == 1) {
            $dateStart->subYear(1);
        }
        $dateStart->setDay(1)->subDay(1);
        $dateStop->setDay(1)->addMonth(1);

        $filter->dateStartFilter[] = array('>' => $dateStart->toString('Y-MM-dd'));
        $filter->dateStartFilter[] = array('<' => $dateStop->toString('Y-MM-dd'));
        $filter->messengeoExtId = array();
        // Set status filter

        // Fill the filter
        $filter->properties = array(
            'id',
            'name'
        );

        // Retrieve the user Id
        $filter->contractId = array(Dm_Session::GetEntry(Dm_Session::CONNECTED_USER)->contractId);
        $filter->userExtId = array(Dm_Session::GetEntry(Dm_Session::CONNECTED_USER)->id);

        // Récupération des campagnes
        $campaignResult = $this->_campaignService->campaignRead($filter);
        $lines = array();

        // Boucle chez Messengeo
        foreach ($campaignResult as $campaignObject) {
            $campaign = new Service_Api_Object_Campaign($campaignObject);
            // Parcours des étapes
            $steps = $campaign->getStepsProperties(array('id', 'dateExecution'));
            foreach ($steps as $step) {
                // Date d'exécution du step
                $date = $step->dateExecution;
//                if(date('m', strtotime($date)) !== $month){
//                }
                // Stats liées à l'étape courante
                $statFilter = new Service_Api_Filter_Stat();
                $statFilter->stepId = array($step->id);
                $statFilter->campaignId = array($campaign->id);
                $statContainer = $this->_campaignService->statRead($statFilter);
                if ($statContainer->status) {
                    // Parcours des messages
                    foreach ($statContainer->result as $media => $container) {
                        if (array_key_exists($campaign->id, $lines) === false) {
                            $dateFormat = new DateTime($date);
                            $lines[$campaign->id] = array(
                                'id' => $campaign->id,
                                'date' => date_format($dateFormat, 'd/m/Y H:i:s'),
                                'campaign' => $campaign->name,
                                Service_Api_Object_Message::SMS => 0,
                                Service_Api_Object_Message::EMAIL => 0,
                                Service_Api_Object_Message::VOICE => 0,
                                Service_Api_Object_Message::VOICEMAIL => 0,
                            );
                        }
                        if (array_search(strtoupper($media), array(
                                strtoupper(Service_Api_Object_Message::SMS),
                                strtoupper(Service_Api_Object_Message::EMAIL),
                                strtoupper(Service_Api_Object_Message::VOICE),
                                strtoupper(Service_Api_Object_Message::VOICEMAIL))) !== false
                        ) {
                            $nbSentMessages = 0;
                            $messageStat = $container['stats']->stats;

                            // getting number of running messages
                            $sentMessages = (isset($messageStat[Service_Api_Object_Message::STATUS_RUNNING]))
                                ? $messageStat[Service_Api_Object_Message::STATUS_RUNNING][0] : 0;
                            // getting number of delivered messages
                            $deliveredMsg = (isset($messageStat[Service_Api_Object_Message::STATUS_DELIVERED]))
                                ? $messageStat[Service_Api_Object_Message::STATUS_DELIVERED][0] : 0;
                            // getting number of soft bounced messages
                            $sbMessages = (isset($messageStat[Service_Api_Object_Message::STATUS_SOFTBOUNCED]))
                                ? $messageStat[Service_Api_Object_Message::STATUS_SOFTBOUNCED][0] : 0;
                            // getting number of hard bounced messages
                            $hbMessages = (isset($messageStat[Service_Api_Object_Message::STATUS_HARDBOUNCED]))
                                ? $messageStat[Service_Api_Object_Message::STATUS_HARDBOUNCED][0] : 0;
                            // getting number of undelivered messages
                            $udMessages = (isset($messageStat[Service_Api_Object_Message::STATUS_UNDELIVERED]))
                                ? $messageStat[Service_Api_Object_Message::STATUS_UNDELIVERED][0] : 0;

                            $nbSentMessages += $sentMessages + $deliveredMsg + $sbMessages + $hbMessages +
                                $udMessages;

                            $lines[$campaign->id][$media] += $nbSentMessages;
                        }
                    }
                }
            }
        }
        // Set session for this month
        Dm_Session::SetEntry('budget' . $month . $year, $lines);
        return $lines;
    }


    /**
     * Checks which campaign API to use, Messengeo or Cmpeo
     *
     * @since Messengeo Campaign API
     *
     * @return boolean
     */
    protected function _contratHasMessengeoCampaignApi()
    {
        $campaignApi = Dm_Session::GetEntry(Dm_Session::CONNECTED_USER_CONTRACT)->getParameterValue('campaignApi');
        return (strcasecmp($campaignApi, 'messengeo') === 0);
    }

    /**
     * Récupération des infos de contacts à partir de leur moyen de contact
     * => Crée un tableau de contacts contenant en plus une colonne "status" contenant la donnée fournie en entrée
     * Stats ne sera jamais très gros (contenu affiché dans une page), pas de traitement par lot requis.
     *
     * @param array $stats Statistiques de contacts ( campaignContactId => stringStatus )
     * @param string $mediaType Moyen de contact
     *
     * @return mixed
     */
    private function _getContactInfo($stats, $mediaType = null)
    {
        $result = array();

        if (count($stats)) {
            $contactIds = array_keys($stats);

            $contactContainer = Service_Contact::loadCampaignContactsById($contactIds);
            if ($contactContainer->size) {
                $fields = array_merge(array('id' => 'Id'), $this->_sessionGetColumns());
                foreach ($contactContainer->list as $ckey => $contact) {
                    if (array_key_exists($contact->id, $stats)) {
                        $result[$contact->id] = array_intersect_key(get_object_vars($contact), $fields);
                        $result[$contact->id]['status'] = $stats[$contact->id];
                        $result[$contact->id]['isMock'] = isset($contact->isMock) ? $contact->isMock : false;
                        unset($contactContainer->list[$ckey]);
                        unset($stats[$contact->id]);
                        unset($contact);
                    }
                }
            } else {
                $this->_helper->FlashMessenger->addMessage(
                    array('error' => ucfirst($this->view->translate('no contacts found')))
                );
            }
        }
        return $result;
    }

    /**
     * Page resumé d'une campagne confirmée
     *
     * @return void
     */
    public function viewAction()
    {
        Dm_Log::Debug('Start ' . __METHOD__);

        $campaignId = $this->_getParam('campaignId');

        if (is_null($campaignId) || !is_numeric($campaignId)) {
            $this->_helper->FlashMessenger->addMessage(
                array('error' => ucfirst($this->view->translate('cannot display campaign without a valid identifier')))
            );
            $this->_redirect($this->view->href('campaign-list'));
        } else {
            $this->view->campaignId = $campaignId;

            // Récupération de la campagne
            $campaignFilter = new Service_Api_Filter_Campaign();
            $campaignFilter->campaignId = array($campaignId);
            $campaignsContainer = $this->_campaignService->campaignRead($campaignFilter);

            if (!$campaignsContainer->size) {
                $this->_helper->FlashMessenger->addMessage(
                    array('error' => ucfirst($this->view->translate('campaign not found')))
                );

                $this->_redirect($this->view->href('campaign-list'));
            } else {
                $campaign = $campaignsContainer->list[0];
                $this->view->campaign = $campaign;

                $list = Mk_Contacts_ContactList::LoadById($campaign->contactListExtId);
                $this->view->list = $list;

                $filter = new Mk_Contacts_ContactList_Filter($campaign->contactListExtId);
                $list->contactNumber = Mk_Factory::GetContactListAdapter()->liststatsRead($filter)->contactNumber;

                // Récupération des étapes de la campagne
                $stepFilter = new Service_Api_Filter_Step();
                $stepFilter->campaignId = array($campaignId);
                $stepFilter->setSort(array(array('dateExecution', 'ASC')));
                $stepContainer = $this->_campaignService->stepRead($stepFilter);

                if ($stepContainer->size) {
                    $steps = $stepContainer->list;
                    $this->view->steps = $steps;

                    // Récupération de messages pour chaque étape
                    foreach ($steps as $step) {
                        $messageFilter = new Service_Api_Filter_Message();
                        $messageFilter->properties = array('id', 'media');
                        $messageFilter->setStepId($step->id);

                        // Récupération de la liste de messages
                        $messageList = $this->_campaignService->messageRead($messageFilter);
                        if ($messageList->size) {
                            $messages = $messageList->list;
                            $campaignMediaFilter = new Service_Api_Filter_CampaignMedia();
                            $campaignMediaFilter->campaignId = $campaignId;

                            foreach ($messages as $message) {
                                $campaignMediaFilter->media = $message->media;
                                // Récupération du nombre de contacts par type de message media
                                $message->contactCount = $this->_campaignService->campaignContactCount(
                                    $campaignMediaFilter
                                );
                            }
                            $step->messages = $messages;
                        }
                    }
                }
            }
        }

        Dm_Log::Debug('End ' . __METHOD__);
    }

    /**
     * Action qui effectue la duplication d'une campagne
     *
     * Paramètres requis :
     * int $campaignId Identifiant de la campagne à dupliquer
     *
     * @return void
     */
    public function duplicateAction()
    {
        $campaignId = $this->_getParam('campaignId');
        $isTemplate = $this->_getParam('isTemplate', false);

        if (is_null($campaignId) || !is_numeric($campaignId)) {
            $this->_helper->FlashMessenger->addMessage(
                array('error' => ucfirst($this->view->translate('cannot duplicate campaign with invalid identifier')))
            );
            $this->_redirect($this->view->href('campaign-list'));
        } else {
            $this->view->campaignId = $campaignId;

            // Duplication de la campagne
            // Lecture de campagnes dupliquées
            /* @var $duplicatedCampaignResponse Service_Api_Object_ObjectList */
            $filter = ($isTemplate) ? new Service_Api_Filter_Template() : new Service_Api_Filter_Campaign();
            $filter->campaignId = array($campaignId);

            if ($isTemplate) {
                $duplicatedCampaignResponse = $this->_campaignService->templateDuplicate($filter);
            } else {
                $duplicatedCampaignResponse = $this->_campaignService->campaignDuplicate($filter);
            }

            if ($duplicatedCampaignResponse->size) {
                $duplicatedCampaigns = $duplicatedCampaignResponse->list;

                foreach ($duplicatedCampaigns as $duplicatedCampaign) {
                    /* @var $duplicatedCampaign Service_Api_Object_Campaign */

                    // if listId or campaignName provided, update campaign with the new data
                    if (!is_null($this->_getParam('listId')) || !is_null($this->_getParam('campaignName'))) {
                        $duplicatedCampaignFilter = new Service_Api_Filter_Campaign();
                        $duplicatedCampaignFilter->campaignId = array($duplicatedCampaign->id);
                        $this->_campaignService->campaignUpdate(
                            $duplicatedCampaignFilter,
                            // array_filter will remove null values
                            array_filter(array(
                                'contactListExtId' => $this->_getParam('listId'),
                                'name' => $this->_getParam('campaignName')
                            ))
                        );
                    }

                    // Lecture du tableau de mapping des identifiants des messages
                    if (!is_null($duplicatedCampaign->messageMapping)) {
                        $messageMapping = $duplicatedCampaign->messageMapping;
                        $messageHandler = new Editor_Model_Message_Table();

                        foreach ($messageMapping as $originalMessageId => $duplicatedMessageId) {
                            /* @var $originalEditorMessage Editor_Model_Message_Row */
                            // Lecture du message editeur original
                            $originalEditorMessage = $messageHandler->fetchRow('extId = ' . $originalMessageId);
                            // Duplication du message editeur original avec le extId qui pointe vers
                            // l'identifiant du message Cmpeo dupliqué
                            $originalEditorMessage->duplicate(
                                $duplicatedMessageId, Dm_Session::GetEntry(Dm_Session::CONNECTED_USER)->id);
                        }
                    }
                }
            } else {
                if ($this->view->hasAccess('template-creation')) {
                    $this->_helper->FlashMessenger->addMessage(
                        array(
                            'error' => ucfirst(
                                $this->view->translate('cannot create template')
                            ),
                        )
                    );
                    $this->_redirect($this->view->href('list-templates'));
                } else {
                    $this->_helper->FlashMessenger->addMessage(
                        array(
                            'error' => ucfirst(
                                $this->view->translate('cannot create campaign')
                            ),
                        )
                    );
                    $this->_redirect($this->view->href('campaign-list'));
                }
            }
        }


        if (!$isTemplate) {
            // redirection vers la liste des campagnes
            $redirect = $this->view->href('campaign-list');
            $message = ucfirst($this->view->translate("campaign was successfully duplicated"));
        } else {
            // duplication d'un modèle
            $params = array('campaignId' => $duplicatedCampaigns[0]->id);

            if ($duplicatedCampaigns[0]->isTemplate) {
                // redirection vers le résumé du template créé
                $redirect = $this->view->href('network-template-edit', $params);
                $message = ucfirst($this->view->translate("template was successfully duplicated"));
            } else {
                // redirection vers le résumé de la campagne créée
                $redirect = $this->view->href('campaign-edit', $params);
                $message = ucfirst($this->view->translate("campaign was successfully created"));
            }
        }

        $this->_helper->FlashMessenger->addMessage(array('succes' => $message));
        $this->_redirect($redirect);
    }

    /**
     * Sauvegarde du contenu serialisé d'un message dans Cmpeo
     *
     * @param Service_Api_Object_Message $message Objet representant un message
     * @param Editor_Model_MessageContent $content Objet representant un contenu serialisé
     *
     * @return int
     */
    public function saveSerializedMessage($message, Editor_Model_MessageContent $content)
    {
        $messageFilter = new Service_Api_Filter_Message();
        $messageFilter->setMessageId($message->id);

        $updateData = array();
        foreach ($content as $key => $value) {
            $updateData[$key] = $value;
        }

        return $this->_campaignService->messageUpdate($messageFilter, $updateData);
    }

    /**
     * Retourne le formulaire de séléction de statuts de messages pour les détails de contacts
     *
     * @return Zend_Form
     */
    private function _getContactStatForm()
    {
        $form = new Zend_Form();

        $statusInput = new Zend_Form_Element_Select('status');

        // filter status list to give only usable statuses depending on media
        $media = $this->_getParam('media', null);

        $statuses = array(
            '' => ucfirst($this->view->translate('all statuses')),
            Service_Api_Object_Message::STATUS_PROCESSING =>
                ucfirst($this->view->translate(Service_Api_Object_Message::STATUS_PROCESSING)),
            Service_Api_Object_Message::STATUS_RUNNING =>
                ucfirst($this->view->translate("missing delivery status")),
            Service_Api_Object_Message::STATUS_DELIVERED =>
                ucfirst($this->view->translate(Service_Api_Object_Message::STATUS_DELIVERED)),
            Service_Api_Object_Message::STATUS_UNDELIVERED =>
                ucfirst($this->view->translate(Service_Api_Object_Message::STATUS_UNDELIVERED)),
            Service_Api_Object_Message::STATUS_OPTOUT =>
                ucfirst($this->view->translate(Service_Api_Object_Message::STATUS_OPTOUT)),
            Service_Api_Object_Message::STATUS_ANSWERED =>
                ucfirst($this->view->translate(Service_Api_Object_Message::STATUS_ANSWERED))
        );

        switch ($media) {
            case Service_Api_Object_Message::SMS:
                break;

            case Service_Api_Object_Message::EMAIL:
                $statuses = array_merge(
                    $statuses,
                    array(
                        Service_Api_Object_Message::STATUS_HARDBOUNCED =>
                            ucfirst($this->view->translate(
                                Service_Api_Object_Message::STATUS_HARDBOUNCED . '.statuses')),
                        Service_Api_Object_Message::STATUS_SOFTBOUNCED =>
                            ucfirst($this->view->translate(
                                Service_Api_Object_Message::STATUS_SOFTBOUNCED . '.statuses')),
                        Service_Api_Object_Message::STATUS_CLICKED =>
                            ucfirst($this->view->translate(Service_Api_Object_Message::STATUS_CLICKED)),
                        Service_Api_Object_Message::STATUS_READ =>
                            ucfirst($this->view->translate(Service_Api_Object_Message::STATUS_READ)),
                        Service_Api_Object_Message::STATUS_UNCLICKED =>
                            ucfirst($this->view->translate(Service_Api_Object_Message::STATUS_UNCLICKED)),
                        Service_Api_Object_Message::STATUS_UNREAD =>
                            ucfirst($this->view->translate(Service_Api_Object_Message::STATUS_UNREAD)),
                    ));
                $linksInput = new Zend_Form_Element_Select('linkPosition');
                $linksInput->addMultiOptions(array(
                    '' => ucfirst($this->view->translate('all.clicksstatuses'))));

                $stepId = $this->_getParam('stepId', null);
                $linksList = Service_Api_Object_Message::GetLinksFromEmailStep($stepId);
                $linksInput->addMultiOptions($linksList);

                $form->addElement($linksInput);
                break;

            default:
                break;
        }

        $statusInput->addMultiOptions($statuses);
        $statusInput->setValue('all statuses');
        $statusInput->setOptions(array('class' => 'form-control'));

        $submitInput = new Zend_Form_Element_Submit('submit');
        $submitInput->setOptions(array("class" => "submit-button btn pull-right"));
        $submitInput->setLabel(ucfirst($this->view->translate('submit')));
        $submitInput->clearDecorators();
        $submitInput->addDecorator(new Zend_Form_Decorator_ViewHelper());

        $form->addElement($statusInput);

        $form->setDecorators(array(new Zend_Form_Decorator_FormElements(), new Zend_Form_Decorator_Form()));
        $form->setElementDecorators(
            array(
                'ViewHelper', 'Label',
                new Dm_Form_Decorator_ShortErrors(),
            )
        );

        $form->addElement($submitInput);

        return $form;
    }

    /**
     * Construit le formulaire d'edition de details d'un campagne
     *
     * @param array $lists Tableau de listes de contacts
     * @param int $listId Identifiant de la liste de contacts à charger par default
     * @param array $rentedLists tableau de listes de contacts louées
     *
     * @return Zend_Form
     */
    protected function _getEditCampaignForm($lists, $listId, $rentedLists)
    {
        $form = new Zend_Form();

        // Element input text
        $nameInput = new Zend_Form_Element_Text('name');
        $nameInput->setRequired(true)
            ->addValidators(array(
                    array('notEmpty', true)
                )
            );

        // Element select
        $selectInput = new Zend_Form_Element_Select('contactListExtId');
        $selectInput->setValue($listId);
        $selectInput->setDisableTranslator(false);

        $listResult = array();
        $listResult[] = ucfirst($this->view->translate("select list"));
        $disableLists = array();
        foreach ($lists as $list) {
            $listResult[$list->id] = $list->name;

            // disabling lists being currently imported
            if (isset($list->importStatus) && $list->importStatus != 'ok') {
                $disableLists[] = $list->id;
            }
        }


        $selectOptions = array();
        if ($this->view->hasAccess('rentalContactList')) {
            if (is_array($rentedLists) && count($rentedLists) > 0) {
                $rentedListResult = array();
                foreach ($rentedLists as $list) {
                    if (preg_match('/^mock_/', $list->id)) {
                        $list->id = 'mock_#';
                    }
                    // disabling lists being currently imported or demo list or expired
                    if (Dm_Session::getConnectedUserContract()->type != "demonstration"
                        && ((isset($list->importStatus) && $list->importStatus != 'ok')
                            || ($list->shootCount > 0) || preg_match('/^mock_/', $list->id)
                            || $list->expired == true)
                    ) {

                        $disableLists [] = $list->id;
                    }
                    $rentedListResult[$list->id] = $list->name;
                }
                $selectOptions = array(ucfirst($this->view->translate('contacts lists')) => $listResult);
                $selectOptions[ucfirst($this->view->translate('rented lists'))] = $rentedListResult;
            } else {
                $selectOptions = $listResult;
            }
        } else {
            $selectOptions = $listResult;
        }

        $selectInput->setMultiOptions($selectOptions)
            ->addFilter('Int');
        if (count($disableLists)) {
            $selectInput->setAttrib('disable', $disableLists);
        }

        // Element textarea
        $textareaInput_1 = new Zend_Form_Element_Textarea('advice');

        // Element textarea
        $textareaInput_2 = new Zend_Form_Element_Textarea('comment');

        $form->addElements(array($nameInput, $selectInput, $textareaInput_1, $textareaInput_2));

        return $form;
    }

    /**
     * Display template
     *
     * @return void
     */
    public function listTemplatesAction()
    {
        $this->view->listId = $this->_getParam('listId');
        $this->view->campaignName = $this->_getParam('campaignName');
        $page = $this->_getParam('page', 1);
        $perPage = $this->_getParam('perPage', Zend_Paginator::getDefaultItemCountPerPage());

        $templateFilter = new Service_Api_Filter_Template();
        $templateFilter->limit = $perPage;
        $offset = ($page - 1) * $perPage;
        $templateFilter->offset = $offset;
        $templateFilter->setSort(array(array('dateCreated', 'DESC')));
        $readResult = $this->_campaignService->templateRead($templateFilter);

        // récupération des messages pour calculer le nombre de canaux par campagne
        $templates = array();
        foreach ($readResult->list as $template) {
            $templates[$template->id] = array('id' => $template->id, 'medias' => array(), 'mediaCount' => 0);
        }
        if (count($templates) > 0) {
            $messageFilter = new Service_Api_Filter_Message();
            $messageFilter->campaignId = array_keys($templates);
            // Récupération de la liste de messages de la campagne
            $messageList = $this->_campaignService->messageRead($messageFilter);
            if ($messageList->size) {
                // Vérification que l'étape contient au moins un message
                foreach ($messageList->list as $message) {
                    if (isset($templates[$message->campaignId]) &&
                        !in_array($message->media, $templates[$message->campaignId]['medias'])
                    ) {
                        $templates[$message->campaignId]['medias'][] = $message->media;
                        $templates[$message->campaignId]['mediaCount']++;
                    }
                }
            }
        }

        $this->view->templatesMediaData = $templates;

        $paginator = Zend_Paginator::factory($readResult, 'ObjectList');
        $paginator->setCurrentPageNumber($page);
        $paginator->setDefaultItemCountPerPage($perPage);

        /* @var $paginator Zend_Paginator */
        $this->view->paginator = $paginator;
    }

    /**
     * Create template
     *
     * @return void
     */
    public function createFromTemplateAction()
    {
        $params = array(
            'campaignId' => $this->_getParam('templateId'),
            'isTemplate' => true,
            'listId' => $this->_getParam('listId'),
            'campaignName' => $this->_getParam('campaignName'),
        );
        $this->_forward('duplicate', 'campaign', 'frontoffice', $params);
    }

    /**
     * Récupère les colonnes d'un contrat en session
     * ou depuis le service contract si les infos n'existent pas encore en session
     *
     * @return array
     */
    private function _sessionGetColumns()
    {
        if (!(Dm_Session::hasEntry('columns'))) {
            $connectedUserContract = Dm_Session::getConnectedUserContract();
            Dm_Session::SetEntry('columns', $connectedUserContract->getColumns());
        }
        return Dm_Session::GetEntry('columns');
    }

    /**
     * Methode permettant de cacher un email en remplaçant des caractères
     *
     * @param string $email Email complet à cacher
     *
     * @return string Email caché
     */
    protected function _hideEmail($email)
    {
        $emailHided = null;
        if (!is_null($email)) {
            $start = substr($email, 0, 2);
            $middle = preg_replace("#[a-z]#", "*", substr($email, count($start), strrpos($email, ".")));
            $end = substr($email, strrpos($email, ".") + 1);
            $emailHided = $start . $middle . $end;
        }
        return $emailHided;
    }

    /**
     * Methode permettant de cacher un numéro de téléphone (fixe / mobile) en remplaçant des caractères
     *
     * @param string $phone Numéro complet à cacher
     *
     * @return string Numéro caché
     */
    protected function _hidePhone($phone)
    {
        $phoneHided = null;
        if (!is_null($phone)) {
            $start = substr($phone, 0, 4);
            $phoneHided = $start . preg_replace("#[0-9]#", "*", substr($phone, 4));
        }
        return $phoneHided;
    }

}
