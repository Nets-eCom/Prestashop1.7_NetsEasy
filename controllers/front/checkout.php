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

use PrestaShop\PrestaShop\Adapter\Cart\CartPresenter;

class EasycheckoutCheckoutModuleFrontController extends ModuleFrontController
{
    public function setMedia()
    {
        parent::setMedia();
        
        $this->context->controller->addJS(_MODULE_DIR_.$this->module->name.'/views/js/checkout.js');
        $this->context->controller->addCSS(_MODULE_DIR_.$this->module->name.'/views/css/checkout.css', 'all');
        
        Media::addJsDef(array('easycheckout_url' => $this->context->link->getModuleLink($this->module->name, 'checkout', array(), Tools::usingSecureMode())));
    }
    
    public function postProcess()
    {
        require_once dirname(__FILE__).'/../../libraries/generalpostprocess.php';
    }
    
    public function initContent()
    {
        parent::initContent();
        
        $cart = $this->context->cart;
        
        if (Tools::getIsset('restartcheckout')) {
            $cart->id_address_delivery = 0;
            $cart->update();
            $easylink = $this->context->link->getModuleLink('easycheckout', 'checkout', array(), Tools::usingSecureMode());
            Tools::redirect($easylink);
        }
        if ($cart->id <= 0) {
            Tools::redirect($this->context->shop->getBaseURL());
        }

        if (!$cart->checkQuantities()) {
            $this->context->smarty->assign(array(
                'available_product_easy' => 'no'
            ));
        } else {
            $this->context->smarty->assign(array(
                'available_product_easy' => 'yes'
            ));
        }

        foreach ($cart->getProducts() as $product) {
            if (!$product['active']) {
                Tools::redirect($this->context->shop->getBaseURL());
            }
        }
        
        $id_shop  = (int)$this->context->shop->id;
        $currency = new Currency($cart->id_currency);
        
        if (!in_array($currency->iso_code, $this->module->limited_currencies)) {
            Tools::redirect($this->context->shop->getBaseURL());
        }
        
        $id_address_delivery = $cart->id_address_delivery;
        if ($id_address_delivery > 0) {
            $easy_address = new Address($id_address_delivery);
            $id_country_delivery = $easy_address->id_country;
            $country = new Country($id_country_delivery, $cart->id_lang);
        } else {
            $country = new Country((int)Configuration::get('PS_COUNTRY_DEFAULT'), $cart->id_lang);
        }
        
        $countries_iso = array();
        require(_PS_MODULE_DIR_.'easycheckout/iso/country_iso_easy.php');
        $country_iso  = $countries_iso[$country->iso_code];
        
        $currency_iso = $currency->iso_code;
        $easy_settings = array();
        $easy_settings['purchase_country']  = $country->iso_code;
        $easy_settings['purchase_currency'] = $currency_iso;
        
        require_once dirname(__FILE__).'/../../libraries/easyredirectcheck.php';
        
        $currency  = new Currency($cart->id_currency);
        $language  = new Language($cart->id_lang);
        
        // $id_address_delivery = $cart->id_address_delivery;
        // $easy_address        = new Address($id_address_delivery);
        
        // $id_country_delivery = $easy_address->id_country;
        // $country             = new Country($id_country_delivery, $cart->id_lang);
        
        if (!$cart->getDeliveryOption(null, true)) {
            $cart->setDeliveryOption($cart->getDeliveryOption());
            $cart->update();
        }

        $this->context->smarty->assign(array(
            'easy_current_country' => $country_iso,
            'easy_cart_id' => $cart->id
        ));
        
        if (Tools::getIsset('ajax') && (int)Tools::getValue('ajax') == 1) {
            $this->ajax = true;
        } else {
            $this->ajax = false; 
        }
        
        if (!isset($cart->id)) {
            if ($this->ajax) {
                die('0');
            } else {
                Tools::redirect($this->context->shop->getBaseURL());
            }
        }
        
        if ($cart->orderExists()) {
            if ($this->ajax) {
                die('0');
            } else {
                Tools::redirect($this->context->shop->getBaseURL());
            }
        }
        
        if ($cart->nbProducts() < 1) {
            if ($this->ajax) {
                die('0');
            } else {
                Tools::redirect($this->context->shop->getBaseURL());
            }
        }
        
        $free_shipping = false;
        foreach ($cart->getCartRules() as $rule) {
            if ($rule['free_shipping']) {
                $free_shipping = true;
                break;
            }
        }
        
        if (isset($this->ajax) && $this->ajax) {
            if (Tools::isSubmit('get_delivery_html')) {
                $del_list = $cart->getDeliveryOptionList($country);
                
                $this->prepareLeftToGet($cart);
                
                $this->context->smarty->assign(array(
                    'delivery_option_list' => $del_list,
                    'giftAllowed'          => (int)(Configuration::get('PS_GIFT_WRAPPING')),
                    'free_shipping'        => $free_shipping,
                    'delivery_option'      => $cart->getDeliveryOption($country, false, false),
                ));
                
                $minimal_purchase = Tools::convertPrice((floatval(Configuration::get('PS_PURCHASE_MINIMUM'))), $currency);
                
                if ($this->context->cart->getOrderTotal(false, Cart::ONLY_PRODUCTS) < $minimal_purchase && $minimal_purchase > 0) {
                    die('PS_PURCHASE_FAILED');
                } else {
                    die($this->context->smarty->fetch('module:easycheckout/views/templates/front/deliveryoptions.tpl'));
                }
            }
            
            if (Tools::isSubmit('save_order_message')) {
                $messageContent = urldecode(Tools::getValue('message'));
                $this->updateMessage($messageContent, $this->context->cart);
                die(json_encode(Message::getMessageByCartId($this->context->cart->id)));
            }
            
            if (Tools::isSubmit('checkProductsAndCarriers')) {
                $return_status_product = '';
                $return_status_carrier = '';
                if (!$cart->checkQuantities()) {
                    $return_status_product = 'NOK';
                } else {
                    $return_status_product = 'OK';
                }
                $del_list = $cart->getDeliveryOptionList($country);
                if (is_array($del_list)) {
                    if (empty($del_list)) {
                        $return_status_carrier = 'NOK';
                    } else {
                        $return_status_carrier = 'OK';
                    }
                } else {
                    $return_status_carrier = 'NOK';
                }
            
                $return = array();
                $retun['return_status_product'] = $return_status_product;
                $retun['return_status_carrier'] = $return_status_carrier;
                
                $return = json_encode($retun, JSON_PRETTY_PRINT);
                
                echo $return;
                die;
            }

            if (Tools::isSubmit('change_gift') || Tools::isSubmit('change_gift_message')) {
                $message = urldecode(Tools::getValue('gift_message'));
                $gift    = (int)Tools::getValue('gift');
                if (Tools::isSubmit('change_gift')) {
                    $this->context->cart->gift = $gift;
                    if ($gift == 0) {
                        $message = '';
                    }
                } else if (Tools::isSubmit('change_gift_message')) {
                    $this->context->cart->gift = 1;
                }  
                if (Validate::isMessage($message)) {
                    $this->context->cart->gift_message = strip_tags($message);
                } else {
                    $this->context->cart->gift_message = '';
                }
                $this->context->cart->update();
                $result = array(
                    'gift'    => $this->context->cart->gift,
                    'message' => $this->context->cart->gift_message,
                );
                die(json_encode($result));
            }
            
            if (Tools::isSubmit('change_delivery_option')) {
                if (Tools::getIsset('new_delivery_option')) {
                    $delivery_option = Tools::getValue('new_delivery_option');
                    if ($this->validateDeliveryOption($delivery_option)) {
                        $cart->setDeliveryOption($delivery_option);
                    }
                    
                    $cart->update();
                    
                    CartRule::autoRemoveFromCart($this->context);
                    CartRule::autoAddToCart($this->context);
                    $cart->save();
                }
                die('1');
            }
            
            if (Tools::isSubmit('update_easy_iframe')) {
                $minimal_purchase = Tools::convertPrice((floatval(Configuration::get('PS_PURCHASE_MINIMUM'))), $currency);
                if ($this->context->cart->getOrderTotal(false, Cart::ONLY_PRODUCTS) < $minimal_purchase && $minimal_purchase > 0) {
                    die('PS_PURCHASE_FAILED');
                }
                die($this->updateEasyIframe($this->context->currency, $this->context->cart->id));
            }
        }
        
        $del_opt  = $cart->getDeliveryOption($country, false, false);
        $del_list = $cart->getDeliveryOptionList($country);
        
        if (is_array($del_list)) {
            if (empty($del_list)) {
                $this->context->smarty->assign(array(
                    'available_carrier_easy' => 'no'
                ));
            } else {
                $this->context->smarty->assign(array(
                    'available_carrier_easy' => 'yes'
                ));
            }
        } else {
            $this->context->smarty->assign(array(
                'available_carrier_easy' => 'no'
            ));
        }
        $easyrestartlink = $this->context->link->getModuleLink('easycheckout', 'checkout', array('restartcheckout' => 1), Tools::usingSecureMode());
        $this->context->smarty->assign('easyrestartlink', $easyrestartlink);
                
        $wrapping_fees_tax_inc = $cart->getGiftWrappingPrice(true);
        
        $this->prepareLeftToGet($cart);
        
        $minimal_purchase = Tools::convertPrice((floatval(Configuration::get('PS_PURCHASE_MINIMUM'))), $this->context->currency);
		$error_array = array();
		
		if ($this->context->cart->getOrderTotal(false, Cart::ONLY_PRODUCTS) < $minimal_purchase && $minimal_purchase > 0) {
            $minimal_purchase = Tools::displayPrice($minimal_purchase);
			$error_array[] = $this->trans('A minimum purchase of %sum% excl tax is required in order to complete the order.', ['%sum%' => $minimal_purchase], 'Modules.easycheckout.errors');
			$this->context->smarty->assign(array(
				'errorMessages' => $error_array,
				'easycheckout_linkback' => $this->context->shop->getBaseURL()
                )
            );
            $presenter = new CartPresenter();
            $presented_cart = $presenter->present($this->context->cart, true);
            
            $this->context->smarty->assign(array(
                'cart' => $presented_cart,
                'static_token' => Tools::getToken(false),
            ));
            return $this->setTemplate('module:easycheckout/views/templates/front/error_checkout.tpl');
        }
        
        $presenter = new CartPresenter();
        $presented_cart = $presenter->present($this->context->cart, true);
        
        $this->context->smarty->assign(array(
            'cart' => $presented_cart,
            'static_token' => Tools::getToken(false),
        ));
        
        $this->assignGiftAndMessageInformation();
        
        $confirmation_url = $this->context->link->getModuleLink('easycheckout', 'confirmation', array('id_cart' => $cart->id, 'easy_secure_key' => Tools::encrypt($cart->id.'EASY_SECURE_KEY')));
        
        if ((int)Configuration::get('EASYCHECKOUT_LIVE_MODE') == 0) {
            $checkoutKey = Configuration::get('EASYCHECKOUT_CHECKOUT_KEY_TEST');
        } else {
            $checkoutKey = Configuration::get('EASYCHECKOUT_CHECKOUT_KEY');
        }

        $easyCheckoutLanguages = array(
            'da' => 'da-DK',
            'sv' => 'se-SE',
            'no' => 'nb-NO',
            'nn' => 'nb-NO',
            'nb' => 'nb-NO',
            'de' => 'de-DE',
            'pl' => 'pl-PL',
            'fr' => 'fr-FR',
            'es' => 'es-ES',
            'it' => 'it-IT',
            'nl' => 'nl-NL',
            'fi' => 'fi-FI',
            'en' => 'en-GB',
            'gb' => 'en-GB'
        );
        
        if (isset($easyCheckoutLanguages[$language->iso_code])) {
            $checkoutLanguage = $easyCheckoutLanguages[$language->iso_code];
        } else {
            $checkoutLanguage = 'en-GB';
        }
        
        $this->context->smarty->assign(array(
            'checkoutLanguage' => $checkoutLanguage
        ));
        
        $freeze_waiting_for_3d_response = false;
        $easyPaymentId = '';
        if (pSQL(Tools::getValue('paymentId')) != '' AND Tools::getValue('paymentFailed') != 'true') {
            $freeze_waiting_for_3d_response = true;
            $easyPaymentId = pSQL(Tools::getValue('paymentId'));
        } else {
            $easyPaymentId = $this->creatOrRetrievePaymentId($currency, $cart->id);
        }
        
        $this->context->smarty->assign(array(
            'freeze_waiting_for_3d_response' => $freeze_waiting_for_3d_response
        ));

        $this->context->smarty->assign(array(
            'easyCheckoutError' => $this->l('An error with Easy occurred, could not load checkout')
        ));
        
        if ($easyPaymentId === '' OR $easyPaymentId === false) {
            return $this->setTemplate('module:easycheckout/views/templates/front/error.tpl');
        }
        
        $this->context->smarty->assign(array(
            'paymentId' => $easyPaymentId
        ));
        
        $this->context->smarty->assign(array(
            'delivery_option_list'   => $del_list,
            'delivery_option'        => $del_opt,
            'free_shipping'          => $free_shipping,
            'easy_live_mode'         => Configuration::get('EASYCHECKOUT_LIVE_MODE'),
            'pwdc_show_paymentlink'  => (int)Configuration::get('EASYCHECKOUT_SHOW_PAYLINK'),
            'pwdc_show_shoppinglink' => (int)Configuration::get('EASYCHECKOUT_SHOW_SHOPPINGLINK'),
            'pwdc_checkout_url'      => $this->context->link->getModuleLink($this->module->name, 'checkout', array(), Tools::usingSecureMode()),
            'checkoutKey'            => $checkoutKey,
            'confirmation_url'       => $confirmation_url
        ));
        
        return $this->setTemplate('module:easycheckout/views/templates/front/checkout.tpl');
    }
    
