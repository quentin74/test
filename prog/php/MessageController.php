<?php

/**
 * MessageController.php
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
 * Description de la classe : MessageController
 *
 * @category Digitaleo
 * @package  Application.Module.Frontoffice.Controllers
 * @author   Digitaleo <developpement@digitaleo.com>
 * @license  http://www.digitaleo.net/licence.txt Digitaleo Licence
 * @link     http://www.digitaleo.net
 */
class Frontoffice_MessageController extends \Zend_Controller_Action
{

    /**
     *
     * @var array
     */
    private static $mediaTypeToPreviewAction = array(
        Service_Api_Object_Message::FACEBOOK => 'preview-facebook',
        Service_Api_Object_Message::TWITTER => 'preview-twitter',
        Service_Api_Object_Message::SITE_MOBILE => 'preview-site-mobile',
        Service_Api_Object_Message::VOICE => 'preview-voice',
        Service_Api_Object_Message::VOICEMAIL => 'preview-voicemail',
        Service_Api_Object_Message::EMAIL => 'preview-email',
        Service_Api_Object_Message::SMS => 'preview-sms'
    );

    /**
     * _context
     *
     * @var Dm_Controller_Action_Helper_ExcelContext
     */
    protected $_context;

    /**
     * Initalisation du context Excel pour l'export
     *
     * @return void
     */
    public function init()
    {
        $this->_helper->removeHelper('PdfContext');
        $this->_context = $this->_helper->getHelper('ExcelContext');

        // initializing Excel context
        $this->_context
            ->addActionContext('detail', 'xls')
            ->addActionContext('form-detail', 'xls')
            ->addActionContext('form-detail', 'file-csv')
            ->initContext();
    }

    /**
     * Action to call to display the Message Media Chooser
     *
     * Awaiting params :
     * - stepId : step identifier
     *
     * @return void
     */
    public function indexAction()
    {
        // Désactivation du layout
        $this->_helper->layout->disableLayout();

        $stepId = $this->_getParam('stepId');
        if (is_null($stepId)) {
            $this->_helper->FlashMessenger->addMessage(
                array('error' => $this->view->translate(sprintf('Cannot add a message wihtout knowing the step')))
            );
            $this->_redirect($this->view->href('campaign-list'));
        } else {
            $step = Service_Api_Object_Step::LoadById($stepId);
            $currentContract = Dm_Session::GetConnectedUserContract();
            $contractMediaTypes = $currentContract->getAvailableMediaForStep($step);
            $productMediaTypes = Service_Api_Object_Message::GetAvailableType();

            $availableMediaTypes = array();

            foreach ($contractMediaTypes as $name => $active) {
                if (in_array($name, $productMediaTypes)) {
                    $availableMediaTypes[$name] = $active;
                }
            }

            $this->view->availableMediaTypes = $availableMediaTypes;
            $this->view->stepId = $stepId;
            $this->view->displayLabImage = $currentContract->isAllowDisplayInactiveChanel() &&
                $currentContract->type !== 'web'; // on affiche pas le lab pour les contrats web
            $this->view->isCampaignAutomatic = $step->isAutomatic;
        }
    }

    /**
     * Add a new message on a step
     *
     * This action will display the template selector if several templates were found and if neither template was
     * specified.
     * If a template identifier was specified the message will be directly created without display
     * the selector.
     * Be careful, if the step, already contains a message, this will be replaced by a new
     *
     * Awaiting params :
     * - stepId     : step identifier
     * - type       : message type to create
     * - templateId : (optionnal) template identifier, if receive
     *
     * @return void
     */
    public function addAction()
    {
        $json = array('status' => 1);

        $stepId = $this->_getParam('stepId');
        $type = $this->_getParam('type');
        $templateId = $this->_getParam('templateId');
        try {
            //---------------------------------------------------------------
            // Check the mandatory parameters
            if (is_null($type)) {
                throw new Exception(ucfirst(
                                        $this->view->translate('cannot add a message wihtout a valid media type.')
                                    ));
            } else {
                if (!is_numeric($stepId)) {
                    throw new Exception(
                        ucfirst(
                            $this->view->translate('cannot add a message wihtout a valid step identifier.')
                        ));
                } else {
                    //---------------------------------------------------------------
                    // Get the template list or single identifier
                    $availableTemplates = Service_Message_Template::LoadByType($type);

                    // available template's messages ids
                    $tplMsgIds = array();
                    foreach ($availableTemplates as $template) {
                        $tplMsgIds[] = $template->messageId;
                    }

                    if (count($tplMsgIds) == 1 || $type === Editor_Model_Message_Row::SMS) {
                        $tplMsgIds = $tplMsgIds[0];
                    }

                    //---------------------------------------------------------------
                    // If a template is sepecified or
                    // if the specified type contain only one template we directly try to create the new message
                    if (is_numeric($templateId) || is_numeric($tplMsgIds)) {
                        //If available template is a set of template and if the specified template is not in the set
                        // we cannot create a new message
                        if (is_array($tplMsgIds)) {
                            if (!in_array($templateId, $tplMsgIds)) {
                                throw new Exception(ucfirst($this->view->translate('cannot add message')));
                            }
                        } else {
                            if (is_numeric($templateId) && $tplMsgIds != $templateId) {
                                throw new Exception(ucfirst($this->view->translate('cannot add message')));
                            } else {
                                //In this case we are sure there are only one template and we will use it
                                $templateId = $tplMsgIds;
                            }
                        }

                        //---------------------------------------------------------------
                        // Here we are sure to need to create a new message for the specified step
                        //---------------------------------------------------------------

                        $campaignService = Dm_Session::GetEntry(Dm_Session::SERVICE_FACTORY)->getCampaignService();
                        /* @var $campaignService Service_Api_Handler_Campaign_Interface */

                        $isAutomatic = 0;
                        //---------------------------------------------------------------
                        // We check if an existing message already exist
                        $filter = new Service_Api_Filter_Message();
                        $filter->stepId = array($stepId);
                        $filter->media = array($type);
                        $result = $campaignService->messageRead($filter);
                        $messageHandler = new Editor_Model_Message_Table();
                        if ($result->size > 0) {
                            $createdMessage = $result->list[0];
                            $messageId = $createdMessage->id;
                            $campaignId = $createdMessage->campaignId;
                            //In this case we get the old editor message to delete we create the new
                            $oldMessage = $messageHandler->fetchRow('extId=' . $messageId);
                        } else {
                            // reading step automation data
                            $stepFilter = new Service_Api_Filter_Step();
                            $stepFilter->stepId = array($stepId);
                            $stepFilter->properties = array('isAutomatic');
                            $stepResult = $campaignService->stepRead($stepFilter);
                            if ($stepResult->size) {
                                $isAutomatic = $stepResult->list[0]->isAutomatic;
                            }

                            $response = $campaignService->messageCreate(
                                array(array('media' => $type, 'stepId' => $stepId, 'isAutomatic' => $isAutomatic))
                            );
                            $createdMessage = $response->list[0];
                            $messageId = $createdMessage->id;
                            $campaignId = $createdMessage->campaignId;
                        }

                        //---------------------------------------------------------------
                        //We get the template to use to duplicate it
                        $originalMessage = $messageHandler->find($templateId)->current();
                        /* @var $originalMessage Editor_Model_Message_Row */

                        //---------------------------------------------------------------
                        //We create the new instance of editor message
                        $editorMessage = $originalMessage->duplicate(
                            $messageId, Dm_Session::GetEntry(Dm_Session::CONNECTED_USER)->id);
                        $duplicatedConcreteMessage = $editorMessage->getConcreteMessage();
                        if ($type === Service_Api_Object_Message::SITE_MOBILE) {
                            $name = ($isAutomatic) ? 'Campagne automatisée ' : 'Campagne ';
                            $duplicatedConcreteMessage->name = $name .
                                Dm_Session::GetEntry(Dm_Session::CONNECTED_USER)->contractName;
                            $duplicatedConcreteMessage->save();
                        }

                        $page = $duplicatedConcreteMessage->getHomePage();
                        $step = Service_Api_Object_Step::LoadById($stepId);

                        $json['status'] = 1;

                        $html = $this->view->action('index', $createdMessage->media . '-editor', null,
                                                    array(
                                                        'messageId' => $messageId,
                                                    )
                        );
                        $json['html'] = $html;
                        $json['messageId'] = $messageId;
                        $json['campaignId'] = $campaignId;
                        $json['contentType'] = 'message';

                        // If the new message is a site, we add a short to it in the session
                        // We add /#siteShortUrl#/ => SHORT_URL in the GLOBAL_REPLACEMENTS_RULES entry into the session
                        // This entry will be used during the edition display
                        if ($type === Service_Api_Object_Message::SITE_MOBILE) {
                            $siteUrl = Dm_Session::GetEntry(Dm_Session::BASE_URL) .
                                $this->view->Href('message_preview', array('messageId' => $editorMessage->messageId)
                                );
                            $apiShortUrl = Dm_Api_Lsms::factory(Zend_Registry::get('lsms'));
                            $siteShortUrl = $apiShortUrl->createShortUrl($siteUrl);
                            // Add entry into the session
                            DM_Session::SetEntry(
                                Dm_Session::GLOBAL_REPLACEMENTS_RULES, array('/#siteShortUrl#/' => $siteShortUrl)
                            );
                        }
                        //---------------------------------------------------------------
                        //If an old message was found, we delete it
                        if (isset($oldMessage)) {
                            $oldMessage->delete();
                        }
                    } else {
                        if (is_null($availableTemplates)) {
                            //If there is no model for the specified type,
                            //which means that the configuration is incorrect
                            throw new Exception("Cannot create message without any template defined in configuration");
                        } else {
                            $html = $this->view->action(
                                'template-chooser', 'message', 'frontoffice',
                                array(
                                    'templates' => $availableTemplates,
                                    'type' => $type,
                                    'stepId' => $stepId
                                )
                            );
                            $json['contentType'] = 'chooser';
                            $json['html'] = $html;
                        }
                    }
                }
            }
        } catch (Exception $e) {
            $json = array(
                'status' => 0,
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            );
        }
        $this->_helper->json->sendJson($json);
    }

