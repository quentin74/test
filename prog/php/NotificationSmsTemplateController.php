<?php

/**
 * SettingsController.php
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
 * Description de la classe : SettingsController
 *
 * @category Digitaleo
 * @package  Application.Module.Frontoffice.Controllers
 * @author   Digitaleo <developpement@digitaleo.com>
 * @license  http://www.digitaleo.net/licence.txt Digitaleo Licence
 * @link     http://www.digitaleo.net
 */
class Frontoffice_NotificationSmsTemplateController extends Zend_Controller_Action
{
    /* @var ServiceFactory */

    protected $_contentService;

    /* @var string */
    protected $_contentType;

    /**
     * Initialisation
     *
     * @return void
     */
    public function init()
    {
        parent::init();
        $this->_contentService = Dm_Session::GetServiceFactory()->getContentService();

        $this->_contentType = 'template/notification-sms';

        // set json context
        $this->_helper->getHelper('contextSwitch')
            ->setActionContext('restAction', array('json'))
            ->initContext('json');
    }

    /**
     * Rest interface for
     *
     * @return JSON [status:1]|[status:0, error:'']
     */
    public function restAction()
    {
        // No layout
        $this->_helper->layout->disableLayout();

        try {
            switch ($this->getRequest()->getMethod()) {
                case 'GET' :
                    $response = $this->_read();
                    break;
                case 'PUT' :
                    $response = $this->_create();
                    break;
                case 'POST':
                    $response = $this->_update();
                    break;
                case 'DELETE':
                    $response = $this->_delete();
                    break;
                default:
                    throw new BadMethodCallException("Method not recognized");
            }
        }catch (BadMethodCallException $exc) {
            $this->getResponse()->setHttpResponseCode(405);
            $response = array("error" => $exc->getMessage());
        }catch (InvalidArgumentException $exc) {
            $this->getResponse()->setHttpResponseCode(401);
            $response = array("error" => $exc->getMessage());
        }catch (Exception $exc) {
            $this->getResponse()->setHttpResponseCode(500);
            $response = array("error" => $exc->getMessage());
        }

        $this->_helper->json($response, true);
    }

    /**
     * Lit les contenus de type template/notification-sms
     *
     * @return array
     */
    public function _read()
    {
        $filter = new Service_Api_Filter_Content();
        $filter->mimeType = $this->_contentType;
        $contents = $this->_contentService->contentsRead($filter);

        $newList = array();
        //$this->createMock();
        foreach ($contents->list as $index => $content) {
            $newList[$index] = new Service_Object_Template_Sms($content);
        }
        $contents->list = $newList;
        return $contents;
    }

    /**
     * Ajoute un template sms
     *
     * @return array
     */
    public function _create()
    {
        $template = json_decode($this->getRequest()->getRawBody())->message;

        $objectList = $this->_contentService->contentCreate(array(
            'name' => $template->name,
            'shareWithNetwork' => $template->shareWithNetwork,
            'description' => json_encode($template),
            'mimeType' => $this->_contentType,
            'source' => "MINE",
            'contentFile' => APPLICATION_PATH . '/../public/resources/templates/item.tnsms'
            )
        );

        if ($objectList->size != 1) {
            throw new Exception('Unable to create template');
        } else {
            return new Service_Object_Template_Sms($objectList->list[0]);
        }
    }

    /**
     * Met à jour un template sms
     *
     * @return array
     */
    public function _update()
    {
        $template = json_decode($this->getRequest()->getRawBody())->message;

        if (!$template || !is_numeric($template->id)) {
            throw new Exception('Object to save not found');
        }

        $content = array(
            'name' => $template->name,
            'description' => json_encode($template),
        );
        $filter = new Service_Api_Filter_Content();
        $filter->id = $template->id;
        $objectList = $this->_contentService->contentsUpdate($filter, $content);

        if (property_exists($objectList, 'count') && $objectList->count == '1') {
            $res = true;
        } else {
            $res = false;
        }
        return $res;
    }

    /**
     * Supprime un contenu
     *
     * @return array
     */
    public function _delete()
    {
        $template = json_decode($this->getRequest()->getRawBody())->message;
        $filter = new Service_Api_Filter_Content();
        $filter->id = $template->id;
        $contents = $this->_contentService->contentsDelete($filter);
        return $contents;
    }

}
