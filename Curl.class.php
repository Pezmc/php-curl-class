<?php

class Curl {

    const USER_AGENT = 'PHP-Curl-Class/1.1 (+https://github.com/pezmc/php-curl-class)';

    public $curl;

    public $error = FALSE;
    public $error_code = 0;
    public $error_message = NULL;

    public $curl_error = FALSE;
    public $curl_error_code = 0;
    public $curl_error_message = NULL;

    public $http_error = FALSE;
    public $http_status_code = 0;
    public $http_error_message = NULL;

    public $request_headers = NULL;
    public $response_headers = NULL;
    public $response = NULL;
    
    private $_cookies = array();
    private $_headers = array();

    /**
     * @throws ErrorException
     */
    function __construct() {
        if (!extension_loaded('curl')) {
            throw new ErrorException('cURL library is not loaded');
        }

        $this->curl = curl_init();
        $this->setUserAgent(self::USER_AGENT);
        $this->setopt(CURLINFO_HEADER_OUT, TRUE);
        $this->setopt(CURLOPT_HEADER, TRUE);
        $this->setopt(CURLOPT_RETURNTRANSFER, TRUE);
    }

    /**
     * @param $url URL to load
     * @param $params Array of keys to set in the query string
     * @return boolean Returns true on success, false on failure @see $this->error_message;
     */
    function get($url, $params=null) {
        $this->setopt(CURLOPT_URL, $this->_buildURL($url, $params));
        $this->setopt(CURLOPT_HTTPGET, TRUE);
        return $this->_exec();
    }

    /**
     * @param $url URL to load
     * @param $data Data to POST to the URL
     * @param $params Array of keys to set in the query string
     * @return boolean Returns true on success, false on failure @see $this->error_message;
     */
    function post($url, $data=array(), $params=array()) {
        $this->setopt(CURLOPT_URL, $this->_buildURL($url, $params));
        $this->setopt(CURLOPT_POST, TRUE);
        $this->setopt(CURLOPT_POSTFIELDS, $this->_postfields($data));
        return $this->_exec();
    }

    /**
     * @param $url URL to load
     * @param $params Array of keys to set in the query string
     * @return boolean Returns true on success, false on failure @see $this->error_message;
     */
    function put($url, $params=array()) {
        $this->setopt(CURLOPT_URL, $this->_buildURL($url, $params));
        $this->setopt(CURLOPT_CUSTOMREQUEST, 'PUT');
        return $this->_exec();
    }

    /**
     * @param $url URL to load
     * @param $data Data to PATCH to the URL
     * @param $params Array of keys to set in the query string
     * @return boolean Returns true on success, false on failure @see $this->error_message;
     */
    function patch($url, $data=array(), $params=array()) {
        $this->setopt(CURLOPT_URL, $this->_buildURL($url, $params));
        $this->setopt(CURLOPT_CUSTOMREQUEST, 'PATCH');
        $this->setopt(CURLOPT_POSTFIELDS, $data);
        return $this->_exec();
    }

    /**
     * @param $url URL to load
     * @param $params Array of keys to set in the query string
     * @return boolean Returns true on success, false on failure @see $this->error_message;
     */    
    function delete($url, $params=array()) {
        $this->setopt(CURLOPT_URL, $this->_buildURL($url, $params));
        $this->setopt(CURLOPT_CUSTOMREQUEST, 'DELETE');
        return $this->_exec();
    }

    /**
     * Use CURLS basic http authentication
     */
    function setBasicAuthentication($username, $password) {
        $this->setopt(CURLOPT_HTTPAUTH, CURLAUTH_BASIC);
        $this->setopt(CURLOPT_USERPWD, $username . ':' . $password);
    }

    /**
     * Set a custom curl HTTP header
     */
    function setHeader($key, $value) {
        $this->_headers[$key] = $key . ': ' . $value;
        $this->setopt(CURLOPT_HTTPHEADER, array_values($this->_headers));
    }

    /**
     * @param string $user_agent Set a custom user agent, e.g. 'Mozilla/4.0 (compatible; MSIE 7.0; Windows NT 5.1'
     */
    function setUserAgent($user_agent) {
        $this->setopt(CURLOPT_USERAGENT, $user_agent);
    }
    
