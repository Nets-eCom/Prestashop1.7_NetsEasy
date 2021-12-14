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

class AdminChargeSubscriptionController extends ModuleAdminController
{
    public function __construct()
    {
        parent::__construct();
    }
    
    public function ajaxProcessChargeSubscription()
    {
        $subscriptionId = Tools::getValue('subscriptionId');
        $id_order = Tools::getValue('id_order');
        
        $order = new Order($id_order);
        $id_cart = $order->id_cart;
        $id_currency = $order->id_currency;
        $currency = new Currency($id_currency);
        $currency_iso = $currency->iso_code;
        
        $easy_module = Module::getInstanceByName('easycheckout');
        
        $amount = $this->module->getOrderAmount($id_cart);
        $currency = $currency_iso;
        
        $reference = Db::getInstance()->getValue("SELECT id_purchase FROM "._DB_PREFIX_."easycheckout_orders WHERE id_order = ".$id_order."");
        
        $merchantNumber = null;
        
        $items = $easy_module->getOrderItemsList($id_cart);
        
        $body_of_request = array(
            'order' => array(
                'items' => $items,
                'amount' => $amount,
                'currency' => $currency_iso,
                'reference' => $reference
            ),
            'merchantNumber' => $merchantNumber
        );
        
        $charge_subscription_result = $this->module->chargeSubscription($body_of_request, $subscriptionId, $id_cart, $currency_iso);
        
        $charge_id = '';
        
        $charge_id_check = @$charge_subscription_result['chargeId'];
        
        if (isset($charge_id_check) AND $charge_id_check != '')  {
            $charge_id = $charge_id_check;
            $code = 'OK';
        } else {
            $charge_id = $this->l('An error occurred');
            $code = 'NOK';
        }
        
        $to_return = array('charge_id' => $charge_id, 'code' => $code);
        
        $this->content = Tools::jsonEncode($to_return);
    }
    
    public function ajaxProcessChangeChargeMessage()
    {
        $subscriptionId = Tools::getValue('subscriptionId');
        $id_order = (int)Tools::getValue('id_order');
        $message = Tools::getValue('message');
        
        $sql = "UPDATE "._DB_PREFIX_."easycheckout_orders SET charge_message = '".$message."' WHERE id_order = ".$id_order."";
        
        Db::getInstance()->execute($sql);
        
        $to_return = array('result' => 'ok');
        
        $this->content = Tools::jsonEncode($to_return);
    }
}