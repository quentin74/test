<?php

/**
 * ContentController.php
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
 * Description de la classe : ContentController
 *
 * @category Digitaleo
 * @package  Application.Module.Frontoffice.Controllers
 * @author   Digitaleo <developpement@digitaleo.com>
 * @license  http://www.digitaleo.net/licence.txt Digitaleo Licence
 * @link     http://www.digitaleo.net
 */
class Frontoffice_ContentController extends Zend_Controller_Action
{

    protected $_imageSize = 'medium';

    /**
     * init()
     *
     * @return void
     */
    public function init()
    {
        parent::init();
        $contentLibrary = Zend_Registry::get('contentLibrary');
        if ((!is_null($contentLibrary)) && (isset($contentLibrary->defaultImageSize))) {
            $this->_imageSize = $contentLibrary->defaultImageSize;
        }
        $contextSwitch = $this->_helper->getHelper('JsonIeContext');
        $contextSwitch->addActionContext('upload-file', 'jsonIe')
            ->initContext('jsonIe');
        $contextSwitch->addActionContext('load-data', 'json')
            ->addActionContext('delete-data', 'json')
            ->addActionContext('update-data', 'json')
            ->initContext('json');
    }

    /**
     * Page d'accueil
     *
     * @return void
     */
    public function indexAction()
    {
        Dm_Log::Debug('Start ' . __METHOD__);

        if ($this->getRequest()->getParam('type') == 'audio') {
            $this->view->contentType = 'audio';
            $this->view->headScript()->appendFile("/scripts/libraries/soundmanager2/script/soundmanager2.js");
        } elseif ($this->getRequest()->getParam('type') == 'other') {
            $this->view->contentType = 'other';
        } else {
            $this->view->contentType = 'image';
        }
        $this->_initDependencies();

        // reading sources
        $allSources = Service_Api_Object_Content::LoadSources();
        if ($this->view->contentType === 'audio') {
            $filteredSources = array();
            foreach ($allSources as $source) {
                if (isset($source['name']) && strtolower($source['name']) != 'fotolia') {
                    $filteredSources[] = $source;
                }
            }
            $this->view->sources = $filteredSources;
        } elseif ($this->view->contentType === 'other') {
            $filteredSources = array();
            foreach ($allSources as $source) {
                if (isset($source['name']) && strtolower($source['name']) != 'fotolia') {
                    $filteredSources[] = $source;
                }
            }
            $this->view->sources = $filteredSources;
        } else {
            $this->view->sources = $allSources;
        }

        // reading content
        $this->view->contentUrl = $this->view->href('load-data');
        $this->view->deleteUrl = $this->view->href('delete-data');
        $this->view->updateUrl = $this->view->href('update-data');
        $contentService = Dm_Session::GetServiceFactory()->getContentService();
        $tmp = $contentService->contentsMaxfilesize();
        $this->view->maxFileSize = $tmp->maxfilesize;
        $this->view->uploadUrl = $this->view->href(
            'upload-file', array('format' => 'jsonIe')
        );
        $this->view->recordUrl = $this->view->href('record-sound');

        Dm_Log::Debug('End ' . __METHOD__);
    }

    /**
     * Action to load content data
     *
     * @return JSON
     */
    public function loadDataAction()
    {
        $contentService = Dm_Session::GetServiceFactory()->getContentService();

        $filter = new Service_Api_Filter_Content();

        $filter->id = $this->_getParam('id');
        $filter->mimeType = $this->_getParam('mimeType');
        $filter->limit = $this->_getParam('limit');
        $filter->offset = $this->_getParam('offset');
        $filter->search = $this->_getParam('search');
        $filter->sources = $this->_getParam('sources');
        $filter->format = $this->_getParam('dim');
        $filter->properties = $this->_getParam('properties');
        $contents = $contentService->contentsRead($filter);
        $this->view->data = $contents;
    }

    /**
     * Action to delete content data
     *
     * @return JSON
     */
    public function deleteDataAction()
    {
        $contentService = Dm_Session::GetServiceFactory()->getContentService();
        $filter = new Service_Api_Filter_Content();
        $filter->id = $this->_getParam('id');
        $contents = $contentService->contentsDelete($filter);
        $this->view->data = $contents;
    }

