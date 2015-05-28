<?php

/**
 * BillingController.php
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
 * Description de la classe : BillingController
 *
 * @category Digitaleo
 * @package  Application.Module.Frontoffice.Controllers
 * @author   Digitaleo <developpement@digitaleo.com>
 * @license  http://www.digitaleo.net/licence.txt Digitaleo Licence
 * @link     http://www.digitaleo.net
 */
class Frontoffice_BillingController extends Zend_Controller_Action
{
    /* @var $_campaignService Service_Api_Handler_Campaign_Interface */
    protected $_campaignService;

    /**
     * Initialisation du helper contextSwitch
     *
     * @return void
     */
    public function init()
    {
        $this->_helper->ajaxContext->initContext();
        // Initialisation du service de gestion de campagnes
        $this->_campaignService = Dm_Session::GetEntry(Dm_Session::SERVICE_FACTORY)->getCampaignService();
        $this->_helper->getHelper('contextSwitch')
                      ->addActionContext('update-data', 'json')
                      ->addActionContext('mobile-code-send', 'json')
                      ->addActionContext('confirm-mobile-code', 'json')
                      ->initContext('json');
    }

    /**
     * Displays fillup form to get required informations about user and contract
     *
     * @return html
     */
    public function completeDataAction()
    {
        $contract = Dm_Session::GetConnectedUserContract();
        $user = Dm_Session::GetConnectedUser();

        $campaignId = $this->_getParam('campaignId', null);

        $campaign = $this->_getCampaign($campaignId);

        // campagne n'est pas trouvée, on redirige vers la liste des campagnes
        if (!$campaign) {
            $path = $this->hrefHelper('campaign-list', array('status' => Service_Api_Object_Campaign::STATUS_EDITING));
            $this->_redirect($path);
        }

        // mobile validé et campagne poussée on renvoie vers la page de résumé de paiement
        if ($user->mobileValidated) {
            if ($campaign->status === Service_Api_Object_Campaign::STATUS_PUSHED) {
                $this->_redirect($this->hrefHelper('billing-resume-before-payment',
                                                   array('campaignId' => $campaignId)));
            }
            // la campagne n'est pas poussée donc pas confirmée on renvoie vers la page d'édition
            $this->_redirect($this->hrefHelper('campaign-edit', array('campaignId' => $campaignId)));
        }

        $this->view->headScript()->appendFile(
            '/scripts/angularjs/billing/web/completeDataApp.js'
        );

        $business_name = preg_match('#^web-.*#', $contract->name) === 1 ? '' : $contract->name;

        $userData = array(
            'firstName' => $user->firstName,
            'name' => $user->name,
            'email' => $user->email,
            'mobile' => $user->mobile,
            'businessName' => $business_name,
            'address' => $contract->address,
            'postCode' => $contract->postCode,
            'city' => $contract->city
        );

        $urls = array(
            'getNextStep' => $this->hrefHelper('get-next-step', array('campaignId' => $campaignId)),
            'checkCode' => $this->hrefHelper('billing-confirm-mobile-code'),
            'sendCode' => $this->hrefHelper('billing-mobile-code-send'),
            'updateData' => $this->hrefHelper('billing-update-data'));

        $this->view->data = array(
            'userData' => $userData,
            'campaignId' => $campaignId,
            'urls' => $urls
        );
    }

