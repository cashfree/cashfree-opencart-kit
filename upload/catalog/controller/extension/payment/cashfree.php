<?php

  class ControllerExtensionPaymentCashFree extends Controller {
    public function index() {
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

    public function confirm() {
      if ($this->session->data['payment_method']['code'] == 'cashfree') {
        $this->language->load('extension/payment/cashfree');

        $appId = trim($this->config->get('payment_cashfree_app_id'));
        $secretKey= trim($this->config->get('payment_cashfree_secret_key'));
        $apiEndpoint = trim($this->config->get('payment_cashfree_api_url'));
        $this->load->model('checkout/order');
        $order_info = $this->model_checkout_order->getOrder($this->session->data['order_id']);
        $cf_request = array();
        $cf_request["appId"] = $appId;
        $cf_request["secretKey"] = $secretKey;
        $cf_request["orderId"] = $this->session->data['order_id'];
        $cf_request["orderNote"] = $order_info['store_name'] . " - #" . $cf_request["orderId"];
        $cf_request["orderCurrency"] = $order_info['currency_code'];
        $cf_request["orderAmount"] = $this->currency->format($order_info['total'], $order_info['currency_code'], $order_info['currency_value'], false);
        error_log(json_encode($cf_request));
        $cf_request["customerPhone"] = $order_info['telephone'];
        $cf_request["customerName"] = html_entity_decode($order_info['payment_firstname'] . ' ' . $order_info['payment_lastname'], ENT_QUOTES, 'UTF-8');
        $cf_request["customerEmail"] =  $order_info['email'];
        $cf_request["returnUrl"] = $this->url->link('extension/payment/cashfree/thankyou', '', 'SSL');
        $cf_request["notifyUrl"] = $this->url->link('extension/payment/cashfree/callback', '', 'SSL');

        $timeout = 10;

        $request_string = "";
        foreach($cf_request as $key=>$value) {
          $request_string .= $key.'='.rawurlencode($value).'&';
        }

        $apiEndpoint = rtrim($apiEndpoint, "/");
        $apiEndpoint = $apiEndpoint."/api/v1/order/create";

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL,"$apiEndpoint?");
        curl_setopt($ch,CURLOPT_POST, count($cf_request));
        curl_setopt($ch,CURLOPT_POSTFIELDS, $request_string);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
        $curl_result=curl_exec ($ch);
        curl_close ($ch);
        $jsonResponse = json_decode($curl_result);
        $response = array();
        if ($jsonResponse->{'status'} == "OK") {
          $response["status"] = 1;
          $response["redirect"] = $jsonResponse->{"paymentLink"}; 
        } else {
          $response["status"] = 0;
          $response["message"] = $this->language->get('cashfree_api_error') . $jsonResponse->{"reason"}; 
        } 
        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($response)); 
      }
    }

    private function processResponse ($postData) {
      $this->load->model('checkout/order');
      $this->language->load('extension/payment/cashfree');

      $order_info = $this->model_checkout_order->getOrder($postData["orderId"]);

      if ($order_info) {
        $cashfree_response = array();
        $cashfree_response["orderId"] = $postData["orderId"];
        $cashfree_response["orderAmount"] = $postData["orderAmount"];
        $cashfree_response["txStatus"] = $postData["txStatus"];
        $cashfree_response["referenceId"] = $postData["referenceId"];
        $cashfree_response["txTime"] = $postData["txTime"];
        $cashfree_response["txMsg"] = $postData["txMsg"];
        $cashfree_response["paymentMode"] = $postData["paymentMode"];
        $cashfree_response["signature"] = $postData["signature"];
  
        $secret_key = trim($this->config->get('payment_cashfree_secret_key'));
        $data = "{$cashfree_response['orderId']}{$cashfree_response['orderAmount']}{$cashfree_response['referenceId']}{$cashfree_response['txStatus']}{$cashfree_response['paymentMode']}{$cashfree_response['txMsg']}{$cashfree_response['txTime']}";
       
        $hash_hmac = hash_hmac('sha256', $data, $secret_key, true) ;
        $computedSignature = base64_encode($hash_hmac);
        if ($cashfree_response["signature"] != $computedSignature) {
          return array("status" => 0, "message" => $this->language->get('cashfree_signature_mismatch'));
        }

        if ($cashfree_response["txStatus"] == 'SUCCESS') {
           if ($order_info["order_status_id"] != $this->config->get('payment_cashfree_order_status_id')) { 
             $this->model_checkout_order->addOrderHistory($cashfree_response['orderId'], $this->config->get('payment_cashfree_order_status_id'),"Payment Received");
           }
           return array("status" => 1);
        } else if ($cashfree_response["txStatus"] == "CANCELLED") {
            return array("status" => 0, "message" => $this->language->get('cashfree_payment_cancelled'));
        } else if ($cashfree_response["txStatus"] == "PENDING") {
            return array("status" => 0, "message" => $this->language->get('cashfree_payment_pending'));
        } else {
            return array("status" => 0, "message" => $this->language->get('cashfree_payment_failed'));
        }

      }
      return array("status" => 0, "message" => "");
    }

    public function thankyou () {

      if (!isset($_POST["orderId"])) {
       $this->response->redirect($this->url->link('checkout/failure'));
      }

      $response = $this->processResponse($_POST);
      if ($response["status"] == 1) {
        $this->response->redirect($this->url->link('checkout/success'));    
      }  else {
        $this->error['warning'] = $response["message"];
        $this->response->redirect($this->url->link('checkout/failure'));
      }
    }

    public function callback () {
      if (!isset($_POST["orderId"])) {
        die();
       }
       $response = $this->processResponse($_POST);
       die();
       //do nothing
    }
  }
?>
