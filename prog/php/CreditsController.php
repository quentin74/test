<?php

/**
 * CreditsController.php
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
 * Description de la classe : CreditsController
 *
 * @category Digitaleo
 * @package  Application.Module.Frontoffice.Controllers
 * @author   Digitaleo <developpement@digitaleo.com>
 * @license  http://www.digitaleo.net/licence.txt Digitaleo Licence
 * @link     http://www.digitaleo.net
 */
class Frontoffice_CreditsController extends Zend_Controller_Action
{
    /**
     * Page d'acceuil
     *
     * @return void
     */
    public function indexAction()
    {
        $this->_helper->layout->setLayout('dash-layout');
    }

    /**
     * Met en session une valeur afin de ne pas afficher le bandeau de rappel du crédit gratuit à dépenser
     * a chaque changement de page. Sera donc afficher a chaque connexion.
     * @throws Exception
     *
     * @return void
     */
    public function ajaxHideRememberFreeEmailsAction()
    {
        $this->_helper->layout->disableLayout();
        $this->_helper->viewRenderer->setNoRender(true);
        Dm_Session::SetEntry('hideRememberFreeEmails', 1);
    }
}