    /**
     * Saves billing informations into contract and user, then sends a verification code to user's mobile
     *
     * @return json
     */
    public function updateDataAction()
    {
        $params = $this->_getAllParams();

        $keys = array('firstName',
                      'name',
                      'businessName',
                      'mobile',
                      'address',
                      'postCode',
                      'city');

        foreach ($keys as $paramNeeded) {
            if (!array_key_exists($paramNeeded, $params)) {
                $this->view->success = false;
                $this->view->message = $this->view->translate('error, please check your fields');
            }
        }

        if ($this->view->success !== false) {
            $contract = Dm_Session::GetConnectedUserContract();
            $user = Dm_Session::GetConnectedUser();

            if ($user->mobile != $params['mobile']) {
                $mobileIsModified = true;
            } else {
                $mobileIsModified = false;
            }

            $user->firstName = $params['firstName'];
            $user->name = $params['name'];
            $user->mobile = $params['mobile'];
            $contract->name = $params['businessName'];
            $contract->address = $params['address'];
            $contract->postCode = $params['postCode'];
            $contract->city = $params['city'];


            // Mise à jour des données contrats et user,
            // //____________________________________________
            $identity = new Service_Api_Object_Identity();
            $identity->apiKey = Zend_Registry::get('baseoKey');

            // initialisation de la factory avec une clé admin.
            $factoryConfig = Dm_Config::GetConfig('mk', 'library');
            Mk_Factory::Init($identity->apiKey, $factoryConfig);
            // //____________________________________________

            try {
                // Mise à jour de l'utilisateur
                $userSaved = $user->save();
                if ($userSaved !== $user->id) {
                    throw new Exception('user id repondu different de sauvé : '
                                        . 'id original[' . $user->id . '] id retourné[' . $userSaved . ']');
                }

                // Mise à jour du contrat
                $contractSaved = $contract->save();
                if ($contractSaved !== $contract->id) {
                    throw new Exception('contrat id repondu different de sauvé : '
                                        . 'id original[' . $contract->id . '] id retourné[' . $contractSaved . ']');
                }
            } catch (Exception $exc) {
                $this->view->message = $this->view->translate('An error has occurred');
                Dm_Log::Error('Erreur lors de la mise à jour de l\'utilisateur ou du contrat user[' . $user->id . '], '
                              . 'contrat [' . $contract->id . ']');
                Dm_Log::Error($exc->getMessage());
                Dm_Log::Error($exc->getTraceAsString());
            }

            // réinitialisation de l'api avec la clé utilisateur
            Mk_Factory::Init($user->userKey, $factoryConfig);

            $result = array('success' => false);
            if ($contractSaved === $contract->id && $userSaved === $user->id) {
                if ($mobileIsModified === true) {
                    // Send mobile validation code :
                    $sendCode = $this->_sendCode();
                    $result['success'] = $sendCode['success'];
                    $this->view->message = $sendCode['message'];
                } else {
                    $result['success'] = true;
                }
            } else {
                $result['message'] = $this->view->translate('An error has occurred');
            }

            $this->view->success = $result['success'] === true;
            if (array_key_exists('message', $result)) {
                $this->view->message = $result['message'];
            }
        }
    }

    /**
     *  saves user's mobile then send a code to user
     *
     * @return json
     *
     */
    public function mobileCodeSendAction()
    {
        $user = Dm_Session::GetConnectedUser();

        $this->view->success = false;

        $mobile = $this->_getParam('mobile');

        $mobileSaved = false;
        // enregistrement mobile
        if ($mobile != $user->mobile) {
            $user->mobile = $mobile;
            try {
                // Mise à jour des données user
                // //____________________________________________
                $identity = new Service_Api_Object_Identity();
                $identity->apiKey = Zend_Registry::get('baseoKey');

                // initialisation de la factory avec une clé admin.
                $factoryConfig = Dm_Config::GetConfig('mk', 'library');
                Mk_Factory::Init($identity->apiKey, $factoryConfig);
                $user->save();
                // réinitialisation de l'api avec la clé utilisateur
                Mk_Factory::Init($user->userKey, $factoryConfig);
                // //____________________________________________
                $mobileSaved = true;
            } catch (Exception $exc) {
                Dm_Log::Error("Erreur lors de l'enregistrement du user (mise à jour du mobile)");
                Dm_Log::Error($exc->getMessage());
                Dm_Log::Error($exc->getTraceAsString());
            }
        } else {
            $mobileSaved = true;
        }

        if ($mobileSaved) {
            $ret = $this->_sendCode();
            $this->view->success = $ret['success'];
            $this->view->message = $ret['message'];
        }
    }

    /**
     * Sends a code to validate user's mobile
     *
     * @return array('success' => boolean, 'message' => string);
     *
     */
    private function _sendCode()
    {
        $response = array('success' => false, 'message' => '');
        // envoi nouveau code
        $sendCode = Mk_Factory::GetUserMobileCheckAdapter()->sendcode();
        if ($sendCode) {
            $response['success'] = true;
            $response['message'] = ucfirst($this->view->translate('activation code has been sent'));
        } else {
            $response['message'] = ucfirst($this->view->translate('unable to send a new activation code'));
        }
        return $response;
    }

