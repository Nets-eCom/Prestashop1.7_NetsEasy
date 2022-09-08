<?php

class AdminNetseasyOrderController extends ModuleAdminController {

    const ENDPOINT_TEST = 'https://test.api.dibspayment.eu/v1/payments/';
    const ENDPOINT_LIVE = 'https://api.dibspayment.eu/v1/payments/';
    const ENDPOINT_TEST_CHARGES = 'https://test.api.dibspayment.eu/v1/charges/';
    const ENDPOINT_LIVE_CHARGES = 'https://api.dibspayment.eu/v1/charges/';
    const RESPONSE_TYPE = "application/json";

    protected $paymentId;
    protected $orderId;
    public $logger;
    public $data = array();

    public function __construct() {
        $this->logger = new FileLogger();
        $this->logger->setFilename(_PS_ROOT_DIR_ . "/var/logs/nets.log");
        // IF NOT EXISTS !!
        $result = DB::getInstance()->execute("CREATE TABLE IF NOT EXISTS " . _DB_PREFIX_ . "nets_payment (
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
        parent::__construct();
    }

    public function getPaymentRequest() {
        $this->orderId = $this->_getSession()->get('orderId');
        $this->getPaymentId($this->orderId);
        $this->data['responseItems'] = $this->checkPartialItems($this->orderId);
        $status = $this->is_easy($this->orderId);
        $this->data['status'] = $status;
        //Handle charge and refund done from portal
        $this->managePortalChargeAndRefund();
        $this->data['paymentId'] = $this->paymentId;
        $this->data['oID'] = $this->orderId;
        $api_return = $this->getCurlResponse($this->getApiUrl() . $this->paymentId, 'GET');
        $response = json_decode($api_return, true);
        $response['payment']['checkout'] = "";
        $this->data['apiGetRequest'] = "Api Get Request: " . print_r($response, true);
        $this->data['printResponseItems'] = "Response Items: " . print_r($this->data['responseItems'], true);
        $this->data['debugMode'] = Configuration::get('NETS_ADMIN_DEBUG_MODE');
    }

    /*
     * Function to fetch payment id from databse table nets_payment_id
     * @param $order_id
     * @return nets payment id
     */

    public function getPaymentId($order_id) {
        $query = DB::getInstance()->executeS("SELECT payment_id FROM `" . _DB_PREFIX_ . "nets_payment_id`  WHERE id_order = '" . (int) $order_id . "'");
        $this->paymentId = reset($query)['payment_id'];
        return $this->paymentId;
    }

    /*
     * Function to get order items to pass capture, refund, cancel api
     * @param $orderid alphanumeric
     * @return array order items and amount
     */

    public function getOrderItems($orderId) {
        //get order products          
        $taxRateShipping = $order_total = 0;
        $product_query = DB::getInstance()->executeS(
                "SELECT product_id,product_reference,product_name,product_price,tax_rate,product_quantity,total_price_tax_incl,total_shipping_price_tax_incl FROM " . _DB_PREFIX_ . "order_detail WHERE id_order = '" . (int) $orderId . "'"
        );

        if (!empty(DB::getInstance()->numRows($product_query))) {
            foreach ($product_query as $prows) {
                //get product tax rate                 
                $quantity = (int) $prows['product_quantity'];
                $taxRate = $prows['tax_rate'];
                $product = $prows['total_price_tax_incl'] / $quantity;
                $taxRateShipping = $tax = $taxRate; // Tax rate in DB format
                $taxFormat = '1' . str_pad(number_format((float) $tax, 2, '.', ''), 5, '0', STR_PAD_LEFT);
                $unitPrice = round(round(($product * 100) / $taxFormat, 2) * 100);
                $netAmount = round($quantity * $unitPrice);
                $grossAmount = round($quantity * ($product * 100));
                $taxAmount = $grossAmount - $netAmount;

                $taxRate = number_format($taxRate, 2) * 100;
                $itemsArray[] = array(
                    'reference' => !empty($prows['product_reference']) ? $prows['product_reference'] : $prows['product_name'],
                    'name' => $prows['product_name'],
                    'quantity' => $quantity,
                    'unit' => 'pcs',
                    'unitPrice' => $unitPrice,
                    'taxRate' => $taxRate,
                    'taxAmount' => $taxAmount,
                    'grossTotalAmount' => $grossAmount,
                    'netTotalAmount' => $netAmount
                );
            }
        }

        //shipping items
        $shippingCost = '';
        $query = DB::getInstance()->executeS("SELECT total_shipping,total_paid,id_carrier FROM `" . _DB_PREFIX_ . "orders`  WHERE id_order = '" . (int) $orderId . "'");
        $shippingCost = reset($query)['total_shipping'];
        $order_total = reset($query)['total_paid'];
        $id_carrier = reset($query)['id_carrier'];

        $query = DB::getInstance()->executeS("SELECT name FROM `" . _DB_PREFIX_ . "carrier` WHERE id_carrier = '" . (int) $id_carrier . "'");
        $shippingReference = reset($query)['name'];

        if (!empty($shippingCost) && $shippingCost > 0) {
            //easy calc method  
            $quantity = 1;
            $shipping = (isset($shippingCost)) ? $shippingCost : 0; // shipping price incl. VAT in DB format 
            $tax = (isset($taxRateShipping)) ? $taxRateShipping : 0; // Tax rate in DB format						

            $taxFormat = '1' . str_pad(number_format((float) $tax, 2, '.', ''), 5, '0', STR_PAD_LEFT);
            $unitPrice = round(round(($shipping * 100) / $taxFormat, 2) * 100);
            $netAmount = round($quantity * $unitPrice);
            $grossAmount = round($quantity * ($shipping * 100));
            $taxAmount = $grossAmount - $netAmount;
            $itemsArray[] = array(
                'reference' => $shippingReference,
                'name' => 'Shipping',
                'quantity' => $quantity,
                'unit' => 'pcs',
                'unitPrice' => $unitPrice,
                'taxRate' => $tax * 100,
                'taxAmount' => $taxAmount,
                'grossTotalAmount' => $grossAmount,
                'netTotalAmount' => $netAmount
            );
        }
        // items total sum
        $itemsGrossPriceSumma = 0;
        foreach ($itemsArray as $total) {
            $itemsGrossPriceSumma += $total['grossTotalAmount'];
        }
        // compile datastring
        $data = array(
            'order' => array(
                'items' => $itemsArray,
                'amount' => $order_total,
                'currency' => ''
            )
        );
        return $data;
    }

    /*
     * Function to get list of partial charge/refund and reserved items list
     * @param order id
     * @return array of reserved, partial charged,partial refunded items
     */

    public function checkPartialItems($orderId) {
        $orderItems = $this->getOrderItems($orderId);

        $products = [];
        $chargedItems = [];
        $refundedItems = [];
        $cancelledItems = [];
        $failedItems = [];
        $itemsList = [];
        if (!empty($orderItems)) {
            foreach ($orderItems['order']['items'] as $items) {
                $products[$items['reference']] = array(
                    'reference' => $items['reference'],
                    'name' => $items['name'],
                    'quantity' => (int) $items['quantity'],
                    'taxRate' => $items['taxRate'],
                    'netprice' => $items['unitPrice'] / 100
                );
            }
            if (isset($orderItems['order']['amount'])) {
                $lists['orderTotalAmount'] = $orderItems['order']['amount'];
            }
        }

        $api_return = $this->getCurlResponse($this->getApiUrl() . $this->paymentId, 'GET');
        $response = json_decode($api_return, true);
        $paymentType = isset($response['payment']['paymentDetails']['paymentType']) ? $response['payment']['paymentDetails']['paymentType'] : '';

        if (!empty($response['payment']['charges'])) {
            $qty = 0;
            $netprice = 0;
            $grossprice = 0;

            foreach ($response['payment']['charges'] as $key => $values) {
                for ($i = 0; $i < count($values['orderItems']); $i++) {
                    if (array_key_exists($values['orderItems'][$i]['reference'], $chargedItems)) {
                        $qty = $chargedItems[$values['orderItems'][$i]['reference']]['quantity'] + $values['orderItems'][$i]['quantity'];
                        $price = $chargedItems[$values['orderItems'][$i]['reference']]['grossprice'] + number_format((float) ($values['orderItems'][$i]['grossTotalAmount'] / 100), 2, '.', '');
                        $priceGross = $price / $qty;
                        $netprice = $values['orderItems'][$i]['unitPrice'] * $qty;
                        $grossprice = $values['orderItems'][$i]['grossTotalAmount'] * $qty;
                        $chargedItems[$values['orderItems'][$i]['reference']] = array(
                            'reference' => $values['orderItems'][$i]['reference'],
                            'name' => $values['orderItems'][$i]['name'],
                            'quantity' => $qty,
                            'taxRate' => $values['orderItems'][$i]['taxRate'] / 100,
                            'grossprice' => $priceGross,
                            'currency' => $response['payment']['orderDetails']['currency']
                        );
                    } else {
                        $grossprice = $values['orderItems'][$i]['grossTotalAmount'] / 100;
                        //For charge all
                        $pquantity = '';
                        foreach ($products as $key => $prod) {
                            if ($prod['reference'] == $values['orderItems'][$i]['reference']) {
                                $pquantity = $prod['quantity'];
                            }
                        }
                        if ($pquantity == $values['orderItems'][$i]['quantity']) {
                            $priceOne = $values['orderItems'][$i]['grossTotalAmount'] / $values['orderItems'][$i]['quantity'];
                            $grossprice = number_format((float) ($priceOne / 100), 2, '.', '');
                        }
                        $chargedItems[$values['orderItems'][$i]['reference']] = array(
                            'reference' => $values['orderItems'][$i]['reference'],
                            'name' => $values['orderItems'][$i]['name'],
                            'quantity' => $values['orderItems'][$i]['quantity'],
                            'taxRate' => $values['orderItems'][$i]['taxRate'] / 100,
                            'grossprice' => $grossprice,
                            'currency' => $response['payment']['orderDetails']['currency']
                        );
                    }
                }
            }
        }

        if (!empty($response['payment']['refunds'])) {
            $qty = 0;
            $netprice = 0;
            foreach ($response['payment']['refunds'] as $key => $values) {
                for ($i = 0; $i < count($values['orderItems']); $i++) {
                    if (array_key_exists($values['orderItems'][$i]['reference'], $refundedItems)) {
                        $qty = $refundedItems[$values['orderItems'][$i]['reference']]['quantity'] + $values['orderItems'][$i]['quantity'];
                        $netprice = $values['orderItems'][$i]['unitPrice'] * $qty;
                        $grossprice = ($refundedItems[$values['orderItems'][$i]['reference']]['grossprice'] + ($values['orderItems'][$i]['grossTotalAmount'] / 100));
                        $refundedItems[$values['orderItems'][$i]['reference']] = array(
                            'reference' => $values['orderItems'][$i]['reference'],
                            'name' => $values['orderItems'][$i]['name'],
                            'quantity' => $qty,
                            'grossprice' => number_format((float) (($grossprice)), 2, '.', ''),
                            'currency' => $response['payment']['orderDetails']['currency']
                        );
                    } else {
                        $grossprice = $values['orderItems'][$i]['grossTotalAmount'] / 100;
                        //For charge all
                        $pquantity = '';
                        foreach ($products as $key => $prod) {
                            if ($prod['reference'] == $values['orderItems'][$i]['reference']) {
                                $pquantity = $prod['quantity'];
                            }
                        }
                        if ($pquantity == $values['orderItems'][$i]['quantity']) {
                            $priceOne = $values['orderItems'][$i]['grossTotalAmount'] / $values['orderItems'][$i]['quantity'];
                            $grossprice = number_format((float) ($priceOne / 100), 2, '.', '');
                        }

                        $refundedItems[$values['orderItems'][$i]['reference']] = array(
                            'reference' => $values['orderItems'][$i]['reference'],
                            'name' => $values['orderItems'][$i]['name'],
                            'quantity' => $values['orderItems'][$i]['quantity'],
                            'grossprice' => $grossprice,
                            'currency' => $response['payment']['orderDetails']['currency']
                        );
                    }
                }
            }
        }
        if (isset($response['payment']['summary']['cancelledAmount'])) {
            foreach ($orderItems['order']['items'] as $items) {
                $cancelledItems[$items['reference']] = array(
                    'name' => $items['name'],
                    'quantity' => (int) $items['quantity'],
                    'netprice' => $items['unitPrice'] / 100
                );
            }
        }
        if (!isset($response['payment']['summary']['reservedAmount']) && $paymentType != 'A2A') {
            foreach ($orderItems['order']['items'] as $items) {
                $failedItems[$items['reference']] = array(
                    'name' => $items['name'],
                    'quantity' => (int) $items['quantity'],
                    'netprice' => $items['unitPrice'] / 100
                );
            }
        }
        // get list of partial charged items and check with quantity and send list for charge rest of items

        foreach ($products as $key => $prod) {
            if (array_key_exists($key, $chargedItems)) {
                $qty = $prod['quantity'] - $chargedItems[$key]['quantity'];
            } else {
                $qty = $prod['quantity'];
            }
            if (array_key_exists($key, $chargedItems) && array_key_exists($key, $refundedItems)) {
                if ($chargedItems[$key]['quantity'] == $refundedItems[$key]['quantity']) {
                    unset($chargedItems[$key]);
                }
            }

            if (array_key_exists($key, $chargedItems) && array_key_exists($key, $refundedItems)) {
                $qty = $chargedItems[$key]['quantity'] - $refundedItems[$key]['quantity'];
                if ($qty > 0)
                    $chargedItems[$key]['quantity'] = $qty;
            }
            if ($qty > 0) {
                $netprice = number_format((float) ($prod['netprice']), 2, '.', '');
                $grossprice = number_format((float) ($prod['netprice'] * ("1." . $prod['taxRate'])), 2, '.', '');
                $itemsList[] = array(
                    'name' => $prod['name'],
                    'reference' => $key,
                    'taxRate' => $prod['taxRate'] / 100,
                    'quantity' => $qty,
                    'netprice' => $netprice,
                    'grossprice' => $grossprice,
                    'currency' => $response['payment']['orderDetails']['currency']
                );
            }
            if ($chargedItems) {
                if ($chargedItems[$key]['quantity'] > $prod['quantity']) {
                    $chargedItems[$key]['quantity'] = $prod['quantity'];
                }
            }
        }
        $reserved = $charged = $cancelled = $refunded = '';
        if (isset($response['payment']['summary']['reservedAmount'])) {
            $reserved = $response['payment']['summary']['reservedAmount'];
        }
        if (isset($response['payment']['summary']['chargedAmount'])) {
            $charged = $response['payment']['summary']['chargedAmount'];
        }
        if (isset($response['payment']['summary']['cancelledAmount'])) {
            $cancelled = $response['payment']['summary']['cancelledAmount'];
        }
        if (isset($response['payment']['summary']['refundedAmount'])) {
            $refunded = $response['payment']['summary']['refundedAmount'];
        }

        if ($reserved != $charged && $reserved != $cancelled) {
            if (count($itemsList) > 0) {
                $lists['reservedItems'] = $itemsList;
            }
        }
        if (count($chargedItems) > 0 && $reserved === $charged) {
            $lists['chargedItems'] = $chargedItems;
        }

        if (count($chargedItems) > 0 && $paymentType == "A2A") {
            $lists['chargedItems'] = $chargedItems;
        }

        if ($reserved != $charged && $reserved != $cancelled) {
            $lists['chargedItemsOnly'] = $chargedItems;
        }
        if (count($refundedItems) > 0) {
            $lists['refundedItems'] = $refundedItems;
        }
        if (count($cancelledItems) > 0) {
            $lists['cancelledItems'] = $itemsList;
        }
        if (count($failedItems) > 0) {
            $lists['failedItems'] = $itemsList;
        }
        return $lists;
    }

    /**
     * Function to check the nets payment status and display in admin order list backend page
     *
     * @return Payment Status
     */
    public function is_easy($oder_id) {
        if (!empty($oder_id)) {
            // Get order db status from orders_status_history if cancelled          
            $query = DB::getInstance()->executeS("SELECT current_state FROM `" . _DB_PREFIX_ . "orders`  WHERE id_order = '" . (int) $oder_id . "'");
            $current_state = reset($query)['current_state'];
            // if order is cancelled and payment is not updated as cancelled, call nets cancel payment api
            if ($current_state === '6') {
                $data = $this->getOrderItems($oder_id);
                // call cancel api here
                $cancelUrl = $this->getVoidPaymentUrl($this->paymentId);
                $cancelBody = [
                    'amount' => $data['order']['amount'] * 100,
                    'orderItems' => $data['order']['items']
                ];
                try {
                    $this->getCurlResponse($cancelUrl, 'POST', json_encode($cancelBody));
                } catch (Exception $e) {
                    return $e->getMessage();
                }
            }

            try {
                // Get payment status from nets payments api
                $api_return = $this->getCurlResponse($this->getApiUrl() . $this->paymentId, 'GET');
                $response = json_decode($api_return, true);
            } catch (Exception $e) {
                return $e->getMessage();
            }
            $dbPayStatus = '';
            $paymentStatus = $cancelled = $reserved = $charged = $refunded = $pending = $chargeid = $chargedate = '';
            $paymentType = isset($response['payment']['paymentDetails']['paymentType']) ? $response['payment']['paymentDetails']['paymentType'] : '';

            if (isset($response['payment']['summary']['cancelledAmount'])) {
                $cancelled = $response['payment']['summary']['cancelledAmount'];
            }
            if (isset($response['payment']['summary']['reservedAmount'])) {
                $reserved = $response['payment']['summary']['reservedAmount'];
            }
            if (isset($response['payment']['summary']['chargedAmount'])) {
                $charged = $response['payment']['summary']['chargedAmount'];
            }
            if (isset($response['payment']['summary']['refundedAmount'])) {
                $refunded = $response['payment']['summary']['refundedAmount'];
            }
            if (isset($response['payment']['refunds'][0]['state']) && $response['payment']['refunds'][0]['state'] == 'Pending') {
                $pending = "Pending";
            }
            $partialc = $reserved - $charged;
            $partialr = $reserved - $refunded;
            if (isset($response['payment']['charges'][0]['chargeId'])) {
                $chargeid = $response['payment']['charges'][0]['chargeId'];
            }
            if (isset($response['payment']['charges'][0]['created'])) {
                $chargedate = $response['payment']['charges'][0]['created'];
            }

            if ($reserved || $paymentType == "A2A") {
                if ($cancelled) {
                    $langStatus = "cancel";
                    $paymentStatus = "Canceled";
                    $dbPayStatus = 1; // For payment status as cancelled in psnets_payment db table
                } elseif ($charged && $pending !== 'Pending') {
                    if ($reserved != $charged && $paymentType != "A2A") {
                        $paymentStatus = "Partial Charged";
                        $langStatus = "partial_charge";
                        $dbPayStatus = 3; // For payment status as Partial Charged in psnets_payment db table                         
                    } else if ($refunded) {
                        if ($reserved != $refunded && $paymentType != "A2A") {
                            $paymentStatus = "Partial Refunded";
                            $langStatus = "partial_refund";
                            $dbPayStatus = 5; // For payment status as Partial Charged in psnets_payment db table                             
                        } else if ($paymentType == "A2A" && $refunded != $charged) {
                            $paymentStatus = "Partial Refunded";
                            $langStatus = "partial_refund";
                            $dbPayStatus = 5;
                        } else {
                            $paymentStatus = "Refunded";
                            $langStatus = "refunded";
                            $dbPayStatus = 6; // For payment status as Refunded in psnets_payment db table
                        }
                    } else {
                        $paymentStatus = "Charged";
                        $langStatus = "charged";
                        $dbPayStatus = 4; // For payment status as Charged in psnets_payment db table
                    }
                } else if ($pending) {
                    $paymentStatus = "Refund Pending";
                    $langStatus = "refund_pending";
                } else {
                    $paymentStatus = 'Reserved';
                    $langStatus = "reserved";
                    $dbPayStatus = 2; // For payment status as Authorized in psnets_payment db table
                }
            } else {
                $paymentStatus = "Failed";
                $langStatus = "failed";
                $dbPayStatus = 0; // For payment status as Failed in psnets_payment db table
            }
            return array(
                'payStatus' => $paymentStatus,
                'langStatus' => $langStatus
            );
        }
    }

    /*
     * Function to capture nets transaction - calls charge API
     * redirects to admin overview listing page
     */

    public function processCharge() {

        $token = Tools::getValue('ordertoken');
        $orderid = Tools::getValue('orderid');
        $ref = !empty(Tools::getValue('reference')) ? Tools::getValue('reference') : Tools::getValue('name');
        $name = Tools::getValue('name');
        $chargeQty = Tools::getValue('single');
        $unitPrice = Tools::getValue('price');
        $taxRate = (int) Tools::getValue('taxrate');
        $payment_id = $this->getPaymentId($orderid);
        $data = $this->getOrderItems($orderid);

        // call charge api here
        $chargeUrl = $this->getChargePaymentUrl($payment_id);
        if (!empty($ref) && !empty($chargeQty)) {
            $totalAmount = 0;
            foreach ($data['order']['items'] as $key => $value) {
                if (in_array($ref, $value) && $ref === $value['reference']) {
                    $unitPrice = $value['unitPrice'];
                    $taxAmountPerProduct = $value['taxAmount'] / $value['quantity'];
                    $value['taxAmount'] = $taxAmountPerProduct * $chargeQty;
                    $netAmount = $chargeQty * $unitPrice;
                    $grossAmount = $netAmount + $value['taxAmount'];
                    $value['quantity'] = $chargeQty;
                    $value['netTotalAmount'] = $netAmount;
                    $value['grossTotalAmount'] = $grossAmount;
                    $itemList[] = $value;
                    $totalAmount += $grossAmount;
                }
            }
            $body = [
                'amount' => round($totalAmount),
                'orderItems' => $itemList
            ];
        } else {
            $body = [
                'amount' => round($data['order']['amount'] * 100),
                'orderItems' => $data['order']['items']
            ];
        }

        $this->logger->logInfo("[" . $payment_id . "] Nets_Order_Overview getorder charge request sent");
        $api_return = $this->getCurlResponse($chargeUrl, 'POST', json_encode($body));
        $response = json_decode($api_return, true);

        $this->logger->logInfo("[" . $payment_id . "] Nets_Order_Overview getorder charge response" . $api_return);
        //save charge details in db for partial refund
        if ((isset($ref) && !empty($ref)) && isset($response['chargeId'])) {
            $charge_query = "insert into " . _DB_PREFIX_ . "nets_payment (`payment_id`, `charge_id`,  `product_ref`, `charge_qty`, `charge_left_qty`,`created`) "
                    . "values ('" . $this->paymentId . "', '" . $response['chargeId'] . "', '" . $ref . "', '" . $chargeQty . "', '" . $chargeQty . "',now())";
            DB::getInstance()->execute($charge_query);
        } else {
            if (isset($response['chargeId'])) {
                foreach ($data['order']['items'] as $key => $value) {
                    $charge_query = "insert into " . _DB_PREFIX_ . "nets_payment (`payment_id`, `charge_id`,  `product_ref`, `charge_qty`, `charge_left_qty`,`created`) "
                            . "values ('" . $this->paymentId . "', '" . $response['chargeId'] . "', '" . $value['reference'] . "', '" . $value['quantity'] . "', '" . $value['quantity'] . "',now())";
                    DB::getInstance()->execute($charge_query);
                }
            }
        }

        Tools::redirectAdmin('sell/orders/' . $orderid . '/view?_token=' . $token);
    }

    /*
     * Function to refund nets transaction - calls Refund API
     * redirects to admin overview listing page
     */

    public function processRefund() {
        $token = Tools::getValue('ordertoken');
        $orderid = Tools::getValue('orderid');
        $ref = !empty(Tools::getValue('reference')) ? Tools::getValue('reference') : Tools::getValue('name');
        $name = Tools::getValue('name');
        $refundQty = Tools::getValue('single');

        $taxRate = (int) Tools::getValue('taxrate');
        $payment_id = $this->getPaymentId($orderid);
        $data = $this->getOrderItems($orderid);

        $api_return = $this->getCurlResponse($this->getApiUrl() . $this->getPaymentId($orderid), 'GET');
        $chargeResponse = json_decode($api_return, true);
        $refundEachQtyArr = array();
        $breakloop = $refExist = false;
        //For partial refund if condition
        if (!empty($ref) && !empty($refundQty)) {
            foreach ($chargeResponse['payment']['charges'] as $ky => $val) {
                foreach ($val['orderItems'] as $arr) {
                    if ($ref == $arr['reference']) {
                        $refExist = true;
                    }
                }

                if ($refExist) {
                    $charge_query = DB::getInstance()->executeS(
                            "SELECT `payment_id`, `charge_id`,  `product_ref`, `charge_qty`, `charge_left_qty` FROM " . _DB_PREFIX_ . "nets_payment WHERE payment_id = '" . $this->paymentId . "' AND charge_id = '" . $val['chargeId'] . "' AND product_ref = '" . $ref . "' AND charge_left_qty !=0"
                    );

                    if (!empty(DB::getInstance()->numRows($charge_query))) {
                        foreach ($charge_query as $crows) {
                            $table_charge_left_qty = $refundEachQtyArr[$val['chargeId']] = $crows['charge_left_qty'];
                        }
                    }

                    if ($refundQty <= array_sum($refundEachQtyArr)) {
                        $leftqtyFromArr = array_sum($refundEachQtyArr) - $refundQty;
                        $leftqty = $table_charge_left_qty - $leftqtyFromArr;
                        $refundEachQtyArr[$val['chargeId']] = $leftqty;
                        $breakloop = true;
                    }

                    if ($breakloop) {

                        foreach ($refundEachQtyArr as $key => $value) {
                            $body = $this->getItemForRefund($ref, $value, $data);
                            $refundUrl = $this->getRefundPaymentUrl($key);

                            $this->logger->logInfo('body' . json_encode($body));
                            $this->logger->logInfo("[" . $payment_id . "] Nets_Order_Overview getorder refund request sent");
                            $api_return = $this->getCurlResponse1($refundUrl, 'POST', json_encode($body));
                            $this->logger->logInfo("[" . $payment_id . "] Nets_Order_Overview getorder refund response" . $api_return);
                            //update for left charge quantity
                            $singlecharge_query = DB::getInstance()->executeS(
                                    "SELECT  `charge_left_qty` FROM " . _DB_PREFIX_ . "nets_payment WHERE payment_id = '" . $this->paymentId . "' AND charge_id = '" . $key . "' AND product_ref = '" . $ref . "' AND charge_left_qty !=0 "
                            );
                            if (!empty(DB::getInstance()->numRows($singlecharge_query))) {
                                foreach ($singlecharge_query as $scrows) {
                                    $charge_left_qty = $scrows['charge_left_qty'];
                                }
                            }
                            $charge_left_qty = $value - $charge_left_qty;
                            if ($charge_left_qty < 0) {
                                $charge_left_qty = -$charge_left_qty;
                            }
                            $qresult = DB::getInstance()->execute(
                                    "UPDATE " . _DB_PREFIX_ . "nets_payment SET charge_left_qty = $charge_left_qty WHERE payment_id = '" . $this->paymentId . "' AND charge_id = '" . $key . "' AND product_ref = '" . $ref . "'"
                            );
                        }
                        break;
                    }
                }
            }
        } else {
            //update for left charge quantity        
            foreach ($chargeResponse['payment']['charges'] as $ky => $val) {
                $itemsArray = array();

                foreach ($val['orderItems'] as $key => $value) {
                    $itemsArray[] = array(
                        'reference' => $value['reference'],
                        'name' => $value['name'],
                        'quantity' => $value['quantity'],
                        'unit' => 'pcs',
                        'unitPrice' => $value['unitPrice'],
                        'taxRate' => $value['taxRate'],
                        'taxAmount' => $value['taxAmount'],
                        'grossTotalAmount' => $value['grossTotalAmount'],
                        'netTotalAmount' => $value['netTotalAmount'],
                    );
                    $qresult = DB::getInstance()->execute(
                            "UPDATE " . _DB_PREFIX_ . "nets_payment SET charge_left_qty = 0 WHERE payment_id = '" . $this->paymentId . "' AND charge_id = '" . $val['chargeId'] . "' AND product_ref = '" . $value['reference'] . "'"
                    );
                }
                $itemsGrossPriceSumma = 0;
                foreach ($itemsArray as $total) {
                    $itemsGrossPriceSumma += $total['grossTotalAmount'];
                }
                $body = [
                    'amount' => $itemsGrossPriceSumma,
                    'orderItems' => $itemsArray
                ];
                //For Refund all				                
                $refundUrl = $this->getRefundPaymentUrl($val['chargeId']);

                $this->logger->logInfo("[" . $payment_id . "] Nets_Order_Overview getorder refund request sent");
                $api_return = $this->getCurlResponse($refundUrl, 'POST', json_encode($body));
                $response = json_decode($api_return, true);
                $this->logger->logInfo("[" . $payment_id . "] Nets_Order_Overview getorder refund response" . $api_return);
            }
        }
        Tools::redirectAdmin('sell/orders/' . $orderid . '/view?_token=' . $token);
    }

    /* Get order Items to refund and pass them to refund api */

    public function getItemForRefund($ref, $refundQty, $data) {
        $totalAmount = 0;
        $itemList = array();
        foreach ($data['order']['items'] as $key => $value) {
            if (in_array($ref, $value) && preg_replace('/\s+/', '', $ref) == preg_replace('/\s+/', '', $value['reference'])) {
                $unitPrice = $value['unitPrice'];
                $taxAmountPerProduct = $value['taxAmount'] / $value['quantity'];

                $value['taxAmount'] = round($taxAmountPerProduct * $refundQty);
                $netAmount = $refundQty * $unitPrice;
                $grossAmount = $netAmount + $value['taxAmount'];

                $value['quantity'] = $refundQty;
                $value['netTotalAmount'] = $netAmount;
                $value['grossTotalAmount'] = $grossAmount;

                $itemList[] = $value;
                $totalAmount += $grossAmount;
            }
        }
        $body = [
            'amount' => $totalAmount,
            'orderItems' => $itemList
        ];
        return $body;
    }

    /*
     * Function to capture nets transaction - calls Cancel API
     * redirects to admin overview listing page
     */

    public function processCancel() {
        $token = Tools::getValue('ordertoken');
        $orderid = Tools::getValue('orderid');
        $data = $this->getOrderItems($orderid);
        $payment_id = $this->getPaymentId($orderid);
        // call cancel api here
        $cancelUrl = $this->getVoidPaymentUrl($payment_id);
        $body = [
            'amount' => round($data['order']['amount'] * 100),
            'orderItems' => $data['order']['items']
        ];
        $api_return = $this->getCurlResponse($cancelUrl, 'POST', json_encode($body));
        $response = json_decode($api_return, true);
        Tools::redirectAdmin('sell/orders/' . $orderid . '/view?_token=' . $token);
    }

    public function getCurlResponse1($url, $method = "POST", $bodyParams = NULL) {
        $result = '';
        // initiating curl request to call api's
        $oCurl = curl_init();
        curl_setopt($oCurl, CURLOPT_URL, $url);
        curl_setopt($oCurl, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($oCurl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($oCurl, CURLOPT_HTTPHEADER, $this->getHeaders());
        if ($method == "POST" || $method == "PUT") {
            curl_setopt($oCurl, CURLOPT_POSTFIELDS, $bodyParams);
        }

        $result = curl_exec($oCurl);
        $info = curl_getinfo($oCurl);

        switch ($info['http_code']) {
            case 401:
                $message = 'NETS Easy authorization failed. Check your keys';
                break;
            case 400:
                $message = 'NETS Easy. Bad request: ' . $result;
                break;
            case 404:
                $message = 'Payment or charge not found';
                break;
            case 500:
                $message = 'Unexpected error';
                break;
        }
        if (!empty($message)) {
            $this->logger->logError("Response Error : " . $message);
        }

        curl_close($oCurl);

        return $result;
    }

    public function getCurlResponse($url, $method = "POST", $bodyParams = NULL) {
        $result = '';
        // initiating curl request to call api's
        $oCurl = curl_init();
        curl_setopt($oCurl, CURLOPT_URL, $url);
        curl_setopt($oCurl, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($oCurl, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($oCurl, CURLOPT_HTTPHEADER, $this->getHeaders());
        if ($method == "POST" || $method == "PUT") {
            curl_setopt($oCurl, CURLOPT_POSTFIELDS, $bodyParams);
        }

        $result = curl_exec($oCurl);
        $info = curl_getinfo($oCurl);

        switch ($info['http_code']) {
            case 401:
                $message = 'NETS Easy authorization failed. Check your keys';
                break;
            case 400:
                $message = 'NETS Easy. Bad request: ' . $result;
                break;
            case 404:
                $message = 'Payment or charge not found';
                break;
            case 500:
                $message = 'Unexpected error';
                break;
        }
        if (!empty($message)) {
            $this->logger->logError("Response Error : " . $message);
        }

        curl_close($oCurl);

        return $result;
    }

    /*
     * Function to fetch payment api url
     *
     * @return payment api url
     */

    public function getApiUrl() {

        if (Configuration::get('NETS_TEST_MODE')) {
            return self::ENDPOINT_TEST;
        } else {
            return self::ENDPOINT_LIVE;
        }
    }

    /*
     * Function to fetch charge id from databse table psnets_payment
     * @param $orderid
     * @return nets charge id
     */

    private function getChargeId($orderid) {
        $api_return = $this->getCurlResponse($this->getApiUrl() . $this->getPaymentId($orderid), 'GET');
        $response = json_decode($api_return, true);
        return $response['payment']['charges'][0]['chargeId'];
    }

    public function getResponse($oder_id) {
        $api_return = $this->getCurlResponse($this->getApiUrl() . $this->getPaymentId($oder_id), 'GET');
        $response = json_decode($api_return, true);
        $result = json_encode($response, JSON_PRETTY_PRINT);
        return $result;
    }

    /*
     * Function to fetch headers to be passed in guzzle http request
     * @return headers array
     */

    private function getHeaders() {
        return [
            "Content-Type: " . self::RESPONSE_TYPE,
            "Accept: " . self::RESPONSE_TYPE,
            "Authorization: " . $this->getSecretKey()
        ];
    }

    /*
     * Function to fetch secret key to pass as authorization
     * @return secret key
     */

    public function getSecretKey() {
        if (Configuration::get('NETS_TEST_MODE')) {
            $secretKey = Configuration::get('NETS_TEST_SECRET_KEY');
        } else {
            $secretKey = Configuration::get('NETS_LIVE_SECRET_KEY');
        }
        return $secretKey;
    }

    /*
     * Function to fetch charge api url
     * @param $paymentId
     * @return charge api url
     */

    public function getChargePaymentUrl($paymentId) {
        return (Configuration::get('NETS_TEST_MODE')) ? self::ENDPOINT_TEST . $paymentId . '/charges' : self::ENDPOINT_LIVE . $paymentId . '/charges';
    }

    /*
     * Function to fetch cancel api url
     * @param $paymentId
     * @return cancel api url
     */

    public function getVoidPaymentUrl($paymentId) {
        return (Configuration::get('NETS_TEST_MODE')) ? self::ENDPOINT_TEST . $paymentId . '/cancels' : self::ENDPOINT_LIVE . $paymentId . '/cancels';
    }

    /*
     * Function to fetch refund api url
     * @param $chargeId
     * @return refund api url
     */

    public function getRefundPaymentUrl($chargeId) {
        return (Configuration::get('NETS_TEST_MODE')) ? self::ENDPOINT_TEST_CHARGES . $chargeId . '/refunds' : self::ENDPOINT_LIVE_CHARGES . $chargeId . '/refunds';
    }

    /*
     * Function to manage portal charge and refund in admin  
     * @param $this->paymentId	
     * @return null
     */

    public function managePortalChargeAndRefund() {
        //get reqeust response
        $api_return = $this->getCurlResponse($this->getApiUrl() . $this->paymentId, 'GET');
        $response = json_decode($api_return, true);
        if (!empty($response['payment']['charges'])) {

            foreach ($response['payment']['charges'] as $key => $values) {
                $charge_query = DB::getInstance()->executeS(
                        "SELECT `charge_id` FROM " . _DB_PREFIX_ . "nets_payment WHERE payment_id = '" . $this->paymentId . "' AND charge_id = '" . $values['chargeId'] . "' "
                );
                if (empty(DB::getInstance()->numRows($charge_query))) {
                    for ($i = 0; $i < count($values['orderItems']); $i++) {
                        $charge_iquery = "insert into " . _DB_PREFIX_ . "nets_payment (`payment_id`, `charge_id`,  `product_ref`, `charge_qty`, `charge_left_qty`,`created`) "
                                . "values ('" . $this->paymentId . "', '" . $values['chargeId'] . "', '" . $values['orderItems'][$i]['reference'] . "', '" . $values['orderItems'][$i]['quantity'] . "', '" . $values['orderItems'][$i]['quantity'] . "',now())";
                        DB::getInstance()->execute($charge_iquery);
                    }
                }
            }
        }
    }

    private function _getSession() {
        return \PrestaShop\PrestaShop\Adapter\SymfonyContainer::getInstance()->get('session');
    }

}
