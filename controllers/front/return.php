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
        $nets->logger->logInfo("Response Received : " . json_encode($_GET));
        $paymentDetails = null;

        if (isset($_GET['id_cart']) && (isset($_GET['paymentId']) || !empty($_GET['paymentid']))) {
            $cartId = (int) ($_GET['id_cart']);
            $paymentId = isset($_GET['paymentid']) ? $_GET['paymentid'] : $_GET['paymentId'];

            if (!isset($cartId) || $cartId <= 0) {
                $nets->logger->logError("[Order Response][" . $paymentId . "] Cart id empty found at time of payment response");
                Tools::redirect('index.php');
            }

            $cart = new Cart($cartId);
            $customer = new Customer($cart->id_customer);

            if (!Validate::isLoadedObject($customer)) {
                $nets->logger->logError("[Order Response][" . $paymentId . "] Invalid customer found at time of payment response");
                Tools::redirect('index.php');
            }

            $nets->logger->logInfo("[Order Payment Method][" . $paymentId . "] Retrieve Payment Request");
            $paymentDetails = $nets->MakeCurl($nets->getApiUrl()['backend'] . $paymentId, array(), 'GET');
            $nets->logger->logInfo("[Order Payment Method][" . $paymentId . "] Retrieve Payment Response : " . json_encode($paymentDetails));

            if ($cart->OrderExists() == false) {

                try {
                    $nets->logger->logInfo("[Order Response][" . $paymentId . "] Validate Order in Process.");
                    $add_order = $this->module->validateOrder(
                            (int) $cartId,
                            Configuration::get('PS_OS_PAYMENT'),
                            (float) $this->context->cart->getOrderTotal(true, Cart::BOTH),
                            $this->GetPaymentMethod($paymentDetails),
                            null,
                            null,
                            (int) $this->context->currency->id,
                            false,
                            $customer->secure_key
                    );

                    if ($add_order) {
                        $nets->logger->logInfo("[Order Response][" . $paymentId . "] Added Order Successfully.");
                    } else {
                        $nets->logger->logInfo("[Order Response][" . $paymentId . "] Add Order Failed");
                        $this->cancelOrder($paymentId, $paymentDetails);
                    }
                } catch (Exception $e) {
                    $this->cancelOrder($paymentId, $paymentDetails);
                    $this->logger->logError("[Order Response][" . $paymentId . "] Order Creation Exception : " . json_encode($e->getMessage()));
                }

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
                    if ($paymentDetails) {
                        $chargeResponse = $paymentDetails;
                    } else {
                        $nets->logger->logInfo("[Order Response][" . $paymentId . "] Retrieve Payment Request for Auto Charge");
                        $chargeResponse = $nets->MakeCurl($nets->getApiUrl()['backend'] . $paymentId, array(), 'GET');
                        $nets->logger->logInfo("[Order Response][" . $paymentId . "] Retrieve Payment Response for Auto Charge");
                    }

                    if (isset($chargeResponse)) {
                        foreach ($chargeResponse->payment->charges as $ky => $val) {
                            foreach ($val->orderItems as $key => $value) {
                                if (isset($val->chargeId)) {
                                    $charge_query = "insert into " . _DB_PREFIX_ . "nets_payment (`payment_id`, `charge_id`,  `product_ref`, `charge_qty`, `charge_left_qty`,`created`) "
                                            . "values ('" . $paymentId . "', '" . $val->chargeId . "', '" . $value->reference . "', '" . $value->quantity . "', '" . $value->quantity . "',now())";
                                    DB::getInstance()->execute($charge_query);
                                    $nets->logger->logInfo("[Order Response][" . $paymentId . "] Updated Data for Auto Charge");
                                }
                            }
                        }
                    }
                }
                //update reference in portal
                $this->OrderRefUpdate($orderId, $paymentId, $paymentDetails);

                setcookie("nets_payment_selected", "", time() - 3600);
                $nets->logger->logInfo("[Order Response][" . $paymentId . "] Redirect to confirmation page");
                Tools::redirect('index.php?controller=order-confirmation&id_cart=' . $cartId . '&id_module=' . $this->module->id . '&id_order=' . $orderId . '&key=' . $customer->secure_key);
            } else {
                setcookie("nets_transaction_error", 'Something went wrong with your order please try again later', time() + 3600, '/');
                $nets->logger->logError("[Order Response][" . $paymentId . "] Empty Order Id from order details : ".json_encode($orderId));
                Tools::redirect('index.php');
            }
        } else {
            setcookie("nets_transaction_error", 'Something went wrong with your order please try again later', time() + 3600, '/');
            $nets->logger->logError("[Order Response] Empty payment response received." . json_encode($_GET));
            Tools::redirect('index.php');
        }
    }

    public function GetPaymentMethod($paymentDetails) {
        $payment_name = "Nets Easy";
        if (isset($paymentDetails->payment->paymentDetails->paymentMethod)) {
            if (!empty($paymentDetails->payment->paymentDetails->paymentMethod)) {
                $payment_name .= ' - ' . $paymentDetails->payment->paymentDetails->paymentMethod;
            }
        }
        return $payment_name;
    }

    public function OrderRefUpdate($orderId, $paymentId, $paymentDetails) {
        $returnResponse = false;
        $NetsEasy = new Netseasy();
        //To fetch complet order details with order id.
        $orderDetails = new Order((int) $orderId);
        $orderReference = '';
        if (!empty($orderDetails)) {
            $orderReference = $orderDetails->reference;
            $payIdResponse = $paymentDetails;

            if (empty($payIdResponse)) {
                $NetsEasy->logger->logInfo("[Order Reference Update][" . $paymentId . "] PaymentId empty and tried again.");
                sleep(2);
                $NetsEasy->logger->logInfo("[Order Reference Update][" . $paymentId . "] Retrieve Payment Request");
                $payIdResponse = $NetsEasy->MakeCurl($NetsEasy->getApiUrl()['backend'] . $paymentId, array(), 'GET');
                $NetsEasy->logger->logInfo("[Order Reference Update][" . $paymentId . "] Retrieve Payment Response : " . json_encode($payIdResponse));
            }

            if (!empty($payIdResponse) && !empty($orderReference)) {
                $requestData = array('checkoutUrl' => $payIdResponse->payment->checkout->url, 'reference' => $orderReference);
                $NetsEasy->logger->logInfo("[Order Reference Update][" . $paymentId . "] Reference Payment Request : " . json_encode($requestData));
                $NetsEasy->MakeCurl($NetsEasy->getUpdateRefUrl($paymentId), $requestData, 'PUT');
                $NetsEasy->logger->logInfo("[Order Reference Update][" . $paymentId . "] Reference Payment Response");
                $returnResponse = true;
            } else {
                $NetsEasy->logger->logError("[Order Reference Update][" . $paymentId . "] Failed to fetch data from retrieve service.");
                Tools::redirect('index.php');
            }
        } else {
            $NetsEasy->logger->logError("[Order Reference Update][" . $paymentId . "] Failed to fetch data from Order details");
            Tools::redirect('index.php');
        }
        return $returnResponse;
    }

    public function cancelOrder($paymentId, $paymentDetails) {
        $NetsEasy = new Netseasy();
        $data = [
            'amount' => $paymentDetails->payment->orderDetails->amount,
        ];
        $NetsEasy->logger->logInfo("[Order Cancel][" . $paymentId . "] Cancel Payment Request : " . json_encode($data));
        $NetsEasy->MakeCurl($NetsEasy->getApiUrl()['backend'] . $paymentId . "/cancels", $data, 'POST');
        $NetsEasy->logger->logInfo("[Order Cancel][" . $paymentId . "] Cancel Payment Response");

        setcookie("nets_transaction_error", 'Something went wrong with your order please try again later', time() + 3600, '/');
        $NetsEasy->logger->logError('order cancelled redirecting to order page');
        Tools::redirect('index.php?controller=order&step=1');
    }

}