	/**
	 * @param $referrer Set a custom referer e.g. http://google.com
	 */
    function setReferrer($referrer) {
        $this->setopt(CURLOPT_REFERER, $referrer);
    }

    /**
     * Add a cookie to the curl request
     */
    function setCookie($key, $value) {
        $this->_cookies[$key] = $value;
        $this->setopt(CURLOPT_COOKIE, http_build_query($this->_cookies, '', '; '));
    }

    /**
     * @param $option Option to set
     * @param $value Value to set
     */
    function setOpt($option, $value) {
        return curl_setopt($this->curl, $option, $value);
    }

    /**
     * @param bool $on Enable verbosity
     * Output curl status to STDERR
     */
    function verbose($on=TRUE) {
        $this->setopt(CURLOPT_VERBOSE, $on);
    }

    /**
     * Close CURL
     */
    function close() {
        curl_close($this->curl);
    }
    
    /**
     * Destructor
     */
    function __destruct() {
    	$this->close();
    }
    
    /**
     * @param string $baseURL URL to build from
     * @param mixed[] $parameters Keys and values for the query string
     * @return string A contact of the baseURL and a query string of parameters
     */
    private function _buildURL($baseURL, $parameters=array()) {
    	if(empty($parameters))
    		return $baseURL;
    	elseif(is_array($parameters))
    		return $baseURL . '?' . http_build_query($parameters);
    	else 
    		return $baseURL . '?' . $parameters;
    }

    /**
     * @param mixed[] $data A (multidomensional) array
     * @param string $key Key of this element
     * @return  query string built from multidimensional array
     */
    private function http_build_multi_query($data, $key=NULL) {
        $query = array();

        foreach ($data as $k => $value) {
            if (is_string($value)) {
                $query[] = urlencode(is_null($key) ? $k : $key) . '=' . rawurlencode($value);
            }
            else if (is_array($value)) {
                $query[] = $this->http_build_multi_query($value, $k . '[]');
            }
        }

        return implode('&', $query);
    }

    /**
     * @param mixed[] $data A (multidimensional) array of key => values
     * @return string Query string representing $data
     */
    private function _postfields($data) {
        if (is_array($data)) {
            if (is_array_multidim($data)) {
                $data = $this->http_build_multi_query($data);
            }
            else {
                // Fix "Notice: Array to string conversion" when $value in
                // curl_setopt($ch, CURLOPT_POSTFIELDS, $value) is an array
                // that contains an empty array.
                foreach ($data as &$value) {
                    if (is_array($value) && empty($value)) {
                        $value = '';
                    }
                }
            }
        }

        return $data;
    }

    /**
     * Run the curl request
     * @return bool Did this method succeed?
     */
    private function _exec() {
        $this->response = curl_exec($this->curl);
        $this->curl_error_code = curl_errno($this->curl);
        $this->curl_error_message = curl_error($this->curl);
        $this->curl_error = !($this->curl_error_code === 0);
        $this->http_status_code = curl_getinfo($this->curl, CURLINFO_HTTP_CODE);
        $this->http_error = in_array(floor($this->http_status_code / 100), array(4, 5));
        $this->error = $this->curl_error || $this->http_error;
        $this->error_code = $this->error ? ($this->curl_error ? $this->curl_error_code : $this->http_status_code) : 0;

        $this->request_headers = preg_split('/\r\n/', curl_getinfo($this->curl, CURLINFO_HEADER_OUT), NULL, PREG_SPLIT_NO_EMPTY);
        $this->response_headers = '';
        if (!(strpos($this->response, "\r\n\r\n") === FALSE)) {
            list($response_header, $this->response) = explode("\r\n\r\n", $this->response, 2);
            if ($response_header === 'HTTP/1.1 100 Continue') {
                list($response_header, $this->response) = explode("\r\n\r\n", $this->response, 2);
            }
            $this->response_headers = preg_split('/\r\n/', $response_header, NULL, PREG_SPLIT_NO_EMPTY);
        }

        $this->http_error_message = $this->error ? (isset($this->response_headers['0']) ? $this->response_headers['0'] : '') : '';
        $this->error_message = $this->curl_error ? $this->curl_error_message : $this->http_error_message;

        return !$this->error;
    }
}

function is_array_multidim($array) {
    if (!is_array($array)) {
        return FALSE;
    }

    return !(count($array) === count($array, COUNT_RECURSIVE));
}