    /**
     * Confirm mobile to api using "code" param, returns json with informations about the transaction.
     *
     * @return json {success:true}
     */
    public function confirmMobileCodeAction()
    {
        $code = $this->_getParam('code');
        $errorMsg = "";

        $this->view->success = false;
        try {
            $apiResult = Mk_Factory::GetUserMobileCheckAdapter()->checkcode($code);
        } catch (Exception $exc) {
            $errorMsg = ucfirst($this->view->translate('error validating your mobile'));
            Dm_Log::Error('communication api error : ' . $exc->getMessage());
        }

        if ($apiResult && property_exists($apiResult, 'success')) {
            if ($apiResult->success) {
                // Le mobile a été validé il faut mettre à jour l'utilisateur en session
                $user = Dm_Session::GetConnectedUser();
                $user->mobileValidated = 1;
                Dm_Session::SetEntry(Dm_Session::CONNECTED_USER, $user);
                $this->view->success = true;
            } else {
                $errorMsg = ucfirst($this->view->translate('code is incorrect, please retry'));
            }
        } else {
            if (property_exists($apiResult, 'message')) {
                $errorMsg = ucfirst($this->view->translate($apiResult->message));
            } else {
                Dm_Log::Error('mobile confirm unable to find error message : ');
                Dm_Log::Error($apiResult);
            }
        }

        if ($this->view->success === false && strlen($errorMsg) > 0) {
            $this->view->message = $errorMsg;
        }
    }

    /**
     * Action to call if user cancel during transaction
     *
     * params :
     * - campaignId int campaign identifier to cancel
     *
     * @return void redirect to the campaign list
     */
    public function cancelBeforePaymentAction()
    {

        $campaignId = $this->_getParam('campaignId', null);
        $campaign = $this->_getCampaign($campaignId);


        $user = Dm_Session::GetConnectedUser();
        $rest = Dm_Config::GetConfig('campaign', 'rest');
        $wrapper = Mk_Factory::getRestWrapper($rest, $user->userKey);
        Dm_Log::Debug($wrapper->campaignsCancel(array('id' => $campaign->messengeoExtId)));

        $path = $this->hrefHelper('campaign-list',
                                  array('status' => Service_Api_Object_Campaign::STATUS_PENDING));
        $this->_redirect($path);

    }

    /**
     * Displays billing resume before redirecting the user to the payment page
     *
     * @return html
     */
    public function resumeBeforePaymentAction()
    {
        // vérification de la campagne
        $campaignId = $this->_getParam('campaignId', null);
        $this->view->cmpeoId = $campaignId;
        $campaign = $this->_getCampaign($campaignId);

        // si la campagne n'est pas poussée, ou pas trouvée, on renvoie vers la liste des campagnes
        if (!$campaign || $campaign->status !== Service_Api_Object_Campaign::STATUS_PUSHED) {
            $path = $this->hrefHelper('campaign-list', array('status' => Service_Api_Object_Campaign::STATUS_PENDING));
            $this->_redirect($path);
        } else {
            if (is_numeric($campaign->messengeoExtId)) {
                $user = Dm_Session::GetConnectedUser();
                $rest = Dm_Config::GetConfig('campaign', 'rest');
                $_restMessengeoClient = Mk_Factory::getRestWrapper($rest, $user->userKey);
                $mFilter = array('id' => $campaign->messengeoExtId,
                                 'properties' => array('DEFAULT', 'cost', 'costTTC'));
                $startA = microtime(true); // LOG TEMPS
                $mCampaigns = $_restMessengeoClient->campaignsRead($mFilter);
                $endA = microtime(true); // LOG TEMPS
                $delayA = $endA - $startA; // LOG TEMPS
                Dm_Log::Debug(' lecture campagne via messengeo temps écoulé : ' . $delayA . 's'); // LOG TEMPS
                if ($mCampaigns->size) {
                    $mCampaign = $mCampaigns->list[0];
                    if ($mCampaign->status !== Service_Api_Object_Campaign::STATUS_PENDING) {
                        $path = $this->hrefHelper('campaign-list', array('status' => $mCampaign->status));
                        $this->_redirect($path);
                    }

                    // detail du coût
                    $params = $this->_getFilterForCost($campaign);
                    // passage des parametres a la vue

                    $messengeo = new Eo_Rest_Wrapper(
                        $rest, Dm_Session::GetConnectedUser()->userKey);
                    $costs = $messengeo->campaigncostsCreate($params)->list[0];

                    $cost = (array)$costs->medias;
                    if (isset($cost['DISCOUNT'])) {
                        $this->view->costDiscount = $cost['DISCOUNT']->cost;
                    }
                    if (isset($cost['ADJUST'])) {
                        $this->view->costAdjust = $cost['ADJUST']->cost;
                        $this->view->labelAdjust = $cost['ADJUST']->label;
                    }

                    if (isset($cost['SMS'])) {
                        $this->view->media = 'sms';
                        $this->view->costMedia = $cost['SMS']->cost;
                        $this->view->freeEmails = $cost['SMS']->units *
                            Dm_Config::GetConfig('ecommerce', 'freeEmails.multiplicator');
                    }
                    if (isset($cost['EMAIL'])) {
                        $this->view->media = 'email';
                        $this->view->costMedia = $cost['EMAIL']->cost;
                    }
                    if (isset($cost['VOICE'])) {
                        $this->view->media = 'voice';
                        $this->view->costMedia = $cost['VOICE']->cost;
                    }
                    if (isset($cost['VOICEMAIL'])) {
                        $this->view->media = 'voicemail';
                        $this->view->costMedia = $cost['VOICEMAIL']->cost;
                    }
                    Dm_Log::Debug('Campaigne de messnegeo  : ' . var_export($mCampaign, true));
                    $this->view->campaign = $mCampaign;
                }
            } else {
                $path = $this->hrefHelper('campaign-list',
                                          array('status' => Service_Api_Object_Campaign::STATUS_PENDING));
                $this->_redirect($path);
            }
        }
    }