    /**
     * This action display the template chooser
     * Awaiting params :
     * - stepId     : step identifier
     * - type       : message type to create
     *
     * @return void
     */
    public function templateChooserAction()
    {
        $this->view->stepId = $this->_getParam('stepId');
        $this->view->type = $this->_getParam('type');
        $this->view->url = $this->view->href('front-message-add');
        $this->view->postUrl = $this->view->href('message-ajax-read-template',
                                                 array(
                                                     'stepId' => $this->view->stepId,
                                                     'type' => $this->view->type
                                                 )
        );;
        // reading content industries
        $industryNames = Service_Api_Object_Content::LoadIndustries();
        $sources = Service_Api_Object_Content::LoadSources();
        $industries = array();
        $industries[''] = ucfirst($this->view->translate('content.all templates'));

        // reading sources
        $industries['MINE'] = ucfirst($this->view->translate("content.mine"));
        foreach ($sources as $source) {
            // reading network source
            if (!in_array($source['name'], array('MINE', 'FOTOLIA', 'Digitaleo'))) {
                $industries[$source['name']] = $source['trad'];
            }
        }
        // reading industries
        foreach ($industryNames as $industry) {
            $industries[$industry] = ucfirst($this->view->translate("content." . $industry));
        }
        $this->view->industries = $industries;
        if (Dm_Session::GetConnectedUser()->role === Mk_Entities_User::ROLE_NETWORK) {
            $this->view->sharing = new Network_Form_Sharing();
        }

        $uploadForm = $this->buildUploadForm();
        $uploadForm->setAction($this->view->href('message-upload-template',
                                                 array(
                                                     'stepId' => $this->view->stepId,
                                                     'type' => $this->view->type
                                                 )
        ));
        $this->view->uploadForm = $uploadForm;
    }

    /**
     * Créer le formulaire de recherche
     *
     * @todo : pas cablé, uniquement pour le salon e-marketting
     *
     * @return \Zend_Form
     */
    protected function buildContentForm()
    {
        $form = new Zend_Form();
        $titleInput = new Zend_Form_Element_Text('name',
                                                 array(
                                                     'label' => 'content title',
                                                     'required' => true
                                                 )
        );
        $commentInput = new Zend_Form_Element_Text('comment', array('label' => 'comment'));
        $submitInput = new Zend_Form_Element_Button('submitContent',
                                                    array('class' => 'submit-button', 'label' => 'submit'));

        $form->addElements(
            array($titleInput, $commentInput, $submitInput)
        );
        foreach ($form->getElements() as $element) {
            /* @var $element Zend_Form_Element */
            $element->setDecorators(array('ViewHelper', 'Errors'));
        }
        return $form;
    }

    /**
     * Créer le formulaire d'upload Html
     *
     * @return \Zend_Form
     */
    protected function buildUploadForm()
    {
        $form = new Zend_Form();
        $fileInput = new Zend_Form_Element_File('contentFile', array('label' => 'file'));
        $fileInput->setDestination(Dm_Config::GetPath('tmp'));
        $filenameInput = new Zend_Form_Element_Text('filename', array('label' => 'filename'));
        $submitInput = new Zend_Form_Element_Button('submitContent',
                                                    array('class' => 'validate-button', 'label' => 'upload'));

        $form->addElements(
            array($fileInput, $filenameInput, $submitInput)
        );
        foreach ($form->getElements() as $element) {
            /* @var $element Zend_Form_Element */
            $element->setDecorators(array('ViewHelper', 'Errors'));
        }
        $fileInput->setDecorators(array('File', 'Errors'));
        return $form;
    }

    /**
     * Action to update message site end date
     *
     * Awaiting params :
     * - messageId     : message identifier
     * - date          : date to set
     *
     * @return JSON {status, message}
     */
    public function ajaxSiteScheduleEndUpdateAction()
    {
        $messageId = $this->_getParam('messageId');
        $date = $this->_getParam('date');
        try {

            if (!isset($messageId)) {
                throw new InvalidArgumentException('Missing message id');
            }
            if (!isset($date)) {
                throw new InvalidArgumentException('Missing valid date');
            }
            $tmp = new Zend_Date($date);
            $messageHandler = new Editor_Model_Message_Table();
            $editorMessage = $messageHandler->fetchRow('extId = ' . $messageId);
            $concreteEditorMessage = $editorMessage->getConcreteMessage();
            $concreteEditorMessage->scheduleStopDate = $tmp->toString('YYYY-MM-dd');
            $concreteEditorMessage->save();
            $json = array(
                'status' => true,
                'message' => 'Done'
            );
        } catch (Exception $e) {
            Dm_Log::Error($e->getTraceAsString());
            $json = array(
                'status' => false,
                'message' => 'Error during date modication'
            );
        }
        $this->_helper->json->sendJson($json);
    }

    /**
     * This action return the templates chooser content
     * Awaiting params :
     * - stepId     : step identifier
     * - type       : message type to create
     *
     * @return JSON
     */
    public function ajaxReadTemplateAction()
    {
        $stepId = $this->_getParam('stepId');
        $type = $this->_getParam('type');
        $search = $this->_getParam('name', null);
        $reset = Dm_Session::hasEntry('resetNextLoad', __CLASS__) && Dm_Session::GetEntry('resetNextLoad', __CLASS__);
        /* @note : find an other solution for placeholder */
        if ($search == 'Mots clés' || $search == 'Keywords') {
            $search = null;
        }

        $availableTemplates = Service_Message_Template::LoadByType(
            $type, $search, $this->_getParam('industryName'), $reset);
        Dm_Session::setEntry('resetNextLoad', false, __CLASS__);

        switch ($type) {
            case Editor_Model_Message_Row::SMS:
                $renderer = 'message/partial-sms-model-thumb.phtml';
                break;
            default:
                $renderer = 'message/partial-model-thumb.phtml';
                break;
        }
        $htmlDatas = '';
        $json = '';
        try {
            $counter = 0;
            if (!empty($availableTemplates)) {
                foreach ($availableTemplates as $datas) {
                    if ($type === Editor_Model_Message_Row::SMS) {
                        $tmp = $datas->comment;
                        $tmp = substr($tmp, 160);
                        $this->view->smsLength = 1 + ceil(strlen($tmp) / 154);
                    }
                    $this->view->stepId = $stepId;
                    $datas->tags = array_map('strtolower', (array)$datas->tags);
                    $this->view->datas = $datas;

                    $htmlDatas .= $this->view->render($renderer);
                    $counter++;
                }
            } else {
                $htmlDatas = ucfirst($this->view->translate('no template found'));
            }
            $json = array(
                'status' => true,
                'nbResults' => $counter,
                'html' => $htmlDatas,
                'noMoreResults' => false
            );
        } catch (Exception $e) {
            Dm_Log::Error($e->getTraceAsString());
            $json = array(
                'status' => false,
                'message' => 'Error during template recuperation'
            );
        }
        $this->_helper->json->sendJson($json);
    }

    /**
     * Change template use for a message
     *
     * @return JSON
     */
    public function ajaxTemplateChangeAction()
    {
        $templateId = $this->_getParam('templateId');
        $messageId = $this->_getParam('messageId');
        $tpoaEnabled = $this->_getParam('tpoaEnabled');
        $tpoaValue = $this->_getParam('tpoaValue');
        $manageResponse = $this->_getParam('manageResponse');
        $modelId = $this->_getParam('modelId');

        $jsonResult = array(
            'status' => true,
            'message' => ''
        );
        try {
            $messageHandler = new Editor_Model_Message_Table();
            if (!is_numeric($templateId)) {
                throw new InvalidArgumentException(
                    'No valid template identifier. Please contact the suport if the problem persists');
            }
            if (!is_numeric($messageId)) {
                throw new InvalidArgumentException(
                    'No valid message identifier. Please contact the suport if the problem persists');
            }
            $originalMessage = $messageHandler->find($messageId)->current();
            $template = $messageHandler->fetchRow('template=' . $templateId);
            //Get the message extId
            $extId = $originalMessage->extId;
            $name = $originalMessage->name;
            //Delete the old message
            $originalMessage->delete();
            //Create the new message
            $editorMessage = $template->duplicate($extId, Dm_Session::GetEntry(Dm_Session::CONNECTED_USER)->id);
            /** @var $editorMessage Editor_Model_Message */
            $editorMessage->extId = $extId;
            $editorMessage->name = $name;
            $editorMessage->save();
            // MAJ du SMS si le TPOA ou la récupération des réponse est défini
            if ((isset($tpoaEnabled) && !empty($tpoaEnabled)) || (isset($manageResponse) && !empty($manageResponse))
            ) {
                $tpoaValue = empty($tpoaEnabled) ? $tpoaValue = "" : $tpoaValue;

                $smsTable = new Editor_Model_Sms_Table();
                $datas = array(
                    'tpoaEnabled' => $tpoaEnabled,
                    'tpoaValue' => $tpoaValue,
                    'manageResponse' => $manageResponse
                );
                $smsTable->update($datas, "messageId = " . $editorMessage->messageId);
            }

            if ($modelId) {
                // On charge les infos du modèle d'origine
                $smsTable = new Editor_Model_Sms_Table();
                $originalSms = $smsTable->find($modelId)->current();
                if ($originalSms->tpoaEnabled == 1 && !empty($originalSms->tpoaValue)
                    && strtolower($originalSms->tpoaValue != 'null')
                ) {
                    $tpoaEnabled = true;
                    $tpoaValue = $originalSms->tpoaValue;
                } else {
                    $tpoaEnabled = false;
                    $tpoaValue = '';
                }
            }

            $jsonResult['tpoa'] = array(
                'enabled' => $tpoaEnabled,
                'value' => $tpoaValue
            );

            $jsonResult['entity'] = array(
                'id' => $editorMessage->messageId,
                'pageId' => $editorMessage->getHomePage()->pageId
            );
        } catch (Exception $e) {
            $jsonResult = array(
                'status' => false,
                'message' => $this->view->translate($e->getMessage())
            );
        }

        $this->_helper->json->sendJson($jsonResult);
    }

