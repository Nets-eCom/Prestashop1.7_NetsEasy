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

if (!$easy_settings) {
    Tools::redirect('index.php?controller=order&step=1');
}

$tmp_address = new Address((int)$cart->id_address_delivery);
$country     = new Country($tmp_address->id_country);

if ($easy_settings['purchase_country'] == 'SE') {
    if ($country->iso_code != 'SE') {
        $id_address_delivery = Configuration::get('EASY_SE_ADDRESS');
        if (!isset($id_address_delivery) OR ($id_address_delivery <= 0)) {
            $this->module->installAddress('SE', 'EASY_SE_ADDRESS');
        }
        $id_address_delivery = Configuration::get('EASY_SE_ADDRESS');
        $this->context->cart->id_address_delivery = $id_address_delivery;
        $this->context->cart->id_address_invoice  = $id_address_delivery;
        $this->context->cart->update();
        
        $update_sql = "UPDATE "._DB_PREFIX_."cart_product SET id_address_delivery=".(int)$id_address_delivery." WHERE id_cart=".(int)$this->context->cart->id;
        Db::getInstance()->execute($update_sql);
        
        $link = $this->context->link->getModuleLink($this->module->name, 'checkout', array(), Tools::usingSecureMode());
        Tools::redirect($link);
    }
} elseif ($easy_settings['purchase_country'] == 'NO') {
    if ($country->iso_code != 'NO') {
        $id_address_delivery = Configuration::get('EASY_NO_ADDRESS');
        if (!isset($id_address_delivery) OR ($id_address_delivery <= 0)) {
            $this->module->installAddress('NO', 'EASY_NO_ADDRESS');
        }
        $id_address_delivery = Configuration::get('EASY_NO_ADDRESS');
        $this->context->cart->id_address_delivery = $id_address_delivery;
        $this->context->cart->id_address_invoice  = $id_address_delivery;
        $this->context->cart->update();
        
        $update_sql = "UPDATE "._DB_PREFIX_."cart_product SET id_address_delivery=".(int)$id_address_delivery." WHERE id_cart=".(int)$this->context->cart->id;
        Db::getInstance()->execute($update_sql);
        
        $link = $this->context->link->getModuleLink($this->module->name, 'checkout', array(), Tools::usingSecureMode());
        Tools::redirect($link);
    }
    
} elseif ($easy_settings['purchase_country'] == 'DK') {
    if ($country->iso_code != 'DK') {
        $id_address_delivery = Configuration::get('EASY_DK_ADDRESS');
        if (!isset($id_address_delivery) OR ($id_address_delivery <= 0)) {
            $this->module->installAddress('DK', 'EASY_DK_ADDRESS');
        }
        $id_address_delivery = Configuration::get('EASY_DK_ADDRESS');
        $this->context->cart->id_address_delivery = $id_address_delivery;
        $this->context->cart->id_address_invoice  = $id_address_delivery;
        $this->context->cart->update();
        
        $update_sql = "UPDATE "._DB_PREFIX_."cart_product SET id_address_delivery=".(int)$id_address_delivery." WHERE id_cart=".(int)$this->context->cart->id;
        Db::getInstance()->execute($update_sql);
        
        $link = $this->context->link->getModuleLink($this->module->name, 'checkout', array(), Tools::usingSecureMode());
        Tools::redirect($link);
    }
    
} else {
    $purchase_country = $easy_settings['purchase_country'];
    $country_iso_code = $country->iso_code;
    
    if ($purchase_country != $country_iso_code) {
        if ($this->context->cart->id_address_delivery == Configuration::get('EASY_'.$purchase_country.'_ADDRESS')) {
            $this->module->installAddress($purchase_country, 'EASY_'.$purchase_country.'_ADDRESS');
        }
        
        $id_address_delivery = Configuration::get('EASY_'.$purchase_country.'_ADDRESS');
        if (!isset($id_address_delivery) OR ($id_address_delivery <= 0)) {
            $this->module->installAddress($purchase_country, 'EASY_'.$purchase_country.'_ADDRESS');
        }
        $id_address_delivery = Configuration::get('EASY_'.$purchase_country.'_ADDRESS');
        $this->context->cart->id_address_delivery = $id_address_delivery;
        $this->context->cart->id_address_invoice  = $id_address_delivery;
        $this->context->cart->update();
        
        $update_sql = "UPDATE "._DB_PREFIX_."cart_product SET id_address_delivery=".(int)$id_address_delivery." WHERE id_cart=".(int)$this->context->cart->id;
        Db::getInstance()->execute($update_sql);
        
        $link = $this->context->link->getModuleLink($this->module->name, 'checkout', array(), Tools::usingSecureMode());
        Tools::redirect($link);
    }
}