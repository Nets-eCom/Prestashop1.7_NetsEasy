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

$countries_iso = array();
require(_PS_MODULE_DIR_.'easycheckout/iso/country_iso.php');

if (Tools::getValue('changeEasyCountry') != '') {
    // Get iso-code
    $country_iso = $countries_iso[Tools::getValue('changeEasyCountry')];
    
    $id_address_delivery = Configuration::get('EASY_'.$country_iso.'_ADDRESS');
    
    if (!isset($id_address_delivery) OR ($id_address_delivery <= 0)) {
        $this->module->installAddress($country_iso, 'EASY_'.$country_iso.'_ADDRESS');
    }
    
    $id_address_delivery = Configuration::get('EASY_'.$country_iso.'_ADDRESS');
    
    $this->context->cart->id_address_delivery = $id_address_delivery;
    $this->context->cart->id_address_invoice  = $id_address_delivery;
    $this->context->cart->update();
    
    $update_sql = "UPDATE "._DB_PREFIX_."cart_product SET id_address_delivery = ".(int)$id_address_delivery." WHERE id_cart=".(int)$this->context->cart->id;
    Db::getInstance()->execute($update_sql);
    
    $link = $this->context->link->getModuleLink($this->module->name, 'checkout', array(), Tools::usingSecureMode());
    Tools::redirect($link);
}