    /**
     * Deletes a template
     *
     * @return array
     */
    public function ajaxTemplateDeleteAction()
    {
        $jsonResult = array(
            'status' => false,
            'message' => ucfirst($this->view->translate('template.cannot delete template, try again later'))
        );
        $templateId = $this->_getParam('templateId');

        if (!is_null($templateId) && is_numeric($templateId)) {
            // deleting API template data
            $contentFilter = new Service_Api_Filter_Content();
            $contentFilter->id[] = $templateId;
            $deleteResult = Dm_Session::GetEntry(Dm_Session::SERVICE_FACTORY)
                                      ->getContentService()
                                      ->contentsDelete($contentFilter);

            if ($deleteResult->count) {
                $templateType = $this->_getParam('templateType');
                if (!is_null($templateType)) {
                    // deleting session template data
                    $sessionTemplates = Dm_Session::GetEntry("template-{$templateType}-");
                    $index = null;
                    foreach ($sessionTemplates as $key => $sessionTemplate) {
                        if ($sessionTemplate->id == $templateId) {
                            $index = $key;
                        }
                    }

                    if (!is_null($index)) {
                        // updating template session data
                        unset($sessionTemplates[$index]);
                        Dm_Session::SetEntry("template-{$templateType}-", $sessionTemplates);
                    }
                }

                $jsonResult['status'] = true;
                unset($jsonResult['message']);
            }
        }
        $this->_helper->json->sendJson($jsonResult);
    }

    /**
     * _getMessageWithCurrentParams
     *
     * @return StdClass
     */
    private function _getMessageWithCurrentParams()
    {
        $message = $this->_getParam('message');
        if (is_null($message)) {
            $messageId = $this->_getParam('messageId');
            if (is_null($messageId) || !is_numeric($messageId)) {
                $this->_helper->FlashMessenger->addMessage(
                    array('error' => $this->view->translate(sprintf('No message to display')))
                );
                $this->_redirect($this->view->href('campaign-list'));
            } else {
                $campaignService = Dm_Session::GetEntry(Dm_Session::SERVICE_FACTORY)->getCampaignService();
                $messageFilter = new Service_Api_Filter_Message();
                $messageFilter->setMessageId($messageId);
                $response = $campaignService->messageRead($messageFilter);

                if (!$response->size) {
                    $this->_helper->FlashMessenger->addMessage(
                        array('error' => $this->view->translate(sprintf('No message to display')))
                    );
                    $this->_redirect($this->view->href('campaign-list'));
                }
                $message = $response->list[0];
            }
        }
        return $message;
    }

    /**
     * Action permettant l'affichage d'un message
     *
     * @return void
     */
    public function previewAction()
    {
        // Désactivation du layout
        $this->_helper->layout->disableLayout();
        $message = $this->_getMessageWithCurrentParams();

        //Récupération de la home page du message
        $messageHandler = new Editor_Model_Message_Table();
        $editorMessage = $messageHandler->fetchRow('extId = ' . $message->id);
        $concreteEditorMessage = $editorMessage->getConcreteMessage();
        $page = $concreteEditorMessage->getHomePage();

        $jsonData = array();
        $jsonData['html'] = $this->view->action(
            self::$mediaTypeToPreviewAction[$message->media], 'message', 'frontoffice',
            array('page' => $page, 'messageId' => $message->id, 'isCampaignTemplate' => $message->isTemplate,
                  'isCampaignAutomatic' => $message->isAutomatic)
        );

        $this->_helper->json->sendJson($jsonData);
    }

    /**
     * Action permettant l'affichage d'un message SMS
     *
     * @return void
     */
    public function previewSmsAction()
    {
        $this->view->messageId = $this->_getParam('messageId');
        $this->view->page = $this->_getParam('page');
    }

    /**
     * Action permettant l'affichage d'un message Vocal
     *
     * @return void
     */
    public function previewVoiceAction()
    {
        $this->view->page = $this->_getParam('page');
        $this->view->messageId = $this->_getParam('messageId');
    }

    /**
     * Action permettant l'affichage d'un message Vocal sur répondeur
     *
     * @return void
     */
    public function previewVoicemailAction()
    {
        $this->view->page = $this->_getParam('page');
        $this->view->messageId = $this->_getParam('messageId');
    }

    /**
     * Action permettant l'affichage d'un message SMS
     *
     * @return void
     */
    public function previewSiteMobileAction()
    {
        $this->view->page = $this->_getParam('page');
        $this->view->messageId = $this->_getParam('messageId');
        $this->view->isCampaignAutomatic = $this->_getParam('isCampaignAutomatic');
    }

    /**
     * Action permettant l'affichage d'un message SMS
     *
     * @return void
     */
    public function previewEmailAction()
    {
        $this->view->messageId = $this->_getParam('messageId');
        $this->view->page = $this->_getParam('page');
        $this->view->isCampaignTemplate = $this->_getParam('isCampaignTemplate');
    }

    /**
     * Action permettant l'affichage d'un message SMS
     *
     * @return void
     */
    public function previewVocalAction()
    {

        $this->view->page = $this->_getParam('page');
    }

    /**
     * Action permettant l'affichage d'un message SMS
     *
     * @return void
     */
    public function previewFacebookAction()
    {
        $this->view->page = $this->_getParam('page');
        $this->view->messageId = $this->_getParam('messageId');
    }

    /**
     * Action permettant l'affichage d'un message SMS
     *
     * @return void
     */
    public function previewTwitterAction()
    {

        $this->view->page = $this->_getParam('page');
        $this->view->messageId = $this->_getParam('messageId');
    }

    /**
     * Suppression d'un message
     *
     * @return void
     */
    public function deleteAction()
    {
        Dm_Log::Debug('Start ' . __METHOD__);

        $messageId = $this->_getParam('messageId');

        $result = true;
        $jsonData = array();

        if (is_null($messageId) || !is_numeric($messageId)) {
            $jsonData['result'] = $result;
            $jsonData['message'] = $this->view->translate(sprintf('Cannot delete message without a valid identifier.'));
        } else {
            // Initialisation du service de gestion de campagnes
            $campaignService = Dm_Session::GetEntry(Dm_Session::SERVICE_FACTORY)->getCampaignService();

            // Récupération du message
            $messageFilter = new Service_Api_Filter_Message();
            $messageFilter->setMessageId($messageId);
            $messageContainer = $campaignService->messageRead($messageFilter);

            if (!$messageContainer->size) {
                $jsonData['result'] = false;
                $jsonData['message'] = $this->view->translate(sprintf('Cannot delete message.'));
            } else {
                $message = $messageContainer->list[0];

                $campaignId = $message->campaignId;
                $stepId = $message->stepId;

                // Lecture de l'étape à laquelle le message est associé
                $stepFilter = new Service_Api_Filter_Step();
                $stepFilter->setStepId($stepId);
                $stepContainer = $campaignService->stepRead($stepFilter);

                if (!$stepContainer->size) {
                    $jsonData['result'] = false;
                    $jsonData['message'] = $this->view->translate(
                        sprintf('Cannot delete message belonging to an invalid step.')
                    );
                } else {
                    $step = $stepContainer->list[0];

                    // Un message peut etre supprimé seulement si le statut de l'étape est 'previewing'
                    if (!(strcmp($step->status, Service_Api_Object_Step::STATUS_EDITING) == 0)) {
                        $jsonData['result'] = false;
                        $jsonData['message'] = $this->view->translate(
                            sprintf('Cannot delete message, step not in edit mode.')
                        );
                    } else {
                        //Lecture de la campagne à laquelle le message est associé
                        if ($step->isTemplate) {
                            $templateFilter = new Service_Api_Filter_Template();
                            $templateFilter->setCampaignId($campaignId);
                            $campaignContainer = $campaignService->templateRead($templateFilter);
                        } else {
                            $campaignFilter = new Service_Api_Filter_Campaign();
                            $campaignFilter->setCampaignId($campaignId);
                            $campaignContainer = $campaignService->campaignRead($campaignFilter);
                        }

                        if (!$campaignContainer->size) {
                            $jsonData['result'] = false;
                            $jsonData['message'] = $this->view->translate(
                                sprintf('Cannot delete message belonging to an invalid campaign.'));
                        } else {
                            $campaign = $campaignContainer->list[0];

                            // Un message peut etre supprimé si le statut de la campagne est 'previewing'
                            if (!(strcmp($campaign->status, Service_Api_Object_Campaign::STATUS_EDITING) == 0)) {
                                $jsonData['result'] = false;
                                $jsonData['message'] = $this->view->translate(
                                    sprintf('Cannot delete message belonging to an invalid campaign.'));
                            } else {
                                // Remove entry into the session if mobile site message
                                if ($message->media == Editor_Model_Message_Row::SITE_MOBILE) {
                                    DM_Session::SetEntry(Dm_Session::GLOBAL_REPLACEMENTS_RULES,
                                                         array('/#siteShortUrl#/' => ''));
                                }
                                $campaignService->messageDelete($messageFilter);

                                $jsonData['result'] = true;
                            }
                        }
                    }
                }
            }
        }

        $this->_helper->json->sendJson($jsonData);

        Dm_Log::Debug('End ' . __METHOD__);
    }

