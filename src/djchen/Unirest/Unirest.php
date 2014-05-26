<?php
namespace djchen\Unirest;

use djchen\Unirest\HttpMethod;
use djchen\Unirest\HttpResponse;

class Unirest
{
    
    private $verifyPeer = true;
    private $socketTimeout = null;
    private $defaultHeaders = array();
    private $debug = false;
    
    /**
     * Verify SSL peer
     * @param bool $enabled enable SSL verification, by default is true
     */
    public function verifyPeer($enabled)
    {
        $this->verifyPeer = $enabled;
    }
    
    /**
     * Set a timeout
     * @param integer $seconds timeout value in seconds
     */
    public function timeout($seconds)
    {
        $this->socketTimeout = $seconds;
    }
    
    /**
     * Set debug mode on or off
     * @param bool $enabled enable debug mode, by default off
     */
    public function debug($enabled)
    {
        $this->debug = $enabled;
    }
    
    /**
     * Set a new default header to send on every request
     * @param string $name header name
     * @param string $value header value
     */
    public function defaultHeader($name, $value)
    {
        $this->defaultHeaders[$name] = $value;
    }
    
    /**
     * Clear all the default headers
     */
    public function clearDefaultHeaders()
    {
        $this->defaultHeaders = array();
    }
    
    /**
     * Send a GET request to a URL
     * @param string $url URL to send the GET request to
     * @param array $headers additional headers to send
     * @param mixed $parameters parameters to send in the querystring
     * @param string $username Basic Authentication username
     * @param string $password Basic Authentication password
     * @return string|stdObj response string or stdObj if response is json-decodable
     */
    public function get($url, $headers = array(), $parameters = NULL, $username = NULL, $password = NULL)
    {
        return $this->request(HttpMethod::GET, $url, $parameters, $headers, $username, $password);
    }
    
    /**
     * Send POST request to a URL
     * @param string $url URL to send the POST request to
     * @param array $headers additional headers to send
     * @param mixed $body POST body data
     * @param string $username Basic Authentication username
     * @param string $password Basic Authentication password
     * @return string|stdObj response string or stdObj if response is json-decodable
     */
    public function post($url, $headers = array(), $body = NULL, $username = NULL, $password = NULL)
    {
        return $this->request(HttpMethod::POST, $url, $body, $headers, $username, $password);
    }
    
    /**
     * Send DELETE request to a URL
     * @param string $url URL to send the DELETE request to
     * @param array $headers additional headers to send
     * @param mixed $body DELETE body data
     * @param string $username Basic Authentication username
     * @param string $password Basic Authentication password
     * @return string|stdObj response string or stdObj if response is json-decodable
     */
    public function delete($url, $headers = array(), $body = NULL, $username = NULL, $password = NULL)
    {
        return $this->request(HttpMethod::DELETE, $url, $body, $headers, $username, $password);
    }
    
    /**
     * Send PUT request to a URL
     * @param string $url URL to send the PUT request to
     * @param array $headers additional headers to send
     * @param mixed $body PUT body data
     * @param string $username Basic Authentication username
     * @param string $password Basic Authentication password
     * @return string|stdObj response string or stdObj if response is json-decodable
     */
    public function put($url, $headers = array(), $body = NULL, $username = NULL, $password = NULL)
    {
        return $this->request(HttpMethod::PUT, $url, $body, $headers, $username, $password);
    }
    
    /**
     * Send PATCH request to a URL
     * @param string $url URL to send the PATCH request to
     * @param array $headers additional headers to send
     * @param mixed $body PATCH body data
     * @param string $username Basic Authentication username
     * @param string $password Basic Authentication password
     * @return string|stdObj response string or stdObj if response is json-decodable
     */
    public function patch($url, $headers = array(), $body = NULL, $username = NULL, $password = NULL)
    {
        return $this->request(HttpMethod::PATCH, $url, $body, $headers, $username, $password);
    }
    
    /**
     * Prepares a file for upload. To be used inside the parameters declaration for a request.
     * @param string $path The file path
     */
    public function file($path)
    {
        if (function_exists("curl_file_create")) {
            return curl_file_create($path);
        } else {
            return "@" . $path;
        }
    }
    
    /**
     * This function is useful for serializing multidimensional arrays, and avoid getting
     * the "Array to string conversion" notice
     */
    public function http_build_query_for_curl($arrays, &$new = array(), $prefix = null)
    {
        if (is_object($arrays)) {
            $arrays = get_object_vars($arrays);
        }
        
        foreach ($arrays AS $key => $value) {
            $k = isset($prefix) ? $prefix . '[' . $key . ']' : $key;
            if (!$value instanceof \CURLFile AND (is_array($value) OR is_object($value))) {
                $this->http_build_query_for_curl($value, $new, $k);
            } else {
                $new[$k] = $value;
            }
        }
    }
    
