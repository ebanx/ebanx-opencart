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
 * EBANX settings controller
 */
class ControllerPaymentEbanx extends Controller
{
	const VERSION = '2.4.0';
	/**
	 * Error messages
	 * @var array
	 */
	private $error = array();

	/**
	 * Initialize the EBANX settings before usage
	 * @return void
	 */
	protected function _setupEbanx()
	{
		\Ebanx\Config::set(array(
		    'integrationKey' => $this->config->get('ebanx_merchant_key')
		  , 'testMode'       => ($this->config->get('ebanx_mode') == 'test')
		  , 'directMode'		 => false
		  , 'sourceData'     => 'OpenCart/' . self::VERSION

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
	 * EBANX settings page
	 * @return void
	 */
	public function index()
	{
		$view = array();

		$this->language->load('payment/ebanx');
		$this->document->setTitle($this->language->get('heading_title'));
		$this->load->model('setting/setting');
		$this->load->model('payment/ebanx');

		// Saves the new settings
		if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validate())
		{
			$this->model_payment_ebanx->updateSettings($this->request->post);

			$this->session->data['success'] = $this->language->get('text_success');

			if($this->isOpencart2())
			{
				$this->response->redirect($this->url->link('extension/payment', 'token=' . $this->session->data['token'], 'SSL'));
			}
			else
			{
				$this->redirect($this->url->link('extension/payment', 'token=' . $this->session->data['token'], 'SSL'));
			}
		}

		$view['heading_title'] = $this->language->get('heading_title');

		$view['text_enabled'] 	= $this->language->get('text_enabled');
		$view['text_disabled'] 	= $this->language->get('text_disabled');
		$view['text_all_zones'] = $this->language->get('text_all_zones');
		$view['text_pay_mode']  = $this->language->get('text_pay_mode');
		$view['text_test_mode'] = $this->language->get('text_test_mode');

		$view['entry_merchant_key'] 	 = $this->language->get('entry_merchant_key');
		$view['entry_test'] 					 = $this->language->get('entry_test');
		$view['entry_order_status_ca'] = $this->language->get('entry_order_status_ca');
		$view['entry_order_status_co'] = $this->language->get('entry_order_status_co');
		$view['entry_order_status_refund'] = $this->language->get('entry_order_status_refund');
		$view['entry_order_status_chargeback'] = $this->language->get('entry_order_status_chargeback');
		$view['entry_order_status_op'] = $this->language->get('entry_order_status_op');
		$view['entry_order_status_pe'] = $this->language->get('entry_order_status_pe');
		$view['entry_geo_zone'] 			 = $this->language->get('entry_geo_zone');
		$view['entry_status'] 				 = $this->language->get('entry_status');
		$view['entry_sort_order'] 		 = $this->language->get('entry_sort_order');
		$view['entry_update_methods'] 		   = $this->language->get('entry_update_methods');

		$view['button_save']   = $this->language->get('button_save');
		$view['button_cancel'] = $this->language->get('button_cancel');

		/*This Block returns the warning if any*/
 		if (isset($this->error['warning']))
 		{
			$view['error_warning'] = $this->error['warning'];
		}
		else
		{
			$view['error_warning'] = '';
		}

		/*This Block returns the error code if any*/
 		if (isset($this->error['merchant_key']))
 		{
			$view['error_merchant_key'] = $this->error['merchant_key'];
		}
		else
		{
			$view['error_merchant_key'] = '';
		}

 		if (isset($this->error['password']))
 		{
			$view['error_password'] = $this->error['password'];
		}
		else
		{
			$view['error_password'] = '';
		}

		/* Making of Breadcrumbs to be displayed on site*/
  		$view['breadcrumbs'] = array();

   		$view['breadcrumbs'][] = array(
       		'text'      => $this->language->get('text_home')
			  , 'href'      => $this->url->link('common/home', 'token=' . $this->session->data['token'], 'SSL')
      	, 'separator' => false
   		);

   		$view['breadcrumbs'][] = array(
       	  'text'      => $this->language->get('text_payment')
				, 'href'      => $this->url->link('extension/payment', 'token=' . $this->session->data['token'], 'SSL')
      	, 'separator' => ' :: '
   		);

   		$view['breadcrumbs'][] = array(
       	  'text'      => $this->language->get('heading_title')
				, 'href'      => $this->url->link('payment/ebanx', 'token=' . $this->session->data['token'], 'SSL')
      	, 'separator' => ' :: '
   		);
   		/* End Breadcrumb Block*/

   		// URL to be directed when the save button is pressed
		$view['action'] = $this->url->link('payment/ebanx', 'token=' . $this->session->data['token'], 'SSL');
		// URL to be redirected when cancel button is pressed
		$view['cancel'] = $this->url->link('extension/payment', 'token=' . $this->session->data['token'], 'SSL');

		// Default settings values
		if (!$this->config->has('ebanx_merchant_key'))
		{
			$this->config->set('ebanx_status', 1);
			$this->config->set('ebanx_order_status_ca_id', 7);
			$this->config->set('ebanx_order_status_co_id', 2);
			$this->config->set('ebanx_order_status_op_id', 1);
			$this->config->set('ebanx_order_status_pe_id', 1);
			$this->config->set('ebanx_order_status_refund_id', 11);
			$this->config->set('ebanx_order_status_chargeback_id', 13);
			$this->config->set('ebanx_sort_order', 1);
		}

		/* This block checks, if the EBANX Integration Key text field is set, it parses it to view
		otherwise get the default EBANX text field from the database and parse it*/
		if (isset($this->request->post['ebanx_merchant_key']))
		{
			$view['ebanx_merchant_key'] = $this->request->post['ebanx_merchant_key'];
		}
		else
		{
			$view['ebanx_merchant_key'] = $this->config->get('ebanx_merchant_key');
		}


		$view['callback'] = HTTP_CATALOG . 'index.php?route=payment/ebanx/callback';

		if (isset($this->request->post['ebanx_mode']))
		{
			$view['ebanx_mode'] = $this->request->post['ebanx_mode'];
		}
		else
		{
			$view['ebanx_mode'] = $this->config->get('ebanx_mode');
		}

		if (isset($this->request->post['ebanx_order_status_ca_id']))
		{
			$view['ebanx_order_status_ca_id'] = $this->request->post['ebanx_order_status_ca_id'];
		}
		else
		{
			$view['ebanx_order_status_ca_id'] = $this->config->get('ebanx_order_status_ca_id');
		}

		if (isset($this->request->post['ebanx_order_status_co_id']))
		{
			$view['ebanx_order_status_co_id'] = $this->request->post['ebanx_order_status_co_id'];
		}
		else
		{
			$view['ebanx_order_status_co_id'] = $this->config->get('ebanx_order_status_co_id');
		}

		if (isset($this->request->post['ebanx_order_status_op_id']))
		{
			$view['ebanx_order_status_op_id'] = $this->request->post['ebanx_order_status_op_id'];
		}
		else
		{
			$view['ebanx_order_status_op_id'] = $this->config->get('ebanx_order_status_op_id');
		}

		if (isset($this->request->post['ebanx_order_status_pe_id']))
		{
			$view['ebanx_order_status_pe_id'] = $this->request->post['ebanx_order_status_pe_id'];
		}
		else
		{
			$view['ebanx_order_status_pe_id'] = $this->config->get('ebanx_order_status_pe_id');
		}

		if (isset($this->request->post['ebanx_order_status_refund_id']))
		{
			$view['ebanx_order_status_refund_id'] = $this->request->post['ebanx_order_status_refund_id'];
		}
		else
		{
			$view['ebanx_order_status_refund_id'] = $this->config->get('ebanx_order_status_refund_id');
		}

		if (isset($this->request->post['ebanx_order_status_chargeback_id']))
		{
			$view['ebanx_order_status_chargeback_id'] = $this->request->post['ebanx_order_status_chargeback_id'];
		}
		else
		{
			$view['ebanx_order_status_chargeback_id'] = $this->config->get('ebanx_order_status_chargeback_id');
		}

		$this->load->model('localisation/order_status');

		$view['order_statuses'] = $this->model_localisation_order_status->getOrderStatuses();

		if (isset($this->request->post['ebanx_geo_zone_id']))
		{
			$view['ebanx_geo_zone_id'] = $this->request->post['ebanx_geo_zone_id'];
		}
		else
		{
			$view['ebanx_geo_zone_id'] = $this->config->get('ebanx_geo_zone_id');
		}

		$this->load->model('localisation/geo_zone');
		$view['geo_zones'] = $this->model_localisation_geo_zone->getGeoZones();

		if (isset($this->request->post['ebanx_status']))
		{
			$view['ebanx_status'] = $this->request->post['ebanx_status'];
		}
		else
		{
			$view['ebanx_status'] = $this->config->get('ebanx_status');
		}

		if (isset($this->request->post['ebanx_sort_order']))
		{
			$view['ebanx_sort_order'] = $this->request->post['ebanx_sort_order'];
		}
		else
		{
			$view['ebanx_sort_order'] = $this->config->get('ebanx_sort_order');
		}

		// Payment update URL
		$view['ebanx_update_payments'] = HTTPS_SERVER . 'index.php?route=payment/ebanx/updatePaymentMethods&token=' . $_SESSION['token'];

		// Render either for OC1 or OC2
		if ($this->isOpencart2())
		{
			$view['header'] = $this->load->controller('common/header');
			$view['footer'] = $this->load->controller('common/footer');
		    $view['column_left'] = $this->load->controller('common/column_left');
		    $view['view_log'] = $this->url->link('payment/ebanx/viewLog');
		    $view['clear_log'] = $this->url->link('payment/ebanx/clearLog');
			$this->response->setOutput($this->load->view('payment/ebanx2.tpl', $view));
		}
		else
		{
			$this->template = 'payment/ebanx.tpl';
			$this->children = array('common/header','common/footer');
			$this->data     = $view;
			$this->response->setOutput($this->render());
		}
	}

	/**
	 * Validates the new settings
	 * @return boolean
	 */

	/* Function that validates the data when Save Button is pressed */
	protected function validate()
	{
		/* Block to check the user permission to manipulate the module*/
		if (!$this->user->hasPermission('modify', 'payment/ebanx'))
		{
			$this->error['warning'] = $this->language->get('error_permission');
		}

		/* Block to check if the ebanx_merchant_key is properly set to save into database, otherwise the error is returned*/
		if (!$this->request->post['ebanx_merchant_key'])
		{
			$this->error['merchant_key'] = $this->language->get('error_merchant_key');
		}

		return !$this->error;
	}

  /**
   * Shows the EBANX log file
   * @return void
   */
  public function viewLog()
  {
  	$logFile = DIR_SYSTEM . 'logs/ebanx.log';

  	if (file_exists($logFile))
  	{
  		$log = file_get_contents($logFile);
  		echo '<pre>' . $log . '</pre>';
  	}
  	else
  	{
  		echo 'The log is empty.';
  	}
  }

  /**
   * Removes the EBANX log file
   * @return void
   */
  public function clearLog()
  {
  	$logFile = DIR_SYSTEM . 'logs/ebanx.log';

  	if (file_exists($logFile))
  	{
  		unlink($logFile);
  	}

  	echo 'The log was cleared.';
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
