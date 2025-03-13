<?php
namespace Opencart\Catalog\Controller\Extension\Opencart\Payment;
class Cashfree extends \Opencart\System\Engine\Controller
{
    private const CASHFREE_SANDBOX_API_ENDPOINT = 'https://sandbox.cashfree.com/pg/';
    private const CASHFREE_PRODUCTION_API_ENDPOINT = 'https://api.cashfree.com/pg/';
    private const CASHFREE_SANDBOX_ENVIRONMENT = 'sandbox';
    private const CASHFREE_PRODUCTION_ENVIRONMENT = 'production';
    private const CASHFREE_API_VERSION = '2023-08-01';

    private string $app_id;
    private string $secret_key;
    private string $api_endpoint;
    private string $environment;

    public function __construct($registry)
    {
        parent::__construct($registry);
        $this->app_id = trim($this->config->get('payment_cashfree_app_id') ?? '');
        $this->secret_key = trim($this->config->get('payment_cashfree_secret_key') ?? '');
        
        if ($this->config->get('payment_cashfree_sandbox')) {
            $this->api_endpoint = self::CASHFREE_SANDBOX_API_ENDPOINT;
            $this->environment = self::CASHFREE_SANDBOX_ENVIRONMENT;
        } else {
            $this->api_endpoint = self::CASHFREE_PRODUCTION_API_ENDPOINT;
            $this->environment = self::CASHFREE_PRODUCTION_ENVIRONMENT;
        }
    }

    public function index()
    {
        $this->load->language('extension/opencart/payment/cashfree');
        
        return $this->load->view('extension/opencart/payment/cashfree', [
            'button_confirm' => $this->language->get('button_confirm'),
            'text_loading' => $this->language->get('text_loading'),
            'text_redirect' => $this->language->get('text_redirect')
        ]);
    }

