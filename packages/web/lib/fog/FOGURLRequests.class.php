<?php
class FOGURLRequests extends FOGBase {
	private $handle,$contextOptions;
	public function __construct($method = false,$data = null,$sendAsJSON = false,$auth = false) {
		parent::__construct();
		$ProxyUsed = false;
		if ($this->DB && $this->FOGCore->getSetting('FOG_PROXY_IP')) {
			foreach($this->getClass('StorageNodeManager')->find() AS $StorageNode) $IPs[] = $this->FOGCore->resolveHostname($StorageNode->get('ip'));
			$IPs = array_filter(array_unique($IPs));
			if (!preg_match('#('.implode('|',$IPs).')#i',$URL)) $ProxyUsed = true;
			$username = $this->FOGCore->getSetting('FOG_PROXY_USERNAME');
			$password = $this->FOGCore->getSetting('FOG_PROXY_PASSWORD');
		}
		$this->handle = curl_multi_init();
		$this->contextOptions = array(
			CURLOPT_HTTPGET => true,
			CURLOPT_FOLLOWLOCATION => true,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_SSL_VERIFYPEER => false,
			CURLOPT_SSL_VERIFYHOST => false,
			CURLOPT_CONNECTTIMEOUT_MS => 10000,
			CURLOPT_TIMEOUT_MS => 10000,
			CURLOPT_ENCODING => '',
			CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows; U; Windows NT 5.1; en-US; rv:1.9.6.12) Gecko/20110319 Firefox/4.0.1 ( .NET CLR 3.5.30729; .NET4.0E)',
			CURLOPT_MAXREDIRS => 20,
			CURLOPT_HEADER => false,
		);
		if ($ProxyUsed) {
			$this->contextOptions[CURLOPT_PROXYAUTH] = CURLAUTH_BASIC;
			$this->contextOptions[CURLOPT_PROXYPORT] = $this->FOGCore->getSetting('FOG_PROXY_PORT');
			$this->contextOptions[CURLOPT_PROXY] = $this->FOGCore->getSetting('FOG_PROXY_IP');
			if ($username) $this->contextOptions[CURLOPT_PROXYUSERPWD] = $username.':'.$password;
		}
	}
	public function process($urls, $method = false,$data = null,$sendAsJSON = false,$auth = false,$callback = false,$file = false) {
		if (!is_array($urls)) $urls = array($urls);
		foreach ($urls AS &$url) {
			if ($method && $method == 'GET' && $data !== null) $url .= '?'.http_build_query($data);
			$ch = curl_init($url);
			$this->contextOptions[CURLOPT_URL] = $url;
			if ($auth) $this->contextOptions[CURLOPT_USERPWD] = $auth;
			if ($file) $this->contextOptions[CURLOPT_FILE] = $file;
			if ($method && $method == 'POST' && $data !== null) {
				if ($sendAsJSON) {
					$data = json_encode($data);
					$this->contextOptions[CURLOPT_HTTPHEADER] = array(
						'Content-Type: application/json',
						'Content-Length: '.strlen($data),
					);
				}
				$this->contextOptions[CURLOPT_POSTFIELDS] = $data;
			}
			$this->contextOptions[CURLOPT_CUSTOMREQUEST] = $method;
			curl_setopt_array($ch,$this->contextOptions);
			$curl[$url] = $ch;
			curl_multi_add_handle($this->handle,$ch);
		}
		$active = null;
		do {
			$mrc = curl_multi_exec($this->handle, $active);
		} while ($mrc == CURLM_CALL_MULTI_PERFORM);
		while ($active && $mrc == CURLM_OK) {
			if (curl_multi_select($this->handle) == -1) usleep(1);
			do {
				$mrc = curl_multi_exec($this->handle,$active);
				$httpCode = curl_multi_info_read($this->handle);
				if ($mrc > 0) throw new Exception('cURL Error: '.curl_multi_strerror($mrc));
				if ($httpCode[0] >= 400) {
					curl_multi_close($this->handle);
					throw new Exception('cURL HTTP Error Code: '.$httpCode[0]);
				}
			} while ($mrc == CURLM_CALL_MULTI_PERFORM);
		}
		foreach($curl AS $url => $ch) {
			if ($callback) $callback($ch);
			$response[] = curl_multi_getcontent($ch);
			curl_multi_remove_handle($this->handle,$ch);
			if ($file) fclose($file);
		}
		return $response;
	}
	public function __destruct()
	{
		curl_multi_close($this->handle);
	}
}
