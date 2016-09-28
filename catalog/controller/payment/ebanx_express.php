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
class ControllerPaymentEbanxExpress extends Controller
{
	/**
	 * Initialize the EBANX settings before usage
	 * @return void
	 */
	protected function _setupEbanx()
	{
		\Ebanx\Config::set(array(
		    'integrationKey' => $this->config->get('ebanx_express_merchant_key')
		  , 'testMode'       => ($this->config->get('ebanx_express_mode') == 'test')
		  , 'directMode'     => true
		));
	}

	/**
	 * Checks if it's opencart 1 or 2
	 * @return mixed
	 */
	protected function isOpencart2()
	{
		return (intval(VERSION) >= 2);
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
		$view = array();
		$this->_setupEbanx();
		$this->load->model('checkout/order');
		$order_info = $this->model_checkout_order->getOrder($this->session->data['order_id']);

		$view['button_confirm'] = $this->language->get('button_confirm');

		// Disable installments on checkout mode
		$view['enable_installments'] = false;

		// Order total with interest
		$interest    = $this->config->get('ebanx_express_installments_interest');
		$order_total = ($order_info['total'] * (100 + floatval($interest))) / 100.0;
		$view['order_total_interest'] = $order_total;

		// Enforce minimum installment value (R$20)
		$maxInstallments = $this->config->get('ebanx_express_max_installments');
		$currencyCode    = strtoupper($order_info['currency_code']);

		$currencyEbanx = \Ebanx\Ebanx::doExchange([
		    'currency_code'      => $currencyCode
		  , 'currency_base_code' => 'BRL'
		]);
		$totalReal = $order_total * number_format($currencyEbanx->currency_rate->rate, 2, '.', '');

	    if (($totalReal / 20) < $maxInstallments)
	    {
		    $maxInstallments = floor($totalReal / 20);
	    }

		$view['max_installments'] = $maxInstallments;

		// Form translations
		$this->language->load('payment/ebanx_express');
		$view['text_wait'] 				 = $this->language->get('text_wait');
		$view['entry_installments_number'] = $this->language->get('entry_installments_number');
		$view['entry_installments_cc']     = $this->language->get('entry_installments_cc');
		$view['entry_payment_method']      = $this->language->get('entry_payment_method');
		$view['entry_dob']                 = $this->language->get('entry_dob');
		$view['entry_card_name']           = $this->language->get('entry_card_name');
		$view['entry_card_number']         = $this->language->get('entry_card_number');
		$view['entry_card_type']           = $this->language->get('entry_card_type');
		$view['entry_card_exp']            = $this->language->get('entry_card_exp');
		$view['entry_ebanx_details']       = $this->language->get('entry_ebanx_details');
		$view['entry_interest']			 = $this->language->get('entry_interest');
		$view['entry_please_select']   	 = $this->language->get('entry_please_select');
		$view['entry_month'] 				 = $this->language->get('entry_month');
		$view['entry_year']  				 = $this->language->get('entry_year');

		// Currency symbol and order total for display purposes
		$view['order_total']   = $order_info['total'];
		$view['currency_code'] = $order_info['currency_code'];

		$view['ebanx_express_direct_cards']  = $this->config->get('ebanx_express_direct_cards');
		$view['ebanx_express_direct_boleto'] = $this->config->get('ebanx_express_direct_boleto');
		$view['ebanx_express_direct_tef']    = $this->config->get('ebanx_express_direct_tef');

		// Check if installments are enabled for direct mode
		$view['enable_installments'] = $this->config->get('ebanx_express_enable_installments');

		// Render normal or direct (Brazil) checkout page
		$template = 'ebanx';

		if ($order_info['payment_iso_code_2'] == 'BR')
		{
			$template .= '_express';
		}

		if ($this->isOpencart2())
		{
			$template .= '2';
		}

		// Preload customer data (CPF and DOB)
		$this->load->model('customer/ebanx_express');
  	    $info = $this->model_customer_ebanx_express->findByCustomerId($this->customer->getId());

  	    $view['entry_tef_details']  = $this->language->get('entry_tef_details');

  	    $view['ebanx_cpf'] = '';
		$view['ebanx_dob'] = '';
		$view['total_view']              = $this->currency->format($order_info['total'], $this->config->get('config_currency'), true, true);
		$view['interest_view']           = $this->currency->format($order_total - $order_info['total'], $this->config->get('config_currency'), true, true);
		$view['totalWithInterest']       = $this->currency->format($order_total, $this->config->get('config_currency'), true, true);

  	    if ($info)
  	    {
  		    $view['ebanx_cpf'] = $info['cpf'];
  		    $view['ebanx_dob'] = $info['dob'];
  	    }

		// Render a custom template if it's available
		if (file_exists(DIR_TEMPLATE . $this->config->get('config_template') . '/template/payment/' . $template . '.tpl'))
		{
			$template = $this->config->get('config_template') . '/template/payment/' . $template . '.tpl';
		}
		else
		{
			$template = 'default/template/payment/' . $template . '.tpl';
		}

		// Render either for OC1 or OC2
		if ($this->isOpencart2())
		{
			return $this->load->view($template, $view);
		}
		else
		{
			$this->template = $template;
			$this->data     = $view;
			$this->render();
		}
	}