    /**
     * Détails de réponses des formulaires
     *
     * @return void
     */
    public function formDetailAction()
    {
        if (!$this->view->hasAccess('exportContact') && $this->_getParam('format') == 'xls') {
            $errorMessage = ucfirst($this->view->translate('cannot export your selection')) . ' <br> ' .
                $this->view->translate("You don't have the necessary credentials to access this resource");
            $this->_helper->FlashMessenger->addMessage(
                array('error' => $errorMessage)
            );
            $this->_redirect($this->view->href('campaign-list'));
        }

        $messageId = $this->_getParam('messageId');
        $formId = $this->_getParam('formId');

        if (is_null($messageId) || !is_numeric($messageId)) {
            $this->_helper->FlashMessenger->addMessage(
                array('error' => $this->view->translate(sprintf('Invalid message identifier')))
            );
            $this->_redirect($this->view->href('campaign-list'));
        }

        if (is_null($formId) || !is_numeric($formId)) {
            $this->_helper->FlashMessenger->addMessage(
                array('error' => $this->view->translate(sprintf('Invalid form identifier')))
            );
            $this->_redirect($this->view->href('campaign-list'));
        }

        /* @var $campaignService Service_Api_Handler_Campaign_Slbeo */
        $campaignService = Dm_Session::GetEntry(Dm_Session::SERVICE_FACTORY)->getCampaignService();

        // Lecture du message
        $messageFilter = new Service_Api_Filter_Message();
        $messageFilter->messageId = array($messageId);
        $messageContainer = $campaignService->messageRead($messageFilter);

        if (!$messageContainer->size) {
            $this->_helper->FlashMessenger->addMessage(
                array('error' => $this->view->translate(sprintf('Cannot display form details')))
            );
            $this->_redirect($this->view->href('campaign-list'));
        } else {
            /* @var $message Service_Api_Object_Message */
            $message = $messageContainer->list[0];

            // Lecture du nom de la campagne
            $campaignFilter = new Service_Api_Filter_Campaign();
            $campaignFilter->campaignId = array($message->campaignId);
            $campaignFilter->properties = array('id', 'name', 'contactListExtId');
            $campaignContainer = $campaignService->campaignRead($campaignFilter);

            // Lien de retour
            $this->view->backUrl = $this->view->href('campaign-stat', array('campaignId' => $message->campaignId));
            // Lien d'export
            $this->view->exportUrl = $this->view->href(
                'form-detail', array('messageId' => $messageId, 'formId' => $formId, 'format' => 'xls')
            );

            if (!$campaignContainer->size) {
                $this->_helper->FlashMessenger->addMessage(
                    array('error' => $this->view->translate(sprintf('Cannot display form details')))
                );
                $this->_redirect($this->view->href('campaign-stat', array('campaignId' => $message->campaignId)));
            } else {
                /* @var $campaign Service_Api_Object_Campaign */
                $campaign = $campaignContainer->list[0];

                $this->view->campaignName = $campaign->name;
                $this->view->campaignId = $campaign->id;
                $this->view->media = $message->media;

                // Chargement du client Broadcasteo
                $broadcasteoClient = Zend_Registry::get('broadcasteoClient');

                /*
                 * Récupération des réponses au formulaire
                 */
                // Pagination
                $page = $this->_getParam('page', 1);
                if ($this->_getParam('format') == 'xls' || $this->_getParam('format') == 'file-csv') {
                    $pageSize = 0;
                } else {
                    $pageSize = $this->_getParam('perPage', Zend_Paginator::getDefaultItemCountPerPage());
                }

                /* @var $identity Service_Api_Object_Identity */
                $identity = Zend_Auth::getInstance()->getIdentity();

                //Récupération de la liste associée à la campagne
                $listFilter = new Mk_Contacts_ContactList_Filter();
                $listFilter->listId = array($campaignContainer->list[0]->contactListExtId);
                $lists = Mk_Factory::GetContactListAdapter()->listStatsRead($listFilter);

                // Demande des réponses aux formulaire chez le client Broadcasteo
                $formReplies = $broadcasteoClient->getSiteFormReplies($identity->apiKey, $formId, $page, $pageSize);
                // Si démonstration surcharge de $formReplies
                if (isset($lists->detailList->list[0]->isMock) && ($lists->detailList->list[0]->isMock)) {
                    $formReplies = $this->_demoFormReplies($formReplies, $messageContainer->list[0]->stepDateExecution);
                }

                // Construction des données pour la vue d'export en format xls
                $metaData = array();
                $metaData[] = 'Date';
                $formRepliesDatas = ((is_array($formReplies->datas)) ? $formReplies->datas : array());
                foreach ($formReplies->metas as $metaValue) {
                    $metaData[] = $metaValue->label;
                }

                $data = array();
                foreach ($formRepliesDatas as $dataValue) {
                    $dataObj = new stdClass();
                    $dataObj->columns[] = date('d/m/Y', strtotime($dataValue->date));

                    foreach ($dataValue->values as $value) {
                        $dataObj->columns[] = $value->value;
                    }
                    $data[] = $dataObj;
                }

                $this->view->metaData = $formReplies->metas;
                $this->view->meta = $metaData;
                $this->view->data = $data;

                // Pagination de données
                $tmp = new stdClass();
                $tmp->size = count($formReplies->datas);
                $tmp->total = $formReplies->total;
                $tmp->list = $formRepliesDatas;
                $paginator = Zend_Paginator::factory($tmp, 'ObjectList');
//                $paginator = Zend_Paginator::factory($formRepliesDatas);
                $paginator->setCurrentPageNumber($page);
                $paginator->setItemCountPerPage($pageSize);
                /* @var $paginator Zend_Paginator */
                $this->view->paginator = $paginator;
            }
        }
    }

    /**
     * Action permettant de générér et charger une image code-barres
     *
     * @return void
     */
    public function qrcodeAction()
    {
        $messageId = $this->_getParam('messageId');
        $this->view->editStatus = $this->_getParam('editStatus');

        if (is_null($messageId) || !is_numeric($messageId)) {
            $this->_helper->FlashMessenger->addMessage(
                array('error' => $this->view->translate(sprintf('Invalid message identifier')))
            );
            return false;
        } else {
            $messageHandler = new Editor_Model_Message_Table();
            $editorMessage = $messageHandler->fetchRow('extId = ' . $messageId);

            /* @var $concreteEditorMessage Editor_Model_Site_Row */
            $concreteEditorMessage = $editorMessage->getConcreteMessage();

            // Génération du shortUrl en passant en paramètre le durée de vie du
            // code-barres (15 mins)
            if (!is_null($this->_getParam('editStatus')) && $this->_getParam('editStatus') == 1) {
                $timestamp = Zend_Date::now()->addMinute(15)->getTimestamp();
                // URL temporaire
                $shortUrl = $concreteEditorMessage->getShortUrl(
                    'display',
                    array(
                        'source' => 'qrcode',
                        't' => $timestamp,
                    )
                );
            } else {
                // URL definitive
                $shortUrl = $concreteEditorMessage->getShortUrl(
                    'display', array(
                        'source' => 'qrcode',
                    )
                );
            }

            // Récuéparation du formulaire
            $form = $this->_getQRCcodeForm();
            $form->setAction($this->view->href('qrcode', array('messageId' => $messageId)));
            $this->view->form = $form;

            // Récupération du URL de l'API Broadcasteo
            $broadcasteoUrl = Dm_Config::GetConfig('beo', 'uri');

            // Mise à jour de la campagne avec les données envoyées en POST
            if ($this->getRequest()->isPost()) {
                if ($form->isValid($this->getRequest()->getParams())) {
                    $size = $form->getValue('size');

                    // Desactivation du layout
                    $this->view->layout()->disableLayout();
                    $this->_helper->viewRenderer->setNoRender(true);

                    // Génération et force download du fichier image
                    $qrcodeFile = $this->_callBroadcasteo(array(
                                                              'a' => 'convertContentToQrCode',
                                                              'text' => $shortUrl,
                                                              'fileName' => null,
                                                              'level' => 0,
                                                              'matrixPointSize' => $size,
                                                              'margin' => 2,
                                                              'saveAndPrint' => true
                                                          ));

                    header('Content-Type: image/png');
//                    header("Content-type: application/force-download");
                    header("Content-Transfer-Encoding: Binary");
                    header('Content-Disposition: attachment; filename=' . basename($qrcodeFile));
                    readfile($broadcasteoUrl . "/data/QRCode/" . $qrcodeFile);
                }
            }

            // Génération du code-barres
            $qrcodeFile = $this->_callBroadcasteo(array(
                                                      'a' => 'convertContentToQrCode',
                                                      'text' => $shortUrl,
                                                      'fileName' => null,
                                                      'level' => 0,
                                                      'matrixPointSize' => 4,
                                                      'margin' => 2
                                                  ));
            $this->view->shortUrl = $shortUrl;
            $this->view->qrcode = $broadcasteoUrl . '/data/QRCode/' . $qrcodeFile;
        }

        $this->view->layout()->disableLayout();
    }

    /**
     * Action permettant de générér et charger une image code-barres
     * Retourne les données en format JSON
     *
     * @return void
     */
    public function ajaxQrcodeAction()
    {
        $json = array('status' => false);
        $messageId = $this->_getParam('messageId');

        if (!is_null($messageId) && is_numeric($messageId)) {
            $messageHandler = new Editor_Model_Message_Table();
            $editorMessage = $messageHandler->fetchRow('extId = ' . $messageId);

            /* @var $concreteEditorMessage Editor_Model_Site_Row */
            $concreteEditorMessage = $editorMessage->getConcreteMessage();

            // Génération du shortUrl en passant en paramètre le durée de vie du
            // code-barres (15 mins)
            $timestamp = Zend_Date::now()->addMinute(15)->getTimestamp();
            $shortUrl = $concreteEditorMessage->getShortUrl(
                'display',
                array(
                    'source' => 'qrcode',
                    't' => $timestamp,
                )
            );

            // Récuéparation du formulaire
            $form = $this->_getQRCcodeForm();
            $form->setAction($this->view->href('qrcode', array('messageId' => $messageId)));
            $this->view->form = $form;

            // Récupération du URL de l'API Broadcasteo
            $broadcasteoUrl = Dm_Config::GetConfig('beo', 'uri');

            // Mise à jour de la campagne avec les données envoyées en POST
            if ($this->getRequest()->isPost()) {
                if ($form->isValid($this->getRequest()->getParams())) {
                    $size = $form->getValue('size');

                    // Génération et force download du fichier image
                    $qrcodeFile = $this->_callBroadcasteo(array(
                                                              'a' => 'convertContentToQrCode',
                                                              'text' => $shortUrl,
                                                              'fileName' => null,
                                                              'level' => 0,
                                                              'matrixPointSize' => $size,
                                                              'margin' => 2,
                                                              'saveAndPrint' => true
                                                          ));

                    header('Content-Type: image/png');
                    header("Content-type: application/force-download");
                    header("Content-Transfer-Encoding: Binary");
                    header('Content-Disposition: attachment; filename=' . basename($qrcodeFile));
                    readfile($broadcasteoUrl . "/data/QRCode/" . $qrcodeFile);
                }
            }

            // Génération du code-barres
            $qrcodeFile = $this->_callBroadcasteo(array(
                                                      'a' => 'convertContentToQrCode',
                                                      'text' => $shortUrl,
                                                      'fileName' => null,
                                                      'level' => 0,
                                                      'matrixPointSize' => 4,
                                                      'margin' => 2
                                                  ));
            $json['status'] = true;
            $json['result'] = array(
                'url' => $shortUrl,
                'img' => $broadcasteoUrl . '/data/QRCode/' . $qrcodeFile
            );
        }

        $this->_helper->json->sendJson($json);
    }

    /**
     * Action AJAx permettant d'envoyer un message de test
     *
     * @return Zend_Form
     */
    public function ajaxTestAction()
    {
        $json = array('status' => false);
        // Récupération du message à tester
        $messageId = $this->_getParam('messageId');
        $data = $this->_getParam('data');

        if (!is_null($messageId) && is_numeric($messageId)) {
            // Récupération du message associé
            $messageHandler = new Editor_Model_Message_Table();
            $message = $messageHandler->find($messageId)->current();

            $recipients = preg_split('/\r\n|[\r\n]/', $data);
            if ($message->type === Service_Api_Object_Message::EMAIL) {
                foreach ($recipients as $email) {
                    if ($email != '' &&
                        preg_match(Dm_Helper_Checker::EMAIL_VALIDATION_REGEX, $email) === 0
                    ) {
                        $json['message'] = ucfirst($this->view->translate('email address invalid : %s', $email));
                        $this->_helper->json->sendJson($json);
                    }
                }
            }
            $json = $this->_sendTestMessage($message, $recipients);
        }
        $this->_helper->json->sendJson($json);
    }

