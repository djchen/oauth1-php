<?php
namespace djchen;

use djchen\OAuth1\OAuth1Exception;
use djchen\Unirest\Unirest;
use djchen\Unirest\HttpMethod;

class OAuth1
{

    const HTTP_OK = 200;

    const OAUTH_CONSUMER_KEY = 'oauth_consumer_key';
    const OAUTH_CONSUMER_SECRET = 'oauth_consumer_secret';
    const OAUTH_TOKEN = 'oauth_token';
    const OAUTH_TOKEN_SECRET = 'oauth_token_secret';
    const OAUTH_SIGNATURE = 'oauth_signature';
    const OAUTH_SIGNATURE_METHOD = 'oauth_signature_method';
    const OAUTH_TIMESTAMP = 'oauth_timestamp';
    const OAUTH_NONCE = 'oauth_nonce';
    const OAUTH_VERSION = 'oauth_version';

    private $config = array(
        'consumerKey' => null,
        'consumerSecret' => null,
        'requestTokenUrl' => null,
        'accessTokenUrl' => null,
        'token' => false,
        'tokenSecret' => false,
        'signatureMethod' => 'HMAC-SHA1',
        'sslVerifyPeer' => true,
        'timeout' => 5
    );

    private $restClient;

    public function __construct($settings)
    {
        foreach ($settings as $setting => $value) {
            if (!array_key_exists($setting, $this->config)) {
                throw new OAuth1Exception(
                    'Unknown configuration setting: ' . $setting
                );
            }
            $this->config[$setting] = $value;
        }
        foreach ($this->config as $configName => $configValue) {
            if ($configValue === null) {
                throw new OAuth1Exception(
                    'Configuration error: ' . $configName . ' not provided'
                );
            }
        }
        $restClient = $this->restClient = new Unirest();
        $restClient->defaultHeader("user-agent", "djchen-oauth1/0.1");
        $this->sslVerifyPeer($this->config['sslVerifyPeer']);
        $this->setTimeout($this->config['timeout']);
    }

    public function sslVerifyPeer($enable)
    {
        $this->restClient->verifyPeer($enable);
    }

    public function setTimeout($seconds)
    {
        $this->restClient->timeout($seconds);
    }

    public function setDebug($enable)
    {
        $this->restClient->debug($enable);
    }

    public function getConfig()
    {
        return $this->config;
    }

    public function setToken($token, $tokenSecret)
    {
        $this->config['token'] = $token;
        $this->config['tokenSecret'] = $tokenSecret;
    }

    public function requestToken($callbackUrl)
    {
        $result = $this->post(
            $this->config['requestTokenUrl'],
            null,
            array('oauth_callback' => $callbackUrl)
        );
        if ($result->code != self::HTTP_OK) {
            throw new OAuth1Exception(
                "Request Token Error: " . $result->raw_body,
                $result->code,
                $result
            );
        }
        parse_str($result->body, $output);
        return $output;
    }

    public function accessToken($token, $tokenSecret, $oauthVerifier)
    {
        $this->config['token'] = $token;
        $this->config['tokenSecret'] = $tokenSecret;
        $result = $this->post(
            $this->config['accessTokenUrl'],
            null,
            array('oauth_verifier' => $oauthVerifier)
        );
        if ($result->code != self::HTTP_OK) {
            throw new OAuth1Exception(
                "Access Token Error: " . $result->raw_body,
                $result->code,
                $result
            );
        }
        parse_str($result->body, $output);
        $this->setToken($output[self::OAUTH_TOKEN], $output[self::OAUTH_TOKEN_SECRET]);
        return $output;
    }

    public function get($url, $params = array(), $oauth = array(), $headers = array())
    {
        return $this->request(HttpMethod::GET, $url, $headers, $params, $oauth);
    }

    public function post($url, $body = null, $oauth = array(), $headers = array())
    {
        return $this->request(HttpMethod::POST, $url, $headers, $body, $oauth);
    }

    public function put($url, $body = null, $oauth = array(), $headers = array())
    {
        return $this->request(HttpMethod::PUT, $url, $headers, $body, $oauth);
    }

    public function delete($url, $body = null, $oauth = array(), $headers = array())
    {
        return $this->request(HttpMethod::DELETE, $url, $headers, $body, $oauth);
    }

    private function generateAuth($method, $url, $params)
    {
        $oauth = array(
            self::OAUTH_CONSUMER_KEY => $this->config['consumerKey'],
            self::OAUTH_SIGNATURE_METHOD => $this->config['signatureMethod'],
            self::OAUTH_TIMESTAMP => time(),
            self::OAUTH_NONCE => md5(mt_rand()),
            self::OAUTH_VERSION => '1.0'
        );
        if (!empty($this->config['token'])) {
            $oauth[self::OAUTH_TOKEN] = $this->config['token'];
            $oauth[self::OAUTH_TOKEN_SECRET] = $this->config['tokenSecret'];
        }

        $oauth = array_merge($oauth, $params);
        $baseStr = $this->generateBaseString($method, $url, $oauth);

        $oauth[self::OAUTH_SIGNATURE] = $this->generateSignature($baseStr);
        ksort($oauth);

        $authHeader = 'OAuth ';
        foreach ($oauth as $key => $value) {
            $authHeader .= rawurlencode($key) . '="' . rawurlencode($value) . '", ';
        }
        return substr($authHeader, 0, -2);
    }

    private function generateBaseString($method, $url, $params)
    {
        $url = parse_url($url);
        if (isset($url['query'])) {
            parse_str($url['query'], $params2);
            $params = array_merge($params, $params2);
        }
        ksort($params);
        $baseUrl = $url['scheme'] . '://' . $url['host'] . $url['path'];
        $baseStr = strtoupper($method) . '&' . rawurlencode($baseUrl) . '&';
        foreach ($params as $key => $value) {
            $baseStr .= rawurlencode(
                rawurlencode($key) . '=' . rawurlencode($value) . '&'
            );
        }
        return substr($baseStr, 0, -3);
    }

    private function generateSignature($baseStr)
    {
        switch ($this->config['signatureMethod']) {
            case 'HMAC-SHA1':
                $signingKey =  $this->config['consumerSecret'] . '&';
                if (isset($this->config['tokenSecret'])) {
                    $signingKey .= $this->config['tokenSecret'];
                }
                return base64_encode(
                    hash_hmac(
                        'sha1',
                        $baseStr,
                        $signingKey,
                        true
                    )
                );
                break;
            default:
                throw new OAuth1Exception(
                    'Unknown or unsupported signature method: ' . $this->config['signatureMethod']
                );
        }
    }

    private function request($method, $url, $headers, $paramsOrBody, $oauth)
    {
        $method = strtolower($method);
        if (!is_array($headers)) {
            $headers = array();
        }

        $headers['authorization'] = $this->generateAuth($method, $url, $oauth);
        $response = $this->restClient->$method($url, $headers, $paramsOrBody);
        return $response;
    }
}
