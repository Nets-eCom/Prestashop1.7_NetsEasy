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

require_once(_PS_MODULE_DIR_.'easycheckout/iso/country_iso.php');

use PrestaShop\PrestaShop\Adapter\Order\OrderPresenter;

class EasycheckoutConfirmationModuleFrontController extends ModuleFrontController
{
    public function setMedia()
    {
        parent::setMedia();
    }
    
    public function init()
    {
        parent::init();
        
        $id_cart = (int)Tools::getValue('id_cart');
        $cart = new Cart($id_cart);
        
        if (!isset($id_cart) || $id_cart <= 0) {
            PrestaShopLogger::addLog('Easy Checkout, redirected to index. Cart not set', 1, null, null, null, true);
            Tools::redirect('index.php');
        }
        $easy_secure_key = Tools::getValue('easy_secure_key');
        if (!isset($easy_secure_key) || Tools::encrypt($cart->id.'EASY_SECURE_KEY') != $easy_secure_key) {
            PrestaShopLogger::addLog('Easy Checkout, redirected to index. Not correct secure key', 1, null, null, null, true);
            Tools::redirect('index.php');
        }
        
        $cart = new Cart($id_cart);
        
        $paymentId = $this->module->retrievePaymentIdInDatabase($this->context->currency->iso_code, $id_cart);
		if (!isset($paymentId) || $paymentId == '') {
            PrestaShopLogger::addLog('Easy Checkout, redirected to index. Payment ID not set in database. Cart with ID '.$id_cart, 1, null, null, null, true);
            Tools::redirect('index.php');
        }
        
        $esyCheckout = $this->module->getPaymentInformation($paymentId);
        
        if (!isset($esyCheckout->paymentId)) {
            PrestaShopLogger::addLog('Easy Checkout, redirected to index. Payment ID from Easy checkout object not set. Cart with ID '.$id_cart, 1, null, null, null, true);
            Tools::redirect('index.php');
        }
        
        if ($cart->OrderExists() == false) {
            $id_order = $this->module->createPrestaShopOrder($id_cart, $esyCheckout);
        } else {
            $id_order = Order::getOrderByCartId((int)$cart->id);
        }
        
        $id_order = Order::getOrderByCartId($id_cart);

        if (0 == (int) $id_order) {
            sleep(1);
            $id_order = Order::getOrderByCartId($id_cart);
            if (0 == $id_order) {
                sleep(1);
                $id_order = Order::getOrderByCartId($id_cart);
                if (0 == $id_order) {
                    sleep(1);
                    $id_order = Order::getOrderByCartId($id_cart);
                }
            }
        }
		
        if (isset($id_order) && $id_order > 0) {
			$order    = new Order($id_order);
			$customer = new Customer($order->id_customer);
			unset($this->context->cookie->id_cart, $cart, $this->context->cart);
            $this->context->cart = new Cart();
            $this->context->smarty->assign(array(
                'cart_qties' => 0,
                'cart' => $this->context->cart
            ));

            $this->module->changeOrderRefInEasy($order->reference, $esyCheckout->paymentId);

            Tools::redirect('index.php?controller=order-confirmation&id_cart='.$id_cart.'&id_module='.$this->module->id.'&id_order='.$id_order.'&key='.$customer->secure_key.'&easy_reference='.$paymentId.'&easy_secure_key='.$easy_secure_key);
        } else {
            $this->context->smarty->assign(array(
                'paymentId' => $esyCheckout->orderDetails->reference
            ));

            return $this->setTemplate('module:easycheckout/views/templates/front/conf_error.tpl');
        }
	}
}