<?php
/**
 * @property \Config $config
 * @property \Language $language
 * @property \Loader $load
 * @property \Request $request
 * @property \Response $response
 * @property \Session $session
 * @property \Url $url
 * @property \ModelCheckoutOrder $model_checkout_order
 * @property \Cart\Currency $currency
 * @property \Encryption $encryption
 * @property \Cart\Cart $cart
 * @property \Cart\Tax $tax
 * @property \Document $document
 */
class ControllerPaymentWalleta extends Controller
{
    const PAYMENT_REQUEST_URL = 'https://cpg.walleta.ir/payment/request.json';
    const PAYMENT_VERIFY_URL = 'https://cpg.walleta.ir/payment/verify.json';
    const PAYMENT_GATEWAY_URL = 'https://cpg.walleta.ir/ticket/';

    public function index()
    {
        $this->load->language('payment/walleta');

        $data['entry_mobile'] = $this->language->get('entry_mobile');
        $data['entry_national_code'] = $this->language->get('entry_national_code');

        $data['button_confirm'] = $this->language->get('button_confirm');
        $data['action'] = $this->url->link('payment/walleta/confirm', '', true);

        return $this->load->view('payment/walleta', $data);
    }

    public function confirm()
    {
        $this->canBeUsed();
        $this->loadLibConfig();
        $this->load->language('payment/walleta');

        $result = array(
            'status' => 'failed',
            'errors' => array(),
        );

        try {
            $params = $this->getPaymentRequestParams();

            if (!$params['payer_mobile']) {
                $result['errors'][] = $this->language->get('error_mobile_required');
            } elseif (!\Walleta\Validation::mobile($params['payer_mobile'])) {
                $result['errors'][] = $this->language->get('error_mobile_invalid');
            }

            if (!$params['payer_national_code']) {
                $result['errors'][] = $this->language->get('error_national_code_required');
            } elseif (!\Walleta\Validation::nationalCode($params['payer_national_code'])) {
                $result['errors'][] = $this->language->get('error_national_code_invalid');
            }

            if ($result['errors']) {
                return $this->sendJsonResponse($result);
            }

            $client = new \Walleta\Client\HttpRequest();
            $response = $client->post(self::PAYMENT_REQUEST_URL, $params);

            if (!$response->isSuccess()) {
                $result['errors'][] = $response->getErrorMessage();
                if ($response->getErrorType() === 'validation_error') {
                    $result['errors'] = array_merge($result['errors'], $response->getValidationErrors());
                }

                return $this->sendJsonResponse($result);
            }

            $result['status'] = 'success';
            $result['redirect'] = self::PAYMENT_GATEWAY_URL . $response->getData('token');
        } catch (Exception $ex) {
            $result['errors'][] = $this->language->get('error_payment_token');
        }

        return $this->sendJsonResponse($result);
    }

    public function verify()
    {
        $this->canBeUsed();
        $this->loadLibConfig();
        $this->load->language('payment/walleta');
        $this->document->setTitle($this->language->get('text_title'));

        $data['heading_title'] = $this->language->get('text_title');
        $data['button_confirm'] = $this->language->get('button_confirm');
        $data['button_continue'] = $this->language->get('button_continue');

        // Breadcrumbs
        $data['breadcrumbs'] = array();
        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_home'),
            'href' => $this->url->link('common/home', '', true)
        );
        $data['breadcrumbs'][] = array(
            'text' => $this->language->get('text_title'),
            'href' => $this->url->link('payment/walleta/verify', '', true)
        );

        try {
            if (!isset($this->request->get['status'])) {
                throw new Exception($this->language->get('error_status'));
            }

            if ($this->request->get['status'] !== 'success') {
                throw new Exception($this->language->get('pay_result_failed'));
            }

            $encrypted_order_id = isset($this->request->get['order_id']) ? $this->request->get['order_id'] : null;
            $order_id = $encrypted_order_id ? $this->encryption->decrypt($encrypted_order_id) : null;

            $this->load->model('checkout/order');

            $order = $this->model_checkout_order->getOrder($order_id);

            if (!$order) {
                throw new Exception($this->language->get('error_order_id'));
            }

            $params = $this->getPaymentVerifyParams($order);

            $client = new \Walleta\Client\HttpRequest();
            $response = $client->post(self::PAYMENT_VERIFY_URL, $params);

            if (!$response->isSuccess()) {
                throw new Exception($response->getErrorMessage());
            }

            if ($response->getData('is_paid') !== true) {
                throw new Exception($this->language->get('error_order_not_paid'));
            }

            $comment = $this->language->get('pay_result_success');
            $this->model_checkout_order
                ->addOrderHistory($order_id, $this->config->get('walleta_order_status_id'), $comment, true);

            $this->response->redirect($this->url->link('checkout/success'));
        } catch (Exception $ex) {
            $data['error_warning'] = $ex->getMessage();
            $data['button_continue'] = $this->language->get('button_view_cart');
            $data['continue'] = $this->url->link('checkout/cart');
        }

