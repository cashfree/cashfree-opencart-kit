<?php

class ControllerExtensionPaymentCashFree extends Controller
{    
    /**
     * Initiate checkout page
     *
     * @return void
     */
    public function index()
    {
        $this->language->load('extension/payment/cashfree');
        $data['button_confirm'] = $this->language->get('button_confirm');
        $data['text_loading'] = $this->language->get('text_loading');
        $data['text_redirect'] = $this->language->get('text_redirect');

        $this->session->data['order_id'];
        if (file_exists(DIR_TEMPLATE . $this->config->get('config_template') . '/extension/payment/cashfree.tpl')) {
            return $this->load->view($this->config->get('config_template') . '/extension/payment/cashfree.tpl', $data);
        } else {
            return $this->load->view('/extension/payment/cashfree.tpl', $data);
        }
    }
    
    /**
     * Redirect to payment page for executing checkout page
     *
     * @return void
     */
    public function confirm()
    {
        if ($this->session->data['payment_method']['code'] == 'cashfree') {
            $this->language->load('extension/payment/cashfree');

            $appId = $this->config->get('cashfree_app_id');
            $secretKey = $this->config->get('cashfree_secret_key');

            $this->load->model('checkout/order');
            $order_info = $this->model_checkout_order->getOrder($this->session->data['order_id']);

            $cf_request = array();
            $cf_request["appId"] = $appId;
            $cf_request["secretKey"] = $secretKey;
            $cf_request["orderId"] = $this->session->data['order_id'];
            $cf_request["orderAmount"] = round($order_info['total'], 2);
            $cf_request["orderCurrency"] = $order_info['currency_code'];
            $cf_request["orderNote"] = $order_info['store_name'] . " - #" . $cf_request["orderId"];
            $cf_request["customerPhone"] = $order_info['telephone'];
            $cf_request["customerName"] = html_entity_decode($order_info['payment_firstname'] . ' ' . $order_info['payment_lastname'], ENT_QUOTES, 'UTF-8');
            $cf_request["customerEmail"] = $order_info['email'];
            $cf_request["returnUrl"] = $this->url->link('extension/payment/cashfree/thankyou', '', 'SSL');
            $cf_request["notifyUrl"] = $this->url->link('extension/payment/cashfree/callback', '', 'SSL');
            $cf_request["source"] = "opencart";

            $jsonResponse = $this->getOrderLink($appId, $secretKey, $this->session->data['order_id']);
            if ($jsonResponse->{'status'} == "OK") {
                $response["status"] = 1;
                $response["redirect"] = $jsonResponse->{"paymentLink"};
            }
            else
            {
                $jsonResponse = $this->createOrder($cf_request);
                if ($jsonResponse->{'status'} == "OK") 
                {
                    $response["status"] = 1;
                    $response["redirect"] = $jsonResponse->{"paymentLink"};
    
                }
                else
                {
                    $response["status"] = 0;
                    $response["message"] = $this->language->get('cashfree_api_error') . $jsonResponse->{"reason"};
                }
            }
            
            $this->response->addHeader('Content-Type: application/json');
            $this->response->setOutput(json_encode($response));
        }
    }
    
    /**
     * Create order using cashfree order api's
     *
     * @param  mixed $cf_request
     * @return void
     */
    public function createOrder($cf_request)
    {
        $apiEndpoint = trim($this->config->get('cashfree_api_url'));
        $timeout = 10;

        $request_string = "";
        foreach ($cf_request as $key => $value) {
            $request_string .= $key . '=' . rawurlencode($value) . '&';
        }

        $apiEndpoint = rtrim($apiEndpoint, "/");
        $apiEndpoint = $apiEndpoint . "/api/v1/order/create";

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "$apiEndpoint?");
        curl_setopt($ch, CURLOPT_POST, count($cf_request));
        curl_setopt($ch, CURLOPT_POSTFIELDS, $request_string);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        $curl_result = curl_exec($ch);
        curl_close($ch);

