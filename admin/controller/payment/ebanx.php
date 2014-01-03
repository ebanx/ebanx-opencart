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

/**
 * EBANX settings controller
 */
class ControllerPaymentEbanx extends Controller
{
	/**
	 * Error messages
	 * @var array
	 */
	private $error = array();

	/**
	 * EBANX settings page
	 * @return void
	 */
	public function index()
	{
		$this->language->load('payment/ebanx');
		$this->document->setTitle($this->language->get('heading_title'));
		$this->load->model('setting/setting');

		// Saves the new settings
		if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate())
		{
			$this->model_setting_setting->editSetting('ebanx', $this->request->post);

			$this->session->data['success'] = $this->language->get('text_success');

			$this->redirect($this->url->link('extension/payment', 'token=' . $this->session->data['token'], 'SSL'));
		}

		$this->data['heading_title'] = $this->language->get('heading_title');

		$this->data['text_enabled'] 	= $this->language->get('text_enabled');
		$this->data['text_disabled'] 	= $this->language->get('text_disabled');
		$this->data['text_all_zones'] = $this->language->get('text_all_zones');
		$this->data['text_pay_mode']  = $this->language->get('text_pay_mode');
		$this->data['text_test_mode'] = $this->language->get('text_test_mode');

		$this->data['entry_merchant_key'] 	 = $this->language->get('entry_merchant_key');
		$this->data['entry_test'] 					 = $this->language->get('entry_test');
		$this->data['entry_order_status_ca'] = $this->language->get('entry_order_status_ca');
		$this->data['entry_order_status_co'] = $this->language->get('entry_order_status_co');
		$this->data['entry_order_status_op'] = $this->language->get('entry_order_status_op');
		$this->data['entry_order_status_pe'] = $this->language->get('entry_order_status_pe');
		$this->data['entry_geo_zone'] 			 = $this->language->get('entry_geo_zone');
		$this->data['entry_status'] 				 = $this->language->get('entry_status');
		$this->data['entry_sort_order'] 		 = $this->language->get('entry_sort_order');
		$this->data['entry_enable_installments']   = $this->language->get('entry_enable_installments');
		$this->data['entry_max_installments']      = $this->language->get('entry_max_installments');
		$this->data['entry_installments_interest'] = $this->language->get('entry_installments_interest');


		$this->data['button_save']   = $this->language->get('button_save');
		$this->data['button_cancel'] = $this->language->get('button_cancel');

 		if (isset($this->error['warning']))
 		{
			$this->data['error_warning'] = $this->error['warning'];
		}
		else
		{
			$this->data['error_warning'] = '';
		}

 		if (isset($this->error['merchant_key']))
 		{
			$this->data['error_merchant_key'] = $this->error['merchant_key'];
		}
		else
		{
			$this->data['error_merchant_key'] = '';
		}

 		if (isset($this->error['password']))
 		{
			$this->data['error_password'] = $this->error['password'];
		}
		else
		{
			$this->data['error_password'] = '';
		}

  		$this->data['breadcrumbs'] = array();

   		$this->data['breadcrumbs'][] = array(
       		'text'      => $this->language->get('text_home')
			  , 'href'      => $this->url->link('common/home', 'token=' . $this->session->data['token'], 'SSL')
      	, 'separator' => false
   		);

   		$this->data['breadcrumbs'][] = array(
       	  'text'      => $this->language->get('text_payment')
				, 'href'      => $this->url->link('extension/payment', 'token=' . $this->session->data['token'], 'SSL')
      	, 'separator' => ' :: '
   		);

   		$this->data['breadcrumbs'][] = array(
       	  'text'      => $this->language->get('heading_title')
				, 'href'      => $this->url->link('payment/ebanx', 'token=' . $this->session->data['token'], 'SSL')
      	, 'separator' => ' :: '
   		);

		$this->data['action'] = $this->url->link('payment/ebanx', 'token=' . $this->session->data['token'], 'SSL');
		$this->data['cancel'] = $this->url->link('extension/payment', 'token=' . $this->session->data['token'], 'SSL');

		// Default settings values
		if (!$this->config->has('ebanx_merchant_key'))
		{
			$this->config->set('ebanx_order_status_ca_id', 7);
			$this->config->set('ebanx_order_status_co_id', 2);
			$this->config->set('ebanx_order_status_op_id', 1);
			$this->config->set('ebanx_order_status_pe_id', 1);
			$this->config->set('ebanx_sort_order', 1);
			$this->config->set('ebanx_enable_installments', 0);
			$this->config->set('ebanx_max_installments', 6);
		}

		if (isset($this->request->post['ebanx_merchant_key']))
		{
			$this->data['ebanx_merchant_key'] = $this->request->post['ebanx_merchant_key'];
		}
		else
		{
			$this->data['ebanx_merchant_key'] = $this->config->get('ebanx_merchant_key');
		}