    private function prepareLeftToGet($cart)
    {
        $shipping = Configuration::getMultiple(array('PS_SHIPPING_FREE_PRICE', 'PS_SHIPPING_FREE_WEIGHT'));
        
        if (isset($shipping['PS_SHIPPING_FREE_PRICE']) && $shipping['PS_SHIPPING_FREE_PRICE'] > 0) {
            $free_fees_price = Tools::convertPrice(
                (float) $shipping['PS_SHIPPING_FREE_PRICE'],
                Currency::getCurrencyInstance((int) $cart->id_currency)
            );
            $orderTotalwithDiscounts = $cart->getOrderTotal(
                true,
                Cart::BOTH_WITHOUT_SHIPPING,
                null,
                null,
                false
            );
            $left_to_get_free_shipping_price = $free_fees_price - $orderTotalwithDiscounts;
            $this->context->smarty->assign('left_to_get_free_shipping_price', Tools::displayPrice($left_to_get_free_shipping_price));
        }
        
        if (isset($shipping['PS_SHIPPING_FREE_WEIGHT']) && $shipping['PS_SHIPPING_FREE_WEIGHT'] > 0) {
            $free_fees_weight = $shipping['PS_SHIPPING_FREE_WEIGHT'];
            $total_weight = $cart->getTotalWeight();
            $left_to_get_free_shipping_weight = $free_fees_weight - $total_weight;
            $this->context->smarty->assign('left_to_get_free_shipping_weight', $left_to_get_free_shipping_weight);
        }
    }
    
