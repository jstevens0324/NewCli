<?php
namespace MssSendza;
use Zend\Http\Request,
    Zend\OAuth;

class Connection
{
    //const SENDZA_HOST = 'http://api.sendzadev.com/v1/services/rest/MySendza';
    const SENDZA_HOST = 'http://api.sendza.com/services/rest/MySendza';

    protected $client;

    protected $options = array(
        'requestScheme'   => OAuth\OAuth::REQUEST_SCHEME_QUERYSTRING,
        'signatureMethod' => 'HMAC-SHA1',
        //'consumerKey'     => '150ed12a404f4bfa8eecfae31b68c8c9',
        //'consumerSecret'  => '725db52035ee4a16857b14b876dc6623',
        'consumerKey'     => '457e1cc080534fc8809a403ce47ac888',
        'consumerSecret'  => 'ee7b2b9f6da147b58ecab9f35dce9f46'
    );

    protected function client()
    {
        if (null === $this->client) {
            $token        = new OAuth\Token\Access;
            $this->client = $token->getHttpClient($this->options);
        }
        return $this->client;
    }

    protected function go($data, $endpoint, $method = Request::METHOD_GET)
    {
        $this->client()->setUri(self::SENDZA_HOST . "/{$endpoint}");
        $this->client()->setMethod($method);
        $this->client()->setEncType('application/json');
        $this->client()->setRawBody(json_encode($data));

        return $this->client()->send()->getContent();
    }
}
