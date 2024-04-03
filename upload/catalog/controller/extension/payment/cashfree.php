<?php

class ControllerExtensionPaymentCashFree extends Controller
{
    const CASHFREE_SANDBOX_API_ENDPOINT = 'https://sandbox.cashfree.com/pg/';
    const CASHFREE_SANDBOX_ENVIRONMENT = 'sandbox';
    const CASHFREE_PRODUCTION_API_ENDPOINT = 'https://api.cashfree.com/pg/';
    const CASHFREE_PRODUCTION_ENVIRONMENT = 'production';
    const CASHFREE_API_VERSION = '2023-08-01';

    /**
     * Initiate checkout page
     *
     * @return void
     */

    public function __construct($registry)
    {
        parent::__construct($registry);
        $this->app_id = trim($this->config->get('payment_cashfree_app_id'));
        $this->secret_key = trim($this->config->get('payment_cashfree_secret_key'));
        $this->api_endpoint = self::CASHFREE_PRODUCTION_API_ENDPOINT;
        $this->environment = self::CASHFREE_PRODUCTION_ENVIRONMENT;
        if ($this->config->get('payment_cashfree_sandbox') == '1') {
            $this->api_endpoint = self::CASHFREE_SANDBOX_API_ENDPOINT;
            $this->environment = self::CASHFREE_SANDBOX_ENVIRONMENT;
        }
    }

    public function index()
    {
        $this->language->load('extension/payment/cashfree');
        $data['button_confirm'] = $this->language->get('button_confirm');
        $data['text_loading'] = $this->language->get('text_loading');
        $data['text_redirect'] = $this->language->get('text_redirect');
        $this->session->data['order_id'];
        if (file_exists(DIR_TEMPLATE . $this->config->get('config_template') . '/template/extension/payment/cashfree')) {
            return $this->load->view($this->config->get('config_template') . '/template/extension/payment/cashfree', $data);
        } else {
            return $this->load->view('/extension/payment/cashfree', $data);
        }
    }

