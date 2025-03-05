<?php
namespace Opencart\Catalog\Model\Extension\Opencart\Payment;

class Cashfree extends \Opencart\System\Engine\Model
{
    public function getMethods($address)
    {
        $this->load->language('extension/opencart/payment/cashfree');
        $country_id = isset($address['country_id']) ? (int)$address['country_id'] : 0;
        $zone_id = isset($address['zone_id']) ? (int)$address['zone_id'] : 0;
        $query = $this->db->query("SELECT * FROM " . DB_PREFIX . "zone_to_geo_zone WHERE geo_zone_id = '" . (int)$this->config->get('payment_cashfree_geo_zone_id') . "' AND country_id = '" . $country_id . "' AND (zone_id = '" . $zone_id . "' OR zone_id = '0')");

        if ($this->config->get('payment_cashfree_total') > 0 && $this->config->get('payment_cashfree_total') > $this->cart->getTotal()) {
            $status = false;
        } elseif (!$this->config->get('payment_cashfree_geo_zone_id')) {
            $status = true;
        } elseif ($query->num_rows) {
            $status = true;
        } else {
            $status = false;
        }


        $method_data = array();

        if ($status) {
            $option_data['cashfree'] = [
                'code' => 'cashfree.cashfree',
                'name' => $this->language->get('text_title')
            ];

            $method_data = [
                'code'       => 'cashfree',
                'name'       => $this->language->get('text_title'),
                'option'     => $option_data,
                'sort_order' => $this->config->get('payment_cashfree_sort_order')
            ];
        }

        return $method_data;
    }
}
