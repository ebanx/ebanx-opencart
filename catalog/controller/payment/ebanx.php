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
		  , 'directMode'     => false
		));
	}

	/**
	 * Save EBANX stuff to log
	 * @param  string $text Text to log
	 * @return void
	 */
	protected function _log($text)
	{
		return;
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

		// Order total with interest
		$interest    = $this->config->get('ebanx_installments_interest');
		$order_total = ($order_info['total'] * (100 + floatval($interest))) / 100.0;
		$this->data['order_total_interest'] = $order_total;
		$currencyCode    = strtoupper($order_info['currency_code']);

		switch ($currencyCode)
	    {
	      case 'USD':
	        $totalReal = $order_total * 2.5;
	        break;
	      case 'EUR':
	        $totalReal = $order_total * 3.4;
	        break;
	      case 'BRL':
	      default:
	        $totalReal = $order_total;
	        break;
	    }

		// Form translations
		$this->language->load('payment/ebanx');
		$this->data['text_wait'] 				 = $this->language->get('text_wait');
		$this->data['entry_payment_method']      = $this->language->get('entry_payment_method');
		$this->data['entry_dob']                 = $this->language->get('entry_dob');
		$this->data['entry_ebanx_details']       = $this->language->get('entry_ebanx_details');
		$this->data['entry_please_select']   	 = $this->language->get('entry_please_select');

		// Currency symbol and order total for display purposes
		$this->data['order_total']   = $order_info['total'];
		$this->data['currency_code'] = $order_info['currency_code'];

		// Render normal or direct checkout page
		$template = 'ebanx_checkout';

		// Preload customer data (CPF and DOB)
		$this->load->model('customer/ebanx');
  	    $info = $this->model_customer_ebanx->findByCustomerId($this->customer->getId());

  	    $this->data['entry_tef_details']  = $this->language->get('entry_tef_details');

  	    $this->data['ebanx_cpf'] = '';
		$this->data['ebanx_dob'] = '';

  	    if ($info)
  	    {
  		    $this->data['ebanx_cpf'] = $info['cpf'];
  		    $this->data['ebanx_dob'] = $info['dob'];
  	    }

		// Render a custom template if it's available
		if (file_exists(DIR_TEMPLATE . $this->config->get('config_template') . '/template/payment/' . $template . '.tpl'))
		{
			$this->template = $this->config->get('config_template') . '/template/payment/' . $template . '.tpl';
		}
		else
		{
			$this->template = 'default/template/payment/' . $template . '.tpl';
		}

		if ($this->isOpencart2())
		{
			return $this->load->view($this->template, $this->data);
		}
		else
		{
			$this->render();
		}		
	}

	/**
	 * EBANX checkout action. Redirects to the EBANX URI.
	 * @return void
	 */
	public function checkout()
	{
		$this->_setupEbanx();
		$this->load->model('checkout/order');
		$this->load->model('payment/ebanx');

		$order_info = $this->model_checkout_order->getOrder($this->session->data['order_id']);

		$address = $order_info['payment_address_1'];
		if (!!$order_info['payment_address_2'])
		{
			$address .= ', ' . $order_info['payment_address_2'];
		}

		$params = array(
		      'mode'      => 'full'
		    , 'operation' => 'request'
			, 'name' 					=> $order_info['payment_firstname'] . ' ' . $order_info['payment_lastname']
			, 'email' 				=> $order_info['email']
			, 'amount' 				=> $order_info['total']
			, 'currency_code' => $this->config->get('config_currency')
			, 'address'			  => $address
			, 'zipcode' 		  => $order_info['payment_postcode']
			, 'phone_number'  => $order_info['telephone']
			, 'payment_type_code' 		=> '_all'
			, 'merchant_payment_code' => $order_info['order_id']
			, 'plugin_source'     => 'Opencart ver. ' . VERSION
		);

		$response = \Ebanx\Ebanx::doRequest($params);

		if ($response->status == 'SUCCESS')
		{
			$this->_log('SUCCESS | Order: ' . $order_info['order_id'] . ', Hash: ' . $response->payment->hash);

			$this->load->model('payment/ebanx');
			$this->model_payment_ebanx->setPaymentHash($order_info['order_id'], $response->payment->hash);

			$this->load->model('checkout/order');

			if($this->isOpencart2())
			{
				$this->model_checkout_order->addOrderHistory($this->session->data['order_id'], $this->config->get('ebanx_order_status_op_id'));
			}
			else
			{
				$this->model_checkout_order->confirm($this->session->data['order_id'], $this->config->get('ebanx_order_status_op_id'));
			}

			echo $response->redirect_url;
			die();
		}
		else
		{
			$this->_log('ERROR | Order: ' . $order_info['order_id'] . ', Error: ' . $response->status_message);
			echo $response->status_message;
		}
		exit;
	}

	/**
	 * Gets the store base URL
	 * @return string
	 */
	protected function _getBaseUrl()
	{
		return $this->config->get('config_url');
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
		$this->data['terms']         = "";

		$hash = isset($this->request->get['hash']) ? $this->request->get['hash'] : false;

		if ($hash && strlen($hash))
		{
			$response = \Ebanx\Ebanx::doQuery(array('hash' => $hash));

			// Update the order status, then redirect to the success page
			if (isset($response->status) && $response->status == 'SUCCESS' && ($response->payment->status == 'PE' || $response->payment->status == 'CO'))
			{
				$this->_log('CALLBACK SUCCESS | Order: ' . $this->data['order_id'] . ', Status: ' . $response->payment->status);

				$this->load->model('checkout/order');

				if($this->isOpencart2())
				{
					if ($response->payment->status == 'CO')
					{
						$this->model_checkout_order->addOrderHistory($this->data['order_id'], $this->config->get('ebanx_order_status_co_id'));
					}
					elseif ($response->payment->status == 'PE')
					{
						$this->model_checkout_order->addOrderHistory($this->data['order_id'], $this->config->get('ebanx_order_status_pe_id'));
					}

					$this->response->redirect($this->url->link('checkout/success'));
				}
				else
				{
					if ($response->payment->status == 'CO')
					{
						$this->model_checkout_order->update($this->session->data['order_id'], $this->config->get('ebanx_order_status_co_id'));
					}
					elseif ($response->payment->status == 'PE')
					{
						$this->model_checkout_order->update($this->session->data['order_id'], $this->config->get('ebanx_order_status_pe_id'));
					}

					$this->redirect($this->url->link('checkout/success'));
				}				
			}
			else
			{
				// if the order fails
				$this->data['continue'] = $this->url->link('checkout/checkout');

				if (file_exists(DIR_TEMPLATE . $this->config->get('config_template') . '/template/payment/ebanx_failure.tpl'))
				{
					$this->template = $this->config->get('config_template') . '/template/payment/ebanx_failure.tpl';
				}
				else
				{
					$this->template = 'default/template/payment/ebanx_failure.tpl';
				}

				if ($this->isOpencart2())
				{
					$this->response->redirect($this->url->link('checkout/failure'));
				}
				else
				{
					$this->response->setOutput($this->render());
				}				
			}
		}
		else
		{
			//$this->data['continue'] = $this->url->link('checkout/cart');

			if (file_exists(DIR_TEMPLATE . $this->config->get('config_template') . '/template/payment/ebanx_failure.tpl'))
			{
				$this->template = $this->config->get('config_template') . '/template/payment/ebanx_failure.tpl';
			}
			else
			{
				$this->template = 'default/template/payment/ebanx_failure.tpl';
			}

			if ($this->isOpencart2())
			{
				$this->response->redirect($this->url->link('checkout/failure'));
			}
			else
			{
				$this->response->setOutput($this->render());
			}	
		}
	}

	protected function isOpencart2()
    {
        return (intval(VERSION) >= 2);
    }
}