<?php
class netseasyWebhookModuleFrontController extends ModuleFrontController
{
    public $logger;

    public function __construct()
    {
        parent::__construct();
        $this->logger = new FileLogger();
        $this->logger->setFilename(_PS_ROOT_DIR_ . "/var/logs/nets_webhook.log");

        // IF NOT EXISTS ps_nets_payment_status table create it!!
        DB::getInstance()->execute("CREATE TABLE IF NOT EXISTS `" . _DB_PREFIX_ . "nets_payment_status` (
		`id` int(10) unsigned NOT NULL auto_increment,		
		`order_id` varchar(50) default NULL,
		`payment_id` varchar(50) default NULL,
		`status` varchar(50) default NULL,	
		`updated` int(2) unsigned default '0',
		`created` datetime NOT NULL,
		`timestamp` timestamp NOT NULL default CURRENT_TIMESTAMP on update CURRENT_TIMESTAMP,
		PRIMARY KEY (`id`)
		)");

        $orderId = '';
        $json = '';
        $hookResponse = file_get_contents('php://input');
        if(isset($hookResponse) && !empty($hookResponse)) {
            $json = json_decode($hookResponse);
        }
        
        if ($json) {
            sleep(40);
            $this->logger->logInfo('----In webhooks----');
            $this->logger->logInfo($hookResponse);
            $paymentId = $json->data->paymentId;
            $event = $json->event;
            $response = preg_replace('/\s/', '', (json_encode($json->data)));
            $timestamp = $json->timestamp;
            $now = date('Y-m-d H:i:s');
            // preparping a controlled sorting order, since sorting a webhook by timestamp in general tend to be unreliable.
            if ($event == 'payment.created') {
                $order = 0;
            }
            if ($event == 'payment.checkout.completed') {
                $order = 1;
            }
            if ($event == 'payment.reservation.created') {
                $order = 2;
            }
            if ($event == 'payment.reservation.created.v2') {
                $order = 3;
            }
            if ($event == 'payment.cancel.created') {
                $order = 4;
            }
            if ($event == 'payment.charge.created') {
                $order = 5;
            }
            if ($event == 'payment.charge.created.v2') {
                $order = 6;
            }
            if ($event == 'payment.refund.completed') {
                $order = 7;
            }
            if ($event == 'payment.charge.failed') {
                $order = 8;
            }

            //Insert or update order status
            $orderResult = DB::getInstance()->executeS(
                "SELECT id_order FROM `" . _DB_PREFIX_ . "orders` WHERE note =  '" . $paymentId . "' limit 0,1"
            );

            if (!empty(DB::getInstance()->numRows($orderResult))) {
                $orderId = reset($orderResult)['id_order'];
            }

            $statusResult = DB::getInstance()->executeS(
                "SELECT id, status FROM `" . _DB_PREFIX_ . "nets_payment_status` WHERE  payment_id =  '" . $paymentId . "' limit 0,1"
            );

            if (empty(DB::getInstance()->numRows($statusResult))) {
                $query = "insert into `" . _DB_PREFIX_ . "nets_payment_status` (`order_id`, `payment_id`,  `status`, `created`) "
                    . "values ('" . $orderId . "', '" . $paymentId . "', '" . $order . "', now())";
                DB::getInstance()->execute($query);
            } else {

                //compare with previous $order and update only if it is greater than previous				
                $status = reset($statusResult)['status'];
                if ($order > $status) {
                    DB::getInstance()->execute(
                        "UPDATE `" . _DB_PREFIX_ . "nets_payment_status` SET status = $order, order_id = '" . $orderId . "', updated = '1' where  payment_id =  '" . $paymentId . "' "
                    );
                }
            }
        }
    }

  
}
