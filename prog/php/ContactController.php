<?php

/**
 * ContactController.php
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
 * Description de la classe : ContactController
 *
 * @category Digitaleo
 * @package  Application.Module.Frontoffice.Controllers
 * @author   Digitaleo <developpement@digitaleo.com>
 * @license  http://www.digitaleo.net/licence.txt Digitaleo Licence
 * @link     http://www.digitaleo.net
 */
class Frontoffice_ContactController extends Zend_Controller_Action
{

    const NUMBER_CONTACTS_TO_IMPORT_PER_STEP    = 10000;
    const NUMBER_PHPEXCEL_ITERATION_PER_REQUEST = 1;
    const IMPORT_CACHE_NAME                     = 'importCache';
    const IMPORT_CUSTOM_DEFAULT_NAME            = 'default';

    const BATCH_OPT_IN_OUT = 10000;

    /**
     * @var Mk_Contacts_Contact_Adapter_Interface
     */
    private $_contactAdapter;

    /**
     * @var Mk_Contacts_ContactList_Adapter_Interface
     */
    private $_contactListAdapter;

    protected $_mediasWithStats = array('sms', 'email', 'voice', 'voicemail', 'plv');

    /**
     * Initialisation du controller
     *
     * @return void
     */
    public function init()
    {
        $this->_contactAdapter = Mk_Factory::GetContactAdapter();
        $this->_contactListAdapter = Mk_Factory::GetContactListAdapter();
        $this->_session = new Zend_Session_Namespace($this->getRequest()->getControllerName());

        $this->_helper->getHelper('contextSwitch')
                      ->addActionContext('list', 'json')
                      ->initContext('json');
    }

    /**
     * Merge contact list in a new contact list
     *
     * params:
     * - ids sets of list identifier, must contain a least of one list id
     * - name (optionnal) new list name, if not set we generate a name like 'List 2013-06-03'
     *
     * @return void - redirect on list page
     */
    public function listMergeAction()
    {
        $date = new Zend_Date();
        $ids = explode('-', $this->_getParam('ids'));
        $newList = new Mk_Contacts_ContactList();
        $newList->name = $this->_getParam('name',
                                          ucfirst($this->view->translate('list')) . ' ' .
                                          $date->toString('YYYY-MM-dd'));
        foreach ($ids as $id) {
            $tmp = new Mk_Contacts_ContactList();
            $tmp->id = $id;
            $lists[] = $tmp;
        }
        $newList->merge($lists);

        $this->_redirect($this->view->href('contact-list'));
    }

    /**
     * Page d'acceuil
     * Affichage du résumé sur les contacts de l'utilisateur ainsi que le detail par liste de contact.
     * Pour chaque ligne possibilité
     * - de modifier le nom de la liste
     * - lancer l'affichage geo de la liste ou de l'ensemble des contact
     * - lancer la gestion des contacts de la liste ou de l'ensemble des contact
     * - créer une campagne a partie de la liste ou de l'ensemble des contact
     * - supprimer la liste
     *
     * @return void
     */
    public function indexAction()
    {
        $filter = new Mk_Contacts_ContactList_Filter();
        $filter->limit = 1;
        $filter->offset = 0;

        $statistics = $this->_contactListAdapter->listStatsRead($filter, false, true);
        /* @var $statistics Mk_Contacts_ContactList_Output_Stats */
        // Lecture des informations de la base de contact de l'utilisateur connecté
        $this->view->contactNumber = $statistics->contactNumber;
        $this->view->smsNumber = $statistics->smsNumber;
        $this->view->emailNumber = $statistics->emailNumber;
        $this->view->voiceNumber = $statistics->voiceNumber;
        $this->view->voicemailNumber = $statistics->voicemailNumber;
        $this->view->plvNumber = $statistics->plvNumber;
        //we set all the values in the view, then we filter them
        $this->filterStatsWithContractsMedias($this->view);

        $this->view->countPerPage = Zend_Paginator::getDefaultItemCountPerPage();

        /* @var $paginator Zend_Paginator */
        $this->view->headScript()->appendFile('/scripts/marketeo-contact.js?' . SCRIPT_VERSION_JS);
        $this->view->headScript()->appendFile('/scripts/marketeo-fileupload.js?' . SCRIPT_VERSION_JS);
        $this->view->headScript()->appendFile('/scripts/marketeo-contactLists.js?' . SCRIPT_VERSION_JS);

        // liste des modèles disponibles pour créer une campagne
        $templateFilter = new Service_Api_Filter_Template();
        $templateFilter->properties = array('id');
        $readTemplatesResult = Dm_Session::GetEntry(Dm_Session::SERVICE_FACTORY)->getCampaignService()
                                         ->templateRead($templateFilter);
        $this->view->nbTemplates = $readTemplatesResult->size;

        // On va chercher la liste des configs enregistrées
        $this->view->customHeadersList = $this->_getCustomHeadersList();
    }

    /**
     * Action qui renvoie le json
     *
     * @return void
     */
    public function contactsListsListAction()
    {
        $this->view->layout()->disableLayout();
        /*
         * Params  : size, page
         * Returns : list of lists
         */
        $page = intval($this->_getParam('page', 1));
        $size = intval($this->_getParam('size', Zend_Paginator::getDefaultItemCountPerPage()));

        $filter = new Mk_Contacts_ContactList_Filter();
        $filter->limit = $size;
        $filter->offset = ($page - 1) * $filter->limit;
        $filter->properties = array('DEFAULT', 'stats', 'importStatus', 'importErrors');

        $statistics = $this->_contactListAdapter->listStatsRead($filter, true);
        $this->filterStatsWithContractsMedias($statistics);

        $return = array(
            'page' => $page,
            'size' => $statistics->detailList->size,
            'total' => $statistics->detailList->total,
            'data' => $statistics->detailList->list
        );

        $this->_helper->json->sendJson($return);
    }

    /**
     * Action qui renvoie le json
     *
     * @return void
     */
    public function contactListSearchAction()
    {
        $this->view->layout()->disableLayout();
        $rawParams = json_decode($this->getRequest()->getRawBody());
        $keyword = $rawParams->keyword;
        $filter = new Mk_Contacts_ContactList_Filter();
        if (!$keyword) {
            $filter->limit = 40;
            $filter->sort = array(array('id', 'DESC'));
        }
        else{
            $filter->name = array(array('operator' => 'CONTAINS', 'value' => $keyword));
        }
        $id = $rawParams->idList;
        if($id)
        {
            $filter->listId = $id;
        }
        $filter->properties = array('DEFAULT', 'stats');
        $lists = $this->_contactListAdapter->listRead($filter)->list;

        foreach ($lists as $list) {
            $return[] = array(
                'id' => $list->id,
                'name' => $list->name,
                'contacts' => $list->contactNumber
            );
        }

        $this->_helper->json->sendJson($return);
    }


    /**
     * Action qui renvoie le json
     *
     * @return void
     */
    public function rentedListsListAction()
    {
        $this->view->layout()->disableLayout();

        if (!$this->view->hasAccess('rentalContactList')) {
            $emptyReturn = array(
                'page' => 1,
                'size' => 0,
                'total' => 0,
                'data' => array()
            );
            $this->_helper->json->sendJson($emptyReturn);
            return;
        }

        /*
         * Params  : size, page
         * Returns : list of lists
         */
        $page = intval($this->_getParam('page', 1));

        $filter = new Mk_Contacts_ContactList_Filter();
        $filter->limit = 0;
        $filter->offset = ($page - 1) * $filter->limit;
        $filter->category = 'RENTED';

        $filter->properties = array('DEFAULT',
                                    'stats',
                                    'importStatus',
                                    'importErrors',
                                    'dateCreated',
                                    'expired',
                                    'dateExpired',
                                    'shootCount');

        $statistics = $this->_contactListAdapter->listStatsRead($filter, true);
        $this->filterStatsWithContractsMedias($statistics);

        $return = array(
            'page' => $page,
            'size' => $statistics->detailList->total,
            'total' => $statistics->detailList->total,
            'data' => $statistics->detailList->list
        );
        $this->_helper->json->sendJson($return);
    }

    /**
     * Action qui supprime les contacts non associé a une liste
     * Parametres attendus :
     *
     * Redirige vers la page de detail des contacts du compte
     *
     * @return void
     */
    public function deleteOrphanAction()
    {
        try {
            $listFilter = new Mk_Contacts_Contact_Filter();
            $listFilter->belongtolist = false;
            $this->_contactAdapter->contactDelete($listFilter);
        } catch (Exception $e) {
            Dm_Log::Error(__METHOD__ . $e->getTraceAsString());
            $this->_helper->FlashMessenger->addMessage(
                array('error' => ucfirst($this->view->translate("cannot delete orphan contact")))
            );
        }
        $this->_helper->FlashMessenger->addMessage(
            array('info' => ucfirst($this->view->translate("orphan deletion done")))
        );
        $this->_redirect($this->view->href('contact-list-edit'));
    }

