<?php
/**
* Prestaworks AB
*
* NOTICE OF LICENSE
*
* This source file is subject to the End User License Agreement(EULA)
* that is bundled with this package in the file LICENSE.txt.
* It is also available through the world-wide-web at this URL:
* http://license.prestaworks.se/license.html
*
* @author Prestaworks AB <info@prestaworks.se>
* @copyright Copyright Prestaworks AB (https://www.prestaworks.se/)
* @license http://license.prestaworks.se/license.html
*/

class CartController extends CartControllerCore
{
    public function initContent()
    {
        if ((int)Configuration::get('EASYCHECKOUT_REPLACE_CHECKOUT') && (int)Configuration::get('EASYCHECKOUT_ACTIVE') && Tools::getValue('action') == 'show' && Tools::getValue('ajax') != 1 && Tools::getValue('update') != 1) {
            Tools::redirect($this->context->link->getModuleLink('easycheckout', 'checkout', array(), Tools::usingSecureMode()));
            die;
        }
        parent::initContent();
    }
}