    /**
     * Redirect to payment page for executing checkout page
     *
     * @return void
     */
    public function confirm()
    {
        $response["status"] = 0;
        $response["message"] = "You have not selected cashfree payment gateway. Please reach out to customer support";

        if ($this->session->data['payment_method']['code'] == 'cashfree') {
            $this->language->load('extension/payment/cashfree');

            $this->load->model('checkout/order');
            $order_info = $this->model_checkout_order->getOrder($this->session->data['order_id']);
            $get_order_response = $this->getOrder($this->session->data['order_id']);

            if (isset($get_order_response['payment_session_id'])) {
                if ($get_order_response['order_status'] == 'ACTIVE'
                    && round($get_order_response['total'], 2) == round($order_info['total'], 2)
                    && $get_order_response['order_currency'] == $order_info['currency_code']) {
                    $response['payment_session_id'] = $get_order_response['payment_session_id'];
                    $response["status"] = 1;
                    $response["environment"] = $this->environment;
                } else {
                    $response["status"] = 0;
                }
                $response["message"] = $get_order_response['message'];
            } else {
                $cf_request = array();
                $customer_details["customer_id"] = "121212";
                $customer_details["customer_email"] = $order_info['email'];
                $customer_details["customer_phone"] = $order_info['telephone'];
                $customer_details["customer_name"] = html_entity_decode($order_info['payment_firstname'] . ' ' . $order_info['payment_lastname'], ENT_QUOTES, 'UTF-8');
                $cf_request["customer_details"] = $customer_details;
                $order_meta["return_url"] = $this->url->link('extension/payment/cashfree/thankyou&order_id={order_id}', '', 'SSL');
                $order_meta["notify_url"] = $this->url->link('extension/payment/cashfree/callback', '', 'SSL');
                $cf_request["order_meta"] = $order_meta;
                $cf_request["order_id"] = (string)$this->session->data['order_id'];
                $cf_request["order_note"] = $order_info['store_name'] . " - #" . $cf_request["order_id"];
                $cf_request["order_amount"] = round($order_info['total'], 2);
                $cf_request["order_currency"] = $order_info['currency_code'];

                $create_order_response = $this->createOrder($cf_request);

                if (isset($create_order_response['payment_session_id'])) {
                    $response['payment_session_id'] = $create_order_response['payment_session_id'];
                    $response["status"] = 1;
                    $response["environment"] = $this->environment;
                } else {
                    $response["status"] = 0;
                }
                $response["message"] = $create_order_response['message'];
            }
        }
        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($response));
    }

    /**
     * Create order using cashfree order api's
     *
     * @param mixed $cf_request
     * @return void
     */
    public function createOrder($cf_request)
    {
        $url = $this->api_endpoint . "orders";
        $data_string = json_encode($cf_request);

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data_string);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'accept: application/json',
            'content-type: application/json',
            'x-api-version: ' . self::CASHFREE_API_VERSION,
            'x-client-id: ' . $this->app_id,
            'x-client-secret: ' . $this->secret_key
        ));

        $response = curl_exec($ch);

        curl_close($ch);

        return json_decode($response, true);
    }

    /**
     * If order is already generate with same order id then get payment links
     *
     * @param mixed $order_id
     * @return bool|string
     */
    public function getOrder($order_id)
    {
        $url = $this->api_endpoint . "orders/" . $order_id;
        $headers = [
            'accept: application/json',
            'x-api-version: ' . self::CASHFREE_API_VERSION,
            'x-client-id: ' . $this->app_id,
            'x-client-secret: ' . $this->secret_key
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $response = curl_exec($ch);

        curl_close($ch);

        return json_decode($response, true);
    }

    /**
     * Process payment on client after cashfree response
     *
     * @param mixed $post_response
     * @return array
     */
    private function processResponse($post_response, $order_info)
    {
        $this->load->model('checkout/order');
        $this->language->load('extension/payment/cashfree');

        if ($post_response["status"] == 'SUCCESS') {
            if ($order_info["order_status_id"] != $this->config->get('payment_cashfree_order_status_id')) {
                // only updated if it has been updated it
                $this->model_checkout_order->addOrderHistory($post_response['order_id'], $this->config->get('payment_cashfree_order_status_id'), $post_response["message"]);
            }
            return array("status" => 1);
        } else if ($post_response["status"] == "CANCELLED") {
            $this->model_checkout_order->addOrderHistory($post_response['order_id'], 7, $post_response["message"] . ' Payment Cancelled! Check Cashfree dashboard for details of Reference Id:' . $post_response['transaction_id']);
            $redirect_url = $this->url->link('checkout/checkout', '', true);
            return array("status" => 0, "message" => $this->language->get('cashfree_payment_cancelled'), "redirect_url" => $redirect_url);
        } else {
            $this->model_checkout_order->addOrderHistory($post_response['order_id'], 10, $post_response["message"] . ' Payment Failed! Check Cashfree dashboard for details of Reference Id:' . $post_response['transaction_id']);
            $redirect_url = $this->url->link('checkout/failure', '', true);
            return array("status" => 0, "message" => $this->language->get('cashfree_payment_failed'), "redirect_url" => $redirect_url);
        }
    }

    /**
     * @return void
     */
    public function thankyou()
    {
        $this->load->model('checkout/order');
        $this->language->load('extension/payment/cashfree');
        if (!isset($_REQUEST["order_id"])) {
            $this->response->redirect($this->url->link('checkout/failure'));
        }

        $order_id = $_REQUEST["order_id"];

        return $this->cashfree_payment_response($order_id);
    }

    /**
     * Checking for notify url
     *
     * @return void
     */
    public function callback()
    {
        if (!isset($_POST["orderId"])) {
            die();
        }
        sleep(30);
        $response = $this->cashfree_payment_response($_POST["orderId"]);
        die();
        //do nothing
    }

    /**
     * @param $order_id
     * @return void
     */
    public function cashfree_payment_response($order_id)
    {
        $this->load->model('checkout/order');
        $this->language->load('extension/payment/cashfree');

        $order_info = $this->model_checkout_order->getOrder($order_id);

        if ($order_info) {
            $url = $this->api_endpoint . "orders/" . $order_id . "/payments";
            $headers = [
                'accept: application/json',
                'x-api-version: ' . self::CASHFREE_API_VERSION,
                'x-client-id: ' . $this->app_id,
                'x-client-secret: ' . $this->secret_key
            ];

            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

            $response = curl_exec($ch);

            $get_payments = json_decode($response, true);

            if (is_array($get_payments) && isset($get_payments[0]) && is_array($get_payments[0]) && isset($get_payments[0]['payment_status']) && $get_payments[0]['payment_status'] === 'SUCCESS') {
                $payments = $get_payments[0];
                if ((number_format($payments['order_amount'], 2) == number_format($order_info['total'], 2)) && ($payments['payment_currency'] == $order_info['currency_code'])) {
                    $post_response['status'] = $payments['payment_status'];
                    $post_response['order_id'] = $order_id;
                    $post_response['message'] = $payments['payment_message'];
                    $post_response['transaction_id'] = $payments['cf_payment_id'];
                    $response = $this->processResponse($post_response, $order_info);
                } else {
                    $post_response['status'] = "FAILED";
                    $post_response['order_id'] = $order_id;
                    $post_response['message'] = "Payment failed. Signature Mismatched";
                    $post_response['transaction_id'] = $payments['cf_payment_id'];
                    $response = $this->processResponse($post_response, $order_info);
                }
            } else {
                if (is_array($get_payments) && isset($get_payments[0]) && is_array($get_payments[0]) && isset($get_payments[0]['payment_status'])) {
                    $payments = $get_payments[0];
                    $post_response['status'] = $payments['payment_status'];
                    $post_response['order_id'] = $order_id;
                    $post_response['message'] = $payments['payment_message'];
                    $post_response['transaction_id'] = $payments['cf_payment_id'];
                    $response = $this->processResponse($post_response, $order_info);
                } else {
                    $post_response['status'] = "FAILED";
                    $post_response['order_id'] = $order_id;
                    $post_response['message'] = "Transaction not found for this order";
                    $post_response['transaction_id'] = "";
                    $response = $this->processResponse($post_response, $order_info);
                }
            }

            if ($response["status"] == 1) {
                $this->response->redirect($this->url->link('checkout/success', '', true));
            } else {
                $this->session->data['error_warning'] = $response["message"];
                $this->response->redirect($response['redirect_url']);
            }

        } else {
            $redirect_url = $this->url->link('checkout/failure', '', true);
            $this->response->redirect($redirect_url);
        }
    }
}