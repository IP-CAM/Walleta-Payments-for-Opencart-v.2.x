<?php

/**
 * @property \Config $config
 * @property \Language $language
 * @property \Loader $load
 */
class ModelPaymentWalleta extends Model
{
    public function getMethod($address)
    {
        if (!$this->config->get('walleta_status')) {
            return array();
        }

        $this->load->language('payment/walleta');

        return array(
            'code' => 'walleta',
            'title' => $this->language->get('text_title'),
            'terms' => '',
            'sort_order' => $this->config->get('walleta_sort_order')
        );
    }
}