    /**
     * getCampaign from campaign id, or false.
     *
     * @param int $campaignId editor/slbeo campaign identifier
     *
     * @return Service_Api_Object_Campaign || false
     */
    private function _getCampaign($campaignId)
    {
        if (is_numeric($campaignId)) {

            if ($this->editorToggler == 'new') {
                Dm_Log::Debug(__METHOD__ . ' we are in a new editor');
                $campaignRow = Editor_Service_Api_Rest_Wrapper_Campaign::LoadById($campaignId);
                if ($campaignRow->getRowData() === null) {
                    return false;
                }
                $campaign = (object)$campaignRow->getRowData()->toArray(); // now we have an stdClass
                // re-create property of the old campaign into the new one
                $campaign->messengeoExtId = $campaign->apiExtId;
                $campaign->contactListExtId = $campaign->contactListId;
                Dm_Log::Debug(__METHOD__ . ' adapted campaign : ' . var_export($campaign, true));
                return $campaign;
            } else {
                // Récupération de la campagne
                $campaignFilter = new Service_Api_Filter_Campaign();
                $campaignFilter->campaignId = array($campaignId);
                $startA = microtime(true); // LOG TEMPS
                $campaignService = Dm_Session::GetEntry(Dm_Session::SERVICE_FACTORY)->getCampaignService();
                $campaigns = $campaignService->campaignRead($campaignFilter);
                $endA = microtime(true); // LOG TEMPS
                $delayA = $endA - $startA; // LOG TEMPS
                //            Dm_Log::Debug('lecture campagne et statut temps écoulé : ' . $delayA . 's'); // LOG TEMPS

                if (!$campaigns->size) {
                    return false;
                } else {
                    $campaign = $campaigns->list[0];
                    return $campaign;
                }
            }
        } else {
            return false;
        }
        Dm_Log::Debug(__METHOD__ . ' End');
    }

    /**
     * @param $campaignId
     *
     * @return array|Editor_Service_Api_Rest_Wrapper_Abstract[]
     */
    private function _getFilterForCost($campaign)
    {
        if ($this->editorToggler == 'new') {
            $params = array('campaignId' => $campaign->apiExtId);
        } else {
            // Récupération des étapes de la campagne
            $msgFilter = new Service_Api_Filter_Message();
            $msgFilter->campaignId = array($campaign->id);
            $msgContainer = $this->_campaignService->messageRead($msgFilter)->list;
            $params = array('mailings' => $msgContainer, 'listId' => $campaign->contactListExtId);
        }
        return $params;
    }

    protected $editorToggler = 'old';

    /**
     * @inheridoc
     *
     * Remplace le helper href par un helper qui permet de rediriger vers le nouvel editeur
     */
    public function preDispatch()
    {
        $this->editorToggler = $this->_getParam('editor', 'old');
    }

    /**
     * Proxy vers le helper href pour gérer le nouvel/ancien editeur
     *
     * @param string $pageName   Le nom de la page dans le fichier de navigation.
     * @param array  $urlOptions Table de cle/valeur pour les parametre de l'url
     *
     * @return l'url construite ou une chaine vide
     */
    public function hrefHelper($pageName, $urlOptions = array())
    {
        $urlOptions = array_merge($urlOptions, array('editor' => $this->editorToggler));
        return $this->view->href($pageName, $urlOptions);
    }
}
