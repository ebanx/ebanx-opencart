<?php

/**
 * Copyright (c) 2013, EBANX Tecnologia da Informação Ltda.
 *  All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions are met:
 *
 * Redistributions of source code must retain the above copyright notice, this
 * list of conditions and the following disclaimer.
 *
 * Redistributions in binary form must reproduce the above copyright notice,
 * this list of conditions and the following disclaimer in the documentation
 * and/or other materials provided with the distribution.
 *
 * Neither the name of EBANX nor the names of its
 * contributors may be used to endorse or promote products derived from
 * this software without specific prior written permission.
 *
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS"
 * AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE
 * IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE
 * DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT HOLDER OR CONTRIBUTORS BE LIABLE
 * FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL
 * DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR
 * SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER
 * CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY,
 * OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE
 * OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 */


require_once DIR_SYSTEM . 'library/ebanx-php/src/autoload.php';

/**
 * The payment actions controller
 */
class ControllerPaymentEbanx extends Controller
{
	/**
	 * Initialize the EBANX settings before usage
	 * @return void
	 */
	protected function _setupEbanx()
	{
		\Ebanx\Config::set(array(
		    'integrationKey' => $this->config->get('ebanx_merchant_key')
		  , 'testMode'       => ($this->config->get('ebanx_mode') == 'test')
		));
	}

	/**
	 * EBANX gateway custom fields for the checkout
	 * @return void
	 */
	public function index()
	{
		$this->load->model('checkout/order');
		$order_info = $this->model_checkout_order->getOrder($this->session->data['order_id']);

		$this->data['button_confirm'] = $this->language->get('button_confirm');

		$this->data['enable_installments'] = $this->config->get('ebanx_enable_installments');
		$this->data['max_installments']    = $this->config->get('ebanx_max_installments');

		// Order total with interest
		$interest    = $this->config->get('ebanx_installments_interest');
		$order_total = ($order_info['total'] * (100 + floatval($interest))) / 100.0;
		$this->data['order_total_interest'] = $order_total;

		// Form translations
		$this->language->load('payment/ebanx');
		$this->data['entry_installments_number'] = $this->language->get('entry_installments_number');
		$this->data['entry_installments_cc']     = $this->language->get('entry_installments_cc');
		$this->data['text_wait'] = $this->language->get('text_wait');

		// Currency symbol and order total for display purposes
		$this->data['order_total']   = $order_info['total'];
		$this->data['currency_code'] = $order_info['currency_code'];

		// Render a custom template if it's available
		if (file_exists(DIR_TEMPLATE . $this->config->get('config_template') . '/template/payment/ebanx.tpl'))
		{
			$this->template = $this->config->get('config_template') . '/template/payment/ebanx.tpl';
		}
		else
		{
			$this->template = 'default/template/payment/ebanx.tpl';
		}

		$this->render();
	}

	/**
	 * EBANX checkout action. Redirects to the EBANX URI.
	 * @return void
	 */
	public function checkout()
	{
		$this->_setupEbanx();
		$this->load->model('checkout/order');

		$order_info = $this->model_checkout_order->getOrder($this->session->data['order_id']);

		$address = $order_info['payment_address_1'];
		if (!!$order_info['payment_address_2'])
		{
			$address .= ', ' . $order_info['payment_address_2'];
		}

		$params = array(
				'name' 					=> $order_info['payment_firstname'] . ' ' . $order_info['payment_lastname']
			, 'email' 				=> $order_info['email']
			, 'amount' 				=> $this->currency->format($order_info['total'], $order_info['currency_code'], $order_info['currency_value'], false)
			, 'currency_code' => $order_info['currency_code']
			, 'address'			  => $address
			, 'zipcode' 		  => $order_info['payment_postcode']
			, 'phone_number'  => $order_info['telephone']
			, 'payment_type_code' 		=> '_all'
			, 'merchant_payment_code' => $order_info['order_id']
		);

		// Installments
		if (isset($this->request->post['instalments']) && $this->request->post['instalments'] > 1)
		{
			$params['instalments']       = $this->request->post['instalments'];
			$params['payment_type_code'] = $this->request->post['payment_type_code'];

			// Add interest to the order total
			$interest    			= $this->config->get('ebanx_installments_interest');
			$order_total 			= ($order_info['total'] * (100 + floatval($interest))) / 100.0;
			$params['amount'] = $this->currency->format($order_total, $order_info['currency_code'], $order_info['currency_value'], false);
		}

		$response = \Ebanx\Ebanx::doRequest($params);

		if ($response->status == 'SUCCESS')
		{
			$this->load->model('payment/ebanx');
			$this->model_payment_ebanx->setPaymentHash($order_info['order_id'], $response->payment->hash);

			$this->load->model('checkout/order');

			$this->model_checkout_order->confirm($this->session->data['order_id'], $this->config->get('ebanx_order_status_op_id'));

			echo $response->redirect_url;
			die();
		}
	}

