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
class Customer extends CustomerCore
{
    public function __construct($id = null) {
        self::$definition['fields']['lastname'] = array('type' => self::TYPE_STRING, 'validate' => 'isString', 'required' => true, 'size' => 255);
        self::$definition['fields']['firstname'] = array('type' => self::TYPE_STRING, 'validate' => 'isString', 'required' => true, 'size' => 255);
        // It sets default value for customer group even when customer does not exist
        $this->id_default_group = (int) Configuration::get('PS_CUSTOMER_GROUP');
        parent::__construct($id);
    }
}