<?php

/**
 * Nets easy - A Nets secure payment module for PrestaShop 1.7
 *
 * This file is the declaration of the module.
 *
 * @license https://opensource.org/licenses/afl-3.0.php
 */
use PHPSQLParser\builders\ReservedBuilder;
use PrestaShopBundle\Form\Admin\Sell\Product\Shipping\ShippingType;
use PrestaShop\PrestaShop\Core\Payment\PaymentOption;
use PrestaShop\PrestaShop\Adapter\Presenter\Cart\CartPresenter;

//use netseasy\controllers\front\OrderInfo;
if (!defined('_PS_VERSION_')) {
    exit;
}
require_once(_PS_ROOT_DIR_ . '/modules/netseasy/Locale.php');

class Netseasy extends PaymentModule {

    public $address;
    public $logger;

    const PAY_METHODS = array(
        'NETS_CARD',
        'NETS_MOBILEPAY',
        'NETS_VIPPS',
        'NETS_SWISH',
        'NETS_SOFORT',
        'NETS_TRUSTLY',
        'NETS_AFTERPAY_INVOICE',
        'NETS_AFTERPAY_INSTALLMENT',
        'NETS_RATEPAY_INSTALLMENT',
        'NETS_PAYPAL'
    );

    /**
     * Netseasy constructor.
     * Set the information about this module
     */
    public function __construct() {
        $this->name = 'netseasy';
        $this->tab = 'payments_gateways';
        $this->version = '1.1.7';
        $this->author = 'Nets Easy';
        $this->displayPaymentName = 'Nets Easy';
        $this->controllers = array('hostedPayment', 'return');
        $this->currencies = true;
        $this->currencies_mode = 'checkbox';
        $this->bootstrap = true;
        $this->displayName = empty(Configuration::get('NETS_PAYMENT_NAME')) ? 'Nets Easy' : Configuration::get('NETS_PAYMENT_NAME');
        $this->description = 'Nets Secure Payment Made Easy';
        $this->confirmUninstall = 'Are you sure you want to uninstall this module?';
        $this->ps_versions_compliancy = array('min' => '1.7.0', 'max' => _PS_VERSION_);

        $this->logger = new FileLogger();
        $this->logger->setFilename(_PS_ROOT_DIR_ . "/var/logs/nets.log");

        parent::__construct();
    }

    // delete config of module
    public function delete_config() {
        $moduleConfiguration = $this->getConfigFormValues();
        foreach ($moduleConfiguration as $key => $value) {
            Configuration::deleteByName($key);
        }
        foreach (self::PAY_METHODS as $key => $value) {
            $moduleConfiguration = $this->getConfigFormValues($value);
            foreach ($moduleConfiguration as $key => $value) {
                Configuration::deleteByName($key);
            }
        }
    }

    /**
     * Install this module and register the following Hooks:
     * @return bool
     */
    public function install() {
        //set default payment mode to empty
        $moduleConfiguration = $this->getConfigFormValues();
        foreach ($moduleConfiguration as $key => $value) {
            Configuration::updateValue($key, '');
        }
        foreach (self::PAY_METHODS as $key => $value) {
            $moduleConfiguration = $this->getConfigFormValues($value);
            foreach ($moduleConfiguration as $key => $value) {
                Configuration::updateValue($key, '');
            }
        }
        if (!Configuration::hasKey('NETS_WEBHOOK_AUTHORIZATION')) {
            Configuration::set('NETS_WEBHOOK_AUTHORIZATION', 'AZ-12345678-az');
        }
        if (!Configuration::get('NETS_WEBHOOK_URL')) {
            Configuration::updateValue('NETS_WEBHOOK_URL', $this->context->link->getModuleLink($this->name, 'webhook', array(), true));
        }
        return parent::install() && $this->addNetsTable() && $this->registerHook('actionAdminControllerSetMedia') && $this->registerHook('displayAdminOrderTop') && $this->registerHook('displayPaymentTop') && $this->registerHook('header') && $this->registerHook('paymentOptions') && $this->registerHook('paymentReturn');
    }

    /**
     * Uninstall this module and remove it from all hooks
     * @return bool
     */
    public function uninstall() {
        $this->delete_config();
        return parent::uninstall();
    }