	/**
	 * Callback action. It's called when returning from EBANX.
	 * @return void
	 */
	public function callback()
	{
		$this->_setupEbanx();

		$this->language->load('payment/ebanx');

		$this->data['title'] = sprintf($this->language->get('heading_title'), $this->config->get('config_name'));

		$this->data['base'] = $this->config->get('config_url');
		if (isset($this->request->server['HTTPS']) && ($this->request->server['HTTPS'] == 'on'))
		{
			$this->data['base'] = $this->config->get('config_ssl');
		}

		// Setup translations
		$this->data['language'] 		 = $this->language->get('code');
		$this->data['direction'] 		 = $this->language->get('direction');
		$this->data['heading_title'] = sprintf($this->language->get('heading_title'), $this->config->get('config_name'));
		$this->data['text_response'] = $this->language->get('text_response');
		$this->data['text_success']  = $this->language->get('text_success');
		$this->data['text_failure']  = $this->language->get('text_failure');
		$this->data['text_success_wait'] = sprintf($this->language->get('text_success_wait'), $this->url->link('checkout/success'));
		$this->data['text_failure_wait'] = sprintf($this->language->get('text_failure_wait'), $this->url->link('checkout/checkout', '', 'SSL'));

		$response = \Ebanx\Ebanx::doQuery(array('hash' => $this->request->get['hash']));

		// Update the order status, then redirect to the success page
		if (isset($response->status) && $response->status == 'SUCCESS' && ($response->payment->status == 'PE' || $response->payment->status == 'CO'))
		{
			$this->load->model('checkout/order');

			if ($response->payment->status == 'CO')
			{
				$this->model_checkout_order->update($this->session->data['order_id'], $this->config->get('ebanx_order_status_co_id'));
			}
			elseif ($response->payment->status == 'PE')
			{
				$this->model_checkout_order->update($this->session->data['order_id'], $this->config->get('ebanx_order_status_pe_id'));
			}

			$this->data['continue'] = $this->url->link('checkout/success');

			if (file_exists(DIR_TEMPLATE . $this->config->get('config_template') . '/template/payment/ebanx_success.tpl'))
			{
				$this->template = $this->config->get('config_template') . '/template/payment/ebanx_success.tpl';
			}
			else
			{
				$this->template = 'default/template/payment/ebanx_success.tpl';
			}

			$this->response->setOutput($this->render());
		}
		else
		{
			$this->data['continue'] = $this->url->link('checkout/cart');

			if (file_exists(DIR_TEMPLATE . $this->config->get('config_template') . '/template/payment/ebanx_failure.tpl'))
			{
				$this->template = $this->config->get('config_template') . '/template/payment/ebanx_failure.tpl';
			}
			else
			{
				$this->template = 'default/template/payment/ebanx_failure.tpl';
			}

			$this->response->setOutput($this->render());
		}
	}

	/**
	 * Notification action. It's called when a payment status is updated.
	 * @return void
	 */
	public function notify()
	{
		$this->_setupEbanx();

		$hashes = explode(',', $this->request->post['hash_codes']);

		foreach ($hashes as $hash)
		{
			$response = \Ebanx\Ebanx::doQuery(array('hash' => $hash));

			if (isset($response->status) && $response->status == 'SUCCESS')
			{
				$this->load->model('checkout/order');

				// Update the order status according to the settings
				$order_id = str_replace('_', '', $response->payment->merchant_payment_code);
				$status = $this->config->get('ebanx_order_status_' . strtolower($response->payment->status) . '_id');
				$this->model_checkout_order->update($order_id, $status);
			}
		}
	}
}