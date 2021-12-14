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
class Address extends AddressCore
{
    public function __construct($id_address = null, $id_lang = null) {
        self::$definition['fields']['lastname'] = array('type' => self::TYPE_STRING, 'validate' => 'isString', 'required' => true, 'size' => 255);
        self::$definition['fields']['firstname'] = array('type' => self::TYPE_STRING, 'validate' => 'isString', 'required' => true, 'size' => 255);
        self::$definition['fields']['address1'] = array('type' => self::TYPE_STRING, 'validate' => 'isString', 'required' => true, 'size' => 255);
        self::$definition['fields']['address2'] = array('type' => self::TYPE_STRING, 'validate' => 'isString', 'size' => 255);
        self::$definition['fields']['city'] = array('type' => self::TYPE_STRING, 'validate' => 'isString', 'required' => true, 'size' => 255);
        parent::__construct($id_address);

        /* Get and cache address country name */
        if ($this->id) {
            $this->country = Country::getNameById($id_lang ? $id_lang : Configuration::get('PS_LANG_DEFAULT'), $this->id_country);
        }
        
    }
}