    /**
     * Returns a string containing the HTML necessary to
     * generate a configuration screen on the admin
     *
     * @return string
     */
    public function getContent() {
        /**
         * If values have been submitted in the form, process.
         */
        $this->context->controller->addJS($this->_path . 'views/js/back.js');
        $this->context->controller->addJS($this->_path . 'views/js/select2.min.js');
        $this->context->controller->addCSS($this->_path . 'views/css/select2.min.css');

        if (((bool) Tools::isSubmit('submitNetsModule')) == true) {
            $this->postProcess();
            $this->context->smarty->assign('success_nets', '');
        }

        foreach (self::PAY_METHODS as $key => $value) {
            if (((bool) Tools::isSubmit('submit_' . $value)) == true) {
                $this->postProcess($value);
                $this->context->smarty->assign('form_success', $value);
            }
        }

        if (!Configuration::get('NETS_WEBHOOK_AUTHORIZATION')) {
            Configuration::updateValue('NETS_WEBHOOK_AUTHORIZATION', 'AZ-12345678-az');
        }
        if (!Configuration::get('NETS_WEBHOOK_URL')) {
            Configuration::updateValue('NETS_WEBHOOK_URL', $this->context->link->getModuleLink($this->name, 'webhook', array(), true));
        }
        $country_list = Country::getCountries((int) Context::getContext()->language->id, false);
        $currency_list = Currency::getCurrencies(true, false, true);
        foreach($currency_list as $key => $value) {
            $currency_list[$key] = (array)$value;
        }

        $this->context->smarty->assign($this->getConfigFormValues());
        foreach (self::PAY_METHODS as $key => $value) {
            $data = $this->getConfigFormValues($value);
            $this->context->smarty->assign($data);
        }
        $this->context->smarty->assign('pay_type_list', self::PAY_METHODS);
        $this->context->smarty->assign('country_list', $country_list);
        $this->context->smarty->assign('currency_list', $currency_list);

        // Call api for fetching latest plugin version.
        if (Configuration::get('NETS_MERCHANT_ID') && Configuration::get('NETS_MERCHANT_EMAIL_ID')) {
            $returnResponse = $this->CallApi();
            if (!empty($returnResponse)) {
                $this->context->smarty->assign(['customData' => $returnResponse]);
            }
        }
        return $this->display(__FILE__, 'views/templates/admin/config.tpl');
    }