		$this->data['callback'] = HTTP_CATALOG . 'index.php?route=payment/ebanx/callback';

		if (isset($this->request->post['ebanx_mode']))
		{
			$this->data['ebanx_mode'] = $this->request->post['ebanx_mode'];
		}
		else
		{
			$this->data['ebanx_mode'] = $this->config->get('ebanx_mode');
		}

		if (isset($this->request->post['ebanx_order_status_ca_id']))
		{
			$this->data['ebanx_order_status_ca_id'] = $this->request->post['ebanx_order_status_ca_id'];
		}
		else
		{
			$this->data['ebanx_order_status_ca_id'] = $this->config->get('ebanx_order_status_ca_id');
		}

		if (isset($this->request->post['ebanx_order_status_co_id']))
		{
			$this->data['ebanx_order_status_co_id'] = $this->request->post['ebanx_order_status_co_id'];
		}
		else
		{
			$this->data['ebanx_order_status_co_id'] = $this->config->get('ebanx_order_status_co_id');
		}

		if (isset($this->request->post['ebanx_order_status_op_id']))
		{
			$this->data['ebanx_order_status_op_id'] = $this->request->post['ebanx_order_status_op_id'];
		}
		else
		{
			$this->data['ebanx_order_status_op_id'] = $this->config->get('ebanx_order_status_op_id');
		}

		if (isset($this->request->post['ebanx_order_status_pe_id']))
		{
			$this->data['ebanx_order_status_pe_id'] = $this->request->post['ebanx_order_status_pe_id'];
		}
		else
		{
			$this->data['ebanx_order_status_pe_id'] = $this->config->get('ebanx_order_status_pe_id');
		}

		$this->load->model('localisation/order_status');

		$this->data['order_statuses'] = $this->model_localisation_order_status->getOrderStatuses();

		if (isset($this->request->post['ebanx_geo_zone_id']))
		{
			$this->data['ebanx_geo_zone_id'] = $this->request->post['ebanx_geo_zone_id'];
		}
		else
		{
			$this->data['ebanx_geo_zone_id'] = $this->config->get('ebanx_geo_zone_id');
		}

		$this->load->model('localisation/geo_zone');

		$this->data['geo_zones'] = $this->model_localisation_geo_zone->getGeoZones();

		if (isset($this->request->post['ebanx_status']))
		{
			$this->data['ebanx_status'] = $this->request->post['ebanx_status'];
		}
		else
		{
			$this->data['ebanx_status'] = $this->config->get('ebanx_status');
		}

		if (isset($this->request->post['ebanx_enable_installments']))
		{
			$this->data['ebanx_enable_installments'] = $this->request->post['ebanx_enable_installments'];
		}
		else
		{
			$this->data['ebanx_enable_installments'] = $this->config->get('ebanx_enable_installments');
		}

		if (isset($this->request->post['ebanx_max_installments']))
		{
			$this->data['ebanx_max_installments'] = $this->request->post['ebanx_max_installments'];
		}
		else
		{
			$this->data['ebanx_max_installments'] = $this->config->get('ebanx_max_installments');
		}

		if (isset($this->request->post['ebanx_installments_interest']))
		{
			$this->data['ebanx_installments_interest'] = $this->request->post['ebanx_installments_interest'];
		}
		else
		{
			$this->data['ebanx_installments_interest'] = $this->config->get('ebanx_installments_interest');
		}


		if (isset($this->request->post['ebanx_sort_order']))
		{
			$this->data['ebanx_sort_order'] = $this->request->post['ebanx_sort_order'];
		}
		else
		{
			$this->data['ebanx_sort_order'] = $this->config->get('ebanx_sort_order');
		}

		$this->template = 'payment/ebanx.tpl';
		$this->children = array(
			'common/header',
			'common/footer'
		);

		$this->response->setOutput($this->render());
	}

	/**
	 * Validates the new settings
	 * @return boolean
	 */
	protected function validate()
	{
		if (!$this->user->hasPermission('modify', 'payment/ebanx'))
		{
			$this->error['warning'] = $this->language->get('error_permission');
		}

		if (!$this->request->post['ebanx_merchant_key'])
		{
			$this->error['merchant_key'] = $this->language->get('error_merchant_key');
		}

		return !$this->error;
	}

	/**
	 * Installs the EBANX extension
	 * @return void
	 */
	public function install()
	{
  	$this->load->model('payment/ebanx');
    $this->model_payment_ebanx->install();
	}

	/**
	 * Uninstalls the EBANX extension
	 * @return void
	 */
	public function uninstall()
	{
    $this->load->model('payment/ebanx');
    $this->model_payment_ebanx->uninstall();
  }
}