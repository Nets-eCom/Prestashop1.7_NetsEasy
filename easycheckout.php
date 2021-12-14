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

if (!defined('_PS_VERSION_')) {
    exit;
}

use PrestaShop\PrestaShop\Core\Payment\PaymentOption;

class Easycheckout extends PaymentModule
{
    public function __construct()
    {
        $this->name  = 'easycheckout';
        $this->tab = 'payment_gateways';
        $this->version = '1.2.33';
        $this->author = 'Prestaworks AB';
        $this->need_instance = 0;
        
        $this->bootstrap = true;
        
        $this->currencies = true;
        $this->currencies_mode = 'checkbox';
        
        parent::__construct();
        
        $this->displayName = $this->l('Easy Checkout');
        $this->description = $this->l('Easy Checkout module integrated by Prestaworks AB');

        $this->limited_currencies = array('NOK', 'SEK', 'DKK', 'EUR');
        
        $this->ps_versions_compliancy = array('min' => '1.7.0.0', 'max' => '1.7.9.99');
    }
    
    public function install()
    {
        if (extension_loaded('curl') == false) {
            $this->_errors = $this->l('You have to enable the cURL extension on your server to install this module');
            return false;
        }
        
        Configuration::updateValue('EASYCHECKOUT_LIVE_MODE', 0);
        Configuration::updateValue('EASYCHECKOUT_ACTIVE', 1);
        Configuration::updateValue('EASYCHECKOUT_USE_WEBHOOK', 1);
        Configuration::updateValue('EASYCHECKOUT_MERCHANT_ID', '');
        Configuration::updateValue('EASYCHECKOUT_SECRET_KEY', '');
        Configuration::updateValue('EASYCHECKOUT_SECRET_KEY_TEST', '');
        Configuration::updateValue('EASYCHECKOUT_CHECKOUT_KEY', '');
        Configuration::updateValue('EASYCHECKOUT_CHECKOUT_KEY_TEST', '');
        Configuration::updateValue('EASYCHECKOUT_SHOW_PAYHOOK', 0);
        Configuration::updateValue('EASYCHECKOUT_REPLACE_CHECKOUT', 1);
        Configuration::updateValue('EASYCHECKOUT_SHOW_PAYLINK', 0);
        Configuration::updateValue('EASYCHECKOUT_SHOW_SHOPPINGLINK', 0);
        
        if ((int)Configuration::get('PS_CONDITIONS_CMS_ID') > 0) {
            Configuration::updateValue('EASYCHECKOUT_CMS_PAGE', (int)Configuration::get('PS_CONDITIONS_CMS_ID'));
        } else {
            Configuration::updateValue('EASYCHECKOUT_CMS_PAGE', 3);
        }
        
        Configuration::updateValue('EASYCHECKOUT_SUPPORTED_TYPES', 1);
        Configuration::updateValue('EASYCHECKOUT_DEFAULT_TYPE', 1);
        Configuration::updateValue('EASYCHECKOUT_RECURRING_PAYMENT', 0);
        Configuration::updateValue('EASYCHECKOUT_RECURRING_INTERVAL', 0);
        Configuration::updateValue('EASYCHECKOUT_RECURRING_MONTHS_FROM_NOW', 0);
        Configuration::updateValue('EASYCHECKOUT_CHARGE_ORDER_STATUS', -1);
        Configuration::updateValue('EASYCHECKOUT_CANCEL_REFUND_ORDER_STATUS', -1);
        
        include(dirname(__FILE__).'/sql/install.php');
        
        $this->installTab('AdminChargeSubscription', 'Charge Easy Subscription', 'AdminParentOrders');
        
        $this->createNewOrderState();
        
        return parent::install() &&
            $this->registerHook('header') &&
            $this->registerHook('backOfficeHeader') &&
            $this->registerHook('paymentOptions') &&
            $this->registerHook('paymentReturn') &&
            $this->registerHook('displayAdminOrder') &&
            $this->registerHook('actionOrderStatusPostUpdate');
    }
    
    public function uninstall()
    {
        $form_values = $this->getConfigFormValues();
        foreach (array_keys($form_values) as $key) {
            Configuration::deleteByName($key);
        }
        
        include(dirname(__FILE__).'/sql/uninstall.php');
        return parent::uninstall();
    }
    
    protected function installTab($class_name, $tab_name, $parent_controller)
    {
        $tab = new Tab();
        $tab->active = 0;
        $tab->class_name  = $class_name;
        $tab->name = array();
        foreach (Language::getLanguages(true) as $lang) {
            $tab->name[$lang['id_lang']] = $this->l($tab_name);
        }
        
        $tab_id           = Tab::getIdFromClassName($parent_controller);
        $tab->id_parent   = $tab_id;
        $tab->module      = $this->name;
        
        $tab->add();
    }
    
    public function getContent()
    {
		if (((bool)Tools::isSubmit('submitEasyCheckoutModule')) == true) {
            $this->postProcess();
        }

        $this->context->smarty->assign('module_dir', $this->_path);
        
        return $this->renderForm();
    }
    
    protected function renderForm()
    {
        $helper = new HelperForm();

        $helper->show_toolbar   = false;
        $helper->table          = $this->table;
        $helper->module         = $this;
        $helper->default_form_language      = $this->context->language->id;
        $helper->allow_employee_form_lang   = Configuration::get('PS_BO_ALLOW_EMPLOYEE_FORM_LANG', 0);
        $helper->identifier = $this->identifier;
        $helper->submit_action  = 'submitEasyCheckoutModule';
        $helper->currentIndex   = $this->context->link->getAdminLink('AdminModules', false).'&configure='.$this->name.'&tab_module='.$this->tab.'&module_name='.$this->name;
        $helper->token          = Tools::getAdminTokenLite('AdminModules');

        $helper->tpl_vars = array(
            'fields_value'  => $this->getConfigFormValues(),
            'languages'     => $this->context->controller->getLanguages(),
            'id_language'   => $this->context->language->id,
        );

        return $helper->generateForm(array($this->getConfigForm()));
    }
    
