<?php

class ModelExtensionPaymentCashfree extends Model
{
    public function getMethod($address, $total)
    {
        $this->load->language('extension/payment/cashfree');
        $method_data = array(
            'code' => 'cashfree',
            'title' => $this->language->get('text_title'),
            'terms' => '',
            'sort_order' => $this->config->get('cashfree_sort_order')
        );
        return $method_data;
    }
}

?>