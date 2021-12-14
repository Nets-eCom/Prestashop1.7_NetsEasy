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

$sql = array();

$sql[] = 'CREATE TABLE IF NOT EXISTS `'._DB_PREFIX_.'easycheckout_orders` (
    `id_cart` int(11) NOT NULL,
    `paymentId` varchar(50) DEFAULT null,
	`id_order` int(11) NOT NULL DEFAULT \'0\',
    `currency_iso` text,
    `is_subscription` int(11) DEFAULT null,
    `subscription_id` varchar(255) DEFAULT null,
    `charge_message` text DEFAULT null,
    `id_purchase` varchar(60) DEFAULT null,
    `payment_status` varchar(20) DEFAULT null,
    `added` datetime DEFAULT null,
    `updated` datetime DEFAULT null,
    `ps_created` tinyint(4) NOT NULL DEFAULT \'0\',
    PRIMARY KEY  (`id_cart`)
) ENGINE='._MYSQL_ENGINE_.' DEFAULT CHARSET=utf8;';

foreach ($sql as $query) {
    if (Db::getInstance()->execute($query) == false) {
        return false;
    }
}