    protected function getConfigForm()
    {
        $states = OrderState::getOrderStates($this->context->language->id);
        $order_states = array();
        $order_states[] = array(
            'id_status' => -1,
            'name'      => '- '.$this->l('Skip').' -'
        );
        
		foreach ($states as $id => $state) {
			$order_states[] = array(
				'id_status' => $state['id_order_state'],
				'name'      => $state['name']
			);
		}
        
        $days_of_month = array();
        for ($i = 0; $i < 32; $i++) {
            if ($i < 1) {
                $days = $this->l('day');
            } else {
                $days = $this->l('days');
            }
            $days_of_month[] = array(
                'id' => $i,
                'days' => $i.' '.$days
            );
        }
        
        $supported_types = array(
            array(
                'id_supported_type' => 1,
                'consumer_type'     => 'B2C'
            ),
            array(  
                'id_supported_type' => 2,
                'consumer_type'     => 'B2B'
            ),
            array(
                'id_supported_type' => 3,
                'consumer_type'     => 'B2C & B2B'
            )
        );
        
        $default_type = array(
            array(
                'id_default_type'  => 1,
                'default_consumer' => 'B2C'
            ),
            array(
                'id_default_type' => 2,
                'default_consumer' => 'B2B'
            )
        );
        
        return array(
            'form' => array(
                'legend' => array(
                'title' => $this->l('Integration Settings'),
                'icon' => 'icon-cogs',
                ),
                'input' => array(
                    array(
                        'type' => 'html',
                        'desc' => '<h4>'.$this->l('General settings').'</h4>',
                        'name' => ''
                    ),
                    array(
                        'type' => 'switch',
                        'label' => $this->l('Active'),
                        'name' => 'EASYCHECKOUT_ACTIVE',
                        'is_bool' => true,
                        'values' => array(
                            array(
                                'id' => 'active_on',
                                'value' => 1,
                                'label' => $this->l('Yes')
                            ),
                            array(
                                'id' => 'active_off',
                                'value' => 0,
                                'label' => $this->l('No')
                            )
                        )
                    ),
                    array(
                        'type' => 'switch',
                        'label' => $this->l('Use webhook'),
                        'name' => 'EASYCHECKOUT_USE_WEBHOOK',
                        'is_bool' => true,
                        'hint' => $this->l('Use only if you have an SSL certificate'),
                        'values' => array(
                            array(
                                'id' => 'active_on',
                                'value' => 1,
                                'label' => $this->l('Yes')
                            ),
                            array(
                                'id' => 'active_off',
                                'value' => 0,
                                'label' => $this->l('No')
                            )
                        )
                    ),
                    array(
                        'type' => 'switch',
                        'label' => $this->l('Live mode'),
                        'name' => 'EASYCHECKOUT_LIVE_MODE',
                        'is_bool' => true,
                        'values' => array(
                            array(
                                'id' => 'active_on',
                                'value' => 1,
                                'label' => $this->l('Yes')
                            ),
                            array(
                                'id' => 'active_off',
                                'value' => 0,
                                'label' => $this->l('No')
                            )
                        )
                    ),
                    array(
                        'col' => 2,
                        'type' => 'text',
                        'desc' => $this->l('Provided by Nets'),
                        'name' => 'EASYCHECKOUT_MERCHANT_ID',
                        'label' => $this->l('Merchant ID')
                    ),
                    array(
                        'type' => 'html',
                        'desc' => '<h4>'.$this->l('Production environment API Settings').'</h4>',
                        'name' => ''
                    ),
                    array(
                        'col' => 2,
                        'type' => 'text',
                        'desc' => $this->l('Provided by Nets'),
                        'name' => 'EASYCHECKOUT_SECRET_KEY',
                        'label' => $this->l('Secret Key')
                    ),
                    array(
                        'col' => 2,
                        'type' => 'text',
                        'desc' => $this->l('Provided by Nets'),
                        'name' => 'EASYCHECKOUT_CHECKOUT_KEY',
                        'label' => $this->l('Checkout Key')
                    ),
                    array(
                        'type' => 'html',
                        'desc' => '<h4>'.$this->l('Test environment API Settings').'</h4>',
                        'name' => ''
                    ),
                    array(
                        'col' => 2,
                        'type' => 'text',
                        'desc' => $this->l('Provided by Nets'),
                        'name' => 'EASYCHECKOUT_SECRET_KEY_TEST',
                        'label' => $this->l('Secret Key')
                    ),
                    array(
                        'col' => 2,
                        'type' => 'text',
                        'desc' => $this->l('Provided by Nets'),
                        'name' => 'EASYCHECKOUT_CHECKOUT_KEY_TEST',
                        'label' => $this->l('Checkout Key')
                    ),
                    array(
                        'type' => 'html',
                        'desc' => '<h4>'.$this->l('Checkout options').'</h4>',
                        'name' => ''
                    ),
                    array(
                        'type' => 'switch',
                        'label' => $this->l('Use Easy Checkout'),
                        'name' => 'EASYCHECKOUT_REPLACE_CHECKOUT',
                        'is_bool' => true,
                        'values' => array(
                            array(
                                'id' => 'active_on',
                                'value' => 1,
                                'label' => $this->l('Yes')
                            ),
                            array(
                                'id' => 'active_off',
                                'value' => 0,
                                'label' => $this->l('No')
                            )
                        )
                    ),
                    array(
                        'type' => 'switch',
                        'label' => $this->l('Continue shopping'),
                        'desc' => $this->l('Show a link to "Continue Shopping" in Easy checkout'),
                        'name' => 'EASYCHECKOUT_SHOW_SHOPPINGLINK',
                        'is_bool' => true,
                        'values' => array(
                            array(
                                'id' => 'active_on',
                                'value' => 1,
                                'label' => $this->l('Yes')
                            ),
                            array(
                                'id' => 'active_off',
                                'value' => 0,
                                'label' => $this->l('No')
                            )
                        )
                    ),
                    array(
                        'type' => 'switch',
                        'label' => $this->l('Standard checkout'),
                        'name' => 'EASYCHECKOUT_SHOW_PAYLINK',
                        'is_bool' => true,
                        'desc' => $this->l('Show a link to other payment methods (The standard PS checkout)'),
                        'values' => array(
                            array(
                                'id' => 'active_on',
                                'value' => 1,
                                'label' => $this->l('Yes')
                            ),
                            array(
                                'id' => 'active_off',
                                'value' => 0,
                                'label' => $this->l('No')
                            )
                        )
                    ),
                    array(
                        'type' => 'switch',
                        'label' => $this->l('Easy payment option'),
                        'name' => 'EASYCHECKOUT_SHOW_PAYHOOK',
                        'is_bool' => true,
                        'desc' => $this->l('Display Easy as a standard PS payment method'),
                        'values' => array(
                            array(
                                'id' => 'active_on',
                                'value' => 1,
                                'label' => $this->l('Yes')
                            ),
                            array(
                                'id' => 'active_off',
                                'value' => 0,
                                'label' => $this->l('No')
                            )
                        )
                    ),
                    array(
                        'type' => 'switch',
                        'label' => $this->l('Prefill customer information'),
                        'name' => 'EASYCHECKOUT_PREFILL_INFORMAION',
                        'is_bool' => true,
                        'hint' => $this->l("Uses the most recent customer's address"),
                        'values' => array(
                            array(
                                'id' => 'active_on',
                                'value' => 1,
                                'label' => $this->l('Yes')
                            ),
                            array(
                                'id' => 'active_off',
                                'value' => 0,
                                'label' => $this->l('No')
                            )
                        )
                    ),
                    array (
                        'type' => 'select',
                        'label' => $this->l('Terms of payment'),
                        'name' => 'EASYCHECKOUT_CMS_PAGE',
                        'options' => array (
                            'query' => CMS::listCms($this->context->language->id, false, true),
                            'id' => 'id_cms',
                            'name' => 'meta_title'
                        )
                    ),
                    array (
                        'type'      => 'select',
                        'label'     => $this->l('Supported consumer type'),
                        'name'      => 'EASYCHECKOUT_SUPPORTED_TYPES',
                        'options'   => array (
                            'query'     => $supported_types,
                            'id'        => 'id_supported_type',
                            'name'      => 'consumer_type'
                        )
                    ),
                    array (
                        'type'      => 'select',
                        'label'     => $this->l('Default consumer type'),
                        'name'      => 'EASYCHECKOUT_DEFAULT_TYPE',
                        'options'   => array (
                            'query'     => $default_type,
                            'id'        => 'id_default_type',
                            'name'      => 'default_consumer'
                        )
                    ),
                    array(
                        'type' => 'html',
                        'desc' => '<h4>'.$this->l('Order handler settings').'</h4>',
                        'name' => ''
                    ),
                    array(
                        'type' => 'select',
                        'label' => $this->l('Charge transaction'),
                        'name' => 'EASYCHECKOUT_CHARGE_ORDER_STATUS',
                        'options' => array(
                            'query' => $order_states,
                            'id' => 'id_status',
                            'name' => 'name'
                        )
                    ),
                    array(
                        'type' => 'select',
                        'label' => $this->l('Cancel/Refund transaction'),
                        'name' => 'EASYCHECKOUT_CANCEL_REFUND_ORDER_STATUS',
                        'hint' => $this->l('If the transaction is reserved, it will be cancelled. If it has previously been charged, it will be refunded.'),
                        'options' => array(
                            'query' => $order_states,
                            'id' => 'id_status',
                            'name' => 'name'
                        )
                    ),
                    array(
                        'type' => 'html',
                        'desc' => '<h4>'.$this->l('Recurring payment settings').'</h4>',
                        'name' => ''
                    ),
                    array(
                        'type' => 'switch',
                        'label' => $this->l('Make orders recurring'),
                        'name' => 'EASYCHECKOUT_RECURRING_PAYMENT',
                        'is_bool' => true,
                        'desc' => $this->l('All orders are subscriptions, handle recurring payments'),
                        'values' => array(
                            array(
                                'id' => 'active_on',
                                'value' => 1,
                                'label' => $this->l('Yes')
                            ),
                            array(
                                'id' => 'active_off',
                                'value' => 0,
                                'label' => $this->l('No')
                            )
                        )
                    ),
                    array(
                        'type' => 'select',
                        'label' => $this->l('Choose interval of days for subscription charging'),
                        'name' => 'EASYCHECKOUT_RECURRING_INTERVAL',
                        'is_bool' => true,
                        'desc' => $this->l('Minimum number of days between each recurring charge'),
                        'options' => array(
                            'query' => $days_of_month,
                            'id' => 'id',
                            'name' => 'days'
                        )
                    ),
                    array(
                        'col' => 2,
                        'type' => 'text',
                        'desc' => $this->l('Number of month from today the subscription will expire'),
                        'cast' => 'int',
                        'name' => 'EASYCHECKOUT_RECURRING_MONTHS_FROM_NOW',
                        'label' => $this->l('Number of month from now the subscription will expire'),
                        'hint' => $this->l('e.g., if the order is placed 2019-04-07, a value of 15 means the subscription will expire 2020-07-07')
                    )
                ),
                'submit' => array(
                    'title' => $this->l('Save')
                )
            )
        );
    }
    
    protected function getConfigFormValues()
    {
        return array(
            'EASYCHECKOUT_LIVE_MODE'         => Configuration::get('EASYCHECKOUT_LIVE_MODE'),
            'EASYCHECKOUT_ACTIVE'            => Configuration::get('EASYCHECKOUT_ACTIVE'),
            'EASYCHECKOUT_USE_WEBHOOK'       => Configuration::get('EASYCHECKOUT_USE_WEBHOOK'),
            'EASYCHECKOUT_SECRET_KEY'        => Configuration::get('EASYCHECKOUT_SECRET_KEY'),
            'EASYCHECKOUT_SECRET_KEY_TEST'   => Configuration::get('EASYCHECKOUT_SECRET_KEY_TEST'),
            'EASYCHECKOUT_CHECKOUT_KEY'      => Configuration::get('EASYCHECKOUT_CHECKOUT_KEY'),
            'EASYCHECKOUT_CHECKOUT_KEY_TEST' => Configuration::get('EASYCHECKOUT_CHECKOUT_KEY_TEST'),
            'EASYCHECKOUT_SHOW_PAYHOOK'       => Configuration::get('EASYCHECKOUT_SHOW_PAYHOOK'),
            'EASYCHECKOUT_REPLACE_CHECKOUT'   => Configuration::get('EASYCHECKOUT_REPLACE_CHECKOUT'),
            'EASYCHECKOUT_SHOW_PAYLINK'       => Configuration::get('EASYCHECKOUT_SHOW_PAYLINK'),
            'EASYCHECKOUT_SHOW_SHOPPINGLINK'  => Configuration::get('EASYCHECKOUT_SHOW_SHOPPINGLINK'),
            'EASYCHECKOUT_PREFILL_INFORMAION' => Configuration::get('EASYCHECKOUT_PREFILL_INFORMAION'),
            'EASYCHECKOUT_MERCHANT_ID'        => Configuration::get('EASYCHECKOUT_MERCHANT_ID'),
            'EASYCHECKOUT_CMS_PAGE'           => Configuration::get('EASYCHECKOUT_CMS_PAGE'),
            'EASYCHECKOUT_SUPPORTED_TYPES'    => Configuration::get('EASYCHECKOUT_SUPPORTED_TYPES'),
            'EASYCHECKOUT_DEFAULT_TYPE'       => Configuration::get('EASYCHECKOUT_DEFAULT_TYPE'),
            'EASYCHECKOUT_RECURRING_PAYMENT'          => Configuration::get('EASYCHECKOUT_RECURRING_PAYMENT'),
            'EASYCHECKOUT_RECURRING_INTERVAL'         => Configuration::get('EASYCHECKOUT_RECURRING_INTERVAL'),
            'EASYCHECKOUT_RECURRING_MONTHS_FROM_NOW'  => Configuration::get('EASYCHECKOUT_RECURRING_MONTHS_FROM_NOW'),
            'EASYCHECKOUT_CHARGE_ORDER_STATUS'        => Configuration::get('EASYCHECKOUT_CHARGE_ORDER_STATUS'),
            'EASYCHECKOUT_CANCEL_REFUND_ORDER_STATUS' => Configuration::get('EASYCHECKOUT_CANCEL_REFUND_ORDER_STATUS')
        );
    }

    protected function postProcess()
    {
        $form_values = $this->getConfigFormValues();

        foreach (array_keys($form_values) as $key) {
            Configuration::updateValue($key, Tools::getValue($key));
        }

        // Prestaworks stuff
        if (Tools::getValue('EASYCHECKOUT_LIVE_MODE')) {
            $domain = $this->context->shop->domain;
            $merchantID = Tools::getValue('EASYCHECKOUT_MERCHANT_ID');
            $url = "https://license.prestaworks.se/netseasy.php?domain=".$domain."&merchant=".$merchantID;
            try {
                $curl = curl_init();
                curl_setopt($curl, CURLOPT_URL, $url);
                curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
                curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
                curl_exec($curl);
            } catch (Exception $e) {
                // Exception handler
            }
        }
    }
    
    public function installAddress($countryCode, $countryAddressConfig)
    {
        $idCountry = Country::getByIso($countryCode);
        $country = new Country($idCountry);
        
        if (is_array($country->name)) {
            $countryName = reset($country->name);
        } else {
            $countryName = $country->name;
        }

        $address = new Address();
        $address->id_country = $country->id;
        $address->alias = sprintf('Easy %s', $countryName);
        $address->address1 = 'Street 1';
        $address->address2 = '';
        $address->postcode = '00000';
        $address->city = 'City';
        $address->firstname = 'Easy';
        $address->lastname = 'Checkout';
        $address->phone_mobile = '000000000';
        $address->id_customer = 0;
        $address->deleted = 0;
        
        if (!$address->save()) {
            PrestaShopLogger::addLog('Easy Checkout, could not create address with country code '.$countryCode, 1, null, null, null, true);
        }
        Configuration::updateValue($countryAddressConfig, $address->id);
        return true;
    }
    
