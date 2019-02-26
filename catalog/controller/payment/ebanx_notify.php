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
* The payment notifications controller
*/

class ControllerPaymentEbanxNotify extends Controller
{
	const VERSION = '2.4.0';
	var $integrationKey;
	var $testMode;

	/**
	 * Initialize the EBANX settings before usage
	 * @return void
	 */
	protected function _setupEbanx()
	{
		if($this->config->get('ebanx_express_merchant_key') != null)
		{
			$this->integrationKey = $this->config->get('ebanx_express_merchant_key');
			$this->testMode = ($this->config->get('ebanx_express_mode') == 'test');
		}

		else
		{
			$this->integrationKey = $this->config->get('ebanx_merchant_key');
			$this->testMode = ($this->config->get('ebanx_mode') == 'test');
		}

		\Ebanx\Config::set(array(
		    'integrationKey' => $this->integrationKey
		  , 'testMode'       => $this->testMode
		  , 'directMode'     => true
		  , 'sourceData'     => 'OpenCart/' . self::VERSION

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
	 * Notification action. It's called when a payment status is updated.
	 * @return void
	 */
	public function notify()
	{
		$view = array();
		$this->_setupEbanx();

		$hashes = $_REQUEST['hash_codes'];
		$notification = $_REQUEST['notification_type'];

		if ($hashes == null)
		{
			return;
		}

		$hashes = explode(',', $hashes);

		foreach ($hashes as $hash)
		{
			$response = \Ebanx\Ebanx::doQuery(array('hash' => $hash));

			if (isset($response->status) && $response->status == 'SUCCESS')
			{
				$payment_type_code = array(
					 'amex'
					,'aura'
					,'diners'
					,'discover'
					,'elo'
					,'hipercard'
					,'mastercard'
					,'visa'

				);

				$type = $response->payment->payment_type_code;

				if(in_array($type, $payment_type_code))
				{
					$order_status = 'ebanx_express_order_status_';
				}
				else
				{
					$order_status = 'ebanx_order_status_';
				}

				$this->load->model('checkout/order');

				// Update the order status according to the settings
				$order_id = str_replace('_', '', $response->payment->merchant_payment_code);

				$status_name = $response->payment->status;

				if($notification != 'update')
				{
					$status_name = $notification;
				}

				$status = $this->config->get($order_status . strtolower($status_name) . '_id');
				if (VERSION >=2)
				{
					$this->model_checkout_order->addOrderHistory($order_id, $status);
				}
				else
				{
					$this->model_checkout_order->update($order_id, $status);
				}

				$this->_log('NOTIFY SUCCESS | Order: ' . $order_id . ', Status: ' . $status_name);
				echo "OK: {$hash} changed to {$status_name}\n";
			}
			else
			{
				echo "NOK: {$hash}\n";
			}
		}
	}
}
