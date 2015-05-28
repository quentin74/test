<?php

/**
 * DashboardController.php
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
 */

/**
 * Description de la classe : DashboardController
 *
 * @category Digitaleo
 * @package  Application.Module.Frontoffice.Controllers
 * @author   Digitaleo <developpement@digitaleo.com>
 * @license  http://www.digitaleo.net/licence.txt Digitaleo Licence
 * @link     http://www.digitaleo.net
 */
class Frontoffice_DashboardController extends Zend_Controller_Action
{
    /* @var Service_Api_Handler_Campaign_Interface */

    protected $_campaignService;

    /* @var Mk_Contacts_ContactList_Adapter_Interface */
    protected $_contactListService;

    /* @var Mk_Contacts_Contact_Adapter_Interface */
    protected $_contactService;

    /** @var Eo_Rest_Wrapper */
    protected $_baseoContentClient;

    /**
     * List of all ajax actions that will only return json
     *
     * @var array
     */
    protected $_ajaxable = array(
        'ajax-unsubscribe',
    );

    /**
     * Initialisation
     *
     * @return void
     */
    public function init()
    {

        // Initialisation des services de gestion de campagnes et contacts
        $this->_campaignService = Dm_Session::GetEntry(Dm_Session::SERVICE_FACTORY)->getCampaignService();
        $this->_contactListService = Mk_Factory::GetContactListAdapter();
        $this->_contactService = Mk_Factory::GetContactAdapter();
        $this->view->JQuery()->addJavascriptFile('/scripts/jquery/plugins/jquery.jfeed.pack.js?' . SCRIPT_VERSION_JS);
        $this->view->headScript()->appendFile('/scripts/marketeo-dashboard.js?' . SCRIPT_VERSION_JS);

        $contentLibraryConf = Zend_Registry::get('contentLibrary');
        $this->_baseoContentClient = new Eo_Rest_Wrapper($contentLibraryConf['rest'],
            Dm_Session::GetEntry(Dm_Session::CONNECTED_USER)->userKey);


        // liste des modèles disponibles pour créer une campagne
        $templateFilter = new Service_Api_Filter_Template();
        $templateFilter->properties = array('id');
        $readTemplatesResult = Dm_Session::GetEntry(Dm_Session::SERVICE_FACTORY)->getCampaignService()
            ->templateRead($templateFilter);
        $this->view->nbTemplates = $readTemplatesResult->size;

        //widget desabo
        $formUnsubscribe = $this->_getUnsubscribeForm();
        $this->view->formUnsubscribe = $formUnsubscribe;

        // set json context
        $jsonContext = $this->_helper->getHelper('ContextSwitch');
        foreach ($this->_ajaxable as $action) {
            $jsonContext->setActionContext($action, array('json'));
        }
        $jsonContext->initContext('json');

        
    }

