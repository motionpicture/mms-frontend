<?php
namespace MvtkService;

/**
 * Abstract
 *
 * @package MvtkService
 */
abstract class MvtkServiceAbstract
{
    use SingletonTrait;

    /**
     * デバイス区分
     */
    const DVC_TYP_PC = '01';
    const DVC_TYP_SP = '09';

    protected $serviceName;
    protected $option;
    protected $soap;
    protected $sendgrid;

    public function setServiceName($serviceName)
    {
        $this->serviceName = $serviceName;
    }

    public function setOption($option)
    {
        $this->option = $option;
    }

    public function createSoap()
    {
        try {
            $this->soap = new Adapter\Soap($this->serviceName, $this->option['soap']);
        } catch (\Exception $e) {
            throw $e;
        }
    }

    public function createSendgrid()
    {
        try {
            $this->sendgrid = new Adapter\Sendgrid($this->option['sendgrid']);
        } catch (\Exception $e) {
            throw $e;
        }
    }

    /**
     * __call
     */
    public function __call($name, $arguments)
    {
        if (is_null($this->soap)) {
            throw new \Exception('Call to undefined method ' . __METHOD__ . ' in ' . __FILE__ . ' on line ' . __LINE__);
        }

        try {
            if (empty($arguments)) {
                $response = $this->soap->$name();
            } else {
                $response = $this->soap->$name($arguments[0]);
            }

            $factory = new Result\Factory();
            $result = $factory->createInstance($response);

            if ($result->isError()) {
                throw new MvtkServiceException("[{$result->RESULT_INFO->STATUS}] {$result->RESULT_INFO->MESSAGE}");
            }
        } catch (\Exception $e) {
            throw $e;
        }

        return $result;
    }
}