    /**
     * Send a cURL request
     * @param string $httpMethod HTTP method to use (based off \Unirest\HttpMethod constants)
     * @param string $url URL to send the request to
     * @param mixed $body request body
     * @param array $headers additional headers to send
     * @param string $username  Basic Authentication username
     * @param string $password  Basic Authentication password
     * @throws Exception if a cURL error occurs
     * @return HttpResponse
     */
    private function request($httpMethod, $url, $body = NULL, $headers = array(), $username = NULL, $password = NULL)
    {
        if ($headers == NULL)
            $headers = array();

        $lowercaseHeaders = array();
        $finalHeaders = array_merge($headers, $this->defaultHeaders);
        foreach ($finalHeaders as $key => $val) {
            $lowercaseHeaders[] = $this->getHeader($key, $val);
        }
        
        $lowerCaseFinalHeaders = array_change_key_case($finalHeaders);
        if (!array_key_exists("user-agent", $lowerCaseFinalHeaders)) {
            $lowercaseHeaders[] = "user-agent: unirest-php/1.1";
        }
        if (!array_key_exists("expect", $lowerCaseFinalHeaders)) {
            $lowercaseHeaders[] = "expect:";
        }
        
        $ch = curl_init();
        $postBody = array();
        if ($httpMethod != HttpMethod::GET) {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $httpMethod);
            if (is_array($body) || $body instanceof Traversable) {
                $this->http_build_query_for_curl($body, $postBody);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $postBody);
            } else {
                curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
            }
        } else if (is_array($body)) {
            if (strpos($url, '?') !== false) {
                $url .= "&";
            } else {
                $url .= "?";
            }
            $this->http_build_query_for_curl($body, $postBody);
            $url .= urldecode(http_build_query($postBody));
        }
        
        curl_setopt($ch, CURLOPT_URL, $this->encodeUrl($url));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_MAXREDIRS, 10);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $lowercaseHeaders);
        curl_setopt($ch, CURLOPT_HEADER, true);
        curl_setopt($ch, CURLINFO_HEADER_OUT, $this->debug);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, $this->verifyPeer);
        curl_setopt($ch, CURLOPT_ENCODING, ""); // If an empty string, "", is set, a header containing all supported encoding types is sent.
        if ($this->socketTimeout != null) {
            curl_setopt($ch, CURLOPT_TIMEOUT, $this->socketTimeout);
        }
        if (!empty($username)) {
            curl_setopt($ch, CURLOPT_USERPWD, $username . ":" . ((empty($password)) ? "" : $password));
        }
        
        $response = curl_exec($ch);
        $error    = curl_error($ch);
        if ($error) {
            throw new Exception($error);
        }
        
        // Split the full response in its headers and body
        $curl_info   = curl_getinfo($ch);
        $header_size = $curl_info["header_size"];
        $header      = substr($response, 0, $header_size);
        $body        = substr($response, $header_size);
        $httpCode    = $curl_info["http_code"];
        
        if ($this->debug === true) {
            error_log("Request Header:\n");
            error_log($curl_info['request_header']);
            error_log("\nResponse Header:\n");
            error_log($header);
            error_log("\nResponse Body:\n");
            error_log($body);
        }

        return new HttpResponse($httpCode, $body, $header);
    }
    
    private function getArrayFromQuerystring($querystring)
    {
        $pairs = explode("&", $querystring);
        $vars  = array();
        foreach ($pairs as $pair) {
            $nv          = explode("=", $pair, 2);
            $name        = $nv[0];
            $value       = $nv[1];
            $vars[$name] = $value;
        }
        return $vars;
    }
    
    /**
     * Ensure that a URL is encoded and safe to use with cURL
     * @param  string $url URL to encode
     * @return string
     */
    private function encodeUrl($url)
    {
        $url_parsed = parse_url($url);
        
        $scheme = $url_parsed['scheme'] . '://';
        $host   = $url_parsed['host'];
        $port   = (isset($url_parsed['port']) ? $url_parsed['port'] : null);
        $path   = (isset($url_parsed['path']) ? $url_parsed['path'] : null);
        $query  = (isset($url_parsed['query']) ? $url_parsed['query'] : null);
        
        if ($query != null) {
            $query = '?' . http_build_query($this->getArrayFromQuerystring($url_parsed['query']));
        }
        
        if ($port && $port[0] != ":")
            $port = ":" . $port;
        
        $result = $scheme . $host . $port . $path . $query;
        return $result;
    }
    
    private function getHeader($key, $val)
    {
        $key = trim(strtolower($key));
        return $key . ": " . $val;
    }
    
}

if (!function_exists('http_chunked_decode')) {
    /**
     * Dechunk an http 'transfer-encoding: chunked' message 
     * @param string $chunk the encoded message 
     * @return string the decoded message
     */
    function http_chunked_decode($chunk)
    {
        $pos     = 0;
        $len     = strlen($chunk);
        $dechunk = null;
        
        while (($pos < $len) && ($chunkLenHex = substr($chunk, $pos, ($newlineAt = strpos($chunk, "\n", $pos + 1)) - $pos))) {
            
            if (!is_hex($chunkLenHex)) {
                trigger_error('Value is not properly chunk encoded', E_USER_WARNING);
                return $chunk;
            }
            
            $pos      = $newlineAt + 1;
            $chunkLen = hexdec(rtrim($chunkLenHex, "\r\n"));
            $dechunk .= substr($chunk, $pos, $chunkLen);
            $pos = strpos($chunk, "\n", $pos + $chunkLen) + 1;
        }
        
        return $dechunk;
    }
}

/**
 * determine if a string can represent a number in hexadecimal 
 * @link http://uk1.php.net/ctype_xdigit
 * @param string $hex 
 * @return boolean true if the string is a hex, otherwise false 
 */
function is_hex($hex)
{
    return ctype_xdigit($hex);
}
