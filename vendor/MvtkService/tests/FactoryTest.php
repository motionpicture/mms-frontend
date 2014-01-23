<?php
namespace MvtkService;

use MvtkService\Factory;

include '../Factory.php';

/**
 * MvtkService FactoryTest
 */
class FactoryTest extends \PHPUnit_Framework_TestCase {
    protected function setUp()
    {
        $this->option = [
            'soap' => [
                'endPoint' => 'https://ssl.movieticket.jp',
            ],
            'storage' => [
                'name' => 'testmovieticketfrontend',
                'key' => 'c93s/ZXgTySSgB6FrCWvOXalfRxKQFd96s61X8TwMUc3jmjAeRyBY9jSMvVQXh4U9gIRNNH6mCkn44ZG/T3OXA==',
            ],
            'sendgrid' => [
                'api_user' => 'azure_2fa68dcc38c9589d53104d96bc2798ed@azure.com',
                'api_key' => 'pwmk27ud',
                'from' => 'info@movieticket.jp',
                'fromname' => 'ムビチケ',
            ],
        ];
    }


    /**
     * @test
     * @expectedException \Exception
     */
    public function getInstanceException() {
        $factory = new Factory();
    }


    /**
     * @test
     */
    public function getInstance() {
        $factory = new Factory($this->option);
        $this->assertInstanceOf('MvtkService\Factory', $factory);

        return $factory;
    }


    /**
     * @test
     * @depends getInstance
     */
    public function createInstanceUtil($factory) {
        $service = $factory->createInstance('Util');
        $this->assertInstanceOf('MvtkService\Common', $service);

        return $service;
    }


    /**
     * @test
     * @depends createInstanceUtil
     */
    public function EncryptDataList($service)
    {
        $arguments = [
        'list' => [
        'abc',
        'DEF',
        ],
        ];

        print_r($arguments);
        $result = $service->EncryptDataList($arguments);
        $this->assertInstanceOf('MvtkService\Result\Common', $result);
        print_r($result);
    }


//     /**
//      * @test
//      * @depends getInstance
//      */
//     public function createInstancePurchase($factory) {
//         $service = $factory->createInstance('Purchase');
//         $this->assertInstanceOf('MvtkService\Purchase', $service);

//         return $service;
//     }


//     /**
//      * @test
//      * @depends getInstance
//      * @expectedException \Exception
//      */
//     public function createInstanceException($factory) {
//         $service = $factory->createInstance('Undefined');
//     }


//     /**
//      * @test
//      * @depends createInstancePurchase
//      */
//     public function SendPurchaseInfoMail($service) {
//         $result = $service->SendPurchaseInfoMail([]);
//         $this->assertInstanceOf('MvtkService\Result\Common', $result);
//     }


//     /**
//      * @test
//      * @depends createInstancePurchase
//      * @expectedException \Exception
//      */
//     public function undefinedMethod($service)
//     {
//         $service->undefinedMethod('');
//     }


//     /**
//      * @test
//      * @depends createInstancePurchase
//      */
//     public function SaibanKssiknrNo($service)
//     {
//         // 決済管理番号採番
//         $result = $service->saibanKssiknrNo();
//         $this->assertInstanceOf('MvtkService\Result\Kessai', $result);

//         return $result;
//     }
}
