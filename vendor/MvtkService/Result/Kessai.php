<?php
namespace MvtkService\Result;

/**
 * MvtkService Result Kessai
 *
 * @package MvtkService
 */
class Kessai extends ResultAbstract
{
    /**
     * 決済エラー種類区分
     */
    const KSSIERRRSHRI_TYP_SUCCESS      = null;
    const KSSIERRRSHRI_TYP_CLIENT_ERROR = '01';
    const KSSIERRRSHRI_TYP_SYSTEM_ERROR = '02';


    /**
     * __construct
     *
     * @param object $response
     */
    public function __construct(\stdClass $response)
    {
        parent::__construct($response);

        if (!property_exists($this, 'KSSIERRRSHRI_TYP')) {
            throw new \Exception('Argument 1 passed to ' . __METHOD__ . ' is invalid response data.');
        }
    }


    /**
     * isKessaiError
     *
     * @return boolean
     */
    public function isKessaiError()
    {
        if ($this->KSSIERRRSHRI_TYP == self::KSSIERRRSHRI_TYP_SUCCESS) {
            return false;
        }

        return true;
    }
}
