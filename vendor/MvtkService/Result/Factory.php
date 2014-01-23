<?php
namespace MvtkService\Result;

/**
 * MvtkService Result Factory
 *
 * @package MvtkService
 */
class Factory
{
    /**
     * createInstance
     *
     * @param object $response
     * @return MvtkService\Soap\Result
     */
    public function createInstance(\stdClass $response)
    {
        $resultKey = array_keys(get_object_vars($response))[0];

        if (!preg_match('/Result$/', $resultKey)) {
            throw new \Exception('Argument 1 passed to ' . __METHOD__ . ' is invalid response data.');
        }

        switch ($resultKey) {
            case 'SaibanKssiknrNoResult':
            case 'GetGmoEntryTranResult':
            case 'GetGmoExecTranResult':
            case 'GetGmoSecureTranResult':
            case 'RegisterKssijhResult':
            case 'RegisterPurchaseInfoResult':
                try {
                    $result = new Kessai($response);
                } catch (\Exception $e) {
                    throw $e;
                }
                break;

            default:
                try {
                    $result = new Common($response);
                } catch (\Exception $e) {
                    throw $e;
                }
                break;
        }

        return $result;
    }
}
