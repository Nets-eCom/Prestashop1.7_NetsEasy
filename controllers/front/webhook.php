<?php
/**
 * Copyright since 2007 PrestaShop SA and Contributors
 * PrestaShop is an International Registered Trademark & Property of PrestaShop SA
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Academic Free License version 3.0
 * that is bundled with this package in the file LICENSE.md.
 * It is also available through the world-wide-web at this URL:
 * https://opensource.org/licenses/AFL-3.0
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@prestashop.com so we can send you a copy immediately.
 *
 * @author    PrestaShop SA and Contributors <contact@prestashop.com>
 * @copyright Since 2007 PrestaShop SA and Contributors
 * @license   https://opensource.org/licenses/AFL-3.0 Academic Free License version 3.0
 */

class netseasyWebhookModuleFrontController extends ModuleFrontController {
    public $logger;

    public function __construct() {
        parent::__construct();
        $this->logger = new FileLogger();
        $this->logger->setFilename(_PS_ROOT_DIR_ . "/var/logs/nets_webhook.log");

        // do not display header and footer as this is api call
        $this->content_only = true;

        // IF NOT EXISTS ps_nets_payment_status table create it!!
        \Db::getInstance()->execute("CREATE TABLE IF NOT EXISTS `" . _DB_PREFIX_ . "nets_payment_status` (
		`id` int(10) unsigned NOT NULL auto_increment,
		`order_id` varchar(50) default NULL,
		`payment_id` varchar(50) default NULL,
		`status` varchar(50) default NULL,
		`updated` int(2) unsigned default '0',
		`created` datetime NOT NULL,
		`timestamp` timestamp NOT NULL default CURRENT_TIMESTAMP on update CURRENT_TIMESTAMP,
		PRIMARY KEY (`id`)
		)");
    }

    /**
     * @inheritdoc
     */
    public function checkAccess() {
        $authorizationString = Configuration::get('NETS_WEBHOOK_AUTHORIZATION');
        if (empty($authorizationString)) {
            return true;
        }

        $this->logger->logInfo("Validate HTTP_AUTHORIZATION headers: ". (int) $authorizationString === $_SERVER['HTTP_AUTHORIZATION']);

        return $authorizationString === $_SERVER['HTTP_AUTHORIZATION'];
    }

    public function postProcess() {
        $orderId = '';
        $json = '';
        $hookResponse = file_get_contents('php://input');
        if(isset($hookResponse) && !empty($hookResponse)) {
            $json = json_decode($hookResponse);
        }

        if ($json) {
            sleep(40); // @todo ??
            $paymentId = $json->data->paymentId;
            $event = $json->event;
            $this->logger->logInfo("[".$paymentId."][".$event."] Response : ".$hookResponse."\n");

            $webhookOrder = $this->getWebhookOrder($event);
            if ($webhookOrder >= 99) {
                $this->logger->logError("[".$paymentId."][".$event."] Unknown event : ".$hookResponse."\n");

                return;
            }

            //Insert or update order status
            $orderResult = \Db::getInstance()->executeS(
                "SELECT id_order FROM `" . _DB_PREFIX_ . "orders` WHERE note =  '" . pSQL($paymentId) . "' limit 0,1"
            );

            if (!empty(\Db::getInstance()->numRows($orderResult))) {
                $orderId = reset($orderResult)['id_order'];
            }

            $statusResult = \Db::getInstance()->executeS(
                "SELECT id, status FROM `" . _DB_PREFIX_ . "nets_payment_status` WHERE  payment_id =  '" . pSQL($paymentId) . "' limit 0,1"
            );

            if (empty(\Db::getInstance()->numRows($statusResult))) {
                $query = "insert into `" . _DB_PREFIX_ . "nets_payment_status` (`order_id`, `payment_id`,  `status`, `created`) "
                    . "values ('" . (int)$orderId . "', '" . pSQL($paymentId) . "', '" . $webhookOrder . "', now())";
                \Db::getInstance()->execute($query);
            } else {

                //compare with previous $order and update only if it is greater than previous
                $status = reset($statusResult)['status'];
                if ($webhookOrder > $status) {
                    \Db::getInstance()->execute(
                        "UPDATE `" . _DB_PREFIX_ . "nets_payment_status` SET status = $webhookOrder, order_id = '" . (int)$orderId . "', updated = '1' where  payment_id =  '" . pSQL($paymentId) . "' "
                    );
                }
            }
        }
    }

    /**
     * Override display as we want to only return empty page with 200 code
     */
    public function display() {
        return true;
    }

    /**
     * controlled sorting order, since sorting a webhook by timestamp in general tend to be unreliable
     */
    private function getWebhookOrder(string $event): int {
        switch ($event) {
            case 'payment.created':
                return 0;
            case 'payment.checkout.completed':
                return 1;
            case 'payment.reservation.created':
                return 2;
            case 'payment.reservation.created.v2':
                return 3;
            case 'payment.cancel.created':
                return 4;
            case 'payment.charge.created':
                return 5;
            case 'payment.charge.created.v2':
                return 6;
            case 'payment.refund.completed':
                return 7;
            case 'payment.charge.failed':
                return 8;

            default:
                return 99;
        }
    }
}
