<?php

/**
 * @property \Cart\User $user
 * @property \Config $config
 * @property \Document $document
 * @property \Language $language
 * @property \Loader $load
 * @property \ModelSettingSetting $model_setting_setting
 * @property \Request $request
 * @property \Response $response
 * @property \Session $session
 * @property \Url $url
 */
class ControllerPaymentWalleta extends Controller
{
    private $error = array();

    public function index()
    {
        $this->load->language('payment/walleta');
        $this->document->setTitle(strip_tags($this->language->get('heading_title')));
        $this->load->model('setting/setting');

        if ($this->request->server['REQUEST_METHOD'] === 'POST' && $this->validate()) {
            $this->model_setting_setting->editSetting('walleta', $this->request->post);
            $this->session->data['success'] = $this->language->get('text_success');
            $this->response->redirect(
                $this->url->link('extension/payment', 'token=' . $this->session->data['token'], true)
            );
        }

        // Language data
        $data['heading_title'] = $this->language->get('heading_title');

        $data['text_edit'] = $this->language->get('text_edit');
        $data['text_enabled'] = $this->language->get('text_enabled');
        $data['text_disabled'] = $this->language->get('text_disabled');
        $data['text_signup'] = $this->language->get('text_signup');

        $data['entry_merchant_code'] = $this->language->get('entry_merchant_code');
        $data['entry_order_status'] = $this->language->get('entry_order_status');
        $data['entry_status'] = $this->language->get('entry_status');
        $data['entry_sort_order'] = $this->language->get('entry_sort_order');

        $data['button_save'] = $this->language->get('button_save');
        $data['button_cancel'] = $this->language->get('button_cancel');

        // Errors
        $data['error_warning'] = isset($this->error['warning']) ? $this->error['warning'] : '';
        $data['error_merchant_code'] = isset($this->error['merchant_code']) ? $this->error['merchant_code'] : '';

        // Settings
        $data['walleta_merchant_code'] = isset($this->request->post['walleta_merchant_code']) ?
            $this->request->post['walleta_merchant_code'] : $this->config->get('walleta_merchant_code');

        $data['walleta_order_status_id'] = isset($this->request->post['walleta_order_status_id']) ?
            $this->request->post['walleta_order_status_id'] : $this->config->get('walleta_order_status_id');

        $this->load->model('localisation/order_status');
        $data['order_statuses'] = $this->model_localisation_order_status->getOrderStatuses();

        $data['walleta_status'] = isset($this->request->post['walleta_status']) ?
            $this->request->post['walleta_status'] : $this->config->get('walleta_status');

        $data['walleta_sort_order'] = isset($this->request->post['walleta_sort_order']) ?
            $this->request->post['walleta_sort_order'] : $this->config->get('walleta_sort_order');

        // Breadcrumbs
        $data['breadcrumbs'] = array();
        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_home'),
            'href' => $this->url->link('common/dashboard', 'token=' . $this->session->data['token'], true)
        );
        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_payment'),
            'href' => $this->url->link('extension/payment', 'token=' . $this->session->data['token'], true)
        );
        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('heading_title'),
            'href' => $this->url->link('payment/walleta', 'token=' . $this->session->data['token'], true)
        );

        // Links
        $data['action'] = $this->url->link('payment/walleta', 'token=' . $this->session->data['token'], true);
        $data['cancel'] = $this->url->link('extension/payment', 'token=' . $this->session->data['token'], true);

        $data['header'] = $this->load->controller('common/header');
        $data['column_left'] = $this->load->controller('common/column_left');
        $data['footer'] = $this->load->controller('common/footer');

        $this->response->setOutput($this->load->view('payment/walleta', $data));
    }

    protected function validate()
    {
        if (!$this->user->hasPermission('modify', 'payment/walleta')) {
            $this->error['warning'] = $this->language->get('error_permission');
        }

        if (!$this->request->post['walleta_merchant_code']) {
            $this->error['merchant_code'] = $this->language->get('error_merchant_code');
        }

        return !$this->error;
    }
}