    /**
     * Action Ajax permettant d'enregistrer les paramètres de séquencement d'envoi d'un message
     *
     * @return json
     */
    public function ajaxSequencingAction()
    {
        $json = array('status' => false);
        // Récupération du message à tester
        $messageId = $this->_getParam('messageId');
        $data = $this->_getParam('data');


        if (!is_null($messageId) && is_numeric($messageId)) {
            // Récupération du message associé
            $messageHandler = new Editor_Model_Message_Table();
            $message = $messageHandler->find($messageId)->current();
            $message->frequency = $data['frequency'];
            $message->quantity = $data['quantity'];

            try {
                $messageIdSaved = $message->save();
                if ($messageIdSaved == $messageId) {
                    $json = array('status' => 'success', 'message' =>
                        ucfirst($this->view->translate('sequencing options saved')));
                }
            } catch (Exception $exc) {
                $json = array('error' =>
                                  ucfirst(
                                      $this->view->translate('unable to save sequencing options for this message')
                                  ));
                Dm_Log::Error($exc->getTraceAsString());
            }
        } else {
            $json = array('error' => 'you must set a frequency and a quantity');
        }
        $this->_helper->json->sendJson($json);
    }

    /**
     * Action permettant de générér l'aperçu d'un message
     *
     * Parametres :
     * messageId - L'identifiant du message à afficher en aperçu
     *
     * @return JSON
     */
    public function overviewAction()
    {
        $this->view->layout()->disableLayout();

        $messageId = $this->_getParam('messageId');
        $media = $this->_getParam('media');
        $jsonData = array();

        if (is_null($messageId) || !is_numeric($messageId)) {
            return false;
        } else {
            switch ($media) {
                case Service_Api_Object_Message::FACEBOOK:
                    $postTable = new Editor_Model_Message_Post_Table();
                    $post = $postTable->fetchRow('extId=' . $messageId);
                    $jsonData = $post->toArray();
                    break;

                default:
                    $engine = new Editor_Model_Engine();
                    $messageHandler = new Editor_Model_Message_Table();
                    $editorMessage = $messageHandler->fetchRow('extId = ' . $messageId);
                    // Génération du code HTML du message
                    $jsonData = $engine->generatePageCode($editorMessage->getConcreteMessage()->getHomePage());
                    break;
            }
        }

        $this->_helper->json->sendJson($jsonData);
    }

    /**
     * Cette méthode effectue un appel à une méthode de l'API Broadcasteo
     *
     * @param mixed $params Tableau contenant le nom et les paramètres
     *                      de la méthode à appeler
     *
     * @return mixed|false Le résultat en cas de succes, false en chas d'echèc
     */
    private function _callBroadcasteo($params)
    {
        // Récupération du URL de l'API Broadcasteo
        $broadcasteoUrl = Dm_Config::GetConfig('beo', 'uri') . '/editor/broadcasteo/';

        //Definition des arametres de l'appel curl
        $opts = array(
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => 60,
            CURLOPT_URL => $broadcasteoUrl,
            CURLOPT_POSTFIELDS => http_build_query($params, null, '&')
        );

        $ch = curl_init();
        curl_setopt_array($ch, $opts);
        // L'appel à l'API
        $result = json_decode(curl_exec($ch));

        //Gestion des cas d'erreur
        if (curl_errno($ch) != 0) {
            $result = curl_error($ch);
        }

        return $result->result;
    }

    /**
     * Retourne le formulaire du popup pour le téléchargement du code-barres
     *
     * @return Zend_Form
     */
    protected function _getQRCcodeForm()
    {
        $form = new Zend_Form();

        $selectInput = new Zend_Form_Element_Select('size');
        $selectInput->setLabel(ucfirst($this->view->translate('size')));
        $selectInput->addMultiOptions(array(
                                          0 => ucfirst($this->view->translate('very small (XS)')),
                                          2 => ucfirst($this->view->translate('small (S)')),
                                          4 => ucfirst($this->view->translate('medium (M)')),
                                          6 => ucfirst($this->view->translate('large (L)')),
                                          8 => ucfirst($this->view->translate('very large (XL)'))
                                      ));
        $selectInput->setValue(4);

        $submitInput = new Zend_Form_Element_Submit('submit');
        $cancelInput = new Zend_Form_Element_Button('cancel');

        $form->addElement($selectInput);
        $form->setDecorators(array(new Zend_Form_Decorator_FormElements(), new Zend_Form_Decorator_Form()));
        $form->setElementDecorators(
            array(
                'ViewHelper', 'Label',
                new Dm_Form_Decorator_ShortErrors(),
            )
        );

        $submitInput->removeDecorator('Label');
        $cancelInput->removeDecorator('Label');

        $form->addElements(array($submitInput, $cancelInput));

        return $form;
    }

    /**
     * Action permettant de tester un message
     *
     * @return Zend_Form
     */
    public function testAction()
    {
        Dm_Log::Debug('Start ' . __METHOD__);

        // Récupération du message à tester
        $messageId = $this->_getParam('messageId');

        if (is_null($messageId) || !is_numeric($messageId)) {
            $this->view->errors = array($this->view->translate(sprintf('Invalid message identifier')));
        } else {
            // Récupération du message associé
            $messageHandler = new Editor_Model_Message_Table();
            $message = $messageHandler->find($messageId)->current();

            // Récupération du formulaire de test
            $form = $this->_getTestForm($messageId);
            $form->setAction($this->view->href('message-test', array('messageId' => $messageId)));
            $this->view->form = $form;

            // Type de message
            $this->view->type = $message->type;

            // Titre de la pop up
            $title = (($message->type == Editor_Model_Message_Row::EMAIL) ? 'please fill one email adress per line' :
                'please fill one mobile phone number per line');
            $this->view->title = $title;

            // Envoi des messages de tests au moyen de contact envoyés en POST
            if ($this->getRequest()->isPost()) {
                $params = $this->getRequest()->getParams();

                // Test la validité du formulaire
                if ($form->isValid($params)) {
                    $dest = preg_split('/\r\n|[\r\n]/', $params["destination"]);
                    $ret = $this->_sendTestMessage($message, $dest);
                    Dm_Log::Debug('Stop ' . __METHOD__);
                    $this->_helper->json->sendJson($ret);
                }
            }

            Dm_Log::Debug('Stop ' . __METHOD__);

            $this->view->layout()->disableLayout();
        }
    }