    public function hookDisplayAdminOrder($params) 
    {
        $orderId = $params['id_order'];
        $order = New Order($orderId);
        
        $subscription_message = '';
        $subscriptionId = '';
        $interval = '';
        
        if ($order->module === $this->name) {
            $this->context->controller->addJS(_MODULE_DIR_.'easycheckout/views/js/backoffice.js');
            $sql = 'SELECT * FROM '._DB_PREFIX_.'message WHERE id_order = '.$orderId.' AND private = 1 ORDER BY date_add DESC';
            $private_messages = Db::getInstance()->executeS($sql);
            $private_messages_count = count($private_messages);
            $this->context->smarty->assign(
                array(
                    'private_messages'       => $private_messages,
                    'private_messages_count' => $private_messages_count,
                )
            );
            
            $charge_message = '';
            $end_date_subscription = '';
            $subscriptionId = Db::getInstance()->getValue("SELECT subscription_id FROM "._DB_PREFIX_."easycheckout_orders WHERE id_order = ".$orderId."");

            if ($subscriptionId != '') {
                $subscription_message .= $this->l('Subscription ID:') . ' '. $subscriptionId;
                $subscription_info = $this->getSubscriptionInformation($subscriptionId);
                if (isset($subscription_info)) {
                    @$interval = $subscription_info->interval;
                    @$end_date_subscription = $subscription_info->endDate;
                    @$charge_message = Db::getInstance()->getValue("SELECT charge_message FROM "._DB_PREFIX_."easycheckout_orders WHERE id_order = ".$orderId."");
                }
            }
            
            Media::addJsDef(array('subscriptionId' => $subscriptionId));
            Media::addJsDef(array('link_to_controller' => $this->context->link->getAdminLink('AdminChargeSubscription')));
            
            $this->context->smarty->assign(
                array(
                    'subscription_message'  => $subscription_message,
                    'interval'              => $interval,
                    'charge_message'        => $charge_message,
                    'end_date_subscription' => $end_date_subscription
                )
            );
            
            return $this->display(__FILE__, 'orderpage.tpl');
        }
    }
    
    public function hookPaymentOptions($params)
	{
        if (Configuration::get('EASYCHECKOUT_SHOW_PAYHOOK')) {
            $currency_id = $params['cart']->id_currency;
            $currency = new Currency((int)$currency_id);
            $customer_id = $params['cart']->id_customer;
            
            $newOptions = array();
            
			$newOption = new PaymentOption();
            $newOption->setCallToActionText($this->l('Pay with Easy'))
                    ->setAction($this->context->link->getModuleLink($this->name, 'checkout', array(), true))
                    ->setAdditionalInformation('<img class="img-fluid img-responsive" src="'.Media::getMediaPath(_PS_MODULE_DIR_.$this->name.'/views/img/Nets_Logo_Pos_RGB.png').'">');
            $newOptions[] = $newOption;

            return $newOptions;
        }
    }
    
    public function hookPaymentReturn($params)
    {
        return;
    }
    
    public function hookActionOrderStatusPostUpdate($params)
    {
        $id_order = (int)$params['id_order'];
        $order    = new Order($id_order);
        
        $id_shop = (int)$order->id_shop;

        $currency = new Currency($order->id_currency);
        $currency_iso = $currency->iso_code;
        
        if ($order->module == $this->name) {
            $newOrderStatus    = $params['newOrderStatus'];
            $chargeOrderStatus = Configuration::get('EASYCHECKOUT_CHARGE_ORDER_STATUS', null, null, $id_shop);
            $cancelOrderStatus = Configuration::get('EASYCHECKOUT_CANCEL_REFUND_ORDER_STATUS', null, null, $id_shop);
            
            if ($newOrderStatus->id == $chargeOrderStatus || $newOrderStatus->id == $cancelOrderStatus) {
                $paymentId = $this->retrievePaymentIdInDatabase($currency_iso, $order->id_cart);
                if (isset($paymentId) && $paymentId != '') {
                    try {
                        $easyCheckout = $this->getPaymentInformation($paymentId);
                        if (!isset($easyCheckout) || !is_object($easyCheckout)) {
                            throw new Exception ('Could not return the Easy Checkout/Order');
                        }
                    } catch (Exception $e) {
                        $errorMessage = $this->l('Failed to update Easy order status - Exception:').' '.$e->getMessage();
                        $this->addOrderMessage($errorMessage, $id_order);
                    }
                    if ($newOrderStatus->id == $chargeOrderStatus) {
                        if ($easyCheckout->summary->reservedAmount != 0 AND $this->getEasyPaymentStatus($order->id_cart) == 'Completed') {
                            try {
                                $chargeId = $this->chargeEasyOrder($order->id_cart, $paymentId, $id_shop);
                              
                                if ($chargeId !== false) {
                                    $this->updatePrestaShopOrderInDb('Charged', $id_order);
                                    $this->addOrderMessage('Easy order updated to "Charged", chargeId = '.$chargeId.'', $id_order);
                                } else {
                                    $this->addOrderMessage('An error occurred while charging the order, plaese try again or check the order in your administration panel', $id_order);
                                }
                            } catch (Exception $e) {
                                $errorMessage = $this->l('Failed to charge Easy order. Exception:').' '.$e->getMessage();
                                $this->addOrderMessage($errorMessage, $id_order);
                            }
                        } else {
                            $errorMessage = $this->l('Failed to charge Easy order. Status of order must be:').' "Completed"';
                            $this->addOrderMessage($errorMessage, $id_order);
                        }
                    } elseif ($newOrderStatus->id == $cancelOrderStatus) {
                        if ($easyCheckout->summary->chargedAmount != 0 AND $this->getEasyPaymentStatus($order->id_cart) == 'Charged') {
                            try {
                                $refundId = $this->refundEasyOrder($order->id_cart, $easyCheckout->charges[0]->chargeId, $id_shop);
                                if ($refundId !== false) {
                                    $this->updatePrestaShopOrderInDb('Refunded', $id_order);
                                    $this->addOrderMessage('Easy order updated to "Refunded", refundId ='.' '. $refundId, $id_order);
                                } else {
                                    $this->addOrderMessage('An error occurred while refunding the order, please try again ord check the order in your administration panel', $id_order);
                                }
                            } catch (Exception $e) {
                                $errorMessage = $this->l('Failed to refund Easy order. Exception:').' '.$e->getMessage();
                                $this->addOrderMessage($errorMessage, $id_order);
                            }
                        } elseif ($easyCheckout->summary->reservedAmount != 0 AND $this->getEasyPaymentStatus($order->id_cart) == 'Completed') {
                            try {
                                $result = $this->cancelEasyOrder($order->id_cart, $paymentId, $id_shop);
                                if ($result !== false) {
                                    $this->updatePrestaShopOrderInDb('Cancelled', $id_order);
                                    $this->addOrderMessage('Easy order updated to "Cancelled"', $id_order);
                                } else {
                                    $this->addOrderMessage('An error occurred while refunding the order, please try again ord check the order in your administration panel', $id_order);
                                }
                            } catch (Exception $e) {
                                $errorMessage = $this->l('Failed to cancel Easy order. Exception:').' '.$e->getMessage();
                                $this->addOrderMessage($errorMessage, $id_order);
                            }
                        }
                    }
                } else {
                    $errorMessage = $this->l('Could not fetch Easy payment ID from the database');
                    $this->addOrderMessage($errorMessage, $id_order);
                }
            }
        }
    }
    
    public function getEasyPaymentID($currency, $id_cart)
    {
        $expired = false;
        $paymentId = $this->retrievePaymentIdInDatabase($currency->iso_code, $id_cart);
        
        if ($paymentId !== false AND $paymentId != '') {
            $easyCheckout = $this->getPaymentInformation($paymentId);
            if ($easyCheckout == false) {
                $paymentId = $this->createPaymentId($currency, $id_cart);
                if ($paymentId !== false) {
                    return $paymentId;
                } else {
                    return false;
                }
            }
            
            $time_of_creation = strtotime($easyCheckout->created);
            $now = strtotime("now");
            $life_time        = $now - $time_of_creation;
            $max_time_allowed = 2 * 60 * 60;
            
            if ($life_time > $max_time_allowed) {
                $expired = true;
            }
            
            if (!$expired) {
                $updatedCheckout = $this->updateEasyCheckout($easyCheckout->paymentId, $currency, $id_cart);
                
                if ($updatedCheckout) {
                    return $easyCheckout->paymentId;
                } else {
                    return false;
                }
            } else {
                $paymentId = $this->createPaymentId($currency, $id_cart);
                if ($paymentId !== false) {
                    return $paymentId;
                } else {
                    return false;
                }
            }
        } else {
            $paymentId = $this->createPaymentId($currency, $id_cart);
            if ($paymentId !== false) {
                return $paymentId;
            } else {
                return false;
            }
        }
    }
    
