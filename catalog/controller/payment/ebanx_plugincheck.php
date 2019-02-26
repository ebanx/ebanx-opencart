<?php

class ControllerPaymentEbanxPlugincheck extends Controller {

	public function index() {
		$info_list = array(
			'mysql'          => $this->getSQLVersion(),
			'php'            => phpversion(),
			'Opencart'       => VERSION,
			'ebanx-opencart' => ControllerPaymentEbanx::VERSION,
			'configs'        => $this->getConfigs(),
			'plugins'        => $this->getPlugins(),
		);
		echo json_encode($info_list);
	}

	private function getSQLVersion()	{
		$query = $this->db->query('SELECT version() AS version');
		return $query->row['version'];
	}

	private function getConfigs()	{
		$configs = array();
		if($this->isEbanxExpressEnabled()){
			$configs = $this->getEbanxExpressConfigs($configs);
		}
		if($this->isEbanxEnabled()){
			$configs = $this->getEbanxConfigs($configs);
		}

		return $configs;
	}

	private function getPlugins()	{
		$plugin_list = [];
		$query = $this->db->query('SELECT code from oc_extension');
		foreach ($query->rows as $plugin){
			array_push($plugin_list, $plugin['code']);
		}
		return $plugin_list;
	}

	private function isEbanxExpressEnabled() {
		return !is_null($this->getEbanxConfig('ebanx_express_mode'));
	}

	private function isEbanxEnabled() {
		return !is_null($this->getEbanxConfig('ebanx_mode'));
	}

	private function getEbanxConfig($config) {
		return $this->config->get('' . $config . '');
	}

	private function getEbanxExpressConfigs(array $configs) {
		$configs['ebanx_express'] = array(
			'ebanx_express_status' => $this->getEbanxConfig('ebanx_express_status') === 1,
			'ebanx_express_mode' => $this->getEbanxConfig('ebanx_express_mode'),
			'ebanx_express_enable_installments' => $this->getEbanxConfig('ebanx_express_enable_installments') === 1,
			'ebanx_express_max_installments' => $this->getEbanxConfig('ebanx_express_max_installments'),
			'entry_installments_interest' => $this->getEbanxConfig('ebanx_express_installments_interest'),
		);
		return $configs;
	}

	private function getEbanxConfigs(array $configs) {
		$configs['ebanx'] = array(
			'ebanx_status' => $this->getEbanxConfig('ebanx_status') === 1,
			'ebanx_mode' => $this->getEbanxConfig('ebanx_mode'),
			'geo_zone_id' => $this->getEbanxConfig('ebanx_geo_zone_id'),
		);
		return $configs;
	}
}