    /**
     * Envoi de message de test
     *
     * @param Editor_Model_Message_Row $message  le message à tester
     * @param array                    $contacts les moyens de contact
     *
     * @return void
     */
    private function _sendTestMessage($message, $contacts)
    {
        Dm_Log::Debug('Start ' . __METHOD__);

        // Suppression des lignes vides du tableau
        $contacts = array_values(array_filter($contacts));
        Dm_Log::Info('Liste des contacts : ' . var_export($contacts, true));
        Dm_Log::Info('Type de message : ' . $message->type);

        // Status du message de test
        $status = 0;
        $resultMessage = $this->view->translate('No test message was sent, please check your list');
        if (empty($contacts)) {
            return array('message' => $resultMessage, 'status' => $status);
        }

        // Setting shortSiteUrl replacement
        $shortUrl = $this->_getShortUrl($message->extId);
        $replacements = (!is_null($shortUrl)) ? array('/#siteShortUrl#/' => $shortUrl) : null;

        // Récupération du message sérialisé
        $concreteEditorMessage = $message->getConcreteMessage();
        $serializeMessage = $concreteEditorMessage->serialize($replacements);

        // Gestion de l'envoi du message en fonction du type
        switch ($message->type) {

            case Editor_Model_Message_Row::SMS:
            case Editor_Model_Message_Row::SITE_MOBILE:
                // Récupération de la configuration pour le sms
                $conf = Dm_Config::GetConfig('media', Editor_Model_Message_Row::SMS);
                $client = new Zend_Soap_Client($conf['wsdl']);

                // Récupération du code
                $contract = Dm_Session::GetConnectedUserContract();
                $code = $contract->smsKey;

                // Type
                $type = '';
                if (array_key_exists('smsType', $client)) {
                    $type = $client['smsType'];
                }

                // Contenu du message SMS ou SITE_MOBILE
                if ($message->type == Editor_Model_Message_Row::SMS) {
                    $smsMessage = $serializeMessage->text;
                } else {
                    // Génération du shortUrl en passant en paramètre le durée de vie du
                    // code-barres (15 mins)
                    $timestamp = Zend_Date::now()->addMinute(15)->getTimestamp();
                    $shortUrl = $concreteEditorMessage->getShortUrl(
                        'display',
                        array(
                            'source' => 'qrcode',
                            't' => $timestamp,
                        )
                    );
                    $smsMessage = $this->view->translate('Your mobile site is available for test:') . ' ' . $shortUrl;
                }

                // Préparation de l'envoi
                $smsSendRequest = array(
                    'sms' => $smsMessage,
                    'nom' => $concreteEditorMessage->getUid(),
                    'code' => $code,
                    'type' => $type,
                );

                // Ajout du tpoa s'il est requis pour le messagge
                if (isset($concreteEditorMessage->tpoaEnabled) && $concreteEditorMessage->tpoaEnabled == 1) {
                    $smsSendRequest['tpoa'] = $concreteEditorMessage->tpoaValue;
                }

                $smsSendRequest['mobile'] = $contacts;
                $res = $client->smsSend($smsSendRequest);
                unset($smsSendRequest);
                if (!isset($res->smsStatus)) {
                    $resultMessage = $this->view->translate('Error calling texto');
                    break;
                }

                // Stockage des résultats
                $result = array();

                // Traitement différent si un ou n destinataires
                if (count($res->smsStatus) > 1) {
                    foreach ($res->smsStatus as $smsStatus) {
                        $result[$smsStatus->status->etat][] = $smsStatus->mobile;
                    }
                } else {
                    $result[$res->smsStatus->status->etat][] = $res->smsStatus->mobile;
                }

                // test des états des différents envois
                if (array_key_exists('wait', $result) || array_key_exists('ok', $result)) {
                    $status = 1;
                    $nbGoodMessages = 0;
                    if (array_key_exists("ok", $result)) {
                        $nbGoodMessages += count($result['ok']);
                    }
                    if (array_key_exists("wait", $result)) {
                        $nbGoodMessages += count($result['wait']);
                    }
                    $resultMessage = $nbGoodMessages . ' ' . $this->view->translate('message(s) has been sent');
                }
                break;

            case Editor_Model_Message_Row::EMAIL:
                // Récupération de la configuration pour l'email
                $conf = Dm_Config::GetConfig('media', Editor_Model_Message_Row::EMAIL);
                $rest = $conf['rest'];
                $application = $conf['application'];

                // Récupération de la clé emailCode
                $key = Dm_Session::GetConnectedUser()->userKey;

                // Initialisation du wrapper messengeo
                $messengeoClient = new Eo_Rest_Wrapper($rest, $key);
                $noreply = Editor_Service_Bridge::GetValidReplyTo($serializeMessage->replyTo);
                $sender = Editor_Service_Bridge::GetValidSender($serializeMessage->sender);
                $apiParam = array(
                    'application' => $application,
                    'key' => $key,
                    'media' => 'email',
                    'name' => $concreteEditorMessage->getUid(),
                    'subject' => ((!empty($serializeMessage->subject)) ? $serializeMessage->subject :
                        $this->view->translate('Email de test')),
                    'replyContact' => ((!empty($serializeMessage->replyTo)) ? $serializeMessage->replyTo : $noreply),
                    'html' => $serializeMessage->html,
                    'sender' => ((!empty($sender)) ? $sender : $noreply)
                );

                // Liste des destinatires
                $dest = array();
                foreach ($contacts as $mobile) {
                    $dest[] = array("recipient" => $mobile);
                }
                $apiParam["contacts"] = $dest;
                $response = $messengeoClient->mailingsCreate($apiParam);
                // Envoi incorrect
                if (isset($response->status)) {
                    $resultMessage = $this->view->translate('Error calling messengeo status = ' . $response->status .
                                                            ', ' . $response->message);
                }
                if ($response->size > 0) {
                    $rep = $response->list[0];
                    $nbGoodMessages = $rep->stats->wait + $rep->stats->on + $rep->stats->ok;
                    $resultMessage = $nbGoodMessages . ' ' . $this->view->translate('message(s) has been sent');
                    $status = (($nbGoodMessages > 0) ? 1 : 0);
                    Dm_Log::Info('Résultat de la demande : ' . var_export($rep->stats, true));
                }
                break;
            case Editor_Model_Message_Row::VOICE:
                // Récupération de la configuration pour la voix
                $conf = Dm_Config::GetConfig('media', Editor_Model_Message_Row::VOICE);
                $rest = $conf['rest'];
                $application = $conf['application'];

                // Récupération de la clé emailCode
                $key = Dm_Session::GetConnectedUser()->userKey;

                // Initialisation du wrapper messengeo
                $messengeoClient = new Eo_Rest_Wrapper($rest, $key);
                $apiParam = array(
                    'application' => $application,
                    'key' => $key,
                    'media' => 'voice',
                    'name' => $concreteEditorMessage->getUid(),
                    'link' => $serializeMessage->link,
                    'type' => 'wav'
                );

                // Liste des destinatires
                $dest = array();
                foreach ($contacts as $phone) {
                    // modification du numéro pour le passer en mode international au cas où il est en 0XXX...
                    $num = array();
                    if (preg_match('/^0([0-9]{9})$/', $phone, $num) === 1) {
                        $phone = '+33' . $num[1];
                    }
                    $dest[] = array("recipient" => $phone);
                }
                $apiParam["contacts"] = $dest;
                $response = $messengeoClient->mailingsCreate($apiParam);

                // Envoi incorrect
                if (isset($response->status)) {
                    $resultMessage = $this->view->translate('Error calling messengeo status = ' . $response->status .
                                                            ', ' . $response->message);
                }
                if ($response->size > 0) {
                    $rep = $response->list[0];
                    $nbGoodMessages = $rep->stats->wait + $rep->stats->on + $rep->stats->ok;
                    $resultMessage = $nbGoodMessages . ' ' . $this->view->translate('message(s) has been sent');
                    $status = (($nbGoodMessages > 0) ? 1 : 0);
                    Dm_Log::Info('Résultat de la demande : ' . var_export($rep->stats, true));
                }
                break;

            case Editor_Model_Message_Row::VOICEMAIL:
                // Récupération de la configuration pour la voix
                $conf = Dm_Config::GetConfig('media', Editor_Model_Message_Row::VOICEMAIL);
                $rest = $conf['rest'];
                $application = $conf['application'];

                // Récupération de la clé emailCode
                $key = Dm_Session::GetConnectedUser()->userKey;

                // Initialisation du wrapper messengeo
                $messengeoClient = new Eo_Rest_Wrapper($rest, $key);
                $apiParam = array(
                    'application' => $application,
                    'key' => $key,
                    'media' => 'voicemail',
                    'name' => $concreteEditorMessage->getUid(),
                    'link' => $serializeMessage->link,
                    'type' => 'wav'
                );

                // Liste des destinatires
                $dest = array();
                foreach ($contacts as $phone) {
                    $num = array();
                    $dest[] = array("recipient" => $phone);
                }
                $apiParam["contacts"] = $dest;
                $response = $messengeoClient->mailingsCreate($apiParam);

                // Envoi incorrect
                if (isset($response->status)) {
                    $resultMessage = $this->view->translate('Error calling messengeo status = ' . $response->status .
                                                            ', ' . $response->message);
                }
                if ($response->size > 0) {
                    $rep = $response->list[0];
                    $nbGoodMessages = $rep->stats->wait + $rep->stats->on + $rep->stats->ok;
                    $resultMessage = $nbGoodMessages . ' ' . $this->view->translate('message(s) has been sent');
                    $status = (($nbGoodMessages > 0) ? 1 : 0);
                    Dm_Log::Info('Résultat de la demande : ' . var_export($rep->stats, true));
                }
                break;

            default:
                $resultMessage = $this->view->translate('Not implemented for this type of message');
                break;
        }

        Dm_Log::Debug('Stop ' . __METHOD__);

        return array('message' => $resultMessage, 'status' => $status);
    }

    /**
     * Returns shortUrl
     *
     * @param int $messageId Message identifier
     *
     * @return string Replaced string
     */
    private function _getShortUrl($messageId)
    {
        $shortUrl = '';

        // Reading campaign identifier
        $messageFilter = new Service_Api_Filter_Message();
        $messageFilter->messageId = array($messageId);
        $messageFilter->properties = array('id', 'campaignId', 'media');

        $messageResult = Dm_Session::GetEntry(Dm_Session::SERVICE_FACTORY)->getCampaignService()
                                   ->messageRead($messageFilter);

        if ($messageResult->size) {
            $site = null;
            if ($messageResult->list[0]->media === Service_Api_Object_Message::SITE_MOBILE) {
                $site = $messageResult->list[0];
            } else {
                // Reading Mobile site message
                $siteFilter = new Service_Api_Filter_Message();
                $siteFilter->campaignId = array($messageResult->list[0]->campaignId);
                $siteFilter->media = array(Service_Api_Object_Message::SITE_MOBILE);
                $siteFilter->properties = array('id');
                $siteResult = Dm_Session::GetEntry(Dm_Session::SERVICE_FACTORY)->getCampaignService()
                                        ->messageRead($siteFilter);

                if ($siteResult->size) {
                    $site = $siteResult->list[0];
                }
            }

            if (!is_null($site)) {
                $messageHandler = new Editor_Model_Message_Table();
                $concreteSiteMessage = $messageHandler->fetchRow('extId = ' . $site->id)->getConcreteMessage();

                // Generating short URL, available for 15 mins
                $shortUrl = $concreteSiteMessage->getShortUrl(
                    'display',
                    array(
                        'source' => 'sms',
                        't' => Zend_Date::now()->addMinute(15)->getTimestamp(),
                    )
                );
            }
        }
        return $shortUrl;
    }

    /**
     * Retourne le formulaire du popup pour tester un message
     *
     * @param int $messageId L'identifiant du message à tester
     *
     * @return Zend_Form
     */
    protected function _getTestForm($messageId)
    {
        $form = new Zend_Form();

        // La zone de saisie pour les coordonnées des destinataires
        $textareaInput = new Zend_Form_Element_Textarea('destination');
        $textareaInput->setAttrib('cols', '54')
                      ->setAttrib('rows', '6')
                      ->addValidators(array(
                                          array('notEmpty', true)
                                      ));

        //@TODO adapter message en fonction du type de media
        $checkInput = new Zend_Form_Element_Checkbox('save');
        $checkInput->setLabel(ucfirst($this->view->translate('save this contact list test')));

        //@TODO charger liste sauvée ...

        $submitInput = new Zend_Form_Element_Submit('submit');
        $cancelInput = new Zend_Form_Element_Button('close');

        // Ajout des zones de saisies
        $form->addElements(array($textareaInput, $checkInput));
        $form->setDecorators(array(new Zend_Form_Decorator_FormElements(), new Zend_Form_Decorator_Form()));
        $form->setElementDecorators(
            array(
                'ViewHelper', 'Label',
                new Dm_Form_Decorator_ShortErrors(),
            )
        );

        $submitInput->removeDecorator('Label');
        $cancelInput->removeDecorator('Label');

        // Ajout des buttons au formulaire
        $form->addElements(array($submitInput, $cancelInput));

        // Ajout de l'aciton du formulaire
        $form->setAction($this->view->href('message-test', array('messageId' => $messageId)));

        return $form;
    }

