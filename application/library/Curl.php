<?php
/**
 * curl工具
 * @author fuqiang
 *
 */ 
class Curl
{
	private static $_ch;
	private static $_header;
	private static $_body;
	
	private static $_cookie = array();
	private static $_options = array();
	private static $_url = array ();
	private static $_referer = array ();
	
	/**
	 * 调用外部url
	 * @param $queryUrl
	 * @param $param 参数
	 * @param string $method
	 * @return bool|mixed
	*/
	public static function callWebServer( $queryUrl, $param='', $method='get', $timeout = 30, $isJson = false, $isUrlcode=true ) {
		if (empty($queryUrl)) {
			return false;
		}
		
		$method = strtolower($method);
		$param = empty($param) ? array() : $param;
		
		//初始化curl
		self::_init($timeout);
		
		if ($method == 'get') {
			$result = self::_httpGet($queryUrl, $param);
		} elseif ( $method == 'post') {
			$result = self::_httpPost($queryUrl, $param, $isJson, $isUrlcode);
		}
		
		if( !empty($result) ){
			return $result;
		}
		
		return true;
	}
	
	public static function setOption($optArray=array()) {
	    foreach($optArray as $opt) {
	        curl_setopt(self::$_ch, $opt['key'], $opt['value']);
	    }
	}
	
	/**
	 * 初始化curl
	 */
	private static function _init($timeout = 30) {
		self::$_ch = curl_init();
	
		curl_setopt(self::$_ch, CURLOPT_HEADER, true);
		curl_setopt(self::$_ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt(self::$_ch, CURLOPT_TIMEOUT, $timeout );
	}
	
	private static function _close() {
		if (is_resource(self::$_ch)) {
			curl_close(self::$_ch);
		}
	
		return true;
	}
	
	private static function _httpGet( $url, $query = array() ) {
	
		if (!empty($query)) {
			$url .= (strpos($url, '?') === false) ? '?' : '&';
			$url .= is_array($query) ? http_build_query($query) : $query;
		}
	
		curl_setopt(self::$_ch, CURLOPT_URL, $url);
		curl_setopt(self::$_ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt(self::$_ch, CURLOPT_HEADER, 0);
		curl_setopt(self::$_ch, CURLOPT_SSL_VERIFYPEER, FALSE);
		curl_setopt(self::$_ch, CURLOPT_SSL_VERIFYHOST, FALSE);
		curl_setopt(self::$_ch, CURLOPT_SSLVERSION, 1);
	
		$result = self::_execute();
		self::_close();
		return $result;
	}
	
	private static function _httpPost($url, $query=array(), $isJson = false, $isUrlcode=true) {
		if (is_array($query)) {
			foreach ($query as $key => $val) {
				if($isUrlcode) {
					$encode_key = urlencode($key);
				} else {
					$encode_key = $key;
				}
				if ($encode_key != $key) {
					unset($query[$key]);
				}
				if ($isUrlcode) {
					$query[$encode_key] = urlencode($val);
				} else {
					$query[$encode_key] = $val;
				}
	
			}
			
			if ($isJson) {
			    $query = json_encode($query, JSON_UNESCAPED_UNICODE);
			} else {
			    $query = http_build_query($query, 'pre_', '&');
			}
		}
		
		$headers = array();
		
		if ($isJson) {
		    $headers[] = 'Content-type: application/json; charset=utf-8';
		    $headers[] = 'Content-Length: ' . strlen($query);
		}
		
		
		curl_setopt(self::$_ch, CURLOPT_URL, $url);
		curl_setopt(self::$_ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt(self::$_ch, CURLOPT_HTTPHEADER, $headers);
		curl_setopt(self::$_ch, CURLOPT_HEADER, 0);
		curl_setopt(self::$_ch, CURLOPT_POST, true );
		curl_setopt(self::$_ch, CURLOPT_POSTFIELDS, $query);
		curl_setopt(self::$_ch, CURLOPT_SSL_VERIFYPEER, FALSE);
		curl_setopt(self::$_ch, CURLOPT_SSL_VERIFYHOST, FALSE);
		curl_setopt(self::$_ch, CURLOPT_SSLVERSION, 1);
	
	
		$result = self::_execute();
		self::_close();
		return $result;
	}
	
	private static function _put($url, $query = array()) {
		curl_setopt(self::$_ch, CURLOPT_CUSTOMREQUEST, 'PUT');
	
		return self::_httpPost($url, $query);
	}
	
	private static function _delete($url, $query = array()) {
		curl_setopt(self::$_ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
	
		return self::_httpPost($url, $query);
	}
	
	private static function _head($url, $query = array()) {
		curl_setopt(self::$_ch, CURLOPT_CUSTOMREQUEST, 'HEAD');
	
		return self::_httpPost($url, $query);
	}
	
	private static function _execute() {
		$response = curl_exec(self::$_ch);
		$errno = curl_errno(self::$_ch);
	   
		if ($errno > 0) {
		    $info = curl_getinfo(self::$_ch);
		    Fn::writeLog(json_encode( $info ));
			throw new \Exception(curl_error(self::$_ch), $errno);
		}
		return  $response;
	}
}

?>