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

class EasycheckoutNotificationModuleFrontController extends ModuleFrontController
{
    public function postProcess()
    {
        sleep(2);
        if ($_SERVER['REQUEST_METHOD'] == 'POST') {

            $easy_body = file_get_contents('php://input');
            $headers = getallheaders();
            $authorization = '';
            if (isset($headers['Authorization'])) {
                $authorization = $headers['Authorization'];
            } else {
                PrestaShopLogger::addLog('Easy, could not create order from webhook. Unauthorized');
                $this->module->response(401);
            }
            $easy_body = json_decode($easy_body, JSON_PRETTY_PRINT);
            if ($easy_body !== FALSE) {
                if (isset($easy_body['id'])) {
                    $easy_information = $easy_body['data'];
                    if (isset($easy_information['paymentId'])) {
                        $paymentId = $easy_information['paymentId'];
                        $reference = $easy_information['order']['reference'];
                        $id_cart = explode('D', $reference);
                        $id_cart = $id_cart[0];
                        if ($authorization != Tools::encrypt('Easy'.$id_cart.'AUTHORIZATION')) {
                            PrestaShopLogger::addLog('Easy, could not create order with cart id '.$id_cart .' from webhook. Unauthorized');
                            $this->module->response(401);
                        }
                        if (isset($id_cart) AND (int)$id_cart > 0) {
                            $cart = new Cart($id_cart);
                            
                            if ($cart->OrderExists()) {
                                // The order has already been created
                                PrestaShopLogger::addLog('Easy, tried to create order with cart id '.$id_cart. ' from webhook but order has already been created');
                            } else {
                                $checkout = $this->module->getPaymentInformation($paymentId);
                                PrestaShopLogger::addLog('Easy, creating order from webhook. Order from cart with id '.$id_cart, 1, null, null, null, true);
                                $this->module->createPrestaShopOrder($id_cart, $checkout);
                                $id_order = Order::getOrderByCartId($id_cart);
                                if ((int) $id_order > 0) {
                                    $order = new Order($id_order);
                                    $this->module->changeOrderRefInEasy($order->reference, $paymentId);
                                }
                            }
                        }
                    }
                }
            }
        }
		
        $this->module->response(200);
    }
}