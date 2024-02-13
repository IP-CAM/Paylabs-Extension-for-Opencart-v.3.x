<?php
class ControllerExtensionPaymentPaylabs extends Controller
{
	private $error = array();

	public function index()
	{
		$this->load->language('extension/payment/paylabs');
		$this->document->setTitle($this->language->get('heading_title'));

		$this->load->model('setting/setting');

		if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate()) {
			$this->model_setting_setting->editSetting('payment_paylabs', $this->request->post);

			$this->session->data['success'] = $this->language->get('text_success');

			$this->response->redirect($this->url->link('marketplace/extension', 'user_token=' . $this->session->data['user_token'], 'SSL'));
		}

		$data['heading_title'] = $this->language->get('heading_title');

		$data['input_paylabs_mid'] = $this->language->get('input_paylabs_mid');
		$data['input_paylabs_pub_key'] = $this->language->get('input_paylabs_pub_key');
		$data['input_paylabs_priv_key'] = $this->language->get('input_paylabs_priv_key');
		$data['input_paylabs_pub_key_sandbox'] = $this->language->get('input_paylabs_pub_key_sandbox');
		$data['input_paylabs_priv_key_sandbox'] = $this->language->get('input_paylabs_priv_key_sandbox');
		$data['input_paylabs_mode'] = $this->language->get('input_paylabs_mode');
		$data['input_paylabs_order_status'] = $this->language->get('input_paylabs_order_status');
		$data['input_paylabs_status'] = $this->language->get('input_paylabs_status');

		if (isset($this->error['warning'])) {
			$data['error_warning'] = $this->error['warning'];
		} else {
			$data['error_warning'] = '';
		}

		if (isset($this->error['mid'])) {
			$data['error_mid'] = $this->error['mid'];
		} else {
			$data['error_mid'] = '';
		}

		$data['breadcrumbs'] = array();

		$data['breadcrumbs'][] = array(
			'text'      => $this->language->get('text_home'),
			'href'      => $this->url->link('common/home', 'user_token=' . $this->session->data['user_token'], 'SSL'),
			'separator' => false
		);

		$data['breadcrumbs'][] = array(
			'text'      => $this->language->get('text_payment'),
			'href'      => $this->url->link('extension/payment', 'user_token=' . $this->session->data['user_token'], 'SSL'),
			'separator' => ' :: '
		);

		$data['breadcrumbs'][] = array(
			'text'      => $this->language->get('heading_title'),
			'href'      => $this->url->link('extension/payment/paylabs', 'user_token=' . $this->session->data['user_token'], 'SSL'),
			'separator' => ' :: '
		);

		$data['action'] = $this->url->link('extension/payment/paylabs', 'user_token=' . $this->session->data['user_token'], 'SSL');

		$data['cancel'] = $this->url->link('extension/payment', 'user_token=' . $this->session->data['user_token'], 'SSL');

		if (isset($this->request->post['payment_paylabs_mid'])) {
			$data['payment_paylabs_mid'] = $this->request->post['payment_paylabs_mid'];
		} else {
			$data['payment_paylabs_mid'] = $this->config->get('payment_paylabs_mid');
		}

		if (isset($this->request->post['payment_paylabs_pub_key'])) {
			$data['payment_paylabs_pub_key'] = $this->request->post['payment_paylabs_pub_key'];
		} else {
			$data['payment_paylabs_pub_key'] = $this->config->get('payment_paylabs_pub_key');
		}

		if (isset($this->request->post['payment_paylabs_priv_key'])) {
			$data['payment_paylabs_priv_key'] = $this->request->post['payment_paylabs_priv_key'];
		} else {
			$data['payment_paylabs_priv_key'] = $this->config->get('payment_paylabs_priv_key');
		}

		if (isset($this->request->post['payment_paylabs_pub_key_sandbox'])) {
			$data['payment_paylabs_pub_key_sandbox'] = $this->request->post['payment_paylabs_pub_key_sandbox'];
		} else {
			$data['payment_paylabs_pub_key_sandbox'] = $this->config->get('payment_paylabs_pub_key_sandbox');
		}

		if (isset($this->request->post['payment_paylabs_priv_key_sandbox'])) {
			$data['payment_paylabs_priv_key_sandbox'] = $this->request->post['payment_paylabs_priv_key_sandbox'];
		} else {
			$data['payment_paylabs_priv_key_sandbox'] = $this->config->get('payment_paylabs_priv_key_sandbox');
		}

		$data['callback'] = HTTP_CATALOG . 'index.php?route=payment/paylabs/callback';

		if (isset($this->request->post['payment_paylabs_mode'])) {
			$data['payment_paylabs_mode'] = $this->request->post['payment_paylabs_mode'];
		} else {
			$data['payment_paylabs_mode'] = $this->config->get('payment_paylabs_mode');
		}

		if (isset($this->request->post['payment_paylabs_order_status_id'])) {
			$data['payment_paylabs_order_status_id'] = $this->request->post['payment_paylabs_order_status_id'];
		} else {
			$data['payment_paylabs_order_status_id'] = $this->config->get('payment_paylabs_order_status_id');
		}

		if (isset($this->request->post['payment_paylabs_status'])) {
			$data['payment_paylabs_status'] = $this->request->post['payment_paylabs_status'];
		} else {
			$data['payment_paylabs_status'] = $this->config->get('payment_paylabs_status');
		}

		$this->load->model('localisation/order_status');

		$data['order_statuses'] = $this->model_localisation_order_status->getOrderStatuses();

		$data['header'] = $this->load->controller('common/header');
		$data['column_left'] = $this->load->controller('common/column_left');
		$data['footer'] = $this->load->controller('common/footer');

		$this->response->setOutput($this->load->view('extension/payment/paylabs', $data));
	}

	private function validate()
	{
		if (!$this->user->hasPermission('modify', 'extension/payment/paylabs')) {
			$this->error['warning'] = $this->language->get('error_permission');
		}

		if (!$this->request->post['payment_paylabs_mid']) {
			$this->error['security'] = $this->language->get('error_security');
		}

		return empty($this->error);
	}

	public function uninstall()
	{
		$this->load->model('setting/setting');
		$this->model_setting_setting->deleteSetting('paylabs');
	}
}