    /**
     * Action ajax permettant de supprimer un ou des contacts
     * Parametre attendus :
     * - ids : (optionnel) liste d'identifiant des contacts à supprimer, optionnel si full present
     * - full : (optionnel) tous les contacts de l'utilisateur
     * - listId : (optionnel) identifiant de la Mk_Contacts_Contactn ne supprime pas les contacts on les desassocie
     *
     * Retourne :
     * - redirection vers la liste des contacts de la liste actuelle
     *
     * @return void
     */
    public function deleteAction()
    {
        $this->_helper->layout()->disableLayout();
        $this->_helper->viewRenderer->setNoRender(true);

        //@todo : modifier pour recupere le resultat du filtre plutot que toute les contact
        $complete = $this->_getParam('full');
        $ids = explode("-", $this->_getParam('ids'));
        $listId = $this->_getParam('listId');
        if (count($ids) > 0 || $complete) {
            $listFilter = new Mk_Contacts_Contact_Filter();
            $this->_initFilter($listFilter);
            if ($listId != null) {
                $listFilter->listId = array($listId);
            }
            if (!$complete) {
                //on ne veut supprimer que les contacts sélectionnés
                $listFilter->contactId = $ids;
            } else {
                // on veut supprimer tous les contacts trouvés
                if ($listId == null) {
                    // si on a pas précisé de list id,
                    // c'est que l'on veut supprimer tous les contacts de l'utilisateur
                    $listFilter->userId = Dm_Session::GetConnectedUser()->id;
                } else {
                    // sinon c'est que l'on veut retirer certains contacts d'une liste et donc il nous faut les
                    // identifiants de contacts correspondant au filtre demandé.
                    // on a besoin des identifiants de contacts pour les retirer de la liste
                    $listFilter->contactId = $this->_contactAdapter->contactGetIds($listFilter);
                }
            }

            if ($listId) {
                // on a précisé une liste, on ne veut donc que désassocier les contacts à la liste.
                $deleteIsDone = $this->_contactListAdapter->contactRemoveFromList($listId, $listFilter->contactId);
                Dm_Log::debug('nombre de contacts retirés de la liste : ' . $deleteIsDone);
            } else {
                // on n'a pas précisé de list Id donc on supprime les contacts selon le filtre
                $contactIds = $this->_contactAdapter->contactGetIds($listFilter);
                $listFilter->filters = null;

                // cas où l'on travaille sur la liste partielle
                // Mise en optout ou en optin par batch de 10 000
                $offset = 0;
                $deleteIsDone = 0;
                do {
                    $partialIds = array_slice($contactIds, $offset, self::BATCH_OPT_IN_OUT);
                    if (count($partialIds) > 0) {
                        Dm_Log::Debug('Delete of contacts ' . $offset . ' - ' . ($offset + self::BATCH_OPT_IN_OUT));
                        $listFilter->contactId = implode(',', $partialIds);
                        $deleteIsDone += $this->_contactAdapter->contactDelete($listFilter);
                    }
                    $offset += self::BATCH_OPT_IN_OUT;
                } while (count($partialIds) > 0);

                Dm_Log::Debug('Nombre de contacts supprimés : ' . $deleteIsDone);
            }
            if (!$deleteIsDone) {
                $this->_helper->FlashMessenger->addMessage(
                    array('error' => ucfirst($this->view->translate("cannot delete your selection")))
                );
            } else {
                $this->_helper->FlashMessenger->addMessage(
                    array('info' => ucfirst($this->view->translate("selection deleted")))
                );
            }
        } else {
            $this->_helper->FlashMessenger->addMessage(
                array('error' => ucfirst($this->view->translate("cannot delete your selection")))
            );
        }
        $this->_redirect($this->view->href('contact-list-edit', array('id' => $listId)));
    }

    /**
     * Action permettant d'exporter un ou des contacts
     * Parametre attendus :
     * - ids : (optionnel) liste d'identifiant des contacts à supprimer, optionnel si full present
     * - full : (optionnel) tous les contacts de l'utilisateur
     * - listId : (optionnel) identifiant de la liste si present on ne supprime pas les contacts on les desassocie
     *
     * Retourne :
     * - redirection vers la liste des contacts de la liste actuelle
     *
     * @return void
     */
    public function exportAction()
    {
        // Identifiant de la liste
        $listId = $this->_getParam('listId');

        if (!$this->view->hasAccess('exportContact')) {
            $errorMessage = ucfirst($this->view->translate('cannot export your selection')) . ' <br> ' .
                $this->view->translate("You don't have the necessary credentials to access this resource");
            $this->_helper->FlashMessenger->addMessage(
                array('error' => $errorMessage)
            );
            $this->_redirect($this->view->href('contact-list-edit', array('id' => $listId)));
        }
        Dm_Log::Debug('Start ' . __METHOD__);

        // Pas de layout
        $this->_helper->layout()->disableLayout();
        $this->_helper->viewRenderer->setNoRender(true);

        // Export de toute la liste
        $complete = $this->_getParam('full', false);

        // Les ids de contacts à exporter
        $contactsIds = $this->_getParam('ids', null);
        $ids = (!is_null($contactsIds) ? explode('-', $contactsIds) : array());

        if (count($ids) > 0 || $complete) {
            $listFilter = new Mk_Contacts_Contact_Filter();
            $this->_initFilter($listFilter);

            // Export de toute la liste ?
            if (!$complete) {
                $listFilter->contactId = $ids;
            } else {
                if (!is_null($listId)) {
                    $listFilter->listId = array($listId);
                }
            }

            // Generate CSV
            $columns = Service_Contact::GetColumns();
            $columns['dateCreated'] = ucfirst($this->view->translate('creation date'));
            $listFilter->properties = array_merge(array('DEFAULT'), array_keys($columns));
            $csv = Mk_Contacts_Contact::GenerateCsv($listFilter, $columns,
                Service_Contact::GetCsvSeparator(),
                Dm_Config::GetConfig('slbeo', 'export.contact'));

            // Generate Filename
            $filename = $this->_setCsvFilename($listFilter);

            // Ecriture des entete http pour transmettre le fichier csv
            header('Content-Description: File Transfer');
            if (headers_sent()) {
                $this->Error('Some data has already been output to browser, can\'t send CSV file');
            }
            header('Content-type: application/octetstream; charset=utf-8');
            header('Content-Length: ' . $csv['filesize']);
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            header('Content-Transfer-Encoding: binary');

            // Lecture du fichier
            readfile($csv['filename']);

            // Suppression du fichier temporaire
            unlink($csv['filename']);
        } else {
            $this->_helper->FlashMessenger->addMessage(
                array('error' => ucfirst($this->view->translate('cannot export your selection')))
            );
        }

        Dm_Log::Debug('End ' . __METHOD__);
    }

    /**
     * Association d'un ensemble de contact à une liste existante ou a une nouvelle liste
     * Parametre attendus :
     * - ids : (optionnel) liste d'identifiant des contacts à supprimer, optionnel si full present
     * ou
     * - full : (optionnel) tous les contacts de l'utilisateur
     *
     * - listId : (optionnel) identifiant de la liste a laquelle associe les contacts, sinon on crée une nouvelle
     * ou
     * - name : (optionnel) nom de la nouvelle liste
     * liste
     *
     * Retourne :
     * - redirection vers la liste des contacts de la liste actuelle
     *
     * @return void
     */
    public function contactAssociationAction()
    {
        $complete = $this->_getParam('full');
        $ids = explode("-", $this->_getParam('ids'));
        $listId = $this->_getParam('listId');
        $fromListId = $this->_getParam('fromListId');
        $name = $this->_getParam('name');

        if (count($ids) > 0 || $complete) {
            if ($complete) {
                $listFilter = new Mk_Contacts_Contact_Filter();
                $this->_initFilter($listFilter);
                if (isset($fromListId)) {
                    $listFilter->listId = array($fromListId);
                }
                $ids = $this->_contactAdapter->contactGetIds($listFilter);
            }

            // Si pas d'identifiant de liste on crée une nouvelle liste
            if (!isset($listId)) {
                // Si pas de nom spécifié on utilise "New List"
                if (!isset($name)) {
                    $name = ucfirst($this->view->translate('new list'));
                }
                $listId = $this->_contactListAdapter->listCreate($name)->id;
            }

            $this->_contactListAdapter->contactAddToList($listId, $ids);
            Dm_Log::Debug('Now, redirection to ' . $this->view->href('contact-list-edit', array('id' => $listId)));
            $this->_redirect($this->view->href('contact-list-edit', array('id' => $listId)));
        } else {
            if (isset($listId)) {
                $this->_helper->FlashMessenger->addMessage(
                    array('error' => ucfirst($this->view->translate("cannot associate your selection into a list")))
                );
            } else {
                $this->_helper->FlashMessenger->addMessage(
                    array('error' =>
                              ucfirst($this->view->translate("cannot create a new list without a valid selection")))
                );
            }
        }
        $this->_redirect($this->view->href('contact-list'));
    }

    /**
     * Affichage des contacts d'une liste ou de tous les contacts de l'utilisateur
     * Parametres attendus :
     * - id : (optionnel) integer identifiant de la liste si pas specifié un affiche l'integralité des contacts
     *
     * @return void
     */
    public function listDisplayMapAction()
    {
        throw new Exception('Fonctionnalité bientôt disponible');
    }

    /**
     * Action ajax permettant de creer une nouvelle liste vide
     * Parametre attendus :
     * - name : string nom de la liste
     *
     * Retourne :
     * - flux JSON {status=>[0|1], message=>TEXT}
     *
     * @return void
     */
    public function listAddAction()
    {
        try {
            $name = $this->_getParam('name', ucfirst($this->view->translate('new list')));
            $result = $this->_contactListAdapter->listCreate($name);

            if (!isset($result->id)) {
                $result = array(
                    'status' => false,
                    'message' => ucfirst($this->view->translate('cannot create a new list'))
                );
            } else {
                $result = array(
                    'status' => true,
                    'message' => ""
                );
            }
        } catch (Exception $e) {
            $result = array(
                'status' => false,
                'message' => $e->getMessage()
            );
        }

        $this->_helper->json->sendJson($result);
    }

