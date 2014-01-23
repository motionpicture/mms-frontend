<?php
namespace MvtkService;

include 'Autoloader.php';

/**
 * MvtkService Factory
 *
 * @package MvtkService
 */
class Factory
{
    private $option;

    /**
     * __construct
     */
    public function __construct($option) {
        $this->option = $option;
    }



    /**
     * createInstance
     *
     * @param string $serviceName
     * @return MvtkService
     */
    public function createInstance($serviceName)
    {
        try {
            switch ($serviceName) {
                case 'Purchase':
                    $service = Purchase::getInstance();
                    break;

                default:
                    $service = Common::getInstance();
                    break;
            }

            $service->setServiceName($serviceName);
            $service->setOption($this->option);

            switch ($serviceName) {
                case 'Purchase':
                    $service->createSoap();
                    $service->createSendgrid();
                    break;

                case 'Film':
                case 'Util':
                    $service->createSoap();
                    break;

                case 'Alert':
                    $service->createSendgrid();
                    break;
            }
        } catch (\Exception $e) {
            throw $e;
        }

        return $service;
    }
}
