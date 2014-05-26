# oauth1-php
----------
oauth1-php is a super simple and basic PHP library for making OAuth 1.0 requests.

###Install with Composer
```javascript
{
  "require" : {
    "djchen/oauth1-php" : "dev-master"
  },
  "autoload": {
    "psr-0": {"djchen": "src"}
  }
}
```

###Dependecies
Requires PHP 5.3+ and cURL
The Unirest library is used to make HTTP requests and is included in the project.

##Usage
###Initializing
```php
$oauth = new OAuth1(array(
        'consumerKey' => 'oauth_consumer_key',
        'consumerSecret' => 'oauth_consumer_secret',
        'token' => 'oauth_token', // optional
        'tokenSecret' => 'oauth_token_secret', // optional
        'requestTokenUrl' => 'request_token_url',
        'accessTokenUrl' => 'access_token_url',
));
```

###Getting a request token
```php
$result = $oauth->requestToken('callback_url');
```

$result is an array with the response params => values

###Getting an access token
```php
$result = $oauth->accessToken('oauth_token', 'oauth_token_secret', 'oauth_verifier');
```

$result is an array with the response params => values

###Making API calls
```php
$response = $oauth->get($url, $params = array(), $httpHeaders = array(), $oauthParams = array());
$response = $oauth->post($url, $body = null, $httpHeaders = array(), $oauthParams = array());
$response = $oauth->put($url, $body = null, $httpHeaders = array(), $oauthParams = array());
$response = $oauth->delete($url, $body = null, $httpHeaders = array(), $oauthParams = array());
```

The $response is an object with these fields
```php
$response->code; // HTTP Response Status Code
$response->headers; // HTTP Response Headers
$response->body; // Parsed response body where applicable, for example JSON responses are parsed to Objects / Associative Arrays.
$response->raw_body; // Original un-parsed response body
```

Debug mode can be turned on via `$oauth->setDebug(true)`. This will log both request headers and response headers and body.
