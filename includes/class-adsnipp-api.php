<?php

/**
 * Description of addsnipp-api
 * Class for API
 */

class Addsnipp_Api {

	private $appid;
	private $key;
	private $secret;
	private $path;
	public $registered;
	private $api_host;
	public $error;
	
	function __construct($appid = '', $key = '', $secret = '', $path = '') {
		$this->appid = $appid;
		$this->key = $key;
		$this->secret = $secret;
		$this->path = $path;
		$this->api_host = parse_url("https://www.sweetcaptcha.com", PHP_URL_HOST);
		$this->registered = (!empty($appid) && !empty($key) && !empty($secret) );
		$this->error = '';
	}

	private function api($method, $params) {
		$basic = array(
			'method' => $method,
			'appid' => $this->appid,
			'key' => $this->key,
			'path' => $this->path,
			'user_ip' => $_SERVER['REMOTE_ADDR'],
			'user_agent' => $_SERVER['HTTP_USER_AGENT'],
			'platform' => 'adsnipp'
		);

		if (is_admin()) {
			return $this->call(array_merge(isset($params[0]) ? $params[0] : $params, $basic));
		} else {
			if ($this->registered) {
				return $this->call(array_merge(isset($params[0]) ? $params[0] : $params, $basic));
			} else {
				//return '<span style="color: red;">'.__('Your sweetCaptcha plugin is not setup yet', 'sweetcaptcha').'</span>';
				return '';
			}
		}
	}

	private function call($params) {
		$param_data = "";
		foreach ($params as $param_name => $param_value) {
			$param_data .= urlencode($param_name) . '=' . urlencode($param_value) . '&';
		}

		$fs = fsockopen($this->api_host, 80, $errno, $errstr, 10 /* The connection timeout, in seconds */);
		if (!$fs) {
			if (isset($params['check'])) {
				return '<div class="error sweetcaptcha" style="text-align: left; ">' . $this->call_error($errstr, $errno) . '</div>';
			}
			return ''; //$this->call_error($errstr, $errno);
		} else
		if (isset($params['check'])) {
			return '';
		}

		$req = "POST /api.php HTTP/1.0\r\n";
		$req .= "Host: " . $this->api_host . "\r\n";
		$req .= "Content-Type: application/x-www-form-urlencoded\r\n";
		$req .= "Referer: " . $_SERVER['HTTP_HOST'] . "\r\n";
		$req .= "Content-Length: " . strlen($param_data) . "\r\n\r\n";
		$req .= $param_data;

		$response = '';
		fwrite($fs, $req);
		while (!feof($fs)) {
			$response .= fgets($fs, 1160);
		}
		fclose($fs);

		$response_arr = explode("\r\n\r\n", $response, 2);
		return $response_arr[1];
	}

	private function call_error($errstr, $errno) {
		return "<p style='color:red;'>" . SWEETCAPTCHA_CONNECT_ERROR . "</p><a style='text-decoration:underline;' href='javascript:void(0)' onclick='javascript:jQuery(\"#sweetcaptcha-error-details\").toggle();'>Details</a><span id='sweetcaptcha-error-details' style='display: none;'><br>$errstr ($errno)</span>";
	}

	public function __call($method, $params) {
		return $this->api($method, $params);
	}

	public function check_access() {
		echo $this->api('get_html', array('check' => 1));
	}

}