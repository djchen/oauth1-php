<?php
namespace djchen\OAuth1;

class OAuth1Exception extends \Exception
{

    protected $response;

    public function __construct($message, $code = 0, $response = null)
    {
        $this->response = $response;
        parent::__construct($message, $code, null);
    }

    public function getResponse()
    {
        return $this->response;
    }

    public function __toString()
    {
        return "OAuth1Exception: [{$this->code}]: {$this->message}\n";
    }

}