    public function updateEasyIframe($currency, $id_cart)
    {
        $paymentId = $this->module->retrievePaymentIdInDatabase($currency->iso_code, $id_cart);
        if (isset($paymentId) AND $paymentId) {
            return $this->module->updateEasyCheckout($paymentId, $currency, $id_cart);
        } else {
            return false;
        }
    }

    public function creatOrRetrievePaymentId($currency, $id_cart)
    {
        $paymentId = $this->module->getEasyPaymentID($currency, $id_cart);
        
        if ($paymentId != '' AND $paymentId !== false) {
            return $paymentId;
        } else {
            return false;
        }
    }
    
    public function assignGiftAndMessageInformation()
    {
        $this->context->smarty->assign(array(
            'gift'                  => $this->context->cart->gift,
            'gift_message'          => $this->context->cart->gift_message,
            'giftAllowed'           => (int)(Configuration::get('PS_GIFT_WRAPPING')),
            'gift_wrapping_price'   => Tools::displayPrice(Tools::convertPrice($this->context->cart->getGiftWrappingPrice(true), Currency::getCurrencyInstance((int)$this->context->cart->id_currency))),
            'message'               => Message::getMessageByCartId($this->context->cart->id),
        ));
    }
    
    public function validateDeliveryOption($delivery_option) {
        if (!is_array($delivery_option)) {
            return false;
        }
        foreach ($delivery_option as $option) {
            if (!preg_match('/(\d+,)?\d+/', $option)) {
                return false;
            }
        }
        return true;
    }
    
    public function updateMessage($messageContent) {
        if ($messageContent) {
            if (!Validate::isMessage($messageContent)) {
                return false;
            } elseif ($oldMessage = Message::getMessageByCartId((int)$this->context->cart->id)) {
                $message = new Message((int)$oldMessage['id_message']);
                $message->message = $messageContent;
                $message->update();
            } else {
                $message = new Message();
                $message->message = $messageContent;
                $message->id_cart = (int)$this->context->cart->id;
                $message->id_customer = (int)$this->context->cart->id_customer;
                $message->add();
            }
        } else {
            if ($oldMessage = Message::getMessageByCartId((int)$this->context->cart->id)) {
                $message = new Message((int)$oldMessage['id_message']);
                $message->delete();
            }
        }
        return true;
    }
}