    /**
     * Page d'acceuil
     *
     * @return void
     */
    public function indexAction()
    {
        $hScript = $this->view->headScript();
        $hScript->appendFile('/scripts/angularjs/lib/moment/min/moment-with-langs.min.js');
        $hScript->appendFile('/scripts/angularjs/lib/eonasdan-bootstrap-datetimepicker/build/js/bootstrap-datetimepicker.min.js');
        $hScript->appendFile('/scripts/angularjs/lib/angular-route/angular-route.min.js');
        $hScript->appendFile('/scripts/angularjs/lib/angular-resource/angular-resource.min.js');
        $hScript->appendFile('/scripts/angularjs/lib/angular-cookies/angular-cookies.min.js');
        $hScript->appendFile('/scripts/bootstrap-ui/ui-bootstrap-tpls-0.10.0.js');
        
        //================================================================================================
        // NG APP & DEPS
        //================================================================================================
        
        $hScript->appendFile('/scripts/bootstrap-daterangepicker/js/daterangepicker.js');
        $hScript->appendFile('/scripts/angularjs/layout/login/login.js');
        $hScript->appendFile('/scripts/bootstrap-daterangepicker/js/moment.min.js');
        $this->view->headLink()->appendStylesheet('/scripts/bootstrap-datetimepicker/css/bootstrap-datetimepicker.css');
        $this->view->headLink()->appendStylesheet('/scripts/bootstrap-daterangepicker/css/daterangepicker-bs3.css');
        $hScript->appendFile('/scripts/angularjs/layout/infoBadge.js');
        $hScript->appendFile('/scripts/angularjs/tools/dynamic-loader.js');
        $hScript->appendFile('/scripts/angularjs/tools/datetimepicker/directive.js');
        
        $hScript->appendFile('/scripts/angularjs/notification/application/notification.js');
        $hScript->appendFile('/scripts/angularjs/notification/controllers/dashboard.js');
        $hScript->appendFile('/scripts/angularjs/notification/services/notification.js');
        $hScript->appendFile('/scripts/angularjs/sms/services/customFields.js');
        $hScript->appendFile('/scripts/angularjs/notification-sms-settings/services/sms-template.js');
        $hScript->appendFile('/scripts/angularjs/notification/filters/list.js');
        $hScript->appendFile('/scripts/angularjs/sms/directives/counterSms.js');
        $hScript->appendFile('/scripts/angularjs/tools/authentication/directive.js');
        $hScript->appendFile('/scripts/angularjs/tools/config/config.js');
        $hScript->appendFile('/scripts/angularjs/tools/filters/directive.js');
        $hScript->appendFile('/scripts/angularjs/sms/directives/counterSms.js');

        $hScript->appendFile('/scripts/angularjs/dashboard/application/appDashboard.js');
        $hScript->appendFile('/scripts/angularjs/campaign/editor/app.js');
        $hScript->appendFile('/scripts/angularjs/campaign/editor/controllers/dashboard.js');
        $hScript->appendFile('/scripts/angularjs/campaign/editor/filters/list-filter.js');
        $hScript->appendFile('/scripts/angularjs/campaign/editor/services/campaign.js');
        $hScript->appendFile('/scripts/angularjs/campaign/common/app.js');
        $hScript->appendFile('/scripts/angularjs/campaign/common/controllers/common.js');
        $hScript->appendFile('/scripts/angularjs/campaign/common/services/common.js');

        $this->_helper->layout->setLayout('dash-layout');
        $currentContract = Dm_Session::GetConnectedUserContract();
        $this->view->mediaTypes = $currentContract->medias;

        // test for displaying the popup for selecting the type of campaign
        $canSelectCampaignType = false;
        $addCampaignUrl = $this->view->href('campaign-add');
        if (($this->view->HasAccess('createAutomaticCampaign')) ||
            ($this->view->pageAccess('list-templates') && $this->view->nbTemplates > 0)
        ) {
            $canSelectCampaignType = true;
            $addCampaignUrl = $this->view->href('campaign-select');
        }
        $this->view->canSelectCampaignType = $canSelectCampaignType;
        $this->view->addCampaignUrl = $addCampaignUrl;

        $statuses = Service_Api_Object_Message::$STATUS_BY_MEDIA[Service_Api_Object_Message::SMS];
        $trsStatuses = array('' => ucfirst($this->view->translate('all')));
        foreach ($statuses as $status) {
            $trsStatuses[$status] = ucfirst($this->view->translate($status . '.export'));
        }
        $this->view->availableStatuses = $trsStatuses;
        
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
            isset($readTemplatesResult) && $readTemplatesResult->size > 0) {
            $urlsCreation['network'] = $this->view->href('list-templates');
        }
        