        $data['column_left'] = $this->load->controller('common/column_left');
        $data['column_right'] = $this->load->controller('common/column_right');
        $data['content_top'] = $this->load->controller('common/content_top');
        $data['content_bottom'] = $this->load->controller('common/content_bottom');
        $data['footer'] = $this->load->controller('common/footer');
        $data['header'] = $this->load->controller('common/header');

        $this->response->setOutput($this->load->view('payment/walleta_verify', $data));
    }

    protected function getPaymentVerifyParams($order)
    {
        return array(
            'merchant_code' => $this->config->get('walleta_merchant_code'),
            'token' => isset($this->request->get['token']) ? $this->request->get['token'] : '',
            'invoice_reference' => $order['order_id'],
            'invoice_amount' => $this->formatMoney($order['total'], $order['currency_code'], $order['currency_value']),
        );
    }

    protected function getPaymentRequestParams()
    {
        $this->load->model('checkout/order');
        $order = $this->model_checkout_order->getOrder($this->session->data['order_id']);
        $encrypted_order_id = $this->encryption->encrypt($order['order_id']);

        $totalAmount = $this->formatMoney($order['total'], $order['currency_code'], $order['currency_value']);
        $callbackUrl = $this->url->link('payment/walleta/verify', 'order_id=' . $encrypted_order_id, true);
        $callbackUrl = str_replace('&amp;', '&', $callbackUrl);

        $data = array(
            'merchant_code' => $this->config->get('walleta_merchant_code'),
            'invoice_reference' => $order['order_id'],
            'invoice_date' => $order['date_added'],
            'invoice_amount' => $totalAmount,
            'payer_first_name' => $order['payment_firstname'],
            'payer_last_name' => $order['payment_lastname'],
            'payer_national_code' => $this->getPayerNationalCode(),
            'payer_mobile' => $this->getPayerMobile(),
            'callback_url' => $callbackUrl,
            'description' => 'پرداخت سفارش #' . $order['order_id'],
            'items' => array(),
        );

        foreach ($this->cart->getProducts() as $product) {
            $taxAmount = $this->tax->calculate(
                    $product['price'],
                    $product['tax_class_id'],
                    $this->config->get('config_tax')
                ) - $product['price'];

            $data['items'][] = array(
                'reference' => $product['product_id'],
                'name' => $product['name'],
                'quantity' => $product['quantity'],
                'unit_price' => $this->formatMoney(
                    $product['price'],
                    $order['currency_code'],
                    $order['currency_value']
                ),
                'unit_discount' => 0,
                'unit_tax_amount' => $this->formatMoney(
                    $taxAmount,
                    $order['currency_code'],
                    $order['currency_value']
                ),
                'total_amount' => $this->formatMoney(
                    $product['total'] + ($taxAmount * $product['quantity']),
                    $order['currency_code'],
                    $order['currency_value']
                ),
            );
        }

        if ($this->cart->hasShipping()) {
            $shippingCost = $this->session->data['shipping_method']['cost'];
            $shippingCost = $this->formatMoney($shippingCost, $order['currency_code'], $order['currency_value']);

            if ($shippingCost > 0) {
                $data['items'][] = array(
                    'name' => 'هزینه ارسال',
                    'quantity' => 1,
                    'unit_price' => $shippingCost,
                    'unit_discount' => 0,
                    'unit_tax_amount' => 0,
                    'total_amount' => $shippingCost,
                );
            }
        }

        return $data;
    }

    public function getPayerMobile()
    {
        return isset($this->request->post['payer_mobile']) ? $this->request->post['payer_mobile'] : '';
    }

    protected function getPayerNationalCode()
    {
        return isset($this->request->post['payer_national_code']) ? $this->request->post['payer_national_code'] : '';
    }

    protected function formatMoney($amount, $currency_code, $currency_value)
    {
        $amount = $this->currency->format($amount, $currency_code, $currency_value, false);
        return $this->currency->convert(round($amount), $currency_code, 'TOM');
    }

    protected function sendJsonResponse(array $data)
    {
        $this->response->addHeader('Content-Type: application/json');
        $this->response->setOutput(json_encode($data));

        return null;
    }

    protected function canBeUsed()
    {
        $payMethod = isset($this->session->data['payment_method']['code']) ? $this->session->data['payment_method']['code'] : null;
        if ($payMethod !== 'walleta') {
            exit;
        }
    }

    protected function loadLibConfig()
    {
        require_once DIR_SYSTEM . 'library' . DIRECTORY_SEPARATOR . 'walleta' . DIRECTORY_SEPARATOR . 'walleta.php';
    }
}