    /**
     * This action displays the click details for a message
     *
     * @return void
     */
    public function detailAction()
    {
        Dm_Log::Debug('Start ' . __METHOD__);

        $messageId = $this->_getParam('messageId');
        $status = $this->_getParam('status');
        $linkPosition = $this->_getParam('linkPosition');

        if (is_null($messageId) || !is_numeric($messageId)) {
            $this->_helper->FlashMessenger->addMessage(
                array('error' => $this->view->translate(sprintf('Invalid message identifier')))
            );
        } else {
            // Initializing the campaign service
            $campaignService = Dm_Session::GetEntry(Dm_Session::SERVICE_FACTORY)->getCampaignService();

            // Read message data
            $messageFilter = new Service_Api_Filter_Message();
            $messageFilter->messageId = array($messageId);
            $messageResult = $campaignService->messageRead($messageFilter);
            if (!$messageResult->size) {
                $this->_helper->FlashMessenger->addMessage(
                    array('error' => $this->view->translate(sprintf('Invalid message')))
                );
            } else {
                /* @var $message Service_Api_Object_Message */
                $message = $messageResult->list[0];
                if (!is_null($message)) {
                    // Read campaign data
                    $campaignFilter = new Service_Api_Filter_Campaign();
                    $campaignFilter->campaignId = array($message->campaignId);
                    $campaignFilter->properties = array('id', 'name');
                    $campaignResult = $campaignService->campaignRead($campaignFilter);
                    if (!$campaignResult->size) {
                        $this->_helper->FlashMessenger->addMessage(
                            array('error' => $this->view->translate(sprintf('Invalid camapign')))
                        );
                    } else {
                        $messageStats = array();
                        $links = array();
                        // getting Messengeo Rest Wrapper
                        $conf = Dm_Config::GetConfig('media', Editor_Model_Message_Row::EMAIL);
                        $rest = $conf['rest'];
                        $application = $conf['application'];
                        // getting Messengeo key from current user
                        $key = Dm_Session::GetConnectedUser()->userKey;
                        $messengeoClient = new Eo_Rest_Wrapper($rest, $key);

                        if (is_null($linkPosition)) {
                            // Connecting to Messengeo and getting satistics of email delivery
                            $messengeoParams = array(
                                'mailingName' => $message->name,
                                'properties' => array(
                                    'id', 'mailingId', 'contact', 'recipient', 'currentStatus',
                                    Service_Api_Object_Message::STATUS_OPTOUT,
                                    Service_Api_Object_Message::STATUS_READ,
                                    Service_Api_Object_Message::STATUS_CLICKED,
                                    Service_Api_Object_Message::STATUS_HARDBOUNCED,
                                    Service_Api_Object_Message::STATUS_SOFTBOUNCED)
                            );

                            // adding API call parameter according to message status
                            if (strcasecmp($status, Service_Api_Object_Message::STATUS_OPTOUT) == 0 ||
                                strcasecmp($status, Service_Api_Object_Message::STATUS_READ) == 0 ||
                                strcasecmp($status, Service_Api_Object_Message::STATUS_CLICKED) == 0 ||
                                strcasecmp($status, Service_Api_Object_Message::STATUS_HARDBOUNCED) == 0 ||
                                strcasecmp($status, Service_Api_Object_Message::STATUS_SOFTBOUNCED) == 0
                            ) {
                                $messengeoParams[$status] = 1;
                            } else {
                                if (!is_null($status)) {
                                    $messengeoParams['status'] = $status;
                                }
                            }

                            // reading message statistics in order to get recipients
                            $messagesResult = $messengeoClient->messagesRead($messengeoParams);

                            foreach ($messagesResult->list as $mailingMessage) {
                                $messageStatus = $status;
                                // getting status
                                if (is_null($messageStatus) || !$messageStatus) {
                                    $messageStatus = $mailingMessage->currentStatus->shortKey;

                                    // the rule for getting the performance status is :
                                    // Delivered and opened => opened
                                    // Delivered, opened and clicked
                                    //     OR delivered, not opened and clicked => clicked
                                    // Delivered, opened, clicked and optout => optout
                                    if (strcasecmp($mailingMessage->currentStatus->shortKey,
                                                   Service_Api_Object_Message::STATUS_DELIVERED) == 0
                                    ) {

                                        if ($mailingMessage->{Service_Api_Object_Message::STATUS_READ} == '1' &&
                                            $mailingMessage->{Service_Api_Object_Message::STATUS_CLICKED} == '1' &&
                                            $mailingMessage->{Service_Api_Object_Message::STATUS_OPTOUT} == '1'
                                        ) {
                                            $messageStatus = Service_Api_Object_Message::STATUS_OPTOUT;
                                        } elseif (
                                            ($mailingMessage->{Service_Api_Object_Message::STATUS_READ} == '1' &&
                                                $mailingMessage->{Service_Api_Object_Message::STATUS_CLICKED} == '1') ||
                                            ($mailingMessage->{Service_Api_Object_Message::STATUS_READ} ==
                                                '0' &&
                                                $mailingMessage->{Service_Api_Object_Message::STATUS_CLICKED} == '1')
                                        ) {
                                            $messageStatus = Service_Api_Object_Message::STATUS_CLICKED;
                                        } elseif ($mailingMessage->{Service_Api_Object_Message::STATUS_READ} == '1') {
                                            $messageStatus = Service_Api_Object_Message::STATUS_READ;
                                        }
                                    }
                                }
                                $messageStats[$messageStatus][] = $mailingMessage->contact->recipient;
                            }
                        } else {
                            // getting links for displaying in the form
                            $linkResult = $messengeoClient->linksRead(array('mailingName' => $message->name,
                                                                            'properties' => array('position', 'url')));

                            if (!$linkResult->size) {
                                $this->_helper->FlashMessenger->addMessage(
                                    array('error' =>
                                              $this->view->translate(sprintf('No statistics to display')))
                                );
                            } else {
                                foreach ($linkResult->list as $link) {
                                    $links[$link->position] = $link->url;
                                }
                            }

                            // sorting links according to their position (ASC)
                            ksort($links);

                            // getting contacts for a link
                            $linkContactResult = $messengeoClient->linksRead(
                                array(
                                    'mailingName' => $message->name,
                                    'position' => $linkPosition,
                                    'properties' => array('clickThruRecipients')));

                            if ($linkContactResult->size) {
                                foreach ($linkContactResult->list as $linkContact) {
                                    if (count($linkContact->clickThruRecipients)) {
                                        foreach ($linkContact->clickThruRecipients as $recipient) {
                                            $messageStats[$status][] = $recipient;
                                        }
                                    }
                                }
                            }
                            $this->view->linkContacts = $messageStats;
                        }

                        $statusForm = $this->_getMessageStatusForm($links, $linkPosition);
                        $statusForm->populate(array('status' => $status, 'links' => $links));

                        // getting contacts names
                        $contactData = $this->_getContactInfo($message->campaignId, $messageStats);

                        // Gestion de la pagination
                        $page = $this->_getParam('page', 1);
                        $elemPerPage = $this->_getParam('perPage', 10);
                        $paginator = Zend_Paginator::factory($contactData);
                        $paginator->setCurrentPageNumber($page);
                        $paginator->setDefaultItemCountPerPage($elemPerPage);

                        // passing data to view
                        $this->view->stats = $paginator;
                        $this->view->form = $statusForm;
                        $this->view->campaignName = $campaignResult->list[0]->name;
                        $this->view->campaignId = $campaignResult->list[0]->id;
                        $this->view->messageId = $messageId;
                        $this->view->media = $message->media;
                        $this->view->status = $status;

                        // back link
                        $this->view->backUrl = $this->view->href('campaign-stat',
                                                                 array('campaignId' => $campaignResult->list[0]->id));
                        // download link
                        $this->view->exportUrl = $this->view->href(
                            'message-detail', array('messageId' => $messageId, 'status' => $status, 'format' => 'xls')
                        );

                        // getting data for csv download
                        // columns names
                        $this->view->meta = array(
                            ucfirst($this->view->translate('status')),
                            ucfirst($this->view->translate('civility')),
                            ucfirst($this->view->translate('first name')),
                            ucfirst($this->view->translate('last name')),
                            ucfirst($this->view->translate('mobile')),
                            ucfirst($this->view->translate('phone')),
                            ucfirst($this->view->translate('fax')),
                            ucfirst($this->view->translate('email')),
                            ucfirst($this->view->translate('birthDate')),
                            ucfirst($this->view->translate('company')),
                            ucfirst($this->view->translate('reference')),
                            ucfirst($this->view->translate('address1')),
                            ucfirst($this->view->translate('address2')),
                            ucfirst($this->view->translate('zipcode')),
                            ucfirst($this->view->translate('city')),
                            ucfirst($this->view->translate('state')),
                            ucfirst($this->view->translate('country')),
                            ucfirst($this->view->translate('field01')),
                            ucfirst($this->view->translate('field02')),
                            ucfirst($this->view->translate('field03')),
                            ucfirst($this->view->translate('field04')),
                            ucfirst($this->view->translate('field05')),
                            ucfirst($this->view->translate('field06')),
                            ucfirst($this->view->translate('field07')),
                            ucfirst($this->view->translate('field08')),
                            ucfirst($this->view->translate('field09')),
                            ucfirst($this->view->translate('field10')),
                            ucfirst($this->view->translate('field11')),
                            ucfirst($this->view->translate('field12')),
                            ucfirst($this->view->translate('field13')),
                            ucfirst($this->view->translate('field14')),
                            ucfirst($this->view->translate('field15')),
                        );

                        // columns data
                        $data = array();
                        foreach ($contactData as $dataValue) {
                            if (isset($dataValue['id'])) {
                                $dataObj = new stdClass();
                                $dataObj->columns[0] = ucfirst(
                                    $this->view->translate($dataValue['status'] . '.export'));
                                $dataObj->columns[1] = (isset($dataValue['civility'])) ? $dataValue['civility'] : '';
                                $dataObj->columns[2] = (isset($dataValue['firstName'])) ? $dataValue['firstName'] : '';
                                $dataObj->columns[3] = (isset($dataValue['lastName'])) ? $dataValue['lastName'] : '';
                                $dataObj->columns[4] = (isset($dataValue['mobile'])) ? $dataValue['mobile'] : '';
                                $dataObj->columns[5] = (isset($dataValue['phone'])) ? $dataValue['phone'] : '';
                                $dataObj->columns[6] = (isset($dataValue['fax'])) ? $dataValue['fax'] : '';
                                $dataObj->columns[7] = (isset($dataValue['email'])) ? $dataValue['email'] : '';
                                $dataObj->columns[8] = (isset($dataValue['birthDate'])) ? $dataValue['birthDate'] : '';
                                $dataObj->columns[9] = (isset($dataValue['company'])) ? $dataValue['company'] : '';
                                $dataObj->columns[10] = (isset($dataValue['reference'])) ? $dataValue['reference'] : '';
                                $dataObj->columns[11] = (isset($dataValue['address1'])) ? $dataValue['address1'] : '';
                                $dataObj->columns[12] = (isset($dataValue['address2'])) ? $dataValue['address2'] : '';
                                $dataObj->columns[13] = (isset($dataValue['zipcode'])) ? $dataValue['zipcode'] : '';
                                $dataObj->columns[14] = (isset($dataValue['city'])) ? $dataValue['city'] : '';
                                $dataObj->columns[15] = (isset($dataValue['state'])) ? $dataValue['state'] : '';
                                $dataObj->columns[16] = (isset($dataValue['country'])) ? $dataValue['country'] : '';
                                $dataObj->columns[17] = (isset($dataValue['field01'])) ? $dataValue['field01'] : '';
                                $dataObj->columns[18] = (isset($dataValue['field02'])) ? $dataValue['field02'] : '';
                                $dataObj->columns[19] = (isset($dataValue['field03'])) ? $dataValue['field03'] : '';
                                $dataObj->columns[20] = (isset($dataValue['field04'])) ? $dataValue['field04'] : '';
                                $dataObj->columns[21] = (isset($dataValue['field05'])) ? $dataValue['field05'] : '';
                                $dataObj->columns[22] = (isset($dataValue['field06'])) ? $dataValue['field06'] : '';
                                $dataObj->columns[23] = (isset($dataValue['field07'])) ? $dataValue['field07'] : '';
                                $dataObj->columns[24] = (isset($dataValue['field08'])) ? $dataValue['field08'] : '';
                                $dataObj->columns[25] = (isset($dataValue['field09'])) ? $dataValue['field09'] : '';
                                $dataObj->columns[26] = (isset($dataValue['field10'])) ? $dataValue['field10'] : '';
                                $dataObj->columns[27] = (isset($dataValue['field11'])) ? $dataValue['field11'] : '';
                                $dataObj->columns[28] = (isset($dataValue['field12'])) ? $dataValue['field12'] : '';
                                $dataObj->columns[29] = (isset($dataValue['field13'])) ? $dataValue['field13'] : '';
                                $dataObj->columns[30] = (isset($dataValue['field14'])) ? $dataValue['field14'] : '';
                                $dataObj->columns[31] = (isset($dataValue['field15'])) ? $dataValue['field15'] : '';
                                $data[] = $dataObj;
                            }
                        }

                        $this->view->data = $data;
                    }
                }
            }
        }

        Dm_Log::Debug('End ' . __METHOD__);
    }

