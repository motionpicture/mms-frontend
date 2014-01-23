<?php
namespace MvtkService\Adapter;

/**
 * MvtkService Adapter Soap
 *
 * @package MvtkService
 */
class Soap
{
    private $wsdlUrl;
    private $client;

    private $wsdls = [
        'Film' => '/services/Film/Filmsvc.svc?wsdl',
        'Purchase' => '/services/Purchase/PurchaseSvc.svc?wsdl',
        'Util' => '/services/Util/UtilSvc.svc?wsdl',
    ];

    /**
     * __construct
     */
    public function __construct($serviceName, $option)
    {
        if (!isset($this->wsdls[$serviceName])) {
            throw new \Exception('Argument 1 passed to ' . __METHOD__ . ' is invalid service name.');
        }

        $this->wsdlUrl = $option['endPoint'] . $this->wsdls[$serviceName];
        $this->client = null;
    }




    /**
     * SOAPクライアントを生成
     */
    private function createClient()
    {
        $option = [
            'trace' => true,
            'exceptions' => true,
        ];

        $this->client = new \SoapClient($this->wsdlUrl, $option);

        if (isset($_COOKIE['.ASPXAUTH'])) {
            $this->client->__setCookie('.ASPXAUTH', $_COOKIE['.ASPXAUTH']);
        }
    }


    /**
     * __call
     */
    public function __call($name, $arguments)
    {
        try {
            if (is_null($this->client)) {
                $this->createClient();
            }

            if (empty($arguments)) {
                $response = $this->client->$name();
            } else {
                $response = $this->client->$name($arguments[0]);
            }
        } catch (\Exception $e) {
            throw $e;
        }

        return $response;
    }
}
