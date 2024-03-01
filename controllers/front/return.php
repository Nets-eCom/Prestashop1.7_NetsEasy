<?php

/**
 * @property Netseasy $module
 */
class netseasyReturnModuleFrontController extends ModuleFrontController {

    /**
     * NOTE: use postProcess because we do not return any content we always redirect
     */
    public function postProcess() {
        $nets = $this->module;
        $logger = $this->module->logger;
        $logger->logInfo("Response Received : " . json_encode($_GET));

        if (!Tools::getIsset('id_cart')) {
            // @todo change setcookie
            setcookie("nets_transaction_error", 'Something went wrong with your order please try again later', time() + 3600, '/');
            $logger->logError("[Order Response] Empty payment response received." . json_encode($_GET));
            Tools::redirect('index.php');
        }

        if (!Tools::getIsset('paymentId') && !Tools::getIsset('paymentid')) {
            // @todo change setcookie
            setcookie("nets_transaction_error", 'Something went wrong with your order please try again later', time() + 3600, '/');
            $logger->logError("[Order Response] Empty payment response received." . json_encode($_GET));
            Tools::redirect('index.php');
        }

        if (Tools::getIsset('paymentFailed')) {
            // @todo change setcookie
            setcookie("nets_transaction_error", 'Something went wrong with your order please try again later', time() + 3600, '/');
            $logger->logError("[Order Response] Empty payment response received." . json_encode($_GET));
            Tools::redirect('index.php');
        }

        $cartId = (int) Tools::getValue('id_cart', 0);
        $paymentId = Tools::getValue('paymentid', Tools::getValue('paymentId', ''));

        // @todo validate $cart instead of $cartId
        if ($cartId <= 0) {
            $logger->logError("[Order Response][" . $paymentId . "] Cart id empty found at time of payment response");
            Tools::redirect('index.php');
        }

        $cart = new Cart($cartId);
        $customer = new Customer($cart->id_customer);

        if (!Validate::isLoadedObject($customer)) {
            $logger->logError("[Order Response][" . $paymentId . "] Invalid customer found at time of payment response");
            Tools::redirect('index.php');
        }

        $logger->logInfo("[Order Payment Method][" . $paymentId . "] Retrieve Payment Request");
        $paymentDetails = $nets->MakeCurl($nets->getApiUrl()['backend'] . $paymentId, array(), 'GET');
        $logger->logInfo("[Order Payment Method][" . $paymentId . "] Retrieve Payment Response : " . json_encode($paymentDetails));

        if (!$cart->orderExists()) {

            try {
                $logger->logInfo("[Order Response][" . $paymentId . "] Validate Order in Process.");
                $add_order = $this->module->validateOrder(
                        (int) $cartId,
                        Configuration::get('PS_OS_PAYMENT'),
                        (float) $this->context->cart->getOrderTotal(true, Cart::BOTH),
                        $this->getPaymentMethod($paymentDetails),
                        null,
                        null,
                        (int) $this->context->currency->id,
                        false,
                        $customer->secure_key
                );

                if ($add_order) {
                    $logger->logInfo("[Order Response][" . $paymentId . "] Added Order Successfully.");
                } else {
                    $logger->logInfo("[Order Response][" . $paymentId . "] Add Order Failed");
                    $this->cancelOrder($paymentId, $paymentDetails);
                }
            } catch (Exception $e) {
                $this->cancelOrder($paymentId, $paymentDetails);
                $logger->logError("[Order Response][" . $paymentId . "] Order Creation Exception : " . json_encode($e->getMessage()));
            }

            $order = Order::getByCartId((int) $cart->id);
        } else {
            $order = Order::getByCartId((int) $cart->id);
        }

        if (!Validate::isLoadedObject($order)) {
            setcookie("nets_transaction_error", 'Something went wrong with your order please try again later', time() + 3600, '/');
            $logger->logError("[Order Response][" . $paymentId . "] Empty Order Id from order details : ".json_encode($order)); // @todo check if json_encode($order) is valid code
            Tools::redirect('index.php');
        }

        $orderReference = $order->reference;

        // @todo use DB::getInstance()->insert
        DB::getInstance()->execute('INSERT INTO ' . _DB_PREFIX_ . 'nets_payment_id (`id_order`, `order_reference_id`, `payment_id`) VALUES (' . (int) $order->id . ', "' . $orderReference . '", "' . pSQL($paymentId) . '")');
        //save charge payment details in ps_nets_payment if auto capture is enabled
        if (Configuration::get('NETS_AUTO_CAPTURE')) {
            if ($paymentDetails) {
                $chargeResponse = $paymentDetails;
            } else {
                $logger->logInfo("[Order Response][" . $paymentId . "] Retrieve Payment Request for Auto Charge");
                $chargeResponse = $nets->MakeCurl($nets->getApiUrl()['backend'] . $paymentId, array(), 'GET');
                $logger->logInfo("[Order Response][" . $paymentId . "] Retrieve Payment Response for Auto Charge");
            }

            if (isset($chargeResponse)) {
                foreach ($chargeResponse->payment->charges as $ky => $val) {
                    foreach ($val->orderItems as $key => $value) {
                        if (isset($val->chargeId)) {
                            // @todo use DB::getInstance()->insert
                            $charge_query = "insert into " . _DB_PREFIX_ . "nets_payment (`payment_id`, `charge_id`,  `product_ref`, `charge_qty`, `charge_left_qty`,`created`) "
                                    . "values ('" . pSQL($paymentId) . "', '" . $val->chargeId . "', '" . $value->reference . "', '" . (int) $value->quantity . "', '" . (int) $value->quantity . "',now())";
                            DB::getInstance()->execute($charge_query);
                            $logger->logInfo("[Order Response][" . $paymentId . "] Updated Data for Auto Charge");
                        }
                    }
                }
            }
        }
        //update reference in portal
        $this->orderRefUpdate($order, $paymentId, $paymentDetails);

        setcookie("nets_payment_selected", "", time() - 3600);
        $logger->logInfo("[Order Response][" . $paymentId . "] Redirect to confirmation page");
        Tools::redirect('index.php?controller=order-confirmation&id_cart=' . $cartId . '&id_module=' . $this->module->id . '&id_order=' . $order->id . '&key=' . $customer->secure_key);
    }