    public function confirm(): void 
    {
        $response = [
            "status" => 0,
            "message" => "You have not selected Cashfree as the payment gateway. Please contact customer support.",
            "redirect_success" => $this->url->link('checkout/success', 'language=' . $this->config->get('config_language'), true),
            "redirect_failure" => $this->url->link('checkout/failure', 'language=' . $this->config->get('config_language'), true)
        ];

        if ($this->session->data['payment_method']['code'] == 'cashfree.cashfree') {
            $this->load->language('extension/opencart/payment/cashfree');
            $this->load->model('checkout/order');
            $order_info = $this->model_checkout_order->getOrder($this->session->data['order_id']);
            $get_order_response = $this->getOrder($this->session->data['order_id']);
            
            // Check if the order exists
            if (isset($get_order_response['payment_session_id'])) {
                if (($get_order_response['order_status'] == 'ACTIVE') && round($get_order_response['order_amount'], 2) == round($order_info['total'], 2) && $get_order_response['order_currency'] == $order_info['currency_code']) {
                    $response['payment_session_id'] = $get_order_response['payment_session_id'];
                    $response["status"] = 1;
                    $response["environment"] = $this->environment;
                    $response["message"] = "Order get created successfully";
                    $response['callback_url'] = $get_order_response['order_meta']['return_url'];
                } else {
                    $response["status"] = 0;
                    $response['callback_url'] = $get_order_response['order_meta']['return_url'];
                    $response["message"] = "There is something wrong with order creation. Please do reach out to support";
                }
            } // Create a new order
            else { 
                $cf_request = $this->prepareOrderRequest($order_info);
                $create_order_response = $this->createOrder($cf_request);

                if (isset($create_order_response['payment_session_id'])) {
                    //$this->log->write('Cashfree Session : 6' . json_encode($this->session));
                    $response['payment_session_id'] = $create_order_response['payment_session_id'];
                    $response["status"] = 1;
                    $response["environment"] = $this->environment;
                    $response["message"] = "Order get created successfully";
                    $response['callback_url'] = $create_order_response['order_meta']['return_url'];
                } else {
                    //$this->log->write('Cashfree Session : 7' . json_encode($this->session));
                    $response["status"] = 0;
                    $response["message"] = $create_order_response['message'];
                }
            }
        }
        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($response));
        
    }

    /**
     * Create order using cashfree order api's
     * 
     */
    private function prepareOrderRequest($order_info) {
        $customer_name = trim($order_info['firstname'] . ' ' . $order_info['lastname']);
        if (empty($customer_name) || strlen($customer_name) < 2) {
            $customer_name = "Guest User"; 
        }
        $customer_phone = preg_replace('/\D/', '', $order_info['telephone']);
        if (empty($customer_phone) || strlen($customer_phone) < 10) {
            $customer_phone = "9999999999"; 
        }
        $order_id = $order_info['order_id'];
        $cf_order_id = "oc_order_id_" . (string)$order_id;
        return [
            "customer_details" => [
                "customer_id" => (string)$order_info['customer_id'],
                "customer_email" => (string)$order_info['email'],
                "customer_phone" => (string)$customer_phone,
                "customer_name" => (string)$customer_name
            ],
            "order_meta" => [
                "return_url" => $this->url->link('extension/opencart/payment/cashfree.thankyou', 'order_id=' .$order_id, true),
                "notify_url" => $this->url->link('extension/opencart/payment/cashfree.callback', '', true)
            ],
            "order_id" => (string)$cf_order_id,
            "order_amount" => round($order_info['total'], 2),
            "order_currency" => $order_info['currency_code']
        ];
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
     * @param mixed $order_id
     * @return bool|string
     */
    public function getOrder($order_id)
    {
        $cf_order_id = "oc_order_id_" . (string)$order_id;
        $url = $this->api_endpoint . "orders/" . $cf_order_id;
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
        $this->language->load('extension/opencart/payment/cashfree');

        if ($post_response["status"] == 'SUCCESS') {
            // only updated if it has been updated it
            if ($order_info["order_status_id"] != $this->config->get('payment_cashfree_order_status_id')) {
                $this->model_checkout_order->addHistory(
                    $post_response['order_id'], 
                    $this->config->get('payment_cashfree_order_status_id'), 
                    $post_response["message"]
                );
            }
            return array(
                "status" => 1, 
                "message" => $this->language->get('cashfree_payment_success'), 
                "redirect_url" => $this->url->link('checkout/success', 'language=' . $this->config->get('config_language'), true)
            );
        } else if ($post_response["status"] == "CANCELLED") {
            $this->model_checkout_order->addHistory(
                $post_response['order_id'], 
                7, 
                $post_response["message"] . ' Payment Cancelled! Check Cashfree dashboard for details of Reference Id:' . $post_response['transaction_id']
            );
            return array(
                "status" => 0, 
                "message" => $this->language->get('cashfree_payment_cancelled'), 
                "redirect_url" => $this->url->link('checkout/checkout', 'language=' . $this->config->get('config_language'), true)
            );
        } else {
            $this->model_checkout_order->addHistory(
                $post_response['order_id'], 
                10, 
                $post_response["message"] . ' Payment Failed! Check Cashfree dashboard for details of Reference Id:' . $post_response['transaction_id']
            );
            return array(
                "status" => 0, 
                "message" => $this->language->get('cashfree_payment_failed'), 
                "redirect_url" => $this->url->link('checkout/failure', 'language=' . $this->config->get('config_language'), true)
            );
        }
    }

    /**
     * Handles redirection after payment completion
     * @return void
     */
    public function thankyou()
    {
        $this->load->model('checkout/order');
        $this->language->load('extension/opencart/payment/cashfree');
        if (!isset($_REQUEST["order_id"])) {
            echo json_encode(["redirect_url" => $this->url->link('checkout/failure', 'language=' . $this->config->get('config_language'), true)]);
            return;
        }
    
        $order_id = $_REQUEST["order_id"];
        $response = $this->cashfree_payment_response($order_id);
        echo json_encode(["redirect_url" => $response]);
    }

    /**
     * Handles callback notification from Cashfree
     * @return void
     */
    public function callback()
    {
        if (!isset($_POST["orderId"])) {
            die();
        }
        sleep(30); // Wait for transaction finalization
        $response = $this->cashfree_payment_response($_POST["orderId"]);
        die();
        //do nothing
    }
    
    /**
     * 
     * Fetches and processes payment response from Cashfree API
     */
    public function cashfree_payment_response($order_id)
    {
        $this->load->model('checkout/order');
        $this->language->load('extension/opencart/payment/cashfree');

        $order_info = $this->model_checkout_order->getOrder($order_id);
        if (!$order_info) {
            return $this->url->link('checkout/failure', 'language=' . $this->config->get('config_language'), true);
        }

        $cf_order_id = "oc_order_id_" . (string)$order_id;
        $url = $this->api_endpoint . "orders/" . $cf_order_id . "/payments";
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
            return $response["redirect_url"];
        } 

        $this->session->data['error_warning'] = $response["message"];
        return $response["redirect_url"];
    }

}