    /**
     * This function used for Custom Api service to fetch plugin latest version with notification.
     * @return type
     */
    public function CallApi() {
        $headers[] = 'Content-Type: application/json';
        $headers[] = 'Accept: application/json';

        $dataArray = [
            "merchant_id" => Configuration::get('NETS_MERCHANT_ID'),
            "merchant_email_id" => Configuration::get('NETS_MERCHANT_EMAIL_ID'),
            "plugin_name" => "Prestashop",
            "plugin_version" => $this->version,
            "integration_type" => Configuration::get('NETS_INTEGRATION_TYPE'),
            "shop_url" => (_PS_BASE_URL_SSL_),
            "timestamp" => date('Y-m-d H:i:s')
        ];

        $postData = json_encode($dataArray);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "https://reporting.sokoni.it/enquiry");
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        if ($postData) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
        }
        $this->logger->logInfo("API Request Data : " . $postData);
        $response = curl_exec($ch);
        $info = curl_getinfo($ch);
        $responseData = '';
        $this->logger->logInfo("API Response HTTP Code : " . $info['http_code']);
        if (curl_error($ch)) {
            $this->logger->logInfo("API Response Error Data : " . json_encode(curl_error($ch)));
        }

        if ($info['http_code'] == 200) {
            if ($response) {
                $this->logger->logInfo("API Response Data : " . stripslashes($response));
                $responseDecoded = json_decode($response);
                if ($responseDecoded->status == '00' || $responseDecoded->status == '11') {
                    $responseData = array('status' => $responseDecoded->status, 'data' => json_decode($responseDecoded->data, true));
                }
            }
        }
        return $responseData;
    }

    /**
     * Display this module as a payment option during the checkout
     *
     * @param array $params
     * @return array|void
     */
    public function hookPaymentOptions() {
        /*
         * Verify if this module is active
         */
        if (!$this->active) {
            return;
        }

        $this->smarty->assign(
                $this->getTemplateVarInfos()
        );

        $newOption = new PaymentOption();
        $paymentOptions = [];
        $split_enabled = false;

        if (Configuration::get('NETS_INTEGRATION_TYPE') === 'REDIRECT') {
            if(Configuration::get('NETS_PAYMENT_SPLIT')) {
                foreach (self::PAY_METHODS as $key => $value) {
                    if ($this->is_valid_paymethod($value)) {
                        $split_enabled = true;
                        ${$value . "Option"} = new PaymentOption();
                        ${$value . "Option"}->setModuleName($this->name)
                                ->setCallToActionText( empty(trim(Configuration::get($value . '_CUSTOM_NAME'))) ? $this->trans($value, array(), 'Modules.Netseasy.Config') : Configuration::get($value . '_CUSTOM_NAME'))
                                ->setAction($this->context->link->getModuleLink($this->name, 'hostedPayment', array("payType" => $value), true));
                        $paymentOptions[] = ${$value . "Option"};
                    }
                }
            } else {
                $newOption->setModuleName($this->name)
                    ->setCallToActionText($this->trans($this->displayName, array()))
                    ->setAction($this->context->link->getModuleLink($this->name, 'hostedPayment', array(), true));
                $paymentOptions[] = $newOption;
            }
        } else {
            if(Configuration::get('NETS_PAYMENT_SPLIT')) {
                foreach (self::PAY_METHODS as $key => $value) {
                    if ($this->is_valid_paymethod($value)) {
                        $split_enabled = true;
                        ${$value . "Option"} = new PaymentOption();
                        ${$value . "Option"}->setModuleName($value)
                        ->setCallToActionText( empty(trim(Configuration::get($value . '_CUSTOM_NAME'))) ? $this->trans($value, array(), 'Modules.Netseasy.Config') : Configuration::get($value . '_CUSTOM_NAME'));
                        $paymentOptions[] = ${$value . "Option"};
                    }
                }
            } else {
                $newOption->setModuleName($this->name)
                    ->setCallToActionText($this->trans($this->displayName, array()));
                $paymentOptions[] = $newOption;
            }
        }

        return $paymentOptions;
    }

    public function is_valid_paymethod($paymethod) {
        $config = $this->getConfigFormValues($paymethod);
        $addressOBJ = new Address($this->context->cart->id_address_delivery);
        $country_id = $addressOBJ->id_country;
        $currency_id = $this->context->cart->id_currency;
        if (!$config[$paymethod . '_ENABLED']) {
            return false;
        } else if (!in_array($country_id, explode(',', $config[$paymethod . '_COUNTRY_IDS']))) {
            return false;
        } else if (!in_array($currency_id, explode(',', $config[$paymethod . '_CURRENCY_IDS']))) {
            return false;
        } else {
            return true;
        }
    }

    public function hookHeader() {
        if (Configuration::get('NETS_INTEGRATION_TYPE') === 'EMBEDDED') {
            $this->context->controller->addJS(array($this->_path . 'views/js/nets_checkout.js'));
            $this->context->controller->addCSS($this->_path . 'views/css/front.css');
        }
    }

    /**
     *  Template form information and cart fetched
     * */
    public function getTemplateVarInfos() {
        $configValues = $this->getConfigFormValues();
        return $configValues;
    }

    /**
     * Save form data.
     */
    protected function postProcess($paytype = null) {
        if ($paytype != null) {
            $formValues = $this->getConfigFormValues($paytype);
            foreach (array_keys($formValues) as $key) {
                if (is_array(Tools::getValue($key))) {
                    $ids = strval(implode(',', Tools::getValue($key)));
                    Configuration::updateValue($key, $ids);
                } else {
                    Configuration::updateValue($key, Tools::getValue($key));
                }
            }
        } else {
            $formValues = $this->getConfigFormValues();
            foreach (array_keys($formValues) as $key) {
                Configuration::updateValue($key, Tools::getValue($key));
            }
        }
    }

    // get list of module configuration
    public function getConfigFormValues($paytype = null) {
        if ($paytype != null) {
            return array(
                $paytype . '_ENABLED' => Configuration::get($paytype . '_ENABLED'),
                $paytype . '_COUNTRY_IDS' => Configuration::get($paytype . '_COUNTRY_IDS'),
                $paytype . '_CURRENCY_IDS' => Configuration::get($paytype . '_CURRENCY_IDS'),
                $paytype . '_CUSTOM_NAME' => Configuration::get($paytype . '_CUSTOM_NAME'),
            );
        } else if ($paytype == null) {
            return array(
                'NETS_MERCHANT_ID' => Configuration::get('NETS_MERCHANT_ID'),
                'NETS_MERCHANT_EMAIL_ID' => Configuration::get('NETS_MERCHANT_EMAIL_ID'),
                'NETS_PAYMENT_NAME' => Configuration::get('NETS_PAYMENT_NAME'),
                'NETS_TEST_MODE' => Configuration::get('NETS_TEST_MODE'),
                'NETS_TEST_CHECKOUT_KEY' => Configuration::get('NETS_TEST_CHECKOUT_KEY'),
                'NETS_TEST_SECRET_KEY' => Configuration::get('NETS_TEST_SECRET_KEY'),
                'NETS_LIVE_CHECKOUT_KEY' => Configuration::get('NETS_LIVE_CHECKOUT_KEY'),
                'NETS_LIVE_SECRET_KEY' => Configuration::get('NETS_LIVE_SECRET_KEY'),
                'NETS_INTEGRATION_TYPE' => Configuration::get('NETS_INTEGRATION_TYPE'),
                'NETS_TERMS_URL' => Configuration::get('NETS_TERMS_URL'),
                'NETS_MERCHANT_TERMS_URL' => Configuration::get('NETS_MERCHANT_TERMS_URL'),
                'NETS_ICON_URL' => Configuration::get('NETS_ICON_URL'),
                'NETS_ADMIN_DEBUG_MODE' => Configuration::get('NETS_ADMIN_DEBUG_MODE'),
                'NETS_FRONTEND_DEBUG_MODE' => Configuration::get('NETS_FRONTEND_DEBUG_MODE'),
                'NETS_PAYMENT_SPLIT' => Configuration::get('NETS_PAYMENT_SPLIT'),
                'NETS_AUTO_CAPTURE' => Configuration::get('NETS_AUTO_CAPTURE'),
                'NETS_WEBHOOK_URL' => Configuration::get('NETS_WEBHOOK_URL'),
                'NETS_WEBHOOK_AUTHORIZATION' => Configuration::get('NETS_WEBHOOK_AUTHORIZATION')
            );
        }
    }

    /**
     * To create curl request
     * @param void $url
     * @param array $data
     * @param void $method
     * */
    public function MakeCurl($url, $data, $method = 'POST') {
        $headers[] = 'Content-Type: application/json';
        $headers[] = 'Accept: application/json';
        $headers[] = 'Authorization: ' . $this->getApiKey()['secretKey'];
        $headers[] = 'commercePlatformTag: Nets_Prestashop_1.7';

        $postData = $data;
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        if ($postData) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData));
        }

        $response = curl_exec($ch);

        $info = curl_getinfo($ch);
        switch ($info['http_code']) {
            case 401:
                $message = 'NETS Easy authorization failed. Check your keys';
                break;
            case 400:
                $message = 'NETS Easy. Bad request: ' . $response;
                break;
            case 404:
                $message = 'Payment or charge not found';
                break;
            case 500:
                $message = 'Unexpected error';
                break;
        }

        if (curl_error($ch)) {
            $this->logger->logError(curl_error("Response Error : " . $ch));
        }

        if ($info['http_code'] == 200 || $info['http_code'] == 201 || $info['http_code'] == 400) {
            if ($response) {
                $responseDecoded = json_decode($response);
                return ($responseDecoded) ? $responseDecoded : null;
            }
        }
    }

    /**
     * To create request for passing in API request
     * @param array $params
     * @param string $split_payment
     * */
    public function createRequestObject($cartId, $split_payment = '') {
        $cart = new Cart($cartId);
        $currency = new Currency($cart->id_currency);
        $customerOBJ = new Customer($cart->id_customer);
        $addressOBJ = new Address($cart->id_address_delivery);
        $countryOBJ = new Country($addressOBJ->id_country);

        //Product items
        $products = $cart->getProducts();

        foreach ($products as $item) {
            // easy calc method
            $product = $item['price_with_reduction']; // product price incl. VAT in DB format
            $quantity = $item['quantity'];
            $tax = $item['rate']; // Tax rate in DB format
            $taxFormat = '1' . str_pad(number_format((float) $tax, 2, '.', ''), 5, '0', STR_PAD_LEFT);
            $unitPrice = round(round(($product * 100) / $taxFormat, 2) * 100);
            $netAmount = round($quantity * $unitPrice);
            $grossAmount = round($quantity * ($product * 100));
            $taxAmount = $grossAmount - $netAmount;

            $itemsProductArray[] = array(
                'reference' => !empty($item['reference']) ? $item['reference'] : $item['name'],
                'name' => $item['name'],
                'quantity' => $quantity,
                'unit' => 'pcs',
                'unitPrice' => $unitPrice,
                'taxRate' => $item['rate'] * 100,
                'taxAmount' => $taxAmount,
                'grossTotalAmount' => $grossAmount,
                'netTotalAmount' => $netAmount
            );
            $itemsArray = $itemsTotalProduct = $itemsProductArray;
        }

        //Shipping items
        $carrierGrossAmount = 0;
        $carrierNetAmount = 0;
        $carrier = new Carrier($cart->id_carrier);
        $carrierShipping = $cart->getPackageShippingCost($cart->id_carrier, $use_tax = true);

        if ($carrierShipping > 0) {
            $carrierName = $carrier->name;
            $carrierTax = Tax::getCarrierTaxRate((int) $carrier->id, $cart->id_address_delivery);
            $carrierQuantity = 1;
            $carrierTaxFormat = 0;
            $carrierUnitPrice = round($carrierShipping * 100);

            //if tax is not selected for shipping
            if ($carrierTax > 0) {
                $carrierTaxFormat = '1' . str_pad(number_format((float) $carrierTax, 2, '.', ''), 5, '0', STR_PAD_LEFT);
                $carrierUnitPrice = round(round(($carrierShipping * 100) / $carrierTaxFormat, 2) * 100);
            }
            $carrierNetAmount = round($carrierQuantity * $carrierUnitPrice);
            $carrierGrossAmount = round($carrierQuantity * ($carrierShipping * 100));
            $carrierTaxAmount = $carrierGrossAmount - $carrierNetAmount;

            if ($carrierName != '') {
                $carrierReference = $carrierName;
            } else {
                $carrierReference = $this->l('Shipping');
            }

            $itemsArray[] = array(
                'reference' => $carrierReference,
                'name' => 'Shipping',
                'quantity' => $carrierQuantity,
                'unit' => 'pcs',
                'unitPrice' => $carrierUnitPrice,
                'taxRate' => $carrierTax * 100,
                'taxAmount' => $carrierTaxAmount,
                'grossTotalAmount' => $carrierGrossAmount,
                'netTotalAmount' => $carrierNetAmount
            );
        }

        //Total product sum items
        $itemsGrossPriceSumma = 0;
        foreach ($itemsTotalProduct as $total) {
            $itemsGrossPriceSumma += $total['grossTotalAmount'];
        }

        //Discount items
        $couponTotalData = $cart->getDiscountSubtotalWithoutGifts();
        if (!empty($couponTotalData) && $couponTotalData > 0) {
            $discountAmount = round(round($couponTotalData, 2) * 100);

            $itemsArray[] = array(
                'reference' => 'discount',
                'name' => 'Discount',
                'quantity' => 1,
                'unit' => 'pcs',
                'unitPrice' => -$discountAmount,
                'taxRate' => 0,
                'taxAmount' => 0,
                'grossTotalAmount' => -$discountAmount,
                'netTotalAmount' => -$discountAmount
            );
        }

        // Gift wrapping item
        if ($cart->gift) {
            $giftWrappingAmount = round(round($cart->getGiftWrappingPrice(), 2) * 100);

            $itemsArray[] = array(
                'reference' => 'gift_wrapping',
                'name' => $this->trans('Gift wrapping', [], 'Shop.Theme.Checkout'),
                'quantity' => 1,
                'unit' => 'pcs',
                'unitPrice' => $giftWrappingAmount,
                'taxRate' => 0,
                'taxAmount' => 0,
                'grossTotalAmount' => $giftWrappingAmount,
                'netTotalAmount' => $giftWrappingAmount
            );
        }

        $requestRefId = 'ps_' . Tools::passwdGen(12);

        //Compile data string
        $data = array(
            'order' => array(
                'items' => $itemsArray,
                'amount' => round(round($cart->getCartTotalPrice(), 2) * 100),
                'currency' => $currency->iso_code,
                'reference' => $requestRefId
            ),
            'checkout' => array(
                'charge' => (Configuration::get('NETS_AUTO_CAPTURE')) ? 'true' : 'false',
                'publicDevice' => 'false',
                'integrationType' => Configuration::get('NETS_INTEGRATION_TYPE'),
            ),
        );

        //Checkout type switch
        if (Configuration::get('NETS_INTEGRATION_TYPE') === 'REDIRECT') {
            $data['checkout']['integrationType'] = 'HostedPaymentPage';
            $data['checkout']['returnUrl'] = $this->context->link->getModuleLink($this->name, 'return', array('id_cart' => $cartId));
            $data['checkout']['cancelUrl'] = $this->context->link->getPageLink('order');
        } else {
            $data['checkout']['integrationType'] = 'EmbeddedCheckout';
            $data['checkout']['url'] = $this->context->link->getModuleLink($this->name, 'return', array('id_cart' => $cartId));
        }
        $data['checkout']['termsUrl'] = Configuration::get('NETS_TERMS_URL');
        $data['checkout']['merchantTermsUrl'] = Configuration::get('NETS_MERCHANT_TERMS_URL');
        $data['checkout']['merchantHandlesConsumerData'] = true;

        //consumer data
        $customerTypeArray = array();
        $consumerTypeData = array('default' => 'B2C', 'supportedTypes' => ["B2C"]);
        if (!empty($addressOBJ->company)) {
            $customerTypeArray = array(
                'name' => $addressOBJ->company,
                'contact' => array(
                    'firstName' => $customerOBJ->firstname,
                    'lastName' => $customerOBJ->lastname
                )
            );
            $customerType = 'company';
        } else {
            $customerTypeArray = array(
                'firstName' => $customerOBJ->firstname,
                'lastName' => $customerOBJ->lastname
            );
            $customerType = 'privatePerson';
            $consumerTypeData['supportedTypes'][] = "B2B";
        }
        $data['checkout']['consumerType'] = $consumerTypeData;
        $isoCode3 = $GLOBALS['countriesList'][$countryOBJ->iso_code]['alpha_3'];
        $consumerData = array(
            'email' => $customerOBJ->email,
            'shippingAddress' => array(
                'addressLine1' => $addressOBJ->address1,
                'addressLine2' => $addressOBJ->address2,
                'postalCode' => str_replace(' ', '', $addressOBJ->postcode),
                'city' => $addressOBJ->city,
                'country' => "$isoCode3"
            ),
            "$customerType" => $customerTypeArray
        );

        if (isset($addressOBJ->phone_mobile) && $addressOBJ->phone_mobile != '') {
            $replace_array = array('/', '-', ' ', "+" . $countryOBJ->call_prefix);
            $consumerData['phoneNumber'] = array(
                "prefix" => "+" . $countryOBJ->call_prefix,
                "number" => str_replace($replace_array, '', $addressOBJ->phone_mobile)
            );
        } elseif (isset($addressOBJ->phone) && $addressOBJ->phone != '') {
            $replace_array = array('/', '-', ' ', "+" . $countryOBJ->call_prefix);
            $consumerData['phoneNumber'] = array(
                "prefix" => "+" . $countryOBJ->call_prefix,
                "number" => str_replace($replace_array, '', $addressOBJ->phone)
            );
        }

        $data['checkout']['consumer'] = $consumerData;

        // Webhooks
        $host = parse_url(Configuration::get('NETS_WEBHOOK_URL'), PHP_URL_HOST);
        if ($host !== 'localhost') {
            if (Configuration::get('NETS_WEBHOOK_AUTHORIZATION') != '0') {
                $webHookUrl = (Configuration::get('NETS_WEBHOOK_URL') ? Configuration::get('NETS_WEBHOOK_URL') : '');
                $authKey = Configuration::get('NETS_WEBHOOK_AUTHORIZATION');
                $data['notifications'] = array(
                    'webhooks' => array(
                        array(
                            'eventName' => 'payment.checkout.completed',
                            'url' => $webHookUrl,
                            'authorization' => $authKey
                        ),
                        array(
                            'eventName' => 'payment.charge.created',
                            'url' => $webHookUrl,
                            'authorization' => $authKey
                        ),
                        array(
                            'eventName' => 'payment.refund.completed',
                            'url' => $webHookUrl,
                            'authorization' => $authKey
                        ),
                        array(
                            'eventName' => 'payment.cancel.created',
                            'url' => $webHookUrl,
                            'authorization' => $authKey
                        ),
                        array(
                            'eventName' => 'payment.charge.created.v2',
                            'url' => $webHookUrl,
                            'authorization' => $authKey
                        ),
                        array(
                            'eventName' => 'payment.refund.initiated.v2',
                            'url' => $webHookUrl,
                            'authorization' => $authKey
                        )
                    )
                );
            }
            $logger = new FileLogger();
            $logger->setFilename(_PS_ROOT_DIR_ . "/var/logs/nets_webhook.log");
        }

        if (!empty($split_payment)) {
            $paymentMethodName = $this->getMethodName($split_payment);
            $split_payment_data[] = array(
                "name" => $paymentMethodName,
                "enabled" => true
            );
            $data['paymentMethodsConfiguration'] = $split_payment_data;
        }
        return $data;
    }

    /**
     * To fetch payment url on environment mode
     * */
    public function getApiUrl() {
        $urlData = array();
        if (Configuration::get('NETS_TEST_MODE') == TRUE) {
            $urlData['backend'] = 'https://test.api.dibspayment.eu/v1/payments/';
            $urlData['frontend'] = 'https://test.checkout.dibspayment.eu/v1/checkout.js';
        } else {
            $urlData['backend'] = 'https://api.dibspayment.eu/v1/payments/';
            $urlData['frontend'] = 'https://checkout.dibspayment.eu/v1/checkout.js';
        }
        return $urlData;
    }

    /**
     * To generate Update Information Url on environment mode
     * */
    public function getUpdateRefUrl($paymentId) {
        $updateRefUrl = $this->getApiUrl()['backend'] . $paymentId . "/referenceinformation";
        return $updateRefUrl;
    }

    /**
     * To fetch checkout and secret on environment mode
     * */
    public function getApiKey() {
        $keyData = array();
        if (Configuration::get('NETS_TEST_MODE') == TRUE) {
            $keyData['checkoutKey'] = Configuration::get('NETS_TEST_CHECKOUT_KEY');
            $keyData['secretKey'] = Configuration::get('NETS_TEST_SECRET_KEY');
        } else {
            $keyData['checkoutKey'] = Configuration::get('NETS_LIVE_CHECKOUT_KEY');
            $keyData['secretKey'] = Configuration::get('NETS_LIVE_SECRET_KEY');
        }
        return $keyData;
    }

    public function isUsingNewTranslationSystem() {
        return true;
    }

    function randomString($length = 8) {
        $str = "";
        $characters = array_merge(range('A', 'Z'), range('a', 'z'), range('0', '9'));
        $max = count($characters) - 1;
        for ($i = 0; $i < $length; $i++) {
            $rand = mt_rand(0, $max);
            $str .= $characters[$rand];
        }
        return $str;
    }

    public function isUpdating() {
        $dbVersion = Db::getInstance()->getValue('SELECT `version` FROM `' . _DB_PREFIX_ . 'module` WHERE `name` = \'' . pSQL($this->name) . '\'');
        return version_compare($this->version, $dbVersion, '>');
    }

    public function hookActionAdminControllerSetMedia() {
        if ($this->isUpdating() || !Module::isEnabled($this->name)) {
            return;
        }
    }

    public function hookDisplayPaymentTop() {
        $nets_payment_selected = @$_COOKIE['nets_payment_selected'];
        $payment_split_type = @$_COOKIE['split_type'];

        if (Configuration::get('NETS_INTEGRATION_TYPE') === 'EMBEDDED' && $nets_payment_selected) {

            $payload = $this->createRequestObject($this->context->cart->id, $payment_split_type);
            $url = $this->getApiUrl()['backend'];
            $checkOut = array(
                'url' => $this->getApiUrl()['frontend'],
                'checkoutKey' => $this->getApiKey()['checkoutKey'],
            );

            $this->logger->logInfo("Payment Request for Embedded : " . json_encode($payload));
            $response = $this->MakeCurl($url, $payload);
            $this->logger->logInfo("Payment Response for Embedded : " . json_encode($response));
            if ($response && !isset($response->errors)) {
                $this->context->smarty->assign([
                    'module' => $this->name,
                    'payment_split_type' => !empty($payment_split_type) ? $payment_split_type : $this->name,
                    'paymentId' => $response->paymentId,
                    'checkout' => $checkOut,
                    'lang' => $this->getLocale($this->context->language->iso_code),
                    'returnUrl' => $this->context->link->getModuleLink($this->name, 'return', array('id_cart' => $this->context->cart->id)),
                    'datastring' => $payload,
                    'debugMode' => (Configuration::get('NETS_FRONTEND_DEBUG_MODE') == TRUE) ? TRUE : FALSE,
                    'payment_options' => self::PAY_METHODS
                ]);
                return $this->display(__FILE__, 'views/templates/hook/paymentEmbedded.tpl');
            } else {
                $this->logger->logError('Invalid request created due to error ' . json_encode($response));

                $this->context->smarty->assign([
                    'error_message' => $response->errors ?? null
                ]);

                return $this->display(__FILE__, '/views/templates/front/payment_error.tpl');
            }
        }
    }

    public function hookDisplayAdminOrderTop($params) {
        $orderId = $params['id_order'];
        if (isset($orderId)) {
            $this->_getSession()->set('orderId', $orderId);
        }
        $order = new Order((int) $orderId);
        $url = Tools::getHttpHost(true) . __PS_BASE_URI__ . 'modules/' . $this->name;

        if ($order->module == $this->name) {

            $order_token = Tools::getAdminToken('AdminOrders' . (int) Tab::getIdFromClassName('AdminOrders') . (int) $this->context->employee->id);
            $nets = Db::getInstance()->getRow('SELECT * FROM ' . _DB_PREFIX_ . 'nets_payment_status WHERE order_id = ' . (int) $orderId);

            require_once(_PS_MODULE_DIR_ . $this->name . '/controllers/admin/AdminNetseasyOrderController.php');
            $netsOrderObj = new AdminNetseasyOrderController();
            $netsOrderObj->getPaymentRequest();

            $this->context->smarty->assign(array(
                'ps_version' => _PS_VERSION_,
                'id_order' => $orderId,
                'order_token' => $order_token,
                'nets' => $nets,
                'url' => $url,
                'path' => $this->_path,
                'module' => $this->name,
                'moduleName' => $this->displayPaymentName,
                'user_token' => Tools::getAdminTokenLite('AdminNetseasyOrder'),
                'order_token' => $_GET['_token'],
                'adminurl' => Tools::getHttpHost(true) . __PS_BASE_URI__ . basename(_PS_ADMIN_DIR_),
                'data' => $netsOrderObj->data
            ));
            return $this->display(__FILE__, 'views/templates/hook/admin_order.tpl');
        }
    }

    private function _getSession() {
        return \PrestaShop\PrestaShop\Adapter\SymfonyContainer::getInstance()->get('session');
    }

    public function addNetsTable() {
        DB::getInstance()->execute("CREATE TABLE IF NOT EXISTS `" . _DB_PREFIX_ . "nets_payment_id`
            ( `id_nets_payment` INT(10) NOT NULL AUTO_INCREMENT ,
             `id_order` INT(10) NOT NULL ,
             `order_reference_id` VARCHAR(9) NOT NULL ,
             `payment_id` VARCHAR(75) NOT NULL ,
             `created_date` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
             PRIMARY KEY (`id_nets_payment`))");

        DB::getInstance()->execute("CREATE TABLE IF NOT EXISTS " . _DB_PREFIX_ . "nets_payment (
            `id` int(10) unsigned NOT NULL auto_increment,
            `payment_id` varchar(50) default NULL,
            `charge_id` varchar(50) default NULL,
            `product_ref` varchar(55) collate latin1_general_ci default NULL,
            `charge_qty` int(11) default NULL,
            `charge_left_qty` int(11) default NULL,
            `updated` int(2) unsigned default '0',
            `created` datetime NOT NULL,
            `timestamp` timestamp NOT NULL default CURRENT_TIMESTAMP on update CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`)
            )");

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

        return true;
    }

    public function getLocale($iso_code) {
        $localeArray = array(
            'GB' => 'en-GB',
            'DK' => 'da-DK',
            'NL' => 'nl-NL',
            'EE' => 'ee-EE',
            'FI' => 'fi-FI',
            'FR' => 'fr-FR',
            'DE' => 'de-DE',
            'IT' => 'it-IT',
            'LV' => 'lv-LV',
            'LT' => 'lt-LT',
            'NO' => 'nb-NO',
            'NN' => 'nb-NO',
            'PL' => 'pl-PL',
            'ES' => 'es-ES',
            'SK' => 'sk-SK',
            'SE' => 'sv-SE'
        );
        $localeCode = 'en-GB';
        if (array_key_exists(strtoupper($iso_code), $localeArray)) {
            $localeCode = $localeArray[strtoupper($iso_code)];
        }
        return $localeCode;
    }

    public function getMethodName($paymentMethod) {
        switch ($paymentMethod) {
            case 'NETS_CARD':
                $paymentName = "Card";
                break;
            case 'NETS_MOBILEPAY':
                $paymentName = "MobilePay";
                break;
            case 'NETS_VIPPS':
                $paymentName = "Vipps";
                break;
            case 'NETS_SWISH':
                $paymentName = "Swish";
                break;
            case 'NETS_SOFORT':
                $paymentName = "Sofort";
                break;
            case 'NETS_TRUSTLY':
                $paymentName = "Trustly";
                break;
            case 'NETS_AFTERPAY_INVOICE':
                $paymentName = "EasyInvoice";
                break;
            case 'NETS_AFTERPAY_INSTALLMENT':
                $paymentName = "EasyInstallment";
                break;
            case 'NETS_RATEPAY_INSTALLMENT':
                $paymentName = "RatePayInstallment";
                break;
            case 'NETS_PAYPAL':
                $paymentName = "PayPal";
                break;
        }
        return $paymentName;
    }

}
