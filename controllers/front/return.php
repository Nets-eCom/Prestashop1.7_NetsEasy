<?php

class netseasyReturnModuleFrontController extends ModuleFrontController {

    public function __construct() {
        parent::__construct();
    }

    public function setMedia() {
        parent::setMedia();
    }

    public function init() {
        parent::init();
        $nets = new Netseasy();

        $cartId = (int) (@$_GET['id_cart']);
        $paymentId = @$_GET['paymentid'];

        if (!isset($cartId) || $cartId <= 0) {
            $nets->logger->logError('Cart id empty found at time of payment return page');
            Tools::redirect('index.php');
        }

        $cart = new Cart($cartId);
        $customer = new Customer($cart->id_customer);

        if (!Validate::isLoadedObject($customer)) {
            $nets->logger->logError('Envalid customer found at time of payment return page');
            Tools::redirect('index.php');
        }

        if ($cart->OrderExists() == false) {
            $this->module->validateOrder(
                    (int) $cartId,
                    Configuration::get('PS_OS_PAYMENT'),
                    (float) $this->context->cart->getOrderTotal(true, Cart::BOTH),
                    $this->module->displayName,
                    null,
                    null,
                    (int) $this->context->currency->id,
                    false,
                    $customer->secure_key
            );
            $orderId = Order::getOrderByCartId((int) $cart->id);
        } else {
            $orderId = Order::getOrderByCartId((int) $cart->id);
        }

        if (isset($orderId) && $orderId > 0) {
            $orderDetails = new Order((int) $orderId);
            $orderReference = $orderDetails->reference;
            DB::getInstance()->execute('INSERT INTO ' . _DB_PREFIX_ . 'nets_payment_id (`id_order`, `order_reference_id`, `payment_id`) VALUES (' . $orderId . ', "' . $orderReference . '", "' . $paymentId . '")');
            //save charge payment details in ps_nets_payment if auto capture is enabled
            if (Configuration::get('NETS_AUTO_CAPTURE')) {
                $chargeResponse = $nets->MakeCurl($nets->getApiUrl()['backend'] . $paymentId, array(), 'GET');
                if (isset($chargeResponse)) {
                    foreach ($chargeResponse->payment->charges as $ky => $val) {
                        foreach ($val->orderItems as $key => $value) {
                            if (isset($val->chargeId)) {
                                $charge_query = "insert into " . _DB_PREFIX_ . "nets_payment (`payment_id`, `charge_id`,  `product_ref`, `charge_qty`, `charge_left_qty`,`created`) "
                                        . "values ('" . $paymentId . "', '" . $val->chargeId . "', '" . $value->reference . "', '" . $value->quantity . "', '" . $value->quantity . "',now())";
                                DB::getInstance()->execute($charge_query);
                            }
                        }
                    }
                }
            }
            setcookie("nets_payment_selected", "", time() - 3600);
            Tools::redirect('index.php?controller=order-confirmation&id_cart=' . $cartId . '&id_module=' . $this->module->id . '&id_order=' . $orderId . '&key=' . $customer->secure_key);
        } else {
            $this->context->smarty->assign(array(
                'paymentId' => $paymentId
            ));
            return $this->setTemplate('module:' . $this->module->name . '/views/templates/front/payment_error.tpl');
        }
    }

}