        $jsonResponse = json_decode($curl_result);
        return $jsonResponse;
    }
    
    /**
     * If order is already generate with same order id then get payment links
     *
     * @param  mixed $appId
     * @param  mixed $secretKey
     * @param  mixed $orderId
     * @return void
     */
    public function getOrderLink($appId, $secretKey, $orderId)
    {
        $url = trim($this->config->get('cashfree_api_url'));
        $url = rtrim($url, "/");
        $url = $url . "/api/v1/order/info/link";

        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "POST",
            CURLOPT_POSTFIELDS => "appId=$appId&secretKey=$secretKey&orderId=$orderId",
            CURLOPT_HTTPHEADER => array(
                "cache-control: no-cache",
                "content-type: application/x-www-form-urlencoded",
            ),
        ));

        $err = curl_error($curl);
        $curl_result = curl_exec($curl);

        curl_close($curl);
        $jsonResponse = json_decode($curl_result);
        return $jsonResponse;
    }
    
    /**
     * Process payment on client after cashfree response
     *
     * @param  mixed $data
     * @return void
     */
    private function processResponse($data)
    {
        $secretKey = $this->config->get('cashfree_secret_key');
        $this->load->model('checkout/order');
        $this->language->load('extension/payment/cashfree');

        $order_info = $this->model_checkout_order->getOrder($data["orderId"]);

        if ($order_info) {
            $signature = $data["signature"];

            $postData = "{$data['orderId']}{$data['orderAmount']}{$data['referenceId']}{$data['txStatus']}{$data['paymentMode']}{$data['txMsg']}{$data['txTime']}";
            $hash_hmac = hash_hmac('sha256', $postData, $secretKey, true);
            $computedSignature = base64_encode($hash_hmac);
            if ($signature != $computedSignature) {
                $this->model_checkout_order->addOrderHistory($data['orderId'], 10, $data["txMsg"] . ' Signature missmatch! Check Cashfree dashboard for details of Reference Id:' . $data['referenceId']);
                $redirectUrl = $this->url->link('checkout/failure', '', true);
                return array("status" => 0, "message" => $this->language->get('cashfree_payment_failed'), "redirectUrl" => $redirectUrl);
            }

            if ($data["txStatus"] == 'SUCCESS') {
                if ($order_info["order_status_id"] != $this->config->get('cashfree_order_status_id')) { 
                    // only updated if it has been updated it
                    $this->model_checkout_order->addOrderHistory($data['orderId'], $this->config->get('cashfree_order_status_id'), "Payment Received", true);
                }
                return array("status" => 1);
            } else if ($data["txStatus"] == "CANCELLED") {
                $this->model_checkout_order->addOrderHistory($data['orderId'], 7, $data["txMsg"] . ' Payment Cancelled! Check Cashfree dashboard for details of Reference Id:' . $data['referenceId']);
                $redirectUrl = $this->url->link('checkout/checkout', '', true);
                return array("status" => 0, "message" => $this->language->get('cashfree_payment_cancelled'), "redirectUrl" => $redirectUrl);
            } else {
                $this->model_checkout_order->addOrderHistory($data['orderId'], 10, $data["txMsg"] . ' Payment Failed! Check Cashfree dashboard for details of Reference Id:' . $data['referenceId']);
                $redirectUrl = $this->url->link('checkout/failure', '', true);
                return array("status" => 0, "message" => $this->language->get('cashfree_payment_failed'), "redirectUrl" => $redirectUrl);
            }

        }
        return array("status" => 0, "message" => "");
    }
    
    /**
     * Redirect to thank you page after process payment on client
     *
     * @return void
     */
    public function thankyou()
    {
        if (!isset($this->request->post["orderId"])) {
            $this->response->redirect($this->url->link('checkout/failure'));
        }

        $response = $this->processResponse($this->request->post);

        if ($response["status"] == 1) {
            $this->response->redirect($this->url->link('checkout/success', '', true));
        } else {
            $this->session->data['error_warning'] = $response["message"];
            $this->response->redirect($response['redirectUrl']);
        }
    }
    
    /**
     * Checking for notify url
     *
     * @return void
     */
    public function callback()
    {
        if (!isset($this->request->post["orderId"])) {
            die();
        }
        sleep(20);
        $response = $this->processResponse($this->request->post);
        die();
        //do nothing
    }
}