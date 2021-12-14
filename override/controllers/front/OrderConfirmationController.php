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

class OrderConfirmationController extends OrderConfirmationControllerCore
{
    public function init()
    {
        $id_cart = (int)(Tools::getValue('id_cart', 0));
        
        $id_order = Order::getOrderByCartId((int)($id_cart));
        
        $secure_key = Tools::getValue('key', false);
        
        $order = new Order((int)($id_order));
        
        if ($order->module == 'easycheckout') {
            $easy_secure_key = Tools::getValue('easy_secure_key');

            if (!isset($easy_secure_key) || $easy_secure_key != Tools::encrypt($id_cart.'EASY_SECURE_KEY')) {
                PrestaShopLogger::addLog('Easy Checkout, redirected to index. Not correct secure key', 1, null, null, null, true);
                Tools::redirect('index.php');
            }
            $customer = new Customer((int)$order->id_customer);
            if ($customer->secure_key == $secure_key) {
                $this->context->customer = $customer;
            }
        }
        
        parent::init();
    }
}
