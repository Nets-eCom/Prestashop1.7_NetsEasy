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

function upgrade_module_1_2_0($module) {
    
    try {
        $sql = 'ALTER TABLE `'._DB_PREFIX_.'easycheckout_orders` ADD `currency_iso` text AFTER `paymentId`';
        Db::getInstance()->execute($sql);
        
    } catch (Exception $e) {
        
    }
    
    return true;
}