    /**
     * Action ajax permettant de supprimer une ou des liste
     * Parametre attendus :
     * - ids : liste d'identifiant des listes à supprimer
     *
     * Retourne :
     * - flux JSON {status=>[0|1], message=>TEXT}
     *
     * @return void
     */
    public function listDeleteAction()
    {
        $ids = explode("-", $this->_getParam('ids'));
        if (is_array($ids)) {
            $idsToDelete = array();
            foreach ($ids as $id) {
                // on ne demande pas la suppression de listes de contacts dont l'identifiant n'est pas numérique,
                // => prise en compte du ^mock_
                if (is_numeric($id)) {
                    $idsToDelete[] = $id;
                }
            }
            $deleteResult = $this->_contactListAdapter->listDelete(new Mk_Contacts_ContactList_Filter($ids));
            if (!$deleteResult) {
                $this->_helper->FlashMessenger->addMessage(
                    array('error' => ucfirst($this->view->translate("cannot delete your selection")))
                );
            } else {
                $this->_helper->FlashMessenger->addMessage(
                    array('info' => ucfirst($this->view->translate("list deleted")))
                );
            }
        } else {
            $this->_helper->FlashMessenger->addMessage(
                array('error' => ucfirst($this->view->translate("cannot delete your selection")))
            );
        }

        if (!is_null($this->_getParam('ajax'))) {
            $this->_helper->layout->disableLayout();
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

        $this->_redirect($this->view->href('contact-list'));
    }

    /**
     * Affichage de la liste de contacts contenu dans la liste ou dans la base complete si aucune liste spécifiée.
     * Parametre attendus :
     * - id : (optionnel) integer identifiant de la liste si pas specifié un affiche l'integralité des contacts
     *
     * @return void
     */
    public function listEditAction()
    {
        $id = $this->_getParam('id');

        // Activer ou non les optout manuel en fonction de l'API
        $contactApi = Dm_Session::GetEntry(Dm_Session::CONNECTED_USER_CONTRACT)
                                ->getCustomParameter('contactApi')->value;
        $this->view->activeManual = ($contactApi == 'baseo') ? 'manuel' : '';

        // Les ids de contacts à traiter
        if (!is_null($id)) {
            $list = Mk_Contacts_ContactList::LoadById($id);
            $this->view->listName = $list->name;
            $this->view->listIsMock = $list->isMock;
        }

        $page = $this->_getParam('page', 1);
        $contactFilter = new Mk_Contacts_Contact_Filter(null, $id);
        $this->_initFilter($contactFilter);

        // Fix #6704 Sort by lastName don't work
        $orderCol = $this->_getParam('orderCol', 'lastName');
        if (isset($orderCol)) {
            $order = $this->_getParam('order', 'asc');
            $contactFilter->orderCol = $orderCol;
            $contactFilter->orderType = $order;
        } else {
            // sorting by dateCreated by default
            $contactFilter->orderCol = 'dateCreated';
            $contactFilter->orderType = 'desc';
        }

        // Passign order data to the view
        $this->view->orderCol = $contactFilter->orderCol;
        $this->view->order = $contactFilter->orderType;

        $contactFilter->limit = $this->_getParam('perPage', Zend_Paginator::getDefaultItemCountPerPage());
        $offset = ($page - 1) * $contactFilter->limit;
        $contactFilter->offset = $offset;
        $contactFilter->total = true;

        try {
            $readResult = $this->_contactAdapter->contactRead($contactFilter);
        } catch (Exception $e) {
            Dm_Log::Error('contacts API error' . ': ' . $e->getMessage());
            $this->_helper->FlashMessenger->addMessage(
                array('error' => ucfirst($this->view->translate('An error was occured')))
            );
        }

        // current URL parameters
        $currentUrlOptions = $this->_helper->urlParameters();
        $this->view->currentUrlOptions = $currentUrlOptions;
        if (isset($readResult)) {
            $paginator = Zend_Paginator::factory($readResult, 'ObjectList');
            $paginator->setCurrentPageNumber($page);
            $paginator->setDefaultItemCountPerPage($contactFilter->limit);
        } else {
            $paginator = null;
        }
        /* @var $paginator Zend_Paginator */
        $this->view->paginator = $paginator;
        $this->view->listId = $id;

        /**
         * Lignes mises en commentaire FIX#15218
         * Ces lignes ont été ajoutées pour la prise en compte du typage date des champs variables
         * pas encore disponible, mais elles génèrent une régression sur les pop-up de confirmation
         * @TODO : fix le problème des popup pour permttre les filtres ar date
         *    $this->view->jQuery()->setLocalPath('/scripts/jquery/jquery-1.8.2.min.js');
         *    $this->view->jQuery()->setUiVersion('1.9.2');
         *    $this->view->jQuery()->setUiLocalPath('/scripts/jquery/jquery-ui-1.9.2.custom.min.js');
         *    $this->view->headScript()->appendFile('/scripts/bootstrap-daterangepicker/js/moment.min.js');
         *    $this->view->headScript()->appendFile('/scripts/bootstrap-datetimepicker/js/bootstrap-datetimepicker.js');
         *    $this->view->headScript()
         *         ->appendFile(
         *      '/scripts/angularjs/lib/eonasdan-bootstrap-datetimepicker/src/js/locales/bootstrap-datetimepicker.fr.js'
         *    );
         *    $this->view->headLink()->
         *                 appendStylesheet('/scripts/bootstrap-datetimepicker/css/bootstrap-datetimepicker.css');
         **/

        $this->view->headScript()->appendFile('/scripts/marketeo-contact.js?' . SCRIPT_VERSION_JS);
    }

    /**
     * Traitement des filtres sur les listes de contact :
     * - processing des params (filters) recu pour completer le Filter API
     * - ajout dans la vue des données necessaires a l'affichage des filtres
     *
     * @param Mk_Contacts_Contact_Filter &$listFilter le filtre à compléter
     *
     * @return void
     */
    protected function _initFilter(Mk_Contacts_Contact_Filter &$listFilter)
    {
        // Get all filters
        $filters = $this->_getParam('filters');

        // Reset filters
        $reset = $this->_getParam('filters-reset', 'false');
        if ($reset != 'false') {
            $filters = null;
            $this->_session->filters = $filters;
        }

        if (!isset($filters)) {
            // get filters criterias from session
            if (!empty($this->_session->filters)) {
                $filters = $this->_session->filters;
            }
        }

        if (!isset($filters)) {
            // filters init
            $this->view->filters = array(
                'availaible' => Service_Contact::GetColumnsWithDetail(),
                'operatorOr' => 1,
                'existing' => array(),
                'filtertype' => $filters['filtertype']
            );
        } else {
            // keep the filters criterias in session
            $this->_session->filters = $filters;

            $this->view->filters = array(
                'availaible' => Service_Contact::GetColumnsWithDetail(),
                'operatorOr' => (array_key_exists('operatorOr', $filters) ? $filters['operatorOr'] : NULL),
                'existing' => array(),
                'filtertype' => $filters['filtertype']
            );

            // filter for API call
            if ($filters['filtertype'] == 1) {
                if (!is_null($filters['optout'][0]['key']) && !empty($filters['optout'][0]['value'])) {
                    $this->view->filters['existing']['optout'] = array(
                        'key' => $filters['optout'][0]['key'],
                        'operation' => 'MEDIA-OPTOUT',
                        'value' => $filters['optout'][0]['value']
                    );
                    $listFilter->filters[] = array(
                        'key' => $filters['optout'][0]['key'],
                        'operation' => 'MEDIA-OPTOUT',
                        'value' => $filters['optout'][0]['value']
                    );
                }
            } else {
                $listFilter->filterOr = $filters['operatorOr'];
                foreach ((array)$filters['criteria'] as $conf) {
                    switch ($conf['operation']) {
                        case 'LOWER':
                        case 'TOP':
                        case 'LIKE':
                        case '=':
                        case '<>':
                            if ((!is_null($conf['value']) && !empty($conf['value']))) {
                                $this->view->filters['existing'][] = array(
                                    'key' => $conf['key'],
                                    'operation' => $conf['operation'],
                                    'value' => $conf['value']
                                );
                                $listFilter->filters[] = array(
                                    'key' => $conf['key'],
                                    'operation' => $conf['operation'],
                                    'value' => $conf['value']
                                );
                            }
                            break;
                        default:
                            $this->view->filters['existing'][] = array(
                                'key' => $conf['key'],
                                'operation' => $conf['operation'],
                                'value' => $conf['value']
                            );
                            $listFilter->filters[] = array(
                                'key' => $conf['key'],
                                'operation' => $conf['operation'],
                                'value' => $conf['value']
                            );
                    }
                }
            }
        }
    }

    /**
     * Action ajax permettant de rennomer une liste
     * Parametre attendus :
     * - name : nouveau nom de la liste
     *
     * Retourne :
     * - flux JSON {status=>[0|1], message=>TEXT}
     *
     * @return void
     */
    public function listRenameAction()
    {
        try {
            $name = $this->_getParam('name', ucfirst($this->view->translate('new list')));
            $id = $this->_getParam('id');
            if (is_numeric($id)) {
                if (!$this->_contactListAdapter->listUpdate($id, $name)) {
                    $result = array(
                        'status' => false,
                        'message' => ucfirst($this->view->translate('cannot update the list name'))
                    );
                } else {
                    $result = array(
                        'status' => true,
                        'message' => ""
                    );
                }
            } else {
                $result = array(
                    'status' => false,
                    'message' => ucfirst($this->view->translate('cannot update the list name'))
                );
            }
        } catch (Exception $e) {
            Dm_Log::Error(__METHOD__ . $e->getTraceAsString());
            $result = array(
                'status' => false,
                'message' => $e->getMessage()
            );
        }

        $this->_helper->json->sendJson($result);
    }

    /**
     * Creation d'un nouveau contact
     * Parametre attendus :
     * - id : int (optionnel) identifiant de la liste dans laquelle associer le contact
     *
     * @return void
     */
    public function addAction()
    {
        $this->_redirect($this->view->href('contact-edit', array('listId' => $this->_getParam('listId'))));
    }

    /**
     * Edition d'un contact existant
     * Parametre attendus :
     * - id : int identifiant du contact
     * - chaque champs de la fiche contact nommée
     *
     * @return void
     */
    public function editAction()
    {
        $this->view->jQuery()->setLocalPath('/scripts/jquery/jquery-1.8.2.min.js');
        $this->view->jQuery()->setUiVersion('1.9.2');
        $this->view->jQuery()->setUiLocalPath('/scripts/jquery/jquery-ui-1.9.2.custom.min.js');
        $this->view->headScript()->appendFile('/scripts/bootstrap-daterangepicker/js/moment.min.js');

        $this->view->headScript()->appendFile('/scripts/bootstrap-datetimepicker/js/bootstrap-datetimepicker.js');
        $this->view->headScript()
                   ->appendFile(
                       '/scripts/angularjs/lib/eonasdan-bootstrap-datetimepicker/'
                       .'src/js/locales/bootstrap-datetimepicker.fr.js'
                   );
        $this->view->headLink()->appendStylesheet('/scripts/bootstrap-datetimepicker/css/bootstrap-datetimepicker.css');

        $this->view->headScript()->appendFile('/scripts/angularjs/tools/pickerdatetimerange/pickerdatetimerange.js');

        $id = $this->_getParam('id');
        $listId = $this->_getParam('listId');
        $this->view->listId = $listId;
        $this->view->showNextButton = false;

        if (!empty($id)) {
            $filter = new Mk_Contacts_Contact_Filter($id);
            // PATCH MIGRATION CONTACT [START]
            $config = Dm_Config::GetConfig('mk', 'library');
            $factoryConfig = Service_Api_Config::overload($config);
            if (isset($factoryConfig['contact']) && isset($factoryConfig['contact']['adapter']) &&
                $factoryConfig['contact']['adapter'] ==
                'baseo'
            ) {
                // PATCH MIGRATION CONTACT [END]
                $filter->properties = array(
                    'DEFAULT',
                    'civility',
                    'userId',
                    'civility',
                    'firstName',
                    'lastName',
                    'fax',
                    'address1',
                    'address2',
                    'zipcode',
                    'city',
                    'state',
                    'country',
                    'birthDate',
                    'company',
                    'reference',
                    'voiceOptout',
                    'field01',
                    'field02',
                    'field03',
                    'field04',
                    'field05',
                    'field06',
                    'field07',
                    'field08',
                    'field09',
                    'field10',
                    'field11',
                    'field12',
                    'field13',
                    'field14',
                    'field15',
                    'dateCreated',
                );
                // PATCH MIGRATION CONTACT [START]
            }
            // PATCH MIGRATION CONTACT [END]
            $readResult = $this->_contactAdapter->contactRead($filter);
            if ($readResult->size != 1) {
                throw new Exception('Contact not found');
            }
            $contact = new Mk_Contacts_Contact($readResult->list[0]);
        } else {
            $contact = new Mk_Contacts_Contact();
            $this->view->showNextButton = true;
        }
        // Récuperation des types des champs du contrat,
        $contactApi = Dm_Session::GetEntry(Dm_Session::CONNECTED_USER_CONTRACT);
        $contractId = $contactApi->id;
        $adapter = Mk_Factory::GetContactAdapter();
        $fieldsWithDetails = $adapter->GetRenamedFieldsWithDetails($contractId);
        // transformation en tableau 'fieldxx' -> type
        $fieldsTypes = new stdClass;
        foreach ($fieldsWithDetails->list as $key => $value) {
            $fieldsTypes->$key = $value['type'];
        }
        $this->view->fieldsTypes = $fieldsTypes;

        /* @var $contact Mk_Contacts_Contact */
        $form = new Frontoffice_Form_Contact(array('contact' => $contact));

        // Traitement de l'eventuel MAJ
        if ($this->getRequest()->isPost()) {
            if ($form->isValid($this->_getAllParams())) {
                try {
                    $postData = $this->_request->getPost();
                    // On prend l'object de base, et on y ajoute les valeurs différentes du formulaire
                    // On y supprime les informations n'ayant pas été modifiées
                    foreach ($contact as $name => $value) {
                        if (array_key_exists($name, $postData) && $value != $postData[$name]) {
                            $contact->$name = $postData[$name];
                        } else if ($name != 'id') {
                            unset($contact->$name);
                        }
                    }
                    if ($contact->save()) {
                        if (empty($id)) {
                            $msg = "contact added";
                            if (isset($listId)) {
                                Mk_Contacts_ContactList::ContactsAddToList($listId, array($contact->id));
                            }
                        } else {
                            $msg = "contact updated";
                        }
                        $this->_helper->FlashMessenger->addMessage(
                            array('success' => ucfirst($this->view->translate($msg)))
                        );

                        // Redirect vers adding contact page when "Valider et suivant" button is clicked
                        $submission = $this->_getParam('submit');
                        $submissionAndNext = $this->_getParam('submitAndNext');
                        if ($submission === null && $submissionAndNext !== null) {
                            $this->_redirect($this->view->href('contact-edit', array('listId' => $listId)));
                        } else {
                            $this->_redirect($this->view->href('contact-list-edit', array('id' => $listId)));
                        }
                    } else {
                        $this->_helper->FlashMessenger->addMessage(
                            array('error' => ucfirst($this->view->translate('Cannot save contact')))
                        );
                    }
                } catch (Exception $e) {
                    $message = $this->formatErrorMessage($e->getMessage());
                    $this->_helper->FlashMessenger->addMessage(
                        array('error' => ucfirst($message))
                    );
                }
            } else {
                $entries = $form->getMessages();
                foreach ($entries as $input => $msgs) {
                    foreach ($msgs as $message) {
                        $this->_helper->FlashMessenger->addMessage(
                            array('error' => $message . ' [' . ucfirst($this->view->translate($input)) . ']')
                        );
                    }
                }
            }
        }
        if (isset($contact->dateCreated)) {
            $dateCreated = DateTime::createFromFormat('Y-m-d H:i:s', $contact->dateCreated);
            $this->view->dateCreated = $dateCreated->format('d/m/Y');
        }
        $this->view->form = $form;
    }

    /**
     * retourne un message d'erreur traduit à partir du message fourni par baseo :
     * Parameter email (XX) is not valid mobile (6665) is not valid (dialing code: 33)
     *
     * @param string $message un message d'erreur d'édition de contact retourné par baseo
     *
     * @return string
     */
    private function formatErrorMessage($message)
    {
        $fields = null;
        if (preg_match('/barcode_/i', $message)) {
            // Récuperation des noms des champs persos du contrat
            $contactApi = Dm_Session::GetEntry(Dm_Session::CONNECTED_USER_CONTRACT);
            $contractId = $contactApi->id;
            $adapter = Mk_Factory::GetContactAdapter();
            $fields = $adapter->GetRenamedFieldsWithDetails($contractId);
        }

        if (preg_match('/^Parameter/i', $message) === 1) {
            // suppression du paramètre
            $message_errors = preg_replace('/^Parameter[[:space:]]/', "", $message);

            // récupération de toutes les lignes
            $errors = array_merge(
                array(0 => ucfirst($this->view->translate('Parameter'))), preg_split('/\n/', $message_errors)
            );

            $errorsStrings = array();
            foreach ($errors as $string) {
                if (strlen($string) > 0) {
                    // récupérer la valeur du champ en erreur entre parenthèses
                    $match = array();
                    preg_match('/\((.*)\)(.{3})/', $string, $match);
                    if (isset($match[1]) && isset($match[2])) {
                        $value = $match[1];
                        $suffix = $match[2];
                    } else {
                        $value = null;
                        $suffix = "";
                    }

                    if ($value !== null) {
                        $string = preg_replace('/\([^\)]*\)' . $suffix . '/', '(%s)' . $suffix, $string);
                        // test sur le préfixe international pour le mobile
                        preg_match('/dialing code: ([0-9]*)\)/', $string, $match);
                        if (isset($match[1])) {
                            $code = $match[1];
                            $string = preg_replace(
                                '/dialing code: ' . $match[1] . '\)/', 'dialing code: %s)', $string);
                        }
                    }

                    $alias = null;
                    if (preg_match('/^(field[0-9]{2})\sbarcode_/i', $string, $match)) {
                        $string = 'field %s (%s) is not valid';
                        $alias = $fields->list[$match[1]]['alias'];
                    }

                    // traduire avec la valeur du champ et tout.
                    if (isset($alias)) {
                        $errorsStrings[] .= ucfirst($this->view->translate($string, array($alias, $value)));
                    } elseif (isset($code)) {
                        $errorsStrings[] .= ucfirst($this->view->translate($string, array($value, $code)));
                    } else {
                        $errorsStrings[] .= ucfirst($this->view->translate($string, $value));
                    }
                }
            }
            $message = implode('<br />', $errorsStrings);
        } else {
            $message = $this->view->translate($message);
        }
        return $message;
    }

    /**
     * Action qui genere un fichier CSV contenant une ligne d'entête
     * correspondant au champs actifs de la compagnie courante
     * L'entête est constitué des alias
     *
     * Aucun paramètre particulier n'est attendu
     *
     * @return void
     */
    public function sampleFileAction()
    {
        /* Nous récupérons le helper CSV */
        $helperCsv = $this->_helper->getHelper('csv');
        /* nom du fichier d’export */
        $helperCsv->setTitle('Marketeo_Contacts.csv');
        $helperCsv->writeData(array(Service_Contact::GetColumns()));

        /* Finalement, nous demandons au helper d’envoyer tous les contacts au format CSV au navigateur */
        $helperCsv->sendCsv();
    }

    /**
     * Affiche le formuaire d'import de contacts
     * Si soumis en POST : Importe un fichier de contact déjà uploader, en le convertissant en UTF-8 au préalable
     * Parametres attendus du formulaire:
     * - fileId : int identifiant du fichier (préalablement uploadé)
     * - listName (OPTIONNEL) string nom de la liste de contact
     *
     * @return void
     *
     * @throws Zend_Exception
     */
    public function importAction()
    {
        $fileInput = new Zend_Form_Element_File('unused');
        $configUpload = Zend_Registry::get('upload');
        $maxSizeApp = (int)$configUpload['maxSize'];
        $maxSizeServer = $fileInput->getMaxFileSize();
        $maxSize = $maxSizeApp < $maxSizeServer ? $maxSizeApp : $maxSizeServer;

        $nameInput = new Zend_Form_Element_Text('listName');

        $nameInput->setOptions(array("class" => "form-control"));

        $customHeadersIndexInput = new Zend_Form_Element_Text('customHeadersIndex');

        $form = new Zend_Form();
        $form->setAction($this->view->href('contact-import'));
        $form->setElements(array($nameInput, $customHeadersIndexInput));

        $request = $this->getRequest();
        if ($request->isPost()) {
            $formData = $request->getPost();
            $importedFilePath = $formData['filePath'];

            if (!empty($importedFilePath) && $form->isValid($formData)) {
                $listname = $form->getElement('listName')->getValue();
                $customHeadersIndex = $form->getElement('customHeadersIndex')->getValue();
                try {
                    Dm_Session::SetEntry('imported-file', $importedFilePath);
                    Dm_Session::SetEntry('list-name', $listname);
                    Dm_Session::SetEntry('customHeadersIndex', $customHeadersIndex);

                    $uploadView = ((Dm_Config::GetConfig('mk', 'library.contact.custoUpload')) ? 'uploaded-custo' :
                        'uploaded');

                    $this->_helper->redirector(
                        $uploadView, $this->getRequest()->getControllerName(), $this->getRequest()->getModuleName()
                    );
                } catch (Exception $e) {
                    Dm_Log::Error(__METHOD__ . $e->getTraceAsString());
                    if (APPLICATION_ENV == 'production') {
                        $this->_helper->FlashMessenger->addMessage(
                            array('error' => $this->view->translate('An error has occurred'))
                        );
                    } else {
                        $this->_helper->FlashMessenger->addMessage(
                            array('error' => $e->getMessage())
                        );
                    }
                    $this->_redirect($_SERVER['HTTP_REFERER']);
                }
            } else {
                Dm_Log::Debug('errors ' . print_r($form->getMessages(), true));
                $form->populate($formData);
            }
        }

        $this->view->columns = Service_Contact::GetColumns();
        $this->view->maxSize = $maxSize;
        $this->view->form = $form;
        $this->_helper->layout->disableLayout();
    }

    /**
     * Importe un fichier de contacts sur le serveur en mode AJAX
     * Parametres attendus:
     * - contactListFile : FILE le fichier de contacts
     *
     * @return void
     */
    public function uploadTmpFileAction()
    {
        if (array_key_exists('contactListFile', $_FILES)) {
            $contactListFile = $_FILES['contactListFile'];
            if (UPLOAD_ERR_OK == $contactListFile['error']) {
                $fileNameUploaded = $contactListFile['name'];
                $fileName = Dm_Utils::StripAccents($fileNameUploaded);

                $destination = Dm_Config::GetPath('tmp') . Dm_Session::getConnectedUser()->id . '/';
                if (!is_dir($destination)) {
                    @mkdir($destination, 0777, true);
                }
                $fullFilePath = $destination . $fileName;
                if (move_uploaded_file($contactListFile['tmp_name'], $fullFilePath)) {
                    $result = array(
                        "success" => true,
                        "message" => "Upload done",
                        "filePath" => "$fullFilePath",
                    );
                } else {
                    $result = array("success" => false, "message" => "Cannot move file");
                }
            } else {
                $result = array("success" => false,
                                "message" => "Cannot upload the file : " . $contactListFile['error']);
            }
        } else {
            $result = array("success" => false, "message" => "File not uploaded, not found on the server");
        }

        $this->_helper->layout->disableLayout();
        $this->view->json = Zend_Json::encode($result);
        $this->view->xhr = array_key_exists('HTTP_X_REQUESTED_WITH', $_SERVER) &&
            $_SERVER['HTTP_X_REQUESTED_WITH'] == 'XMLHttpRequest';
        $contentType = !$this->view->xhr ? 'text/html' : 'application/json';
        $this->getResponse()->setHeader('Content-Type', $contentType . ';  charset=utf-8', true);
    }

    /**
     * Début de l'import une fois le fichier uploadé et convertit
     *
     * Enchainement des fonctions:
     * 1 -> importAction()
     * 2 -> uploadedAction()
     * 3 -> listcreateAction()
     * 4 -> importingAction()
     * 5 -> importedAction()
     *
     * @return void
     */
    public function uploadedAction()
    {
        set_time_limit(900);
        $importedFilePath = Dm_Session::GetEntry('imported-file');
        $listname = Dm_Session::GetEntry('list-name');
        try {
            $writer = Mk_Factory::GetContactListWriter();
            $writer->init($importedFilePath, $listname, Service_Contact::GetColumns(),
                          Service_Contact::GetCsvSeparator());
            $this->view->backUrl = $this->view->href('contact-list');
            $this->view->layout()->title = 'Import a contact list';
            $this->view->checkHeaders = $writer->isHeaderColumnsMatch();
            $this->view->dataPreview = $writer->getDatas();
            $this->view->headers = $writer->getHeaderColumns();
            $this->view->diffCols = $writer->getDiffColumns();
            Dm_Session::SetEntry('writer', $writer);
        } catch (Exception $e) {
            $this->_helper->FlashMessenger->addMessage(
                array('error' => ucfirst($this->view->translate($e->getMessage())))
            );
            $this->_helper->redirector('index', $this->getRequest()->getControllerName(),
                                       $this->getRequest()->getModuleName());
        }
    }

    /**
     * Début de l'import une fois le fichier uploadé et convertit
     *
     * Enchainement des fonctions:
     * 1 -> importAction()
     * 2 -> uploadedAction() / uploadedCustoAction()
     * 3 -> listcreateAction()
     * 4-> importingAction()
     * 5-> importedAction()
     *
     * @return void
     */
    public function uploadedCustoAction()
    {
        set_time_limit(900);
        $importedFilePath = Dm_Session::GetEntry('imported-file');
        $listname = Dm_Session::GetEntry('list-name');
        $customHeadersIndex = (Dm_Session::GetEntry('customHeadersIndex') == '') ?
            -1 :
            Dm_Session::GetEntry('customHeadersIndex');

        try {
            // Lecture des entete déjà enregistrés pour l'utilisateur
            $custoHeadersParam =
                Dm_Session::GetEntry(Dm_Session::CONNECTED_USER)->getCustomParameter('customizingHeaders');

//            // Stockage des entetes personnalisés
//            $custoHeadersParamValue = array();
//
//            // Le paramètre existe
//            if (!is_null($custoHeadersParam)) {
//                $custoHeadersParamValue = json_decode($custoHeadersParam->value);
//                if ((is_null($custoHeadersParamValue)) || (!is_array($custoHeadersParamValue))) {
//                    $custoHeadersParamValue = array();
//                }
//            }

            $writer = Mk_Factory::GetContactListWriter();
            $writer->init($importedFilePath, $listname, Service_Contact::GetColumns(),
                          Service_Contact::GetCsvSeparator());

            //$this->view->custoHeaders = $custoHeadersParamValue;

            $this->view->backUrl = $this->view->href('contact-list');

            $this->view->layout()->title = 'Import a contact list';
            $this->view->checkHeaders = $writer->isHeaderColumnsMatch();
            $this->view->dataPreview = $writer->getDatas();
            //$this->view->headers = $writer->getCustoHeaderColumns();

            $this->view->headersCount = $writer->getColumnsCount();
            $this->view->headersList = $writer->getHeaderColumns();

            $this->view->diffCols = $writer->getDiffColumns();
            Dm_Session::SetEntry('writer', $writer);

            // On met les fonctions suivantes après le set du writer car nécessaire pour getCustomHeaders
            // On va chercher la liste des configs enregistrées
            $this->view->customHeadersList = $this->_getCustomHeadersList();

            // On va sélectionner la meilleur config enregistrée
            $customHeaders = $this->_getCustomHeaders($customHeadersIndex);

            if ($customHeaders['matchAll'] && $customHeadersIndex > -1) {
                // On est en mode sélection de la config d'import dès le début,
                // et si on a tout matché, on pase directement à l'import
                Dm_Session::SetEntry('lineIgnore', $customHeaders['lineIgnore']);
                Dm_Session::SetEntry('headers', $customHeaders['headers']);

                $this->_helper->redirector('listcreate',
                                           $this->getRequest()->getControllerName(),
                                           $this->getRequest()->getModuleName());
            } else {
                // On passe uniquement l'entrée des champs pour la non-régrésssion
                $this->view->customHeadersSelected = $customHeaders['index'];
                $this->view->customHeadersLineIgnore = $customHeaders['lineIgnore'];
                $this->view->customHeaders = $customHeaders['headers'];
            }
        } catch (Exception $e) {
            $this->_helper->FlashMessenger->addMessage(
                array('error' => ucfirst($this->view->translate($e->getMessage())))
            );
            $this->_helper->redirector('index', $this->getRequest()->getControllerName(),
                                       $this->getRequest()->getModuleName());
        }
    }

    /**
     * L'action permettant de créer la liste de contact
     *
     * @return void
     */
    public function listcreateAction()
    {
        $this->view->layout()->title = 'Importing...';

        $writer = Dm_Session::GetEntry('writer');
        //$writer->setImportFirstLine($this->_getParam('importFirstLine', 'false') == 'false');

        // Modification des entêtes
        if (Dm_Config::GetConfig('mk', 'library.contact.custoUpload')) {
            // Sauvegarde des données d'import de l'utilisateur
            $customHeadersParam =
                Dm_Session::GetEntry(Dm_Session::CONNECTED_USER)->getCustomParameter('customizingHeaders');

            // On regarde si la variable session est rempli pour le cas ou on passe direct sans la verif
            $headers = Dm_Session::GetEntry('headers');
            $headers = (empty($headers)) ? $this->_getParam('headers', array()) : $headers;

            $lineIgnore = Dm_Session::GetEntry('lineIgnore');
            $lineIgnore = (empty($lineIgnore)) ? $this->_getParam('customLineIgnore', 0) : $lineIgnore;

            // On vide les variables session car sinon tout le temps rempli et problème
            Dm_Session::SetEntry('lineIgnore', '');
            Dm_Session::SetEntry('headers', '');

            // On forme le paramètre à passer à setCustomHeaders
            $param = array(
                'name' => self::IMPORT_CUSTOM_DEFAULT_NAME,
                'default' => 1,
                'lineIgnore' => $lineIgnore,
                'headers' => $headers,
            );

            // On prepare l'enregistrement
            $customHeadersParam->value = json_encode($this->_setCustomHeaders($param));

            // On enregistre
            $user = Dm_Session::GetEntry(Dm_Session::CONNECTED_USER);
            $user->setCustomParameter('customizingHeaders', $customHeadersParam);
            $_userAdapter = Dm_Session::GetEntry(Dm_Session::ADMIN_MK_USER_ADAPTER);
            $_userAdapter->updateParameters($user->id, array($customHeadersParam));

            // Supression des n premières lignes d'entete originale au fichier d'origine
            $writer->deleteCsvFileNFirstLine($lineIgnore);
            // Ajout de la ligne d'entete au fichier d'origine
            $writer->addHeaders($headers);
            // Suppression de colonnes
            $writer->deleteColumns($headers);
            // Zip du fichier
            $writer->zipFile();
        }

        $writer->createList();
        Dm_Session::SetEntry('writer', $writer);

        /**
         * Patch for asynchronous contact import
         *
         * @todo To be removed once the Baseo migration is complete
         */
        $this->view->contactApi = Dm_Session::GetEntry(Dm_Session::CONNECTED_USER_CONTRACT)
                                            ->getCustomParameter('contactApi')->value;
    }

    /**
     * L'action permettant de sauvegarder une config
     *
     * @return void
     */
    public function ajaxSaveCustomHeadersAction()
    {
        $jsonResult = array('status' => true);

        // On forme le paramètre à passer à setCustomHeaders
        $param = array(
            'name' => $this->_getParam('customName', ''),
            'default' => 0,
            'lineIgnore' => $this->_getParam('lineIgnore', 0),
            'headers' => $this->_getParam('headers', array()),
        );

        if (empty($param['name']) || empty($param['headers'])) {
            $jsonResult['status'] = false;
            $jsonResult['message'] = ucfirst($this->view->translate('automatic.cannot save import config'));
        } else {
            // On récupère l'objet
            $customHeadersParam =
                Dm_Session::GetEntry(Dm_Session::CONNECTED_USER)->getCustomParameter('customizingHeaders');

            // On prepare l'enregistrement
            $customHeadersParam->value = json_encode($this->_setCustomHeaders($param));

            // On enregistre
            $user = Dm_Session::GetEntry(Dm_Session::CONNECTED_USER);
            $user->setCustomParameter('customizingHeadersList', $customHeadersParam);
            $_userAdapter = Dm_Session::GetEntry(Dm_Session::ADMIN_MK_USER_ADAPTER);

            if ($_userAdapter->updateParameters($user->id, array($customHeadersParam))) {
                $jsonResult['message'] = 'enregistrement ok';

                // On va chercher la liste des configs enregistrées
                $jsonResult['customList'] = $this->_getCustomHeadersList();
            } else {
                $jsonResult['status'] = false;
                $jsonResult['message'] = ucfirst($this->view->translate('automatic.cannot save import config'));
            }
        }

        $this->_helper->json->sendJson($jsonResult);
    }

    /**
     * L'action permettant de supprimer une config
     *
     * @return void
     */
    public function ajaxDeleteCustomHeadersAction()
    {
        $jsonResult = array('status' => true);
        $indexDelete = $this->_getParam('index', '');

        if (!isset($indexDelete) && $indexDelete < 0) {
            $jsonResult['status'] = false;
            $jsonResult['message'] = ucfirst($this->view->translate('automatic.cannot delete import config'));
        } else {
            // On récupère l'objet
            $customHeadersParam =
                Dm_Session::GetEntry(Dm_Session::CONNECTED_USER)->getCustomParameter('customizingHeaders');

            // On prepare l'enregistrement
            $customHeadersParam->value = json_encode($this->_deleteCustomHeaders($indexDelete));

            // On enregistre
            $user = Dm_Session::GetEntry(Dm_Session::CONNECTED_USER);
            $user->setCustomParameter('customizingHeadersList', $customHeadersParam);
            $_userAdapter = Dm_Session::GetEntry(Dm_Session::ADMIN_MK_USER_ADAPTER);

            if ($_userAdapter->update($user)) {
                $jsonResult['message'] = 'supression ok';

                // On va chercher la liste des configs enregistrées
                $jsonResult['customList'] = $this->_getCustomHeadersList();
            } else {
                $jsonResult['status'] = false;
                $jsonResult['message'] = ucfirst($this->view->translate('automatic.cannot delete import config'));
            }
        }

        $this->_helper->json->sendJson($jsonResult);
    }

    /**
     * L'action permettant de récupérer une config sélectionnée
     *
     * @return void
     */
    public function ajaxGetCustomHeadersAction()
    {
        $jsonResult = array('status' => true);

        $index = $this->_getParam('index', '');
        if ($index == '') {
            $jsonResult['status'] = false;
            $jsonResult['message'] = ucfirst($this->view->translate('automatic.cannot get import config'));
        } else {
            // On va chercher la liste des configs enregistrées
            $jsonResult['customHeaders'] = $this->_getCustomHeaders($index);
        }

        $this->_helper->json->sendJson($jsonResult);
    }

    /**
     * Récupère la liste des jeux d'entetes enregistrés
     *
     * @return void
     */
    protected function _getCustomHeadersList()
    {
        Dm_Log::Info("Récupération de liste des configurations d'entetes");

        // Lecture des entete déjà enregistrés pour l'utilisateur
        $customHeadersLists = json_decode(Dm_Session::GetEntry(Dm_Session::CONNECTED_USER)
                                                    ->getCustomParameter('customizingHeaders')->value);

        $customHeadersList = array();

        // Si property_exists($customHeadersLists[0],'name') alors nouvelle version
        if (!is_null($customHeadersLists) &&
            !empty($customHeadersLists) &&
            is_array($customHeadersLists) &&
            property_exists($customHeadersLists[0], 'name')
        ) {

            // On parcourt la liste des configurations enregistrées
            foreach ($customHeadersLists as $index => $value) {
                if (!$value->default) {
                    $customHeadersList[$index] = $value->name;
                }
            }
        }

        return $customHeadersList;
    }

    /**
     * Récupère le meilleur jeu d'entêtes pour le fichier à importer
     *
     * @param int $customIndex numero de liste custom
     *
     * @return void
     */
    protected function _getCustomHeaders($customIndex = -1)
    {
        Dm_Log::Info("Récupération de la meilleure entete");

        // On récupère la ligne d'entête du fichier csv sous forme de tableau
        $writer = Dm_Session::GetEntry('writer');
        $lineHeaders = 1;
        $importFirstLine = $writer->readCsvFileNiemeLine($lineHeaders);
        $importHeaderCount = count($importFirstLine);

        // Lecture des entete déjà enregistrés pour l'utilisateur
        $customHeadersLists = json_decode(Dm_Session::GetEntry(Dm_Session::CONNECTED_USER)
                                                    ->getCustomParameter('customizingHeaders')->value);

        // On prépare les variables
        $customHeaders = array('index' => -1, 'lineIgnore' => 0, 'headers' => array(), 'matchAll' => false);
        $bestMatchesScore = 0;

        // Si on est sur une ancienne version, on récupère la liste
        if (!is_null($customHeadersLists) &&
            !empty($customHeadersLists) &&
            is_array($customHeadersLists)
        ) {

            // Si property_exists($customHeadersLists[0],'name') alors nouvelle version
            if (!property_exists($customHeadersLists[0], 'name')) {
                $customDefaultLineIgnore = 0;
                $customDefaultFields = $customHeadersLists;
                unset($customHeadersLists);
            }
        }

        // On ajoute la config de base
        if ($customIndex == -1) {
            // On va chercher les colonnes des SELECT
            $translatedColumns = $writer->getTranslatedColumns();
            foreach ($translatedColumns as $value) {
                $headers[strtoupper(Dm_Utils::StripAccents($value))] = strtoupper(Dm_Utils::StripAccents($value));
            }

            // On ajoute cette config dans la liste
            $customHeadersLists[] = json_decode(json_encode(array(
                                                                'name' => 'colums',
                                                                'default' => 0,
                                                                'lineIgnore' => 1,
                                                                'fields' => $headers
                                                            )));
        }

        if (!is_null($customHeadersLists) &&
            !empty($customHeadersLists) &&
            is_array($customHeadersLists)
        ) {

            // Si on a fourni un numéro de liste custom, on va chercher celle-ci
            if (isset($customIndex) && $customIndex >= 0) {
                $customHeadersListsTmp = $customHeadersLists[$customIndex];
                if (!empty($customHeadersListsTmp)) {
                    $customHeadersLists = array();
                    $customHeadersLists[] = $customHeadersListsTmp;
                }
            }

            // On parcourt la liste des configurations enregistrées
            foreach ($customHeadersLists as $index => $value) {
                if ($value->default) {
                    $customDefaultLineIgnore = $value->lineIgnore;
                    $customDefaultFields = json_decode(json_encode($value->fields), true);
                } else {
                    // Si on n'ignore pas le meme nombre de ligne, on va recharger les entetes
                    if ($value->lineIgnore != $lineHeaders) {
                        $lineHeaders = $value->lineIgnore;
                        $importFirstLine = $writer->readCsvFileNiemeLine($lineHeaders);
                        $importHeaderCount = count($importFirstLine);
                    }
                    // On fait ça pour supprimer la structure stdCLass
                    $headers = json_decode(json_encode($value->fields), true);

                    // On compare avec les champs en entete
                    $checkHeaders = $this->_checkHeaders($importFirstLine, $headers);

                    // Si on a trouve plus de correspondance, on choisit cet import
                    if ($checkHeaders['matchesScore'] > $bestMatchesScore) {
                        $bestMatchesScore = $checkHeaders['matchesScore'];
                        $customHeaders['index'] = (isset($customIndex) && $customIndex >= 0) ? $customIndex : $index;
                        $customHeaders['lineIgnore'] = $value->lineIgnore;
                        $customHeaders['headers'] = $checkHeaders['headers'];
                    }

                    // Si on a matché tous les champs, on stoppe
                    if ($bestMatchesScore == $importHeaderCount) {
                        $customHeaders['matchAll'] = true;
                        break;
                    }
                }
            }

            // Si on a pas tout matche, et que l'on a une config par défaut
            // ayant plus de colonnes qu'avec les configs persos, on la prends
            if (isset($customDefaultFields) && $bestMatchesScore == 0) {
                $customHeaders['index'] = -1;
                $customHeaders['lineIgnore'] = $customDefaultLineIgnore;
                $customHeaders['headers'] = $customDefaultFields;
            }
        }
        return $customHeaders;
    }

    /**
     * Vérifie les headers
     *
     * @param string[] $headers        entêtes
     * @param string[] $headersCompare entêtes de comparaison
     *
     * @return array
     */
    protected function _checkHeaders($headers, $headersCompare)
    {
        $checkHeaders = array(
            'matchesScore' => 0,
            'headers' => array(),
        );

        // On compare avec les champs en entete
        foreach ($headers as $header) {
            $header = (empty($header)) ? '_empty_' : strtoupper(Dm_Utils::StripAccents(trim($header)));
            if (array_key_exists($header, $headersCompare)) {
                $checkHeaders['matchesScore']++;
                $checkHeaders['headers'][] = $headersCompare[$header];
            } else {
                $checkHeaders['headers'][] = '';
            }
        }
        return $checkHeaders;
    }

    /**
     * Mets en forme la configuration des entetes
     *
     * @param array $param les paramètres
     *
     * @return void
     */
    protected function _setCustomHeaders($param)
    {
        // On enregistre le nom de cette import custom
        $customHeaders['name'] = $name = (isset($param['name']) && !empty($param['name'])) ?
            $param['name'] : 'Import config';
        $customHeaders['default'] = (isset($param['default']) && !empty($param['default'])) ?
            $param['default'] : 0;
        $customHeaders['lineIgnore'] = (isset($param['lineIgnore']) && !empty($param['lineIgnore'])) ?
            $param['lineIgnore'] : 0;
        $headers = $param['headers'];

        // On récupère la configuration existante
        $customHeadersLists = json_decode(Dm_Session::GetEntry(Dm_Session::CONNECTED_USER)
                                                    ->getCustomParameter('customizingHeaders')->value);

        // On récupère certaines infos
        $customHeadersIndex = 0;
        if (!is_array($customHeadersLists) || !property_exists($customHeadersLists[0], 'name')) {
            $customHeadersLists = array();
        } else {
            $customHeadersIndex = count($customHeadersLists);

            // On vérifie que l'entrée existe ou non
            foreach ($customHeadersLists as $index => $item) {
                if (strtoupper($item->name) == strtoupper(Dm_Utils::StripAccents(trim($name)))) {
                    $customHeadersIndex = $index;
                    break;
                }
            }
        }

        // On récupère la ligne d'entête du fichier csv sous forme de tableau
        if ($customHeaders['default']) {
            $customHeaders['fields'] = $headers;
        } else {
            $writer = Dm_Session::GetEntry('writer');
            $importFirstLine = $writer->readCsvFileNiemeLine($customHeaders['lineIgnore']);

            // On enregistre la corrélation entre les champs
            foreach ($importFirstLine as $headerIndex => $headerValue) {
                $customHeaders['fields'][strtoupper(Dm_Utils::StripAccents(trim($headerValue)))] =
                    strtoupper($headers[$headerIndex]);
            }
        }

        // On ajoute l'entrée
        $customHeadersLists[$customHeadersIndex] = $customHeaders;

        return $customHeadersLists;
    }

    /**
     * Supression de la configuration demandée
     *
     * @param int $index index du header custom
     *
     * @return void
     * $customHeadersParam->value = json_encode($writer->deleteCustomHeaders(2));
     */
    protected function _deleteCustomHeaders($index)
    {
        // On récupère la configuration existante
        $customHeadersLists = json_decode(Dm_Session::GetEntry(Dm_Session::CONNECTED_USER)
                                                    ->getCustomParameter('customizingHeaders')->value);

        // On enregistre le nom de cette import custom
        if (!empty($customHeadersLists) && isset($index) && $index >= 0) {
            // On supprime l'index passé, et on reorganise le tableau
            unset($customHeadersLists[$index]);
            $customHeadersLists = (is_array($customHeadersLists)) ? array_merge($customHeadersLists) : NULL;
        }

        return $customHeadersLists;
    }

    /**
     * L'action permettant générer une itération et afficher la progression de l'import
     *
     * @return void
     */
    public function importingAction()
    {
        $this->view->layout()->title = 'Importing...';
        $writer = Dm_Session::GetEntry('writer');

        /**
         * Patch for asynchronous contact import
         *
         * @todo To be removed once the Baseo migration is complete
         */
        /* @var $userContract Service_Object_Contract */
        $contactApi = Dm_Session::GetEntry(Dm_Session::CONNECTED_USER_CONTRACT)
                                ->getCustomParameter('contactApi')->value;
        $this->view->contactApi = $contactApi;

        Dm_Log::Debug('°°°°°°° IMPORTING');
        if ($writer->importing(Dm_Config::GetConfig('slbeo', 'import.contact'))) {
            Dm_Log::Debug('°°°°°°° 1');
            if ($contactApi == 'slbeo') {
                Dm_Log::Debug('°°°°°°° 1.1');
                $response = $this->_contactListAdapter->listCount(array($writer->getListId()));

                $this->view->nbContactsInList = $response[0];
                $this->view->nbLinesRead = $writer->getNbLinesRead();
                $this->view->nbContactsInvalid = $writer->getNbContactInvalid();
                $this->view->nbLineHeaders = $writer->getNbLineHeader();
            }
            Dm_Session::SetEntry('writer', $writer);
        } else {
            Dm_Log::Debug('°°°°°°° 2');
            if ($contactApi == 'slbeo') {
                Dm_Log::Debug('°°°°°°° 2.1');
                Dm_Session::SetEntry('writer', $writer);
                $this->_helper->redirector('imported', $this->getRequest()->getControllerName(),
                                           $this->getRequest()->getModuleName());
            } else {
                $this->_helper->redirector('import-done', $this->getRequest()->getControllerName(),
                                           $this->getRequest()->getModuleName());
            }
        }
    }

    /**
     * Displays the page for imported list
     *
     * @return void
     */
    public function importDoneAction()
    {

        Dm_Log::Debug('import done');
    }

    /**
     * L'action permettant d'afficher le résultat final de l'import
     *
     * @return void
     */
    public function importedAction()
    {
        $this->view->layout()->title = 'Importing finished';
        $writer = Dm_Session::GetEntry('writer');
        $response = $this->_contactListAdapter->listCount(array($writer->getListId()));

        $this->view->nbContactsInList = $response[0];
        $this->view->nbLinesRead = $writer->getNbLinesRead();
        $this->view->nbContactsInvalid = $writer->getNbContactInvalid();
        $this->view->nbLineHeaders = $writer->getNbLineHeader();
        $writer->cleanFiles();
        Dm_Session::removeEntry('writer');
    }

    /**
     * Géolocalisation : Récupère les information d'un contact pour les infos bulles
     *
     * @todo Stocker les contacts geo en session
     * et recuperer le contact dans la session
     *
     * @return void
     */
    public function getContactInfoBubbleAction()
    {
        $contactId = $this->getRequest()->getParam('id');

        if (is_null($contactId)) {
            throw new Zend_Exception("Missing parameter");
        }

        // Récupération des contacts de la liste demandée
        $contactFilter = new Mk_Contacts_Contact_Filter();
        $contactFilter->returnColumns = array('id', 'civility', 'firstName', 'lastName', 'address1', 'address2',
                                              'zipcode', 'city', 'email', 'phone', 'mobile');
        $contactFilter->count = 1; //définition de la limite de contact à afficher sur la map.
        $contactFilter->ids = array($contactId);
        $contacts = $this->_contactAdapter->contactRead($contactFilter);

        if ($contacts->size == 1) {
            $this->view->contact = $contacts->list['0'];
        }

        $this->_helper->layout->disableLayout();
    }

    /**
     * Géolocalisation : Chargement des contacts d'une liste de contacts pour la géolocalisation
     *
     * Parametres :
     * id - Identifiant de la liste de contacts
     *
     * @deprecated
     *
     * @return void
     */
    public function getContactsGeoAction()
    {
        // Récupération des contacts de la liste demandée
        $listFilter = new Mk_Contacts_Contact_Filter();

        // Récupération de la liste de contacts à afficher
        $contactListId = $this->getRequest()->getParam('id');
        if (!is_null($contactListId)) {
            $listFilter->listId = $contactListId;
        }

        $listFilter->geostatus = 1;
        $listFilter->returnColumns = array('id', 'civility', 'firstName', 'lastName', 'lat', 'lon');

        $contactList = $this->_contactAdapter->contactRead($listFilter);
        $contactsGeo = array();
        $contactGeo = new Service_Api_Object_ContactGeo();
        $cols = array_keys(get_object_vars($contactGeo));
        if ($contactList->size > 0) {
            foreach ($contactList->list as $contact) {
                $contactGeo = new Service_Api_Object_ContactGeo();
                foreach ($cols as $vars) {
                    $contactGeo->{$vars} = $contact->{$vars};
                }
                $contactsGeo[] = $contactGeo;
            }
        }
        $jsonData = array();
        $jsonData["data"] = $contactsGeo;
        $this->_helper->json->sendJson($jsonData);
    }

    /**
     * Géolocalisation : Créé une nouvelle liste à partir de contacts existant
     *
     * Parametres attendus :
     *  - listsName     string    Le nom de la liste
     *  - strContactIds integer[] Les ids de contacts à ajouter à la nouvelle liste
     *
     * @return void
     *
     */
    public function createListWithExistingContactsAction()
    {
        $ret = array();

        // Creation de la nouvelle liste
        $listName = $this->_getParam("listName");
        $listOut = $this->_contactListAdapter->listCreate($listName);

        // La liste est bien créée
        if ($listOut) {
            $ret[] = $listOut->id;
            Dm_Log::Debug(sprintf("List créée: %s", var_export($listOut, 1)));

            // Association avec les contacts
            $strContactIds = $this->_getParam('strContactIds');
            if ($strContactIds) {
                $contactIds = explode(';', $this->_getParam('strContactIds'));
                $this->_contactListAdapter->contactAddToList($listOut->id, $contactIds);
                Dm_Log::Debug(sprintf("Contacts associés : %s", var_export($strContactIds, 1)));
            }
        }
        $this->_helper->json->sendJson($ret);
    }

    /**
     * création de contacts à partir de paramètres.
     *
     * Paramètres attendus (get ou post) :
     * - campaignName           string          urlencoded string
     * - campaignId             int             campaign id
     * - stepId                 int             step id
     * - media                  string          media type (sms, email etc...)
     * - status                 string          status of contacts to filter
     *
     * @return void
     */
    public function createListFromStatsAction()
    {
        $campaignName = urldecode($this->_getParam('campaignName'));
        $campaignId = $this->_getParam('campaignId');
        $stepId = $this->_getParam('stepId');
        $mediaType = $this->_getParam('media');
        $status = ($this->_getParam('status')) ? $this->_getParam('status') : '';

        if ($campaignId && $stepId && $mediaType) {
            $listName = ($campaignName) ? "$campaignName - " : "";
            $listName .= "" . $this->view->translate($mediaType) . ' ' . $this->view->translate($status);

            // récupération des stats de l'étape
            $statFilter = new Service_Api_Filter_Stat();
            $statFilter->media = array($mediaType);
            $statFilter->stepId = array($stepId);
            $statFilter->status = array($status);
            $statFilter->restrictedAccess = !$this->view->hasAccess('contactManagement');
            $createdListId = Service_Contact::CreateContactListFromStatfilter2($statFilter, $listName);

            if ($createdListId > 0) {
                $this->_helper->FlashMessenger->addMessage(
                    array('info' => ucfirst($this->view->translate("list '%s' created", $listName)))
                );
            } else {
                // ERROR
                $this->_helper->FlashMessenger->addMessage(
                    array('error' => ucfirst($this->view->translate('error creating contacts list')))
                );
            }
        } else {
            // ERROR
            $this->_helper->FlashMessenger->addMessage(
                array('error' => ucfirst($this->view->translate('error creating contacts list, parameter missing')))
            );
        }

        $this->_redirect($this->view->href('contact-list'));
    }

    /**
     * Action permettant de gérer les Optout et Optin des contacts en masse
     * Parametre attendus :
     * - contacts (optionnel)    : liste des identifiants des contacts
     * - contactList (optionnel) : identifiant de la liste de contacts
     * - status                  : le status 'optin' ou 'optout'
     *
     * @return void
     */
    public function optAction()
    {
        // Pas de layout
        $this->_helper->layout()->disableLayout();
        $this->_helper->viewRenderer->setNoRender(true);

        // Suppression de toute la liste
        $complete = $this->_getParam('full', false);
        // Les ids de contacts à supprimer
        $contactsIds = $this->_getParam('ids', null);
        $ids = (!is_null($contactsIds) ? explode('-', $contactsIds) : array());
        // Optin ou optout
        $status = $this->_getParam('status', null);
        // Media
        $media = $this->_getParam('media', null);
        // Identifiant de la liste
        $listId = $this->_getParam('listId');
        Dm_Log::Debug('=====================================********************');
        Dm_Log::Debug('Complete: ' . ($complete ? 'true' : 'false'));
        if (count($ids) > 0 || $complete) {
            $listFilter = new Mk_Contacts_Contact_Filter();
            $this->_initFilter($listFilter);

            // Mise en optin / optout sur toute la liste ?
            if ($complete) {
                $listFilter->listId = array($listId);
                if (isset($listFilter->filters)) {
                    Dm_Log::Debug('Complex is set');
                    $ids = $this->_contactAdapter->contactGetIds($listFilter);
                    unset($listFilter->listId);
                } else {
                    Dm_Log::Debug('Complex is not set');
                }
            }

            // Mise en optout ou en optin
            if (isset($listFilter->listId)) {
                Dm_Log::Debug('Changement optin/optout par listeId');
                // cas où l'on travaille sur la liste entière
                $optIsDone = (($status == 'optin') ? $this->_contactAdapter->contactStart($listFilter, $media) :
                    $this->_contactAdapter->contactStop($listFilter, $media));
            } else {
                Dm_Log::Debug('Changement optin/optout par contactIds');
                // cas où l'on travaille sur la liste partielle
                // Mise en optout ou en optin par batch de 10 000
                $offset = 0;
                $optIsDone = 0;
                do {
                    $partialIds = array_slice($ids, $offset, self::BATCH_OPT_IN_OUT);
                    if (count($partialIds) > 0) {
                        Dm_Log::Debug('optin/optout of contacts ' . $offset . ' - '
                                      . ($offset + self::BATCH_OPT_IN_OUT));
                        $listFilter->contactId = implode(',', $partialIds);
                        $retOpt = (($status == 'optin')
                            ? $this->_contactAdapter->contactStart($listFilter, $media)
                            : $this->_contactAdapter->contactStop($listFilter, $media));
                        $optIsDone += $retOpt->count;
                    }
                    $offset += self::BATCH_OPT_IN_OUT;
                } while (count($partialIds) > 0);
            }
            // optin / opout terminé
            $error = false;
            $msg = '';

            // Affichage du retour
            if ($optIsDone == 0) {
                $error = true;
                $msg = ucfirst($this->view->translate('cannot ' . $status . ' your selection'));
            } else {
                $msg = ucfirst($this->view->translate('your selection is now ' . $status));
            }
        } else {
            $error = true;
            $msg = ucfirst($this->view->translate('cannot ' . $status . ' your selection'));
        }

        // Action sur tous les médias : redirection
        if (is_null($media)) {
            $this->_helper->FlashMessenger->addMessage(array(($error == true) ? 'error' : 'info' => $msg));
            $this->_redirect($this->view->href('contact-list-edit', array('id' => $listId)));
        } else {
            // Action sur un médias: traitement asynchrone
            $result = array(
                'status' => ($error == false),
                'message' => $msg
            );
            $this->_helper->json->sendJson($result);
        }
    }

    /**
     * Génère le nom du fichier à partir d'un filtre contact
     *
     * @param Mk_Contacts_Contact_Filter $filter le filtre de sélection des contacts
     *
     * @return string
     */
    protected function _setCsvFilename(Mk_Contacts_Contact_Filter $filter)
    {
        if (isset($filter->listId) && !is_null(current($filter->listId))) {
            $listName = Mk_Contacts_ContactList::LoadById($filter->listId)->name;
        } else {
            $listName = $this->view->translate('all my contacts');
        }
        if (is_array($filter->contactId)) {
            // On décompose la liste des identifiants de contacts fournis
            $listName .= '_' . $this->view->translate('selection');
        }
        $listName = preg_replace('/[^a-zA-Z0-9_\.-]/', '', $listName) . '.csv';
        return $listName;
    }

    /**
     * Get only the stats of the medias this contract as subscribed to
     *
     * @param $stats
     *
     * @return stdClass
     */
    protected function filterStatsWithContractsMedias(&$stats)
    {
        $contract = Dm_Session::getConnectedUserContract();
        $this->filterStats($stats, $contract->medias);
        if (isset($stats->detailList) && isset($stats->detailList->list) && is_array($stats->detailList->list)) {
            foreach ($stats->detailList->list as &$detailStat) {
                $this->filterStats($detailStat, $contract->medias);
            }
        }
    }

    private function filterStats(&$stats, $validMedias)
    {
        $lcValidMedias = array_map('strtolower', $validMedias);
        foreach ($this->_mediasWithStats as $media) {
            $lcMedia = strtolower($media);
            $attrName = $lcMedia . 'Number';
            // if the contract does not have the media, we unset it
            if (!in_array($lcMedia, $lcValidMedias) && isset($stats->{$attrName})) {
                unset($stats->{$attrName});
            }
        }
    }
}
