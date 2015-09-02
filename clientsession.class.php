<?php
class ClientSession
{
	public $user_cookies = array();
	public $user_headers = array();

	public $proxy_ip   = false;
	public $proxy_port = false;
	public $proxy_pass = false;
	public $proxy_type = false; //CURLPROXY_HTTP;

	private $cookies = array();
	private $headers = array();

	public function __construct()
	{
	}
	public function set_proxy($ip, $port, $pass = false, $type = CURLPROXY_HTTP)
	{
		$this->proxy_ip   = $ip;
		$this->proxy_port = $port;
		$this->proxy_pass = $pass;
		$this->proxy_type = $type;
	}
	public function get_cookie($key)
	{
		if(key_exists($key, $this->cookies))
			return $this->cookies[$key];
		if(key_exists($key, $this->user_cookies))
			return $this->user_cookies[$key];
		return null;
	}
	public function set_cookie($key, $value)
	{
		return $this->user_cookies[$key] = $value;
	}
	public function remove_cookie($key)
	{
		if(key_exists($key, $this->user_cookies))
		{
			unset($this->user_cookies[$key]);
			return true;
		}
		if(key_exists($key, $this->cookies))
		{
			unset($this->cookies[$key]);
			return true;
		}
		return false;
	}
	public function set_header($key, $value)
	{
		return $this->user_headers[$key] = $value;
	}
	public function set_headers($headers)
	{
		$this->user_headers = array_merge($this->user_headers, $headers);
	}
	public function remove_header($key)
	{
		if(!key_exists($key, $this->user_headers))
			return false;
		unset($this->user_headers[$key]);
		return true;
	}
	public function parse_headers($headers)
	{
		$headers = trim($headers);
		preg_match_all('/([^:]+):([^\r\n]+)/', $headers, $matches);
		//Remove any unnecessary whitespace
		foreach($matches as $k => $v)
		{
			$matches[$k] = array_map('trim', $v);
		}
		$result = array();
		for($i = 0; $i < count($matches[0]); $i++)
		{
			if(key_exists($matches[1][$i], $result))
			{
				if(is_array($result[$matches[1][$i]]))
					$result[$matches[1][$i]][] = $matches[2][$i];
				else
					$result[$matches[1][$i]] = array($result[$matches[1][$i]], $matches[2][$i]);
			}
			else
				$result[$matches[1][$i]] = $matches[2][$i];
		}
		return $result;
	}
	public function parse_cookies($cookies)
	{
		$ret = array();
		foreach((array)$cookies as $cookie)
		{
			$res = $this->parse_cookie($cookie);
			$ret[$res['key']] = $res['value'];
		}
		return $ret;
	}
	public function parse_cookie($cookie_str)
	{
		preg_match('/([^;,\s=]+)=([^;,\s]+);?/', $cookie_str, $cookie);
		array_map('trim', $cookie);
		return array('key' => $cookie[1], 'value' => $cookie[2]);
	}
	/*
	 * @credit jmj001
	 * @credit Dubz
	 *
	 * Posts data to a website using the curl method
	 *
	 * @param $url The URL to post to
	 * @param $data The array of data to be posted
	 * @param $headers Additional headers to be sent
	 * @param $proxy The proxy address to be used
	 * @param $proxyport Port to connect to proxy
 	 * @param $proxywd Password to access proxy
	 * @param $proxtype Type of proxy connection
	 * @param $timeout Time to wait for proxy connection
	 * @return An array of strings containing the status and content (html)
	 */
	public function request($method, $url, $data = false, $timeout = 10)
	{
		$method = strtoupper($method);
		$curl = curl_init();
		if($curl)
		{
			curl_setopt($curl, CURLOPT_URL, $url);
			if($this->proxy_ip)
			{
				curl_setopt($curl, CURLOPT_PROXY, $this->proxy_ip);
				curl_setopt($curl, CURLOPT_PROXYPORT, $this->proxy_port);
				if($this->proxy_pass)
					curl_setopt($curl, CURLOPT_PROXYUSERPWD, $this->proxy_pass);
				curl_setopt($curl, CURLOPT_PROXYTYPE, $this->proxy_type);
			}
			curl_setopt($curl, CURLOPT_TIMEOUT, $timeout);
			curl_setopt($curl, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 6.3; WOW64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/35.0.1916.153 Safari/537.36');
			curl_setopt($curl, CURLOPT_HEADER, true);
			curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
			curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
			switch($method)
			{
				/* This is default normally */
				case 'GET':
					curl_setopt($curl, CURLOPT_HTTPGET, true);
					if($data)
						curl_setopt($curl, CURLOPT_URL, $url.'?'.http_build_query($data));
				break;
				case 'POST':
					curl_setopt($curl, CURLOPT_POST, true);
					curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($data));
				break;
				default:
					curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $method);
			}
			#Lets merge the headers with some defaults
			$headers = array_merge(array('Content-Type' => 'application/x-www-form-urlencoded'), $this->headers, $this->user_headers, array('Cookie' => $this->generate_cookie_header()));
			#Change the array to add the key to the values and set
			$headers = array_map(function($v, $k) { return ($v ? $k.': '.$v : ''); }, $headers, array_keys($headers));
			curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
			curl_setopt($curl, CURLINFO_HEADER_OUT, true);
			$html = curl_exec($curl);
			curl_close($curl);
			$content = explode("\r\n\r\n", $html, 3);
			if(count($content) == 2)
			{
				$headers = $this->parse_headers($content[0]);
				$content = $content[1];
			}
			else
			{
				$headers = $this->parse_headers(implode("\r\n\r\n", array($content[0], $content[1])));
				$content = $content[2];
			}
			if(key_exists('Set-Cookie', $headers))
				$this->cookies = array_merge($this->cookies, $this->parse_cookies($headers['Set-Cookie']));
			return array(
				"status" => "ok",
				"headers" => $headers,
				"content" => $content,
			);
		}
		else
		{
			return array(
				"status" => "error",
			);
		}
	}
	private function generate_cookie_header()
	{
		$cookies = array_merge($this->cookies, $this->user_cookies);
		return implode(' ', array_map(function($k, $v) {
			return $k.'='.$v.';';
		}, array_keys($cookies), $cookies));
	}
}