	/**
	 * EBANX direct checkout action. Redirects to the success URI.
	 * @return void
	 */
	public function checkoutDirect()
	{
		$view = array();
		$this->_setupEbanx();
		$this->load->model('checkout/order');
		$this->load->model('customer/ebanx_express');
		$this->load->model('payment/ebanx_express');

		$order_info = $this->model_checkout_order->getOrder($this->session->data['order_id']);

		// Get full address
		$address = $order_info['payment_address_1'];
		if (!!$order_info['payment_address_2'])
		{
			$address .= ', ' . $order_info['payment_address_2'];
		}

		$params = array(
		      'mode'      => 'full'
		    , 'operation' => 'request'
		    , 'payment'   => array(
				          'name'              => $order_info['payment_firstname'] . ' ' . $order_info['payment_lastname']
				        , 'document'          => preg_replace('/\D/', '', $this->request->post['ebanx']['cpf'])
				        , 'birth_date'        => $this->request->post['ebanx']['dob']
				        , 'email'             => $order_info['email']
				        , 'phone_number'      => $order_info['telephone']
				        , 'currency_code'     => $this->config->get('config_currency')
				        , 'amount_total'      => $order_info['total']
				        , 'payment_type_code' => $this->request->post['ebanx']['cc_type']
				        , 'merchant_payment_code' => $order_info['order_id']
				        , 'zipcode'           => $order_info['payment_postcode']
				        , 'address'           => $address
				        , 'street_number'     => preg_replace('/[\D]/', '', $address)
				        , 'city'              => $order_info['payment_city']
				        , 'state'             => $order_info['payment_zone_code']
				        , 'country'           => 'br'
				        , 'plugin_source'     => 'Opencart ver. ' . VERSION
			    	)
	  );

		// Add installments to order
		if (isset($this->request->post['ebanx']['installments']) && intval($this->request->post['ebanx']['installments']) > 1 &&
			  $this->request->post['ebanx']['cc_type'] != 'discover')
		{
			$params['payment']['instalments']       = $this->request->post['ebanx']['installments'];
			$params['payment']['payment_type_code'] = $this->request->post['ebanx']['cc_type'];

			// Add interest to the order total
			$interest    = $this->config->get('ebanx_express_installments_interest');
			$order_total = ($order_info['total'] * (100 + floatval($interest))) / 100.0;
			$params['payment']['amount_total'] = number_format($order_total, 2, '.', '');

			// Save installments to total
			$this->model_payment_ebanx_express->updateTotalsWithInterest(array(
				  'order_id'       => $order_info['order_id']
				, 'total_text'     => $this->currency->format($params['payment']['amount_total'])
				, 'total_value'    => $params['payment']['amount_total']
				, 'interest_text'  => $this->currency->format($order_total - $order_info['total'])
				, 'interest_value' => $order_total - $order_info['total']
			));
		}

    // Add credit card fields if the method is credit card
    $params['payment']['payment_type_code'] = $this->request->post['ebanx']['cc_type'];
    $params['payment']['creditcard'] = array(
        'card_name'     => $this->request->post['ebanx']['cc_name']
      , 'card_number'   => $this->request->post['ebanx']['cc_number']
      , 'card_cvv'      => $this->request->post['ebanx']['cc_cvv']
      , 'card_due_date' => str_pad($this->request->post['ebanx']['cc_exp']['month'], 2, '0', STR_PAD_LEFT)
      										 . '/' . $this->request->post['ebanx']['cc_exp']['year']
    );

    // Persist the customer DOB and CPF
    $data = array(
  		  'cpf' => $params['payment']['document']
  		, 'dob' => $params['payment']['birth_date']
  	);


  	$id = $this->customer->getId();
    if ($this->model_customer_ebanx_express->findByCustomerId($id))
    {
    	// if customer doesn't exist, add it his details into the database
    	$this->model_customer_ebanx_express->update($id, $data);
    }
    else
    {
    	// if the customer data is available in the database, update his details
    	$this->model_customer_ebanx_express->insert($id, $data);
    }

    // Do the payment request
		$response = \Ebanx\Ebanx::doRequest($params);

		if ($response->status == 'SUCCESS')
		{
			$this->_log('SUCCESS | Order: ' . $order_info['order_id'] . ', Hash: ' . $response->payment->hash);

			$this->load->model('payment/ebanx_express');
			$this->model_payment_ebanx_express->setPaymentHash($order_info['order_id'], $response->payment->hash);

			$this->load->model('checkout/order');

			if($this->isOpencart2())
			{
				$this->model_checkout_order->addOrderHistory($this->session->data['order_id'], $this->config->get('ebanx_express_order_status_op_id'));
			}
			else
			{
				$this->model_checkout_order->confirm($this->session->data['order_id'], $this->config->get('ebanx_express_order_status_op_id'));
			}			

			// If payment method is boleto, redirect to boleto page
			if ($response->payment->payment_type_code == 'boleto')
			{
				echo $this->_getBaseUrl() . 'index.php?route=payment/ebanx/boleto/&hash=' . $response->payment->hash;
			}
			// Else, redirect to callback page
			elseif(isset($response->redirect_url))
			{
				echo $response->redirect_url;
			}
			else
			{
				echo $this->_getBaseUrl() . 'index.php?route=payment/ebanx_express/callback/&hash=' . $response->payment->hash;
			}
		}
		else
		{
			// Display the EBANX error message or the default one
			if (isset($response->status_message))
			{
				$this->_log('ERROR | Order: ' . $order_info['order_id'] . ', Error: ' . $response->status_message);
				echo $response->status_message;
			}
			else
			{
				echo 'Unknown error, please contact the store owner.';
			}
		}
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
		$view = array();
		$this->_setupEbanx();

		$this->language->load('payment/ebanx_express');

		$view['title'] = sprintf($this->language->get('heading_title'), $this->config->get('config_name'));

		$view['base'] = $this->config->get('config_url');
		if (isset($this->request->server['HTTPS']) && ($this->request->server['HTTPS'] == 'on'))
		{
			$view['base'] = $this->config->get('config_ssl');
		}

		// Setup translations
		$view['language'] 		 = $this->language->get('code');
		$view['direction'] 		 = $this->language->get('direction');
		$view['heading_title'] = sprintf($this->language->get('heading_title'), $this->config->get('config_name'));
		$view['text_response'] = $this->language->get('text_response');
		$view['text_success']  = $this->language->get('text_success');
		$view['text_failure']  = $this->language->get('text_failure');
		$view['text_success_wait'] = sprintf($this->language->get('text_success_wait'), $this->url->link('checkout/success'));
		$view['text_failure_wait'] = sprintf($this->language->get('text_failure_wait'), $this->url->link('checkout/checkout', '', 'SSL'));

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
						$this->model_checkout_order->addOrderHistory($this->data['order_id'], $this->config->get('ebanx_express_order_status_co_id'));
					}
					elseif ($response->payment->status == 'PE')
					{
						$this->model_checkout_order->addOrderHistory($this->data['order_id'], $this->config->get('ebanx_express_order_status_pe_id'));
					}

					$this->response->redirect($this->url->link('checkout/success'));
				}
				else
				{
					if ($response->payment->status == 'CO')
					{
						$this->model_checkout_order->update($this->session->data['order_id'], $this->config->get('ebanx_express_order_status_co_id'));
					}
					elseif ($response->payment->status == 'PE')
					{
						$this->model_checkout_order->update($this->session->data['order_id'], $this->config->get('ebanx_express_order_status_pe_id'));
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

		// Render either for OC1 or OC2
		if ($this->isOpencart2())
		{
			$this->response->setOutput($this->load->view($template, $view));
		}
		else
		{
			$this->template = $template;
			$this->data     = $view;
			$this->response->setOutput($this->render());
		}
	}
}