    /**
     * Generating message status form
     *
     * @param string[] $links        Array of links
     * @param int|null $linkPosition Position of the link to set as value of the link select
     *
     * @return void
     */
    private function _getMessageStatusForm($links, $linkPosition = null)
    {
        $form = new Zend_Form();

        /*
         * Status select
         */
        $statusInput = new Zend_Form_Element_Select('status');
        $statusInput->addMultiOptions(
            array(
                'all' => ucfirst($this->view->translate('all status')),
                Service_Api_Object_Message::STATUS_PROCESSING => ucfirst($this->view->translate('processing messages')),
                Service_Api_Object_Message::STATUS_RUNNING => ucfirst($this->view->translate('running messages')),
                Service_Api_Object_Message::STATUS_DELIVERED => ucfirst($this->view->translate('delivered messages')),
                Service_Api_Object_Message::STATUS_UNDELIVERED =>
                    ucfirst($this->view->translate('undelivered messages')),
                Service_Api_Object_Message::STATUS_HARDBOUNCED =>
                    ucfirst($this->view->translate('permanent errors (hard bounces)')),
                Service_Api_Object_Message::STATUS_SOFTBOUNCED =>
                    ucfirst($this->view->translate('temporary errors (soft bounces)')),
                Service_Api_Object_Message::STATUS_CLICKED => ucfirst($this->view->translate('clickers')),
                Service_Api_Object_Message::STATUS_READ => ucfirst($this->view->translate('openers')),
                Service_Api_Object_Message::STATUS_OPTOUT => ucfirst($this->view->translate('unsubscribers')),
            ));
        $statusInput->removeDecorator('DtDdWrapper');
        $statusInput->removeDecorator('HtmlTag');
        $statusInput->removeDecorator('Label');

        $form->addElement($statusInput);

        if (count($links)) {
            /*
             * Link select
             */
            $linkSelect = new Zend_Form_Element_Select('link');
            $linkSelect->setValue($linkPosition);
            $linkSelect->addMultiOptions($links);

            $linkSelect->removeDecorator('DtDdWrapper');
            $linkSelect->removeDecorator('HtmlTag');
            $linkSelect->removeDecorator('Label');
            $form->addElement($linkSelect);
        }

        return $form;
    }

    /**
     * Searches and returns contact data based on email addresses
     *
     * @param int   $campaignId Campaign identifier
     * @param array $stats      Contact statistics
     *
     * @return mixed
     */
    private function _getContactInfo($campaignId, $stats)
    {
        // Initializing the campaign service
        $campaignService = Dm_Session::GetEntry(Dm_Session::SERVICE_FACTORY)->getCampaignService();

        $result = array();

        if (count($stats)) {
            // getting campaign contacts
            $contactFilter = new Service_Api_Filter_CampaignContact();
            $contactFilter->campaignId = array($campaignId);
            /* @var $contactContainer Service_Api_Object_ObjectList */
            $contactContainer = $campaignService->contactRead($contactFilter);

            if ($contactContainer->size) {
                $contacts = $contactContainer->list;

                // searching email address in array of contacts
                foreach ($stats as $status => $stat) {
                    foreach ($stat as $email) {
                        foreach ($contacts as $contact) {
                            if (strcasecmp($contact->email, $email) == 0) {
                                $result[] = array(
                                    'id' => $contact->id,
                                    'status' => $status,
                                    'civility' => $contact->civility,
                                    'firstName' => $contact->firstName,
                                    'lastName' => $contact->lastName,
                                    'mobile' => $contact->mobile,
                                    'phone' => $contact->phone,
                                    'fax' => $contact->fax,
                                    'email' => $email,
                                    'birthDate' => $contact->birthDate,
                                    'company' => $contact->company,
                                    'reference' => $contact->reference,
                                    'address1' => $contact->address1,
                                    'address1' => $contact->address1,
                                    'address2' => $contact->address2,
                                    'zipcode' => $contact->zipcode,
                                    'city' => $contact->city,
                                    'state' => $contact->state,
                                    'country' => $contact->country,
                                    'field01' => $contact->field01,
                                    'field02' => $contact->field02,
                                    'field03' => $contact->field03,
                                    'field04' => $contact->field04,
                                    'field05' => $contact->field05,
                                    'field06' => $contact->field06,
                                    'field07' => $contact->field07,
                                    'field08' => $contact->field08,
                                    'field09' => $contact->field09,
                                    'field10' => $contact->field10,
                                    'field11' => $contact->field11,
                                    'field12' => $contact->field12,
                                    'field13' => $contact->field13,
                                    'field14' => $contact->field14,
                                    'field15' => $contact->field15,
                                );
                                break;
                            }
                        }
                    }
                }
            }
        }

        return $result;
    }

    /**
     * Action called to upload an HTML file and integrate it as a new message
     *
     * Parameters :
     * - type : message type
     * - stepId : step identifier
     *
     * @return void
     */
    public function uploadTemplateAction()
    {
        try {
            $type = $this->_getParam('type', Service_Api_Object_Message::EMAIL);
            $step = Service_Api_Object_Step::LoadById($this->_getParam('stepId'));
            $form = $this->buildUploadForm();
            if ($this->getRequest()->isPost()) {
                $formData = $this->getRequest()->getPost();
                if ($form->isValid($formData)) {
                    if ($form->contentFile->receive()) {

                        //We check if the uploaded file is an HTML file or a complet zip archive
                        $fileName = $form->contentFile->getFileName();
                        $message = $step->createMessageFromHtmlFile($fileName, $type);

                        //We get the html code for the new message
                        $html = $this->view->action(
                            self::$mediaTypeToPreviewAction[$type], 'message', 'frontoffice',
                            array(
                                'message' => $message,
                                'messageId' => $message->id,
                                'page' => $message->getEditorMessage()->getHomePage()
                            )
                        );
                        $result = array(
                            'status' => '1',
                            'contentType' => 'message',
                            'messageId' => $message->id,
                            'campaignId' => $step->campaignId,
                            'html' => $html);
                    } else {
                        throw new Exception('No uploaded file to process');
                    }
                }
            } else {
                throw new Exception('Cannot call this action with GET method');
            }
        } catch (Exception $e) {
            Dm_Log::Error($e->getMessage() . '\n' . $e->getTraceAsString());
            $result = array('status' => '0', 'message' => $this->view->translate($e->getMessage()));
        }

        // Fix pour upload ajax avec IE (sinon il propose de telecharger le fichier ou lieu de retourner le json)
        $this->view->xhr = array_key_exists('HTTP_X_REQUESTED_WITH', $_SERVER) &&
            $_SERVER['HTTP_X_REQUESTED_WITH'] == 'XMLHttpRequest';
        $this->view->json = Zend_Json::encode($result);
        $this->_helper->layout->disableLayout();
    }

    /**
     * Methode permettant de construire des réponses au formulaire pour la démonstration
     *
     * @param string $formReplies   Réponse au formulaire
     * @param string $executionDate Date d'execution du message
     *
     * @return mixed Réponses du formulaire
     */
    protected function _demoFormReplies($formReplies, $executionDate)
    {
        if (count($formReplies->metas) > 0) {
            $date = new DateTime($executionDate);
            $formReplies->total += 10;
            for ($i = 0; $i < 10; $i++) {
                $values = array();
                for ($j = 0; $j < count($formReplies->metas); $j++) {
                    $values[] = (object)array('value' => $formReplies->metas[$j]->label . '  ' . ($i + 1));
                }
                $date->modify('+' . $i . 'day');
                $formReplies->datas[] = (object)array(
                    'date' => $date->format('Y/m/d'),
                    'values' => $values,
                );
            }
        }
        return $formReplies;
    }

    /**
     * Saves the template redirecting to content save, but with some additionnal treatment (cache reset)
     *
     * @return void
     */
    protected function templateChooserEditAction()
    {
        Dm_Session::SetEntry('resetNextLoad', true, __CLASS__);
        foreach (array('dateExpiration', 'datePublication') as $propName) {
            if (($propValue = $this->getRequest()->getParam($propName, null)) !== null
                    && Zend_Date::isDate($propValue, 'Y-m-d')
            ) {
                $this->getRequest()->setParam($propName, $propValue . ' 00:00:00');
            }
        }
        $this->_forward('update-data', 'content', 'frontoffice', $this->getRequest()->getParams());
    }

}