    /**
     * Action to update content data
     *
     * -params all request params
     * -rawParams all raw params
     *
     * @return Dm_Controller_Service_Response data and meta data
     * @throws Exception
     */
    public function updateDataAction()
    {
        $contentService = Dm_Session::GetServiceFactory()->getContentService();
        /* @var $contentService Service_Api_Handler_Content_Baseo */
        $filter = new Service_Api_Filter_Content();
        $filter->id = $this->_getParam('id');

        $data = array();
        foreach (array('name', 'description', 'tags', 'shareWithNetwork', 'datePublication', 'dateExpiration')
                 as $attrName) {
            if (($val = $this->_getParam($attrName)) !== null) {
                $data[$attrName] = $val;
            }
        }
        if (sizeof($data) > 0) {
            try {
                $count = $contentService->contentsUpdate($filter, $data);
                $this->view->assign(
                    array_merge(
                        get_object_vars($count) ?: array(),
                        array('message' => ucfirst($this->view->translate('content updated successfully'))))
                );
            } catch (Mk_ApiException $e) {
                $this->view->error = $this->view->getHelper('errors')->translateFullException($e);
            }
        }
    }

    /**
     * Page de contenu audio
     *
     * @return void
     */
    public function audioAction()
    {
        Dm_Log::Debug('Start ' . __METHOD__);
        $this->getRequest()->setParam('type', 'audio');
        $this->_forward('index');
        Dm_Log::Debug('End ' . __METHOD__);
    }

    /**
     * Page de contenu de tous les fichiers hors images et audio
     *
     * @return void
     */
    public function otherAction()
    {
        Dm_Log::Debug('Start ' . __METHOD__);
        $this->getRequest()->setParam('type', 'other');
        $this->_forward('index');
        Dm_Log::Debug('End ' . __METHOD__);
    }

    /**
     * Formulaire d'upload d'image
     *
     * @return void
     */
    public function uploadFileAction()
    {
        Dm_Log::Debug('Start ' . __METHOD__);
        $importPath = Dm_Config::GetPath("tmp");
        $fileName = $_FILES['contentFile']['name'];
        $fileTitle = $this->_getParam('name') ? $this->_getParam('name') : $fileName;
        if (move_uploaded_file($_FILES['contentFile']['tmp_name'], $importPath . $fileName)) {
            $contentService = Dm_Session::GetServiceFactory()->getContentService();
            $unzipContent = $this->_getParam('unzip', 0);

            /* @var $contentService Service_Api_Handler_Content_Interface */
            if ($unzipContent == 1) {
                Dm_Log::Debug("Importing zip file...");
                $objectList = $contentService->contentArchiveCreate(
                    array(
                        'name' => $fileTitle,
                        'contentFile' => $importPath . $fileName,
                        'tags' => $this->_getParam('tags'),
                        'shareWithNetwork' => $this->_getParam('affiliate',
                            0),
                    )
                );
            } else {
                Dm_Log::Debug("Importing classic file...");
                $objectList = $contentService->contentCreate(
                    array(
                        'name' => $fileTitle,
                        'contentFile' => $importPath . $fileName,
                        'tags' => $this->_getParam('tags'),
                        'shareWithNetwork' => $this->_getParam('affiliate', 0),
                    )
                );
            }
            Dm_Log::Debug("Linst of imported file(s) :");
            Dm_Log::Debug($objectList);
            if ($objectList->size < 1) {
                throw new Exception('Error uploading file');
            } else {
                $this->view->objects = $objectList->list;
            }
        }
        Dm_Log::Debug('End ' . __METHOD__);
        $this->view->data = "done";
    }

    /**
     * Initializing JS dependencies for the content library
     *
     * @return void
     */
    protected function _initDependencies()
    {
        // Loading jsonp library for cross-domain AJAX requests
        $this->view->headScript()->appendFile('/scripts/jquery/plugins/jsonp/jquery.jsonp-2.4.0.min.js?' .
            SCRIPT_VERSION_JS);
//        $this->view->headScript()->appendFile('/scripts/libraries/content/plugins/jqueryForm/jquery.form.js');
    }

    /**
     * Action to record a Voicemail message
     *
     * Params :
     * - recipient : string recipient
     *
     * @throws Exception
     * @return boolean
     * @throws Exception
     */
    public function recordSoundAction()
    {
        $this->_helper->viewRenderer->setNoRender(true);
        $recipient = $this->_getParam('recipient');
        if ($recipient) {
            $contentService = Dm_Session::GetServiceFactory()->getContentService();
            $contentName = 'Enregistrement audio ' . date('d/m/Y H:i:s');
            $return = $contentService->contentsRecord($recipient, $contentName);
            if ($return->success != 1) {
                throw new Exception('Error during record');
            }
            $this->_helper->json->sendJson(array('contentName' => $contentName));
            return true;
        }
        return false;
    }

}