    public function createPaymentId($currency, $id_cart)
    {
        $cart_info = $this->getCartInfo($currency, $id_cart);
        $cart_info = json_encode($cart_info, JSON_PRETTY_PRINT);
        if ((int)Configuration::get('EASYCHECKOUT_LIVE_MODE')) {
            $url = 'https://api.dibspayment.eu';
            $key = Configuration::get('EASYCHECKOUT_SECRET_KEY');
        } else {
            $url = 'https://test.api.dibspayment.eu';
            $key = Configuration::get('EASYCHECKOUT_SECRET_KEY_TEST');
        }
        
        $path = '/v1/payments/';
        
        $easy_url = $url.$path;
        
        $header = $this->getHeaderData($key);
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $easy_url);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $header);
        
        curl_setopt($curl, CURLOPT_POST, 1);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $cart_info);
        
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        
        $response      = json_decode(curl_exec($curl));
        $response_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        
        if ($response_code == 201) {
            $paymentId = $response->paymentId;
            $use_subscription = (int)Configuration::get('EASYCHECKOUT_RECURRING_PAYMENT');
            $this->createEasyOrderInDatabase($paymentId, $id_cart, $currency->iso_code, $use_subscription, $subscription_id = '');
            return $response->paymentId;
        } else {            
            if(isset($response->errors)) {
                foreach($response->errors as $key => $error) {
                    $message = '';
                    if (is_array($error)) {
                        $message = ' '.reset($error);
                    }
                    PrestaShopLogger::addLog("Easy: ".$key.$message, 1, null, null, null, true);
                }
            }
            return false;
        }
    }
    
    public function getPaymentInformation($paymentId)
    {
        if ((int)Configuration::get('EASYCHECKOUT_LIVE_MODE')) {
            $url = 'https://api.dibspayment.eu';
            $key = Configuration::get('EASYCHECKOUT_SECRET_KEY');
        } else {
            $url = 'https://test.api.dibspayment.eu';
            $key = Configuration::get('EASYCHECKOUT_SECRET_KEY_TEST');
        }
        
        $path = '/v1/payments/'.$paymentId;
        
        $easy_url = $url.$path;
        $header = $this->getHeaderData($key);
        $curl = curl_init();
        
        curl_setopt($curl, CURLOPT_URL, $easy_url);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $header);
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'GET');
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        
        $response      = json_decode(curl_exec($curl));
        $response_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);

        if ($response_code == 200) {
            return $response->payment;
        } else {
            return false;
        }
    }
    
    public function getCartInfo($currency, $id_cart)
    {
        $countries_iso = array();
        require(_PS_MODULE_DIR_.'easycheckout/iso/country_iso_easy.php');
        
        $cart_info = array();
        $cart_info['order'] = array();
        
        $easy_reference = $id_cart.'D'.date("U");
        $easy_currency  = $currency->iso_code;
        $easy_items     = $this->getCartItemsList($id_cart);
        
        $cart_info['order']['items'] = $easy_items;
		
		$total_value_items = 0;
		foreach ($easy_items as $item) {
			$total_value_items = $total_value_items + $item['grossTotalAmount'];
		}
        
        $cart_info['order']['amount']    = $total_value_items;
        $cart_info['order']['currency']  = $easy_currency;
        $cart_info['order']['reference'] = $easy_reference;
        
        $cart_info['checkout'] = array();
        
        $easy_url   = $this->context->link->getModuleLink('easycheckout', 'checkout');
        $cms        = new CMS((int)Configuration::get('EASYCHECKOUT_CMS_PAGE'), (int)$this->context->language->id);
        $easy_terms = $this->context->link->getCMSLink($cms, $cms->link_rewrite, Configuration::get('PS_SSL_ENABLED'));
        
        $shippingCountries = array();
        $countries = Country::getCountries($this->context->language->id, true);
		$not_valid_countries = array('AN', 'AX', 'VI', 'AS', 'AI', 'AQ', 'AW', 'BM', 'BV', 'VG', 'IO', 'KY', 'CK', 'FK', 'FO', 'GF', 'PF', 'TF', 'GI', 'GP', 'GU', 'GG', 'HM', 'IM', 'JE', 'CX', 'CC', 'MO', 'MQ', 'YT', 'MS', 'NU', 'MP', 'NF', 'NC', 'PS', 'PN', 'PR', 'RE', 'BL', 'MF', 'PM', 'SJ', 'GS', 'TK', 'TC', 'EH', 'WF');
        foreach ($countries as $country) {
			if (!in_array($country['iso_code'], $not_valid_countries)) {
				$shippingCountries[]['countryCode'] = $countries_iso[$country['iso_code']];
			}
		}
        
        $consumerType = $this->getConsumerType();
        
        $cart_info['checkout']['url']                         = $easy_url;
        $cart_info['checkout']['termsUrl']                    = $easy_terms;
        $cart_info['checkout']['shipping']['countries']       = $shippingCountries;

        $cart_info['checkout']['shipping']['enableBillingAddress'] = true;

        $cart_info['checkout']['merchantHandlesShippingCost'] = false;
        
        if ((int)Configuration::get('EASYCHECKOUT_PREFILL_INFORMAION') == 1) {
            if ($this->context->customer->logged) {
                $consumer = new Customer($this->context->customer->id);
                $easy_consumer = array();
                $easy_consumer['reference'] = $consumer->firstname . '_' . $consumer->id . '_' . date('U');
                $easy_consumer['email']     = $consumer->email;
                $consumer_addresses = $consumer->getAddresses($this->context->language->id);
                
                if (is_array($consumer_addresses) AND !empty($consumer_addresses)) {
                    $cart_info['checkout']['merchantHandlesConsumerData'] = true;
                    end($consumer_addresses);
                    $lastKey = key($consumer_addresses);
                    $consumer_address = $consumer_addresses[$lastKey];
                    $easy_shippping_address = array();
                    $easy_shippping_address['addressLine1'] = $consumer_address['address1'];
                    $easy_shippping_address['addressLine2'] = $consumer_address['address2'];
                    $easy_shippping_address['postalCode']   = str_replace(' ', '', $consumer_address['postcode']);
                    $easy_shippping_address['city']         = $consumer_address['city'];
                    $countries_iso = array();
                    require(_PS_MODULE_DIR_.'easycheckout/iso/country_iso_easy.php');
                    $country_iso  = $countries_iso[Country::getIsoById($consumer_address['id_country'])];
                    $easy_shippping_address['country'] = $country_iso;
                    $easy_consumer['shippingAddress']  = $easy_shippping_address;
                    $easy_phone_number = array();
                    if ($country_iso == 'SE') {
                        $easy_phone_number['prefix'] = '+46';
                    } elseif ($country_iso == 'FIN') {
                        $easy_phone_number['prefix'] = '+358';
                    } elseif ($country_iso == 'NOR') {
                        $easy_phone_number['prefix'] = '+47';
                    } elseif ($country_iso == 'DNK') {
                        $easy_phone_number['prefix'] = '+45';
                    } else {
                        $easy_phone_number['prefix'] = '+46';
                    }
                    
                    if (isset($consumer_address['phone_mobile']) AND $consumer_address['phone_mobile'] != '') {
                        $easy_phone_number['number'] = str_replace ('+', '', $consumer_address['phone_mobile']);
                    } elseif (isset($consumer_address['phone']) AND $consumer_address['phone'] != '') {
                        $easy_phone_number['number'] = str_replace ('+', '', $consumer_address['phone']);
                    } else {
                        $easy_phone_number['number'] = '0730000000';
                    }
                    $easy_consumer['phoneNumber'] = $easy_phone_number;
                    
                    $pw_firstname  = $consumer->firstname;
                    $pw_lastname   = $consumer->lastname;
                    $personal_info = array();
                    $personal_info['firstName'] = $pw_firstname;
                    $personal_info['lastName']  = $pw_lastname;
                    if (isset($consumer_address['company']) AND $consumer_address['company'] != '') {
                        $company_info = array();
                        $contact = array();
                        $contact['firstName']     = $pw_firstname;
                        $contact['lastName']      = $pw_lastname;
                        $company_name             = $consumer_address['company'];
                        $company_info['name']     = $company_name;
                        $company_info['contact']  = $contact;
                        $easy_consumer['company'] = $company_info;
                    } else {
                        $easy_consumer['privatePerson'] = $personal_info;
                    }
                }
                $cart_info['checkout']['consumer'] = $easy_consumer;
            } else {
                $cart_info['checkout']['merchantHandlesConsumerData'] = 'false';
            }
        } else {
            $cart_info['checkout']['merchantHandlesConsumerData'] = 'false';
        }
        
        $cart_info['checkout']['consumerType']                = $consumerType;
        
        if ((int)Configuration::get('EASYCHECKOUT_USE_WEBHOOK') == 1) {
            $webhook = array();
            $webhook['eventName']     = 'payment.checkout.completed';
            $webhook['url']           = $this->context->link->getModuleLink($this->name, 'notification', array(), Tools::usingSecureMode());
            $webhook['authorization'] = Tools::encrypt('Easy'.$id_cart.'AUTHORIZATION');

            $cart_info['notifications']['webhooks'][] = $webhook;
        }
        
        $use_subscription = 0;
        if ((int)Configuration::get('EASYCHECKOUT_RECURRING_PAYMENT') == 1) {
            $use_subscription = 1;
            $number_of_months = (int)Configuration::get('EASYCHECKOUT_RECURRING_MONTHS_FROM_NOW');
            $endDate          = date('c', strtotime('+'.$number_of_months.' months', time()));
            $interval         = (int)Configuration::get('EASYCHECKOUT_RECURRING_INTERVAL');
            
            $subscription = array();
            if ($use_subscription) {
                $subscription['endDate']   = $endDate;
                $subscription['interval']  = $interval;
                $cart_info['subscription'] = $subscription;
            }
        }
        
        return $cart_info;
    }
    
    public function retrievePaymentIdInDatabase($currency_iso, $id_cart)
    {
        return Db::getInstance()->getValue("
        SELECT paymentId FROM "._DB_PREFIX_."easycheckout_orders WHERE id_cart = ".$id_cart." AND currency_iso = '".$currency_iso."'
        ");
    }
    
    public function createEasyOrderInDatabase($paymentId, $id_cart, $currency_iso, $use_subscription, $subscription_id)
    {
        $datetime = date('Y-m-d H:i:s');
        
        $sql = "INSERT INTO "._DB_PREFIX_."easycheckout_orders (id_cart, paymentId, currency_iso, is_subscription, subscription_id, id_purchase, payment_status, added, updated, ps_created, id_order)
                VALUES (".$id_cart.", '".$paymentId."', '".$currency_iso."', ".$use_subscription.", '".$subscription_id."', '', 'Created', '".$datetime."', '".$datetime."', 0, 0)
                ON DUPLICATE KEY UPDATE paymentId = '".$paymentId."', currency_iso = '".$currency_iso."', is_subscription = ".$use_subscription.", subscription_id = '".$subscription_id."', id_purchase = '', payment_status = 'Created', added = '".$datetime."', updated = '".$datetime."', ps_created = 0, id_order = 0";
                                    
        Db::getInstance()->execute($sql);
    }
    
    public function getConsumerType()
    {
        $supported_types = (int)Configuration::get('EASYCHECKOUT_SUPPORTED_TYPES');
        $default_type    = (int)Configuration::get('EASYCHECKOUT_DEFAULT_TYPE');
        
        if ($default_type == 1) {
            $default = 'B2C';
        } elseif ($default_type == 2) {
            $default = 'B2B';
        }
        
        if ($supported_types == 1) {
            $supportedTypes = array('B2C');
        } elseif ($supported_types == 2) {
            $supportedTypes = array('B2B');
        } elseif ($supported_types == 3) {
            $supportedTypes = array('B2C', 'B2B');
        }
        
        return array('supportedTypes' => $supportedTypes, 'default' => $default);
    }
    
    public function chargeSubscription($body, $subscriptionId, $id_cart, $currency_iso)
    {
        $body = json_encode($body, JSON_PRETTY_PRINT);
        
        if ((int)Configuration::get('EASYCHECKOUT_LIVE_MODE')) {
            $url = 'https://api.dibspayment.eu';
            $key = Configuration::get('EASYCHECKOUT_SECRET_KEY');
        } else {
            $url = 'https://test.api.dibspayment.eu';
            $key = Configuration::get('EASYCHECKOUT_SECRET_KEY_TEST');
        }
        
        $path = '/v1/subscriptions/'.$subscriptionId.'/charges';
        
        $easy_url = $url.$path;
        
        $header = $this->getHeaderData($key);
        
        $curl = curl_init();
        
        curl_setopt($curl, CURLOPT_URL, $easy_url);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $header);
        curl_setopt($curl, CURLOPT_POST, 1);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $body);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        
        $response      = json_decode(curl_exec($curl));
        $response_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        
        if ($response_code == 202 OR $response_code == 200) {
            $chargeId = $response->chargeId;
            $paymentId = $response->paymentId;
            
            $use_subscription = (int)Configuration::get('EASYCHECKOUT_RECURRING_PAYMENT');
            
            $this->createEasyOrderInDatabase($paymentId, $id_cart, $currency_iso, $use_subscription, $subscriptionId);
            
            $return_array = array();
            $return_array['chargeId']  = $chargeId;
            $return_array['paymentId'] = $paymentId;
            
            return $return_array;
            
        } else {
            return false;
        }
    }
    
    public function changeOrderRefInEasy($reference, $paymentId)
    {
        if ((int)Configuration::get('EASYCHECKOUT_LIVE_MODE')) {
            $url = 'https://api.dibspayment.eu';
            $key = Configuration::get('EASYCHECKOUT_SECRET_KEY');
        } else {
            $url = 'https://test.api.dibspayment.eu';
            $key = Configuration::get('EASYCHECKOUT_SECRET_KEY_TEST');
        }
        
        $path = '/v1/payments/'.$paymentId.'/referenceinformation';
        
        $easy_url = $url.$path;
        
        $header = $this->getHeaderData($key);
        
        $new_ref_info = array(
            'reference' => $reference,
            'checkoutUrl' => $this->context->link->getModuleLink('easycheckout', 'checkout')
        );
        $new_ref_info = json_encode($new_ref_info, JSON_PRETTY_PRINT);

        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $easy_url);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $header);
        
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'PUT');
        curl_setopt($curl, CURLOPT_POSTFIELDS, $new_ref_info);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
       
		curl_exec($curl);
    }

    public function getOrderAmount($id_cart)
    {
        $order = Order::getByCartId($id_cart);
        return $this->fixEasyAmount($order->total_paid_tax_incl);
    }

    public function getOrderItemsList($id_cart)
    {
        $orderItemsList = [];

        if ((int) $id_cart > 0) {
            $cart = new Cart($id_cart);
            $order = Order::getByCartId($id_cart);
            
            if (Validate::isLoadedObject($order)) {
                $orderRows = $order->getProductsDetail();
                
                foreach ($orderRows as $orderRow) {
                    $reference = $orderRow['product_reference'];
                    if ("" == $reference) {
                        $reference = $orderRow['product_id'];
                        if ((int) $orderRow['product_attribute_id'] > 0) {
                            $reference .= "-".$orderRow['product_attribute_id'];
                        }
                    }
                    $name = $orderRow['product_name'];
                    $quantity = (int) $orderRow['product_quantity'];

                    $unitPrice = Tools::ps_round($orderRow['unit_price_tax_excl'], 2);
                    $grossTotalAmount = Tools::ps_round($orderRow['total_price_tax_incl'], 2);
                    $netTotalAmount = Tools::ps_round($orderRow['total_price_tax_excl'], 2);
                    $taxAmount = Tools::ps_round($grossTotalAmount - $netTotalAmount, 2);

                    $orderDetailTax = OrderDetail::getTaxListStatic((int) $orderRow['id_order_detail']);
                    $tax = new Tax((int) $orderDetailTax[0]['id_tax']);
                    $taxRate = $tax->rate;

                    $unit = 'pcs';

                    $orderItem = [
                        'reference' 	   => $reference,
                        'name' 			   => $name,
                        'quantity'  	   => $quantity,
                        'unit'      	   => $unit,
                        'unitPrice' 	   => $this->fixEasyAmount($unitPrice),
                        'taxRate'   	   => $this->fixEasyAmount($taxRate),
                        'taxAmount' 	   => $this->fixEasyAmount($taxAmount),
                        'grossTotalAmount' => $this->fixEasyAmount($grossTotalAmount),
                        'netTotalAmount'   => $this->fixEasyAmount($netTotalAmount)
                    ];

                    $orderItemsList[] = $orderItem;
                }
            }

            // Shipping
            $carrier = new Carrier($cart->id_carrier, $cart->id_lang);
            
            $total_shipping_wt = Tools::ps_round($cart->getOrderTotal(true, Cart::ONLY_SHIPPING), 2);
            $total_shipping_wt = $this->fixEasyAmount($total_shipping_wt);
            
            $total_shipping_without_taxes = Tools::ps_round($cart->getOrderTotal(false, Cart::ONLY_SHIPPING), 2);
            $total_shipping_without_taxes = $this->fixEasyAmount($total_shipping_without_taxes);
            
            $shipping_tax_amount = $total_shipping_wt - $total_shipping_without_taxes;
            $shipping_unit_price = $total_shipping_without_taxes;
            
            $tax_rate = Tools::ps_round(Tax::getCarrierTaxRate((int)$carrier->id, $cart->id_address_delivery), 2);
            $tax_rate = $this->fixEasyAmount($tax_rate);
            
            $carrier_name = $carrier->name;
            if ($carrier_name != '') {
                $reference = $carrier->name;
            } else {
                $reference = $this->l('Shipping');
            }
            
            $orderItem = array(
                'reference' 	   => $reference,
                'name' 			   => 'Shipping',
                'quantity'  	   => 1,
                'unit'      	   => 'pcs',
                'unitPrice' 	   => $shipping_unit_price,
                'taxRate'   	   => $tax_rate,
                'taxAmount' 	   => $shipping_tax_amount,
                'grossTotalAmount' => $total_shipping_wt,
                'netTotalAmount'   => $total_shipping_without_taxes
            );
            
            $orderItemsList[] = $orderItem;

            // VOUCHERS
            $cart_vouchers = $cart->getCartRules();
            $voucher_info = array();
            $cart_contains_voucher = false;
            if (!empty($cart_vouchers)) {
                $cart_contains_voucher = true;
            }

            if ($cart_contains_voucher) {
                $reference = $this->l('Voucher');
                $name = $this->l('Voucher');
                $quantity = 1;
                $unitPrice = Tools::ps_round($cart->getOrderTotal(false, Cart::ONLY_DISCOUNTS), 2);
                $unitPrice = $this->fixEasyAmount($unitPrice);

                $unitPriceWithTaxes = Tools::ps_round($cart->getOrderTotal(true, Cart::ONLY_DISCOUNTS), 2);
                $unitPriceWithTaxes = $this->fixEasyAmount($unitPriceWithTaxes);

                $taxRate = Tools::ps_round((($unitPriceWithTaxes - $unitPrice) / $unitPrice), 2);
                $taxRate = $this->fixEasyAmount($taxRate);
                $taxRate = $taxRate * 100;

                $taxAmount = $unitPriceWithTaxes - $unitPrice;
                $taxAmount = $taxAmount;
                
                $grossTotalAmount = $unitPriceWithTaxes;
                $netTotalAmount   = $unitPrice;

                $orderItem = array(
                    'reference' 	   => $reference,
                    'name' 			   => $name,
                    'quantity'  	   => 1,
                    'unit'      	   => 'pcs',
                    'unitPrice' 	   => $unitPrice * -1,
                    'taxRate'   	   => $taxRate,
                    'taxAmount' 	   => $taxAmount * -1,
                    'grossTotalAmount' => $grossTotalAmount * -1,
                    'netTotalAmount'   => $netTotalAmount * -1
                );
                
                $orderItemsList[] = $orderItem;
            }
            
            // GIFT WRAPPING
            if ($cart->gift) {
                $reference = $this->l('Gift wrapping');
                $name      = $this->l('Wrapping');
                $quantity  = 1;
                $unitPrice = Tools::ps_round($cart->getOrderTotal(false, Cart::ONLY_WRAPPING), 2);
                $unitPrice = $this->fixEasyAmount($unitPrice);
                
                $unitPriceTaxInc = Tools::ps_round($cart->getOrderTotal(true, Cart::ONLY_WRAPPING), 2);
                $unitPriceTaxInc = $this->fixEasyAmount($unitPriceTaxInc);
                
                $grossTotalAmountGift = $unitPriceTaxInc;
                $netTotalAmount = $unitPrice;
                
                $taxRate  = Tools::ps_round((($unitPriceTaxInc - $unitPrice) / $unitPrice) , 2);
                $taxRate = $this->fixEasyAmount($taxRate);
                
                $taxAmount  = $grossTotalAmountGift - $netTotalAmount;
                
                $orderItem = array(
                    'reference' 	   => $reference,
                    'name' 			   => $name,
                    'quantity'  	   => 1,
                    'unit'      	   => 'pcs',
                    'unitPrice' 	   => $unitPrice,
                    'taxRate'   	   => $taxRate,
                    'taxAmount' 	   => $taxAmount,
                    'grossTotalAmount' => $grossTotalAmountGift,
                    'netTotalAmount'   => $netTotalAmount
                );
        
                $orderItemsList[] = $orderItem;
            }
        }

        return $orderItemsList;
    }
    
    // public function getOrderItemsList($id_cart)
    public function getCartItemsList($id_cart)
    {
        $cart = new Cart($id_cart);
        $orderitemslist = array();

        // Products
        foreach ($cart->getProducts() as $product) {
            $product_reference = $product['reference'];
            if ($product_reference == '') {
                $product_reference = $product['id_product'];
                if ((int) $product['id_product_attribute'] > 0) {
                    $product_reference .= "-".$product['id_product_attribute'];
                }
            }
            $product_name_extra = isset($cartProduct['attributes_small']) ? $cartProduct['attributes_small'] : '';
            if ($product_name_extra != '') {
                $product_name = $product['name']. ', '.$product_name_extra;
            } else {
                $product_name = $product['name'];
            }
            
            $product_name = str_replace("'", "", $product_name);
            $product_name = str_replace('"', '', $product_name);
            $product_name = substr($product_name, 0, 128);
            
            $quantity = $product['quantity'];
            
            $unitPrice = Tools::ps_round($product['price_with_reduction_without_tax'], 2);
            $unitPrice = $this->fixEasyAmount($unitPrice);
            
            $taxRate = Tools::ps_round($product['rate'], 2);
            $taxRate = $this->fixEasyAmount($taxRate);

            $taxAmount  = Tools::ps_round($product['total_wt'], 2) - Tools::ps_round($product['total'], 2);
            $taxAmount = $this->fixEasyAmount($taxAmount);
            
            $grossTotalAmount = Tools::ps_round($product['total_wt'], 2);
            $grossTotalAmount = $this->fixEasyAmount($grossTotalAmount);
            
            $netTotalAmount = Tools::ps_round($product['total'], 2);
            $netTotalAmount = $this->fixEasyAmount($netTotalAmount);
            
			$orderItem = array(
				'reference' 	   => $product_reference,
				'name' 			   => $product_name,
				'quantity'  	   => $quantity,
				'unit'      	   => 'pcs',
				'unitPrice' 	   => $unitPrice,
				'taxRate'   	   => $taxRate,
				'taxAmount' 	   => $taxAmount,
				'grossTotalAmount' => $grossTotalAmount,
				'netTotalAmount'   => $netTotalAmount
			);
			
            $orderitemslist[] = $orderItem;
        }
        
        // Shipping
        $carrier = new Carrier($cart->id_carrier, $cart->id_lang);
        
        $total_shipping_wt = Tools::ps_round($cart->getOrderTotal(true, Cart::ONLY_SHIPPING), 2);
        $total_shipping_wt = $this->fixEasyAmount($total_shipping_wt);
        
        $total_shipping_without_taxes = Tools::ps_round($cart->getOrderTotal(false, Cart::ONLY_SHIPPING), 2);
        $total_shipping_without_taxes = $this->fixEasyAmount($total_shipping_without_taxes);
        
        $shipping_tax_amount = $total_shipping_wt - $total_shipping_without_taxes;
        $shipping_unit_price = $total_shipping_without_taxes;
        
        $tax_rate = Tools::ps_round(Tax::getCarrierTaxRate((int)$carrier->id, $cart->id_address_delivery), 2);
        $tax_rate = $this->fixEasyAmount($tax_rate);
        
		$carrier_name = $carrier->name;
		if ($carrier_name != '') {
			$reference = $carrier->name;
		} else {
			$reference = $this->l('Shipping');
		}
		
		$orderItem = array(
			'reference' 	   => $reference,
			'name' 			   => 'Shipping',
			'quantity'  	   => 1,
			'unit'      	   => 'pcs',
			'unitPrice' 	   => $shipping_unit_price,
			'taxRate'   	   => $tax_rate,
			'taxAmount' 	   => $shipping_tax_amount,
			'grossTotalAmount' => $total_shipping_wt,
			'netTotalAmount'   => $total_shipping_without_taxes
		);
		
        $orderitemslist[] = $orderItem;

		// VOUCHERS
        $cart_vouchers = $cart->getCartRules();
        $voucher_info = array();
        $cart_contains_voucher = false;
        if (!empty($cart_vouchers)) {
            $cart_contains_voucher = true;
        }

        if ($cart_contains_voucher) {
            $reference = $this->l('Voucher');
            $name = $this->l('Voucher');
            $quantity = 1;
            $unitPrice = Tools::ps_round($cart->getOrderTotal(false, Cart::ONLY_DISCOUNTS), 2);
            $unitPrice = $this->fixEasyAmount($unitPrice);

            $unitPriceWithTaxes = Tools::ps_round($cart->getOrderTotal(true, Cart::ONLY_DISCOUNTS), 2);
            $unitPriceWithTaxes = $this->fixEasyAmount($unitPriceWithTaxes);

            $taxRate = Tools::ps_round((($unitPriceWithTaxes - $unitPrice) / $unitPrice), 2);
            $taxRate = $this->fixEasyAmount($taxRate);
            $taxRate = $taxRate * 100;

            $taxAmount = $unitPriceWithTaxes - $unitPrice;
            $taxAmount = $taxAmount;
            
            $grossTotalAmount = $unitPriceWithTaxes;
            $netTotalAmount   = $unitPrice;

            $orderItem = array(
                'reference' 	   => $reference,
                'name' 			   => $name,
                'quantity'  	   => 1,
                'unit'      	   => 'pcs',
                'unitPrice' 	   => $unitPrice * -1,
                'taxRate'   	   => $taxRate,
                'taxAmount' 	   => $taxAmount * -1,
                'grossTotalAmount' => $grossTotalAmount * -1,
                'netTotalAmount'   => $netTotalAmount * -1
            );
            
            $orderitemslist[] = $orderItem;
        }
        
        // GIFT WRAPPING
        if ($cart->gift) {
            $reference = $this->l('Gift wrapping');
            $name      = $this->l('Wrapping');
            $quantity  = 1;
            $unitPrice = Tools::ps_round($cart->getOrderTotal(false, Cart::ONLY_WRAPPING), 2);
			$unitPrice = $this->fixEasyAmount($unitPrice);
			
            $unitPriceTaxInc = Tools::ps_round($cart->getOrderTotal(true, Cart::ONLY_WRAPPING), 2);
			$unitPriceTaxInc = $this->fixEasyAmount($unitPriceTaxInc);
            
            $grossTotalAmountGift = $unitPriceTaxInc;
            $netTotalAmount = $unitPrice;
            
            $taxRate  = Tools::ps_round((($unitPriceTaxInc - $unitPrice) / $unitPrice) , 2);
            $taxRate = $this->fixEasyAmount($taxRate);
            
            $taxAmount  = $grossTotalAmountGift - $netTotalAmount;
            
			$orderItem = array(
				'reference' 	   => $reference,
				'name' 			   => $name,
				'quantity'  	   => 1,
				'unit'      	   => 'pcs',
				'unitPrice' 	   => $unitPrice,
				'taxRate'   	   => $taxRate,
				'taxAmount' 	   => $taxAmount,
				'grossTotalAmount' => $grossTotalAmountGift,
				'netTotalAmount'   => $netTotalAmount
			);
	
			$orderitemslist[] = $orderItem;
        }

        return $orderitemslist;
    }
    
    public function fixEasyAmount($amount)
    {
        $amount = (string)($amount * 100);
        $amount = (int)$amount;

        return $amount;
    }
    
    public function chargeEasyOrder($id_cart, $paymentId, $id_shop)
    {
        $amount = $this->getOrderAmount($id_cart);
        $items  = $this->getOrderItemsList($id_cart);
        
        $body_of_request = array(
            'amount'     => $amount,
            'orderitems' => $items
        );
        
        $body_of_request = json_encode($body_of_request, JSON_PRETTY_PRINT);
        
        if ((int)Configuration::get('EASYCHECKOUT_LIVE_MODE')) {
            $url = 'https://api.dibspayment.eu';
            $key = Configuration::get('EASYCHECKOUT_SECRET_KEY', null, null, $id_shop);
        } else {
            $url = 'https://test.api.dibspayment.eu';
            $key = Configuration::get('EASYCHECKOUT_SECRET_KEY_TEST', null, null, $id_shop);
        }
        
        $path = '/v1/payments/'.$paymentId.'/charges';

        $easy_url = $url.$path;
        
        $header = $this->getHeaderData($key);
        
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $easy_url);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $header);
        
        curl_setopt($curl, CURLOPT_POST, 1);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $body_of_request);
        
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        
        $response      = json_decode(curl_exec($curl));
        $response_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);

        if ($response_code == 200 OR $response_code == 201) {
            return $response->chargeId;
        } else {
            return false;
        }
    }
    
    public function refundEasyOrder($id_cart, $chargeId, $id_shop)
    {
        $amount = $this->getOrderAmount($id_cart);
        $items  = $this->getOrderItemsList($id_cart);
        
        $body_of_request = array(
            'amount'     => $amount,
            'orderitems' => $items
        );
        
        $body_of_request = json_encode($body_of_request, JSON_PRETTY_PRINT);
        
        if ((int)Configuration::get('EASYCHECKOUT_LIVE_MODE')) {
            $url = 'https://api.dibspayment.eu';
            $key = Configuration::get('EASYCHECKOUT_SECRET_KEY', null, null, $id_shop);
        } else {
            $url = 'https://test.api.dibspayment.eu';
            $key = Configuration::get('EASYCHECKOUT_SECRET_KEY_TEST', null, null, $id_shop);
        }
        
        $path = '/v1/charges/'.$chargeId.'/refunds';
        
        $easy_url = $url.$path;
        
        $header = $this->getHeaderData($key);
        
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $easy_url);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $header);
        
        curl_setopt($curl, CURLOPT_POST, 1);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $body_of_request);
        
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        
        $response      = json_decode(curl_exec($curl));
        $response_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        
        if ($response_code == 200 OR $response_code == 201) {
            return $response->refundId;
        } else {
            return false;
        }
    }
    
    public function cancelEasyOrder($id_cart, $paymentId, $id_shop)
    {
        $amount = $this->getOrderAmount($id_cart);
        $items  = $this->getOrderItemsList($id_cart);
        
        $body_of_request = array(
            'amount'     => $amount,
            'orderitems' => $items
        );
        
        $body_of_request = json_encode($body_of_request, JSON_PRETTY_PRINT);
        
        if ((int)Configuration::get('EASYCHECKOUT_LIVE_MODE')) {
            $url = 'https://api.dibspayment.eu';
            $key = Configuration::get('EASYCHECKOUT_SECRET_KEY', null, null, $id_shop);
        } else {
            $url = 'https://test.api.dibspayment.eu';
            $key = Configuration::get('EASYCHECKOUT_SECRET_KEY_TEST', null, null, $id_shop);
        }
        
        $path = '/v1/payments/'.$paymentId.'/cancels';
        
        $easy_url = $url.$path;
        
        $header = $this->getHeaderData($key);
        
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $easy_url);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $header);
        
        curl_setopt($curl, CURLOPT_POST, 1);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $body_of_request);
        
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
        
        $response      = json_decode(curl_exec($curl));
        $response_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        
        if ($response_code == 200 OR $response_code == 201 OR $response_code == 204) {
            return true;
        } else {
            return false;
        }
    }
    
    public function updateEasyCheckout($paymentId, $currency, $id_cart)
    {
        $cart = new Cart($id_cart);
        $update_info = array();
        
        $cart_info = $this->getCartInfo($currency, $id_cart);

        $update_info['amount'] = $this->fixEasyAmount(Tools::ps_round($cart->getOrderTotal(true), 2));
        $update_info['items'] = $cart_info['order']['items'];
		$update_info['shipping'] = array(
            'costSpecified' => false
        );

        $update_info = json_encode($update_info, JSON_PRETTY_PRINT);
        
        if ((int)Configuration::get('EASYCHECKOUT_LIVE_MODE')) {
            $url = 'https://api.dibspayment.eu';
            $key = Configuration::get('EASYCHECKOUT_SECRET_KEY');
        } else {
            $url = 'https://test.api.dibspayment.eu';
            $key = Configuration::get('EASYCHECKOUT_SECRET_KEY_TEST');
        }
        
        $path = '/v1/payments/'.$paymentId.'/orderitems';
        $easy_url = $url.$path;
        
        $header = $this->getHeaderData($key);
        
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $easy_url);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $header);
        
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'PUT');
        curl_setopt($curl, CURLOPT_POSTFIELDS, $update_info);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
       
		$response = curl_exec($curl);
        $response_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);

        if ($response_code == 204) {
            return true;
        } else {
            return false;
        }
    }
    
    public function getEasyPaymentStatus($id_cart)
    {
        $status = Db::getInstance()->getValue("SELECT payment_status FROM "._DB_PREFIX_."easycheckout_orders WHERE id_cart=".(int)$id_cart."");
        return $status;
    }
    
    public function createPrestaShopOrder($id_cart, $easyCheckout)
    {
        $cart = new Cart((int)$id_cart);

        $countries_iso = array();
        require(_PS_MODULE_DIR_.'easycheckout/iso/country_iso.php');
        
        $dbi = Db::getInstance();
        $dbi->execute("
        UPDATE "._DB_PREFIX_."easycheckout_orders SET ps_created = 1 WHERE id_cart = ".(int)$id_cart." AND ps_created = 0
        ");
        if (!($dbi->Affected_Rows() > 0)) {
            PrestaShopLogger::addLog("Easy, PrestaShop order already being created somewhere else. Order cart id ".(int) $id_cart."", 1, null, null, null, true);
            return false;
        }

        if ($cart->OrderExists() == false) {
            $currency = new Currency($cart->id_currency);
            
            $isB2C = false;
            $siret = '';
            
            // B2B or B2C
            if ((isset($easyCheckout->consumer->company) AND isset($easyCheckout->consumer->company->name) AND $easyCheckout->consumer->company->name != '')) {
                $isB2C = false;
            } else {
                $isB2C = true;
            }
            
            // Logged in customer, existing customer or create new customer
            if ($cart->id_customer > 0) {
				$customer = new Customer($cart->id_customer);
			} else {
				if ($isB2C) {
					if ((int)Customer::customerExists($easyCheckout->consumer->privatePerson->email, true, true) > 0) {
						$customer = new Customer(Customer::customerExists($easyCheckout->consumer->privatePerson->email, true, true));
					} else {
						$customer = $this->addEasyCustomerPrestaShop($cart->id, $easyCheckout, true, null);
					}
				} else {
                    if (isset($easyCheckout->consumer->company->registrationNumber)) {
                        $siret = $easyCheckout->consumer->company->registrationNumber;
                    };
					if ((int)Customer::customerExists($easyCheckout->consumer->company->contactDetails->email, true, true) > 0) {
						$customer = new Customer(Customer::customerExists($easyCheckout->consumer->company->contactDetails->email, true, true));
					} else {
						$customer = $this->addEasyCustomerPrestaShop($cart->id, $easyCheckout, false, $siret);
					}
				}
            }
			
            $easyCheckoutCountryCode = $countries_iso[$easyCheckout->consumer->shippingAddress->country];
            //HERE SEPERATE BILLING ADDRESS FROM SHIPPING ADDRESS
            $addresses = $this->updateCreatePrestaShopAddress(Country::getByIso($easyCheckoutCountryCode), $easyCheckout, $customer->id, $isB2C, $siret);
            
            $cart->id_address_invoice   = $addresses['billing_address']->id;
            $cart->id_address_delivery  = $addresses['shipping_address']->id;
            
            $new_delivery_options = array();
            $new_delivery_options[(int)$cart->id_address_delivery]  = $cart->id_carrier.',';
            $new_delivery_options_serialized = json_encode($new_delivery_options);
            if ($cart->id_carrier > 0) {
                $cart->delivery_option = $new_delivery_options_serialized;
            } else {
                $cart->delivery_option = '';
            }
            $cart->secure_key  = $customer->secure_key;
            $cart->id_customer = $customer->id;
            $cart->save();
            
            $cache_id = 'objectmodel_cart_' . $cart->id . '*';
            Cache::clean($cache_id);
            
            Db::getInstance()->execute('UPDATE '._DB_PREFIX_.'cart SET delivery_option=\''.pSQL($new_delivery_options_serialized).'\' WHERE id_cart='.(int)$cart->id);
            Db::getInstance()->execute('UPDATE '._DB_PREFIX_.'cart_product SET id_address_delivery='.(int)$cart->id_address_delivery.' WHERE id_cart='.(int)$cart->id);
            Db::getInstance()->execute('UPDATE '._DB_PREFIX_.'customization SET id_address_delivery='.(int)$cart->id_address_delivery.' WHERE id_cart='.(int)$cart->id);
            Db::getInstance()->execute('UPDATE '._DB_PREFIX_.'cart SET id_customer='.(int)$customer->id.', secure_key=\''.pSQL($customer->secure_key).'\' WHERE id_cart='.(int)$cart->id);
            
            $cart = new Cart($cart->id);
            
            $comment = $this->l('Easy paymentId:') . ' ' .$easyCheckout->paymentId . "\n";
            
            // $total = $easyCheckout->orderDetails->amount / 100;
            $total = 0;
            if (isset($easyCheckout->summary)) {
                if (isset($easyCheckout->summary->reservedAmount) && $easyCheckout->summary->reservedAmount > 0) {
                    $total = $easyCheckout->summary->reservedAmount / 100;
                } elseif (isset($easyCheckout->summary->chargedAmount) && $easyCheckout->summary->chargedAmount > 0) {
                    $total = $easyCheckout->summary->chargedAmount / 100;
                }
            }
            
            $is_subscription = false;
            $new_order_state_id = (int)Configuration::get('PS_OS_PAYMENT');
            if ((int)Configuration::get('EASYCHECKOUT_RECURRING_PAYMENT')) {
                $is_subscription = true;
                $new_order_state_id = (int)Configuration::get('Easy_Recurrent_Payment');
            }
            
            
            if (isset($easyCheckout->paymentDetails->paymentMethod)) {
                $payment_method = $easyCheckout->paymentDetails->paymentMethod;
            } else {
                $payment_method = $easyCheckout->paymentDetails->paymentType;
            }
            
            $this->validateOrder(
                (int)$cart->id,
                $new_order_state_id,
                $total,
                'Easy, ' . $payment_method,
                $comment . '<br />',
                array(),
                (int)$currency->id,
                false,
                $customer->secure_key
            );
            
            $id_order = Order::getOrderByCartId((int)$cart->id);
            $this->createPrestaShopOrderInDb('Completed', $cart->id, $id_order, $easyCheckout->orderDetails->reference, $easyCheckout->paymentId);
            return $id_order;
        } else {
            PrestaShopLogger::addLog("Easy, order already exists for cart with id ".$id_cart."", 1, null, null, null, true);
            return false;
        }
    }
    
    public function addOrderMessage($message, $id_order)
    {
        $msg = new Message();
        
        $msg->message   = $message;
        $msg->id_order  = (int)$id_order;
        $msg->private   = 1;
        
        $msg->add();
    }
    
    private function getHeaderData($key)
    {
        $header = array();
        $header[] = 'Content-Type: application/json';
        $header[] = 'Authorization: '.$key;
        $header[] = 'commercePlatformTag: PrestaworksEasy';
        return $header;
    }
    
    public function getSubscriptionInformation($subscriptionId)
    {
        if ((int)Configuration::get('EASYCHECKOUT_LIVE_MODE')) {
            $url = 'https://api.dibspayment.eu';
            $key = Configuration::get('EASYCHECKOUT_SECRET_KEY');
        } else {
            $url = 'https://test.api.dibspayment.eu';
            $key = Configuration::get('EASYCHECKOUT_SECRET_KEY_TEST');
        }
        
        $path = '/v1/subscriptions/'.$subscriptionId;
        
        $easy_url = $url.$path;
        
        $header = $this->getHeaderData($key);
        
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $easy_url);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $header);
        
        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, 'GET');
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);

        $response      = json_decode(curl_exec($curl));
        $response_code = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        
        return $response;
    }
    
    public function addEasyCustomerPrestaShop($id_cart, $easyCheckout, $b2c = true, $siret = null)
    {
        $invalidNameCharacters = array('?', '#', '!', '=', '&', '{', '}', '[', ']', '{', '}', '(', ')', ':', ',', ';', '+', '"', "'", "¤", '1', '2', '3', '4', '5', '6', '7', '8', '9', '0');
        
        $cart = new Cart((int)$id_cart);
        
        $customer = new Customer();
        
        if ($b2c) {
            $firstName = str_replace($invalidNameCharacters, array(' '), (Tools::strlen($easyCheckout->consumer->privatePerson->firstName) > 31 ? Tools::substr($easyCheckout->consumer->privatePerson->firstName, 0, 31) : $easyCheckout->consumer->privatePerson->firstName));
            $lastName = $easyCheckout->consumer->privatePerson->lastName != null ? str_replace($invalidNameCharacters, array(' '), (Tools::strlen($easyCheckout->consumer->privatePerson->lastName) > 31 ? Tools::substr($easyCheckout->consumer->privatePerson->lastName, 0, 31) : $easyCheckout->consumer->privatePerson->lastName)) : $firstName;
        } else {
            $firstName = str_replace($invalidNameCharacters, array(' '), (Tools::strlen($easyCheckout->consumer->company->contactDetails->firstName) > 31 ? Tools::substr($easyCheckout->consumer->company->contactDetails->firstName, 0, 31) : $easyCheckout->consumer->company->contactDetails->firstName));
            $lastName = $easyCheckout->consumer->company->contactDetails->lastName != null ? str_replace($invalidNameCharacters, array(' '), (Tools::strlen($easyCheckout->consumer->company->contactDetails->lastName) > 31 ? Tools::substr($easyCheckout->consumer->company->contactDetails->lastName, 0, 31) : $easyCheckout->consumer->company->contactDetails->lastName)) : $firstName;
        }
        $firstName      = empty($firstName)     ? 'First name'      : $firstName;
        $lastName       = empty($lastName)      ? 'Last name'       : $lastName;

        $customer->firstname = $firstName;
        $customer->lastname  = $lastName;
        
        $password = Tools::passwdGen(8);
        $customer->is_guest         = 0;
        $customer->passwd           = Tools::encrypt($password);
        $customer->id_default_group = (int)Configuration::get('PS_CUSTOMER_GROUP', null, $cart->id_shop);
        $customer->optin            = 0;
        $customer->active           = 1;
		
        if ($b2c) {
            $customer->email = $easyCheckout->consumer->privatePerson->email;
        } else {
            $customer->email = $easyCheckout->consumer->company->contactDetails->email;
        }
        $customer->id_gender = 0;
        
        if (!$b2c) {
            $customer->siret = $siret;
        }
        
        $customer->add();
        
        return $customer;
    }
    
    public function updateCreatePrestaShopAddress($countryId, $easyCheckout, $customerId, $b2c, $siret)
    {
        $invalidNameCharacters = array('?', '#', '!', '=', '&', '{', '}', '[', ']', '{', '}', '(', ')', ':', ',', ';', '+', '"', "'", "¤", '1', '2', '3', '4', '5', '6', '7', '8', '9', '0');
		
		$easy_checkout_address = array();
        $customer = new Customer($customerId);
        $new_address_id = 0;

        //BILLING ADDRESS
        $company_name = '';
        if ($b2c) {
            $firstName = str_replace($invalidNameCharacters, array(' '), (Tools::strlen($easyCheckout->consumer->privatePerson->firstName) > 31 ? Tools::substr($easyCheckout->consumer->privatePerson->firstName, 0, 31) : $easyCheckout->consumer->privatePerson->firstName));
            $lastName  = $easyCheckout->consumer->privatePerson->lastName != null ? str_replace($invalidNameCharacters, array(' '), (Tools::strlen($easyCheckout->consumer->privatePerson->lastName) > 31 ? Tools::substr($easyCheckout->consumer->privatePerson->lastName, 0, 31) : $easyCheckout->consumer->privatePerson->lastName)) : $firstName;
        } else {
            if (isset($easyCheckout->consumer->company->name)) {
                $company_name = $easyCheckout->consumer->company->name;
            }
            $firstName = str_replace($invalidNameCharacters, array(' '), (Tools::strlen($easyCheckout->consumer->company->contactDetails->firstName) > 31 ? Tools::substr($easyCheckout->consumer->company->contactDetails->firstName, 0, 31) : $easyCheckout->consumer->company->contactDetails->firstName));
            $lastName  = $easyCheckout->consumer->company->contactDetails->lastName != null ? str_replace($invalidNameCharacters, array(' '), (Tools::strlen($easyCheckout->consumer->company->contactDetails->lastName) > 31 ? Tools::substr($easyCheckout->consumer->company->contactDetails->lastName, 0, 31) : $easyCheckout->consumer->company->contactDetails->lastName)) : $firstName;
        }
        $firstName      = empty($firstName)     ? 'First name'      : $firstName;
        $lastName       = empty($lastName)      ? 'Last name'       : $lastName;

        $new_address_id = 0;
        $address_exist = false;
        // Look for addresses on the customer
        $easy_checkout_address['firstname'] = $firstName;
        $easy_checkout_address['lastname']  = $lastName;
        $easy_checkout_address['city']      = $easyCheckout->consumer->billingAddress->city;
        $easy_checkout_address['address1']  = $easyCheckout->consumer->billingAddress->addressLine1;
        $easy_checkout_address['address2']  = $easyCheckout->consumer->billingAddress->addressLine2;
        $easy_checkout_address['postcode']  = $easyCheckout->consumer->billingAddress->postalCode;
        $sql = 'SELECT id_address FROM '._DB_PREFIX_.'address WHERE 
            id_customer = ' . (int)$customerId  . ' AND 
            firstname   = "' . pSQL($firstName)  . '" AND 
            lastname    = "' . pSQL($lastName)   . '" AND 
            city        = "' . pSQL($easy_checkout_address['city'])      . '" AND 
            address1    = "' . pSQL($easy_checkout_address['address1'])  . '" AND 
            address2    = "' . pSQL($easy_checkout_address['address2'])  . '" AND 
            postcode    = "' . pSQL($easy_checkout_address['postcode'])  . '" AND 
            company     = "' . pSQL($company_name) . '"';
        $new_address_id = Db::getInstance()->getValue($sql);

        if ($new_address_id > 0) {
            $address = new Address($new_address_id, $this->context->language->id);
            $addresses['billing_address'] = $address;
        } else {
            $address = new Address();
            $address->firstname = $firstName;
            $address->lastname  = $lastName;
        
            $address->address1    = $easyCheckout->consumer->billingAddress->addressLine1;
            $address->address2    = $easyCheckout->consumer->billingAddress->addressLine2;
            $address->company     = $company_name;
            $address->city        = $easyCheckout->consumer->billingAddress->city;
            $address->postcode    = $easyCheckout->consumer->billingAddress->postalCode;
            $address->country     = Country::getNameById(Configuration::get('PS_LANG_DEFAULT'), $countryId);
            $address->id_customer = $customerId;
            $address->id_country  = $countryId;
            if ($b2c) {
                $phone_number_prefix    = $easyCheckout->consumer->privatePerson->phoneNumber->prefix != null ? $easyCheckout->consumer->privatePerson->phoneNumber->prefix : '';
                $phone_number_number    = $easyCheckout->consumer->privatePerson->phoneNumber->number != null ? $easyCheckout->consumer->privatePerson->phoneNumber->number : '000000';
            } else {
                $phone_number_prefix    = $easyCheckout->consumer->company->contactDetails->phoneNumber->prefix != null ? $easyCheckout->consumer->company->contactDetails->phoneNumber->prefix : '';
                $phone_number_number    = $easyCheckout->consumer->company->contactDetails->phoneNumber->number != null ? $easyCheckout->consumer->company->contactDetails->phoneNumber->number : '000000';
            }
            $address->phone         = $phone_number_prefix.$phone_number_number;
            $address->phone_mobile  = $phone_number_prefix.$phone_number_number;
            $address->alias         = $this->l('Easy address');
        
            if (!$b2c AND $siret != '') {
                $address->vat_number = $siret;
            }

            $address->add();
            $addresses['billing_address'] = $address;
        }
        //SHIPPING ADDRESS
        $new_address_id = 0;
        $company_name = '';
        if ($b2c) {
            $name = $easyCheckout->consumer->shippingAddress->receiverLine;
            $name = explode(' ', $name, 2);

            $firstName  = $name[0];
            $lastName   = count($name) > 1 ? $name[1] : $firstName;
            $firstName  = str_replace($invalidNameCharacters, array(' '), $firstName);
            $firstName  = Tools::strlen($firstName > 31) ? Tools::substr($firstName, 0, 31) : $firstName;
            $lastName   = str_replace($invalidNameCharacters, array(' '), $lastName);
            $lastName   = Tools::strlen($lastName > 31) ? Tools::substr($lastName, 0, 31) : $lastName;
        } else {
            $company_name = $easyCheckout->consumer->shippingAddress->receiverLine;
            $firstName = str_replace($invalidNameCharacters, array(' '), (Tools::strlen($easyCheckout->consumer->company->contactDetails->firstName) > 31 ? Tools::substr($easyCheckout->consumer->company->contactDetails->firstName, 0, 31) : $easyCheckout->consumer->company->contactDetails->firstName));
            $lastName  = $easyCheckout->consumer->company->contactDetails->lastName != null ? str_replace($invalidNameCharacters, array(' '), (Tools::strlen($easyCheckout->consumer->company->contactDetails->lastName) > 31 ? Tools::substr($easyCheckout->consumer->company->contactDetails->lastName, 0, 31) : $easyCheckout->consumer->company->contactDetails->lastName)) : $firstName;
        }
        $firstName      = empty($firstName)     ? 'First name'      : $firstName;
        $lastName       = empty($lastName)      ? 'Last name'       : $lastName;
        
        // Look for addresses on the customer
        $easy_checkout_address['firstname'] = $firstName;
        $easy_checkout_address['lastname']  = $lastName;
        $easy_checkout_address['city']      = $easyCheckout->consumer->shippingAddress->city;
        $easy_checkout_address['address1']  = $easyCheckout->consumer->shippingAddress->addressLine1;
        $easy_checkout_address['address2']  = $easyCheckout->consumer->shippingAddress->addressLine2;
        $easy_checkout_address['postcode']  = $easyCheckout->consumer->shippingAddress->postalCode;
        $sql = 'SELECT id_address FROM '._DB_PREFIX_.'address WHERE 
            id_customer = ' . (int)$customerId  . ' AND 
            firstname   = "' . pSQL($firstName)  . '" AND 
            lastname    = "' . pSQL($lastName)   . '" AND 
            city        = "' . pSQL($easy_checkout_address['city'])      . '" AND 
            address1    = "' . pSQL($easy_checkout_address['address1'])  . '" AND 
            address2    = "' . pSQL($easy_checkout_address['address2'])  . '" AND 
            postcode    = "' . pSQL($easy_checkout_address['postcode'])  . '" AND 
            company     = "' . pSQL($company_name) . '"';
        $new_address_id = Db::getInstance()->getValue($sql);
        
        if ($new_address_id > 0) {
            $address = new Address($new_address_id, $this->context->language->id);
            $addresses['shipping_address'] = $address;
        } else {
            $address = new Address();
            $address->firstname = $firstName;
            $address->lastname  = $lastName;
        
            $address->address1    = $easyCheckout->consumer->shippingAddress->addressLine1;
            $address->address2    = $easyCheckout->consumer->shippingAddress->addressLine2;
            $address->company     = $company_name;
            $address->city        = $easyCheckout->consumer->shippingAddress->city;
            $address->postcode    = $easyCheckout->consumer->shippingAddress->postalCode;
            $address->country     = Country::getNameById(Configuration::get('PS_LANG_DEFAULT'), $countryId);
            $address->id_customer = $customerId;
            $address->id_country  = $countryId;
            if ($b2c) {
                    //since shippingAddress doesn't contain the phone number we set it to an empty string
                    $phone_number_prefix    = '';//$easyCheckout->consumer->privatePerson->phoneNumber->prefix != null ? $easyCheckout->consumer->privatePerson->phoneNumber->prefix : '';
                    $phone_number_number    = '';//$easyCheckout->consumer->privatePerson->phoneNumber->number != null ? $easyCheckout->consumer->privatePerson->phoneNumber->number : '000000';
            } else {
                $phone_number_prefix    = '';//$easyCheckout->consumer->company->contactDetails->phoneNumber->prefix != null ? $easyCheckout->consumer->company->contactDetails->phoneNumber->prefix : '';
                $phone_number_number    = '';//$easyCheckout->consumer->company->contactDetails->phoneNumber->number != null ? $easyCheckout->consumer->company->contactDetails->phoneNumber->number : '000000';
            }
            $address->phone         = $phone_number_prefix.$phone_number_number;
            $address->phone_mobile  = $phone_number_prefix.$phone_number_number;
            $address->alias         = $this->l('Easy address');
        
            //shippingAddress doesn't contain this field
            // if (!$b2c AND $siret != '') {
            //     $address->vat_number = $siret;
            // }

            $address->add();
            $addresses['shipping_address'] = $address;
        }
        return $addresses;
    }
    
    public function createPrestaShopOrderInDb($status, $id_cart, $id_order, $reference = '', $paymentId = '')
    {
        $use_subscription = 0;
        $subscriptionId = '';
        
        if ($paymentId == '') {
            $paymentId = Db::getInstance()->getValue("SELECT paymentId FROM "._DB_PREFIX_."easycheckout_orders WHERE id_cart = ".$id_cart."");
        }
        
        $check_subscription = Db::getInstance()->getValue("SELECT is_subscription FROM "._DB_PREFIX_."easycheckout_orders WHERE id_cart = ".$id_cart."");
        if ($check_subscription) {
            $easyCheckout = $this->getPaymentInformation($paymentId);
            $subscriptionId = $easyCheckout->subscription->id;
        }
        
        $sql = "
        UPDATE "._DB_PREFIX_."easycheckout_orders
        SET id_order = ".(int)$id_order.", payment_status = '".pSQL($status)."',
            subscription_id = '".$subscriptionId."', id_purchase = '".$reference."',
            updated = NOW() WHERE id_cart = ".(int)$id_cart."
        ";
        
        Db::getInstance()->execute($sql);
    }
    
    protected function createNewOrderState()
    {
        $new_order_state_name = 'Easy Recurrent';
        
        $state_exist = false;
        $states = OrderState::getOrderStates((int)$this->context->language->id);
        
        $id_order_state = 0;
        
        foreach ($states as $state) {
            if (in_array($new_order_state_name, $state)) {
                $state_exist = true;
                $id_order_state = $state['id_order_state'];
                break;
            }
        }
        
        if (!$state_exist) {
            $order_state = new OrderState();
            $order_state->color = '#bbaa00';
            $order_state->send_email = false;
            $order_state->invoice = 1;
            $order_state->logable = 1;
            $order_state->module_name = $this->name;
            $order_state->name = array();
            $languages = Language::getLanguages(false);
            foreach ($languages as $language) {
                $order_state->name[$language['id_lang']] = $new_order_state_name;
            }
            
            $order_state->add();
            
            $id_order_state = $order_state->id;
        }
        
        if ($id_order_state > 0) {
            Configuration::updateValue('Easy_Recurrent_Payment', $id_order_state);
        }
        
        return true;
    }
    
    public function getDeliveryAddressId()
    {
        $idAddress = null;

        switch ($this->context->country->iso_code) {
            case 'DK':
                $idAddress = Configuration::get('EASY_DENMARK_ADDRESS_ID');
                break;
            case 'NO':
                $idAddress = Configuration::get('EASY_NORWAY_ADDRESS_ID');
                break;
            case 'SE':
                $idAddress = Configuration::get('EASY_SWEEDEN_ADDRESS_ID');
                break;
            default:
                $idAddress = Configuration::get('EASY_SWEEDEN_ADDRESS_ID');
                break;
        }

        return (int) $idAddress;
    }
    
    public function response($resp)
    {
        header("Content-Type: application/json; charset=utf-8");
        // http_response_code(200);
        // echo json_encode("200 OK");
        http_response_code($resp);
        switch ($resp) {
            case 200:
                echo json_encode("200 OK");
                break;
                case 401:
                    echo json_encode("401 Unauthorized");
                    break;
            default:
                echo json_encode("200 OK");
                break;
        }
        die;
    }
    
    public function updatePrestaShopOrderInDb($status, $id_order)
    {
        $sql = "
        UPDATE "._DB_PREFIX_."easycheckout_orders SET payment_status = '".pSQL($status)."', updated = NOW() WHERE id_order = ".(int)$id_order."
        ";   
        Db::getInstance()->execute($sql);
    }
}