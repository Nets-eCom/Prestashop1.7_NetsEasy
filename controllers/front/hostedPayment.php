<?php

class netseasyHostedPaymentModuleFrontController extends ModuleFrontController {

    public function __construct() {
        parent::__construct();
    }

    public function init() {
        parent::init();
        if (!$this->module->active) {
            Tools::redirect($this->context->link->getPageLink('order'));
        }
    }

    public function initcontent() {
        parent::initcontent();
    }

    public function postProcess() {
        /**
         * Get current cart object from session
         */
        $cart = $this->context->cart;
        $nets = new Netseasy();
        $authorized = false;
        /**
         * Verify if this module is enabled and if the cart has
         * a valid customer, delivery address and invoice address
         */
        if (!$this->module->active || $cart->id_customer == 0 || $cart->id_address_delivery == 0 || $cart->id_address_invoice == 0) {
            Tools::redirect('index.php?controller=order&step=1');
        }

        /**
         * Verify if this payment module is authorized
         */
        foreach (Module::getPaymentModules() as $module) {
            if ($module['name'] == 'netseasy') {
                $authorized = true;
                break;
            }
        }

        if (!$authorized) {
            die($this->l('This payment method is not available.'));
        }

        /** @var CustomerCore $customer */
        $customer = new Customer($cart->id_customer);
        /**
         * Checked if this is a valid customer account
         */
        if (!Validate::isLoadedObject($customer)) {
            Tools::redirect('index.php?controller=order&step=1');
        }

        $split_payment = Tools::getValue('payType','');
        $requestObj = $nets->createRequestObject($this->context->cart->id, $split_payment);
        $nets->logger->logInfo("Payment Request for Host : " . json_encode($requestObj));
        $response = $this->module->makeCreatePaymentCurl($requestObj);
        $nets->logger->logInfo("Payment Response for Host : " . json_encode($response));
        if ($response && !isset($response->errors)) {
            //$lang = @$this->context->language->locale;
            $lang = $nets->getLocale($this->context->language->iso_code);
            if ($lang) {
                Tools::redirect($response->hostedPaymentPageUrl . "&language=$lang");
            } else {
                Tools::redirect($response->hostedPaymentPageUrl);
            }
        } else {
            $nets->logger->logError('Invalid request created for hosted payment redirecting to order controller step 1'. json_encode($requestObj));
            $errorMessage = Context::getContext()->getTranslator()->trans('payment_id_error', [], 'Modules.Netseasy.Payment_error');
            if (isset($response->errors->amount[0])) {
                $errorMessage = Context::getContext()->getTranslator()->trans('payment_amount_error', [], 'Modules.Netseasy.Payment_error');
            }
            $this->errors[] = $errorMessage;
            $this->redirectWithNotifications('index.php?controller=order&step=1');
        }
    }

}
