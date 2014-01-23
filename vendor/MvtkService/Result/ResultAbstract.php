<?php
namespace MvtkService\Result;

/**
 * MvtkService ResultAbstract
 *
 * @package MvtkService
 */
abstract class ResultAbstract
{
    /**
     * ステータス
     */
    const STATUS_SUCCESS           = 'N000';
    const STATUS_CRITICAL_ERROR    = 'E001';
    const STATUS_CHECK_ERROR       = 'L001';
    const STATUS_REPLICATION_ERROR = 'L002';
    const STATUS_CLIENT_ERROR      = 'L003';
    const STATUS_OVERFLOW_ERROR    = 'L004';


    /**
     * __construct
     *
     * @param object $response
     */
    public function __construct(\stdClass $response)
    {
        $resultKey = array_keys(get_object_vars($response))[0];

        if (!isset($response->$resultKey->RESULT_INFO->STATUS)) {
            throw new \Exception('Argument 1 passed to ' . __METHOD__ . ' is invalid response data.');
        }

        foreach ($response->$resultKey as $key => $value) {
            $this->$key = $value;
        }
    }


    /**
     * toArray
     *
     * @return array
     */
    public function toArray()
    {
        return $this->object2Array($this);
    }

    /**
     * object2Array
     *
     * @param object $obj
     * @return array
     */
    private function object2Array($obj)
    {
        $_arr = is_object($obj) ? get_object_vars($obj) : $obj;
        $arr  = [];
        foreach ($_arr as $key => $val) {
            $val = (is_array($val) || is_object($val)) ? $this->object2Array($val) : $val;
            $arr[$key] = $val;
        }
        return $arr;
    }


    /**
     * isError
     *
     * @return boolean
     */
    public function isError()
    {
        if ($this->RESULT_INFO->STATUS == self::STATUS_SUCCESS) {
            return false;
        }

        return true;
    }
}