    protected function getPaymentMethod($paymentDetails) {
        $payment_name = $this->module->displayPaymentName;
        if (isset($paymentDetails->payment->paymentDetails->paymentMethod)) {
            if (!empty($paymentDetails->payment->paymentDetails->paymentMethod)) {
                $payment_name .= ' - ' . $paymentDetails->payment->paymentDetails->paymentMethod;
            }
        }
        return $payment_name;
    }

    protected function orderRefUpdate(Order $orderDetails, $paymentId, $paymentDetails) {
        $logger = $this->module->logger;
        $returnResponse = false;
        $orderReference = '';
        if (!empty($orderDetails)) {
            $orderReference = $orderDetails->reference;
            $payIdResponse = $paymentDetails;

            if (empty($payIdResponse)) {
                $logger->logInfo("[Order Reference Update][" . $paymentId . "] PaymentId empty and tried again.");
                sleep(2);
                $logger->logInfo("[Order Reference Update][" . $paymentId . "] Retrieve Payment Request");
                $payIdResponse = $this->module->MakeCurl($this->module->getApiUrl()['backend'] . $paymentId, array(), 'GET');
                $logger->logInfo("[Order Reference Update][" . $paymentId . "] Retrieve Payment Response : " . json_encode($payIdResponse));
            }

            if (!empty($payIdResponse) && !empty($orderReference)) {
                $requestData = array('checkoutUrl' => $payIdResponse->payment->checkout->url, 'reference' => $orderReference);
                $logger->logInfo("[Order Reference Update][" . $paymentId . "] Reference Payment Request : " . json_encode($requestData));
                $this->module->MakeCurl($this->module->getUpdateRefUrl($paymentId), $requestData, 'PUT');
                $logger->logInfo("[Order Reference Update][" . $paymentId . "] Reference Payment Response");
                $returnResponse = true;
            } else {
                $logger->logError("[Order Reference Update][" . $paymentId . "] Failed to fetch data from retrieve service.");
                Tools::redirect('index.php');
            }
        } else {
            $logger->logError("[Order Reference Update][" . $paymentId . "] Failed to fetch data from Order details");
            Tools::redirect('index.php');
        }

        return $returnResponse;
    }

    protected function cancelOrder($paymentId, $paymentDetails) {
        $logger = $this->module->logger;
        $data = [
            'amount' => $paymentDetails->payment->orderDetails->amount,
        ];
        $logger->logInfo("[Order Cancel][" . $paymentId . "] Cancel Payment Request : " . json_encode($data));
        $this->module->MakeCurl($this->module->getApiUrl()['backend'] . $paymentId . "/cancels", $data, 'POST');
        $logger->logInfo("[Order Cancel][" . $paymentId . "] Cancel Payment Response");

        setcookie("nets_transaction_error", 'Something went wrong with your order please try again later', time() + 3600, '/');
        $logger->logError('order cancelled redirecting to order page');
        Tools::redirect('index.php?controller=order&step=1');
    }

}
