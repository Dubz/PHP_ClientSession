<?php
class ClientSession
{
	public $user_cookies = array();
	public $user_headers = array();

	public $curlopt = array();
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
	public function get_curlopt($key)
	{
		if(array_key_exists($key, $this->curlopt))
			return $this->curlopt[$key];
		else
			return false;
	}
	public function set_curlopt($key, $value)
	{
		$this->curlopt[$key] = $value;
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
	public function get_header($key)
	{
		if(key_exists($key, $this->headers))
			return $this->headers[$key];
		if(key_exists($key, $this->user_headers))
			return $this->user_headers[$key];
		return null;
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
	 * @param $method The type of request to send (GET, POST, etc.)
	 * @param $url The URL to send the request
	 * @param $data The array of data to be sent
	 * @param $follow_redirects Whether or not the client should follow location headers
	 * @param $timeout Time to wait for proxy connection
	 * @return An array of strings containing the status and content (html)
	 */
	public function request($method, $url, $data = false, $follow_redirects = false, $timeout = 10)
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
			$headers = array_merge(array('Content-Type' => 'application/x-www-form-urlencoded'), $this->user_headers, array('Cookie' => $this->generate_cookie_header()));
			#Change the array to add the key to the values and set
			$headers = array_map(function($v, $k) { return ($v ? $k.': '.$v : ''); }, $headers, array_keys($headers));
			curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
			curl_setopt($curl, CURLINFO_HEADER_OUT, true);
			foreach($this->curlopt as $opt => $val)
			{
				curl_setopt($curl, $opt, $val);
			}
			$html = curl_exec($curl);
			curl_close($curl);
			$content = explode("\r\n\r\n", $html, 3);
			if(count($content) == 2)
			{
				$this->headers = $this->parse_headers(preg_replace('/^.*\n/', '', $content[0]));
				$content = $content[1];
			}
			else
			{
				$this->headers = $this->parse_headers(preg_replace('/^.*\n/', '', implode("\r\n\r\n", array($content[0], $content[1]))));
				$content = $content[2];
			}
			if(key_exists('Set-Cookie', $this->headers))
				$this->cookies = array_merge($this->cookies, $this->parse_cookies($this->headers['Set-Cookie']));
			# Now that we are done processing, we can check for location headers and send requests (if the user chose to)
			if($follow_redirects && array_key_exists('Location', $this->headers))
			{
				return $this->request('GET', $this->headers['Location'], false, true);
			}
			return array(
				"status" => "ok",
				"headers" => $this->headers,
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
	/*
	 * @credit Dubz
	 *
	 * Fetch all the forms from given HTML or URL
	 * Tis will use the client session, so any cookie headers sent will be kept
	 * Useful for easily bypassing any CSRF tokens
	 *
	 * This function is still under beta testing.
	 * It is still prone to issues and does not fetch 100%
	 *
	 * Select tags are not fetched (input tags only)
	 * Does not handle tags that are generated on the client side (via JS, etc.)
	 * Please add any necessary data as needed after calling this function
	 *
	 * @param $data Either a URL to request, or HTML already fetched
	 * @return An array of forms containing the action, method, and input tags
	 */
	public function rip_forms($data)
	{
		# Is this HTML? Simple check (since URLs shouldn't have a < in it, or a >)
		if(stripos($data, '<') !== false)
			$html = $data;
		else
		{
			$res = $this->request('GET', $data, false, true);
			// var_export($res);
			$html = $res['content'];
		}
		$forms = array();
		// print PHP_EOL.PHP_EOL."Fetching form...".PHP_EOL.PHP_EOL.PHP_EOL;
		preg_match_all('/<form((?:\s+\w+(?:=([\"|\'])[\w\W]*?\2)?)*?)\s*>([\w\W]*?)<\/form>/i', $html, $form, PREG_SET_ORDER);
		// var_export($form);
		# Get all the forms!
		foreach($form as $i => $f)
		{
			# Where you goin?!
			// print PHP_EOL.PHP_EOL."Fetching form attributes...".PHP_EOL.PHP_EOL.PHP_EOL;
			preg_match_all('/\s+(\w+)(?:=([\"|\'])([\w\W]*?)\2)?/', $f[1], $attributes, PREG_SET_ORDER);
			// var_export($attributes);
			foreach($attributes as $i => $attrib)
			{
				$v = $attrib[3];
				switch($attrib[1])
				{
					case 'action':
						$action = html_entity_decode($v, ENT_HTML5);
					break;
					case 'method':
						$method = $v;
					break;
				}
			}
			// print PHP_EOL.PHP_EOL."Data would be sent to $action via $method method.".PHP_EOL.PHP_EOL.PHP_EOL;
			# Lets grab the input tags to submit the necessary data
			// print PHP_EOL.PHP_EOL."Fetching input tags...".PHP_EOL.PHP_EOL.PHP_EOL;
			preg_match_all('/<input((?:\s+\w+(?:=([\"|\'])[\w\W]*?\2)?)*?)\s*\/?\s*>/', $f[3], $input, PREG_SET_ORDER);
			// var_export($input);
			// continue;
			$data = array();
			foreach($input as $i => $inp)
			{
				// print PHP_EOL.PHP_EOL."Fetching input tag attributes...".PHP_EOL.PHP_EOL.PHP_EOL;
				preg_match_all('/\s+(\w+)(?:=([\"|\'])([\w\W]*?)\2)?/', $inp[1], $attributes, PREG_SET_ORDER);
				// var_export($attributes);
				$data[$i] = array();
				foreach($attributes as $attrib)
				{
					if(count($attrib) == 2)
						$data[$i][$attrib[1]] = NULL;
					if(count($attrib) == 4)
						$data[$i][$attrib[1]] = $attrib[3];
				}
			}
			$forms[] = array('action' => $action, 'method' => $method, 'input' => $data);
		}
		// var_export($forms);
		return $forms;
	}
	private function generate_cookie_header()
	{
		$cookies = array_merge($this->cookies, $this->user_cookies);
		return implode(' ', array_map(function($k, $v) {
			return $k.'='.$v.';';
		}, array_keys($cookies), $cookies));
	}
}