        $this->view->urlsCreation = json_encode($urlsCreation);
    }

    /**
     * contenu du tableau des listes de campagnes
     *
     * @return void
     */
    public function ajaxCampaignsListAction()
    {
        // Pas de layout pour cette action
        $this->_helper->layout->disableLayout();

        // Liste de statuts
        $statusList = array(
            Service_Api_Object_Campaign::STATUS_EDITING => Service_Api_Object_Campaign::STATUS_EDITING,
            Service_Api_Object_Campaign::STATUS_CONFIRMED => Service_Api_Object_Campaign::STATUS_RUNNING,
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

        // Récupération des dernières campagnes
        $campaignFilter = new Service_Api_Filter_Campaign();
        $campaignFilter->limit = 10;
        $campaignFilter->sort = array(
            array('dateCreated', 'DESC')
        );
        $campaignFilter->properties = array(
            'id',
            'name',
            'status',
            'dateStart',
            'contactListName', //Setté uniquement pour les campagnes confirmées
            'contactListExtId',
            'isAutomatic'
        );
        $campaignContainer = $this->_campaignService->campaignRead($campaignFilter);
        $campaigns = $campaignContainer->list;

        //++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
        // @TODO Deplacer ce traitement pour qu'il soit fait au rennomage de la liste
        // actuellement le nom de la liste n'est sauve qu'une fois la campagne confirmée
        // c'est pourquoi le code ci dessous se charge de recupere le nom de la liste des
        // campagne non confirmée
        //++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
        // Les derniers ids de listes contacts
        $contactListExtId = array();
        // Lecture des identifiants de campagnes pour les passer
        // dans le filtre de séléction de listes
        if (!empty($campaigns)) {
            foreach ($campaigns as $campaign) {
                /* @var $campaign Service_Api_Object_Campaign */
                if ($campaign->contactListExtId != null) {
                    $contactListExtId[] = $campaign->contactListExtId;
                }
            }
        }
        // Lecture des listes de contacts des compagnes en edition
        $listIds = array_values(array_filter(array_unique($contactListExtId)));
        $lContainer = $this->_contactListService->listRead(new Mk_Contacts_ContactList_Filter($listIds));
        /* @var $lContainer Mk_Contacts_ContactList_Output_List */
        $lists = $lContainer->list;
        // Lecture de nom de listes de contacts pour les passer
        // à la vue d'affichage de listes de campagnes
        if (!empty($campaigns) && !empty($lists)) {
            foreach ($lists as $list) {
                $listNames[$list->id] = $list->name;
            }
        } else {
            $listNames = array();
        }

        if (!empty($campaigns)) {
            foreach ($campaigns as $campaign) {
                if (empty($campaign->contactListName)) {
                    if (array_key_exists($campaign->contactListExtId, $listNames)) {
                        $campaign->contactListName = $listNames[$campaign->contactListExtId];
                    } else {
                        $campaign->contactListName = "-";
                    }
                }
            }
        }

        //++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++++
        $this->view->campaigns = $campaigns;
    }

    /**
     * contenu du tableau de listes de listes de contacts
     *
     * @return void
     */
    public function ajaxContactsListsListAction()
    {
        // Pas de layout pour cette action
        $this->_helper->layout->disableLayout();
        // Récupération des dernières listes de contacts
        $filter = new Mk_Contacts_ContactList_Filter(null, null, null, null, 5);
        $filter->total = false;
        $filter->properties = array('DEFAULT', 'stats', 'importStatus', 'importErrors');
        $listContainer = $this->_contactListService->listStatsRead($filter, true);
        // Les listes de contacts à afficher dans le dashboard
        $readResult = $listContainer->detailList->list;

        $this->_helper->json($readResult, true);
    }

    /**
     * contenu du tableau de listes de listes de contacts
     *
     * @return void
     */
    public function ajaxContactsListsListDeprecatedAction()
    {
        // Pas de layout pour cette action
        $this->_helper->layout->disableLayout();
        // Récupération des dernières listes de contacts
        $filter = new Mk_Contacts_ContactList_Filter(null, null, null, null, 5);
        $filter->total = false;
        $filter->properties = array('DEFAULT', 'stats', 'importStatus', 'importErrors');
        $listContainer = $this->_contactListService->listStatsRead($filter, true);
        // Les listes de contacts à afficher dans le dashboard
        $this->view->lists = $listContainer->detailList;

        $currentContract = Dm_Session::GetConnectedUserContract();
        $this->view->medias = $currentContract->medias;
    }

    /**
     * Lecture des ratios de delivrabilité
     *
     * @return void
     */
    public function ajaxRatioAction()
    {
        // Pas de layout pour cette action
        $this->_helper->layout->disableLayout();

        $currentContract = Dm_Session::GetConnectedUserContract();
        $mediaTypes = $currentContract->medias;

        $ratio = array();

        if (in_array(strtoupper(Service_Api_Object_Message::SMS), $mediaTypes)) {
            $ratio[Service_Api_Object_Message::SMS] = '-';
        }
        if (in_array(strtoupper(Service_Api_Object_Message::EMAIL), $mediaTypes)) {
            $ratio[Service_Api_Object_Message::EMAIL] = array(
                'delivrability' => '-',
                'opening' => '-',
            );
        }
        if (in_array(strtoupper(Service_Api_Object_Message::VOICE), $mediaTypes)) {
            $ratio[Service_Api_Object_Message::VOICE] = array('delivrability');
        }
        if (in_array(strtoupper(Service_Api_Object_Message::VOICEMAIL), $mediaTypes)) {
            $ratio[Service_Api_Object_Message::VOICEMAIL] = array('delivrability');
        }

        // Récupération des ratios de délivrabilité
        $ratioContainer = $this->_campaignService->userRatioRead();
        if ($ratioContainer->status) {
            $ratioResult = $ratioContainer->result;

            // Ratios du taux de délivrabilité des SMS
            if (in_array(strtoupper(Service_Api_Object_Message::SMS), $mediaTypes)) {
                $ratio[Service_Api_Object_Message::SMS] =
                    $ratioResult[Service_Api_Object_Message::SMS]['delivrability'];
            }
            if (in_array(strtoupper(Service_Api_Object_Message::EMAIL), $mediaTypes)) {
                $ratio[Service_Api_Object_Message::EMAIL]['delivrability'] =
                    $ratioResult[Service_Api_Object_Message::EMAIL]['delivrability'];
                $ratio[Service_Api_Object_Message::EMAIL]['opening'] =
                    $ratioResult[Service_Api_Object_Message::EMAIL]['opening'];
            }
            if (in_array(strtoupper(Service_Api_Object_Message::VOICE), $mediaTypes)) {
                $ratio[Service_Api_Object_Message::VOICE]['delivrability'] =
                    $ratioResult[Service_Api_Object_Message::VOICE]['delivrability'];
            }
            if (in_array(strtoupper(Service_Api_Object_Message::VOICEMAIL), $mediaTypes)) {
                $ratio[Service_Api_Object_Message::VOICEMAIL]['delivrability'] =
                    $ratioResult[Service_Api_Object_Message::VOICEMAIL]['delivrability'];
            }

        }

        $this->_helper->json->sendJson($ratio);
    }

    /**
     * Lecture de répartition de contacts par type de canal media
     *
     * @return void
     */
    public function ajaxContactDistributionAction()
    {
        // Pas de layout pour cette action
        $this->_helper->layout->disableLayout();

        $currentContract = Dm_Session::GetConnectedUserContract();
        $mediaTypes = $currentContract->medias;

        // Récupération des dernières listes de contacts
        $globalStats = $this->_contactListService->listStatsRead(new Mk_Contacts_ContactList_Filter(), false, true);

        // Construction des données pour l'affichage des la distribution de contacts par canal
        $listStats = array();

        if (in_array(strtoupper(Service_Api_Object_Message::SMS), $mediaTypes)) {
            $listStats[Service_Api_Object_Message::SMS] = $globalStats->smsNumber;
        }
        if (in_array(strtoupper(Service_Api_Object_Message::EMAIL), $mediaTypes)) {
            $listStats[Service_Api_Object_Message::EMAIL] = $globalStats->emailNumber;
        }
        if (in_array(strtoupper(Service_Api_Object_Message::VOICE), $mediaTypes)) {
            $listStats[Service_Api_Object_Message::VOICE] = $globalStats->voiceNumber;
        }
        if (in_array(strtoupper(Service_Api_Object_Message::VOICEMAIL), $mediaTypes)) {
            $listStats[Service_Api_Object_Message::VOICEMAIL] = $globalStats->voicemailNumber;
        }
        if (in_array(strtoupper(Service_Api_Object_Message::PLV), $mediaTypes)) {
            $listStats[Service_Api_Object_Message::PLV] = $globalStats->plvNumber;
        }

        $this->view->listStats = $listStats;
    }

    /**
     * Récupération de la date d'envoi d'une campagne
     * Si la campagne n'est pas en edition, la date d'envoi est
     * la date d'execution de la prochaine étape
     *
     * @param int $campaignId Identifiant de la campagne
     *
     * @return date|null
     */
    protected function _getCampaignSendingDate($campaignId)
    {
        $sendingDate = null;

        // Lecture des étapes de la campagne
        $stepFilter = new Service_Api_Filter_Step();
        $stepFilter->setCampaignId($campaignId);
        $stepContainer = $this->_campaignService->stepRead($stepFilter);

        if (!empty($stepContainer->list)) {
            $steps = $stepContainer->list;

            foreach ($steps as $step) {
                if ((is_null($sendingDate)) || (strtotime($sendingDate) > strtotime($step->dateExecution))) {
                    $sendingDate = date('d/m/Y', strtotime($step->dateExecution));
                }
            }
        }

        return $sendingDate;
    }

    /**
     * Returns latest network notes
     *
     * @return void
     */
    public function ajaxNoteAction()
    {
        // No layout
        $this->_helper->layout->disableLayout();

        // getting notes for the network user
        $noteFilter = new Service_Api_Filter_Note();
        //$noteFilter->sort = 'datePublication DESC';
        $noteFilter->limit = 3;
        $noteFilter->properties = array('object', 'body', 'datePublication');

        // getting notes for the network user
        $noteResult = Service_Api_Object_Note::read($noteFilter);

        $notes = array();
        if (isset($noteResult->list)) {
            foreach ($noteResult->list as $note) {
                $emailReg = Dm_Helper_Checker::EMAIL_VALIDATION_REGEX;
                $urlReg = '#((http|https)\://[a-zA-Z0-9\-\.]+\.[a-zA-Z]{2,6}+\/?\S*)[[:space:]]?#';
                // si on trouve une ou plusieurs adresses email
                if (preg_match($emailReg, $note->body) === 1) {
                    $note->body = preg_replace($emailReg, '<a href="mailto:$1">$1</a> ', $note->body);
                }
                // si on trouve une ou plusieurs url
                if (preg_match($urlReg, $note->body) === 1) {
                    $note->body = preg_replace($urlReg, '<a target="_blank" href="$1">$1</a> ', $note->body);
                }
                $notes[] = $note;
            }
        }
        $this->_helper->json->sendJson($notes);
    }

    /**
     * Construit le formulaire du widget de désabonnement
     *
     * @return Zend_Form
     */
    protected function _getUnsubscribeForm()
    {
        $form = new Zend_Form();
        $desaboArea = new Zend_Form_Element_Textarea('desaboArea');
        $desaboArea->setAttrib("style", "width:90%; height:60px; margin: 10px auto;");
        $desaboArea->setAttrib("class", "form-control");

        $form->addElement($desaboArea);


        $form->setDecorators(array(
                new Zend_Form_Decorator_FormElements(),
                new Zend_Form_Decorator_Form()
            )
        );
        $form->setElementDecorators(
            array(
                'ViewHelper',
                new Dm_Form_Decorator_ShortErrors(),
            )
        );
        return $form;
    }

    /**
     * Traitement AJAX du widget de désabonnement
     *
     * Parametres :
     * campaignId       - Identifiant de la campagne
     * name             - (optionnel) Nom de la campagne
     * contactListExtId - (optionnel) Identifiant de la liste de contacts
     * comment          - (optionnel) Commentaire de la campagne
     *
     * @return void
     */
    public function ajaxUnsubscribeAction()
    {
        $this->_helper->layout->disableLayout();
        $contactsEmailToDelete = array();
        $contactPhoneToDelete = array();
        $contactList = array_filter(explode(PHP_EOL, $this->_getParam('contactList')), 'trim');

        if (count($contactList) == 0) {
            $this->view->status = $this->view->translate('please enter at least one contact');
            return;
        }

        foreach ($contactList as $contactLine) {
            $contacts = explode(",", $contactLine);
            foreach ($contacts as $contact) {
                $contact = trim($contact);
                //$contactsToDelete[] = $contact;
                if (filter_var($contact, FILTER_VALIDATE_EMAIL)) {
                    $contactsEmailToDelete[] = $contact;
                } else {
                    $contactPhoneToDelete[] = $contact;
                }
            }
        }

        $contactsEmail = array_filter($contactsEmailToDelete);
        $contactsPhone = array_filter($contactPhoneToDelete);
        $contract = Dm_Session::getConnectedUserContract();
        $contractId = $contract->id;

        if (count($contactsEmail)) {
            $filter = new Mk_Contacts_Contact_Filter();
            $filter->contractId = $contractId;
            $filter->meanOfContact = $contactsEmail;
            $emailDeleted = $this->_contactService->contactStopByMeanOfContact($filter, array("EMAIL"));
        }

        if (count($contactsPhone)) {
            $filter = new Mk_Contacts_Contact_Filter();
            $filter->contractId = $contractId;
            $filter->meanOfContact = $contactsPhone;
            $phoneDeleted = $this->_contactService->contactStopByMeanOfContact($filter,
                array("SMS", "VOICE", "VOICEMAIL"));
        }

        $msg = $this->view->translate("your request has been treated");
        $this->view->status = $msg;
    }

}

