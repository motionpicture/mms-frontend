<?php
namespace MvtkService;

/**
 * MvtkService SingletonTrait
 *
 * @package MvtkService
 */
trait SingletonTrait
{
    /**
     * getInstance
     *
     * @param array $option
     * @return MvtkService
     */
    public static function getInstance()
    {
        static $instance = null;

        return $instance ?: $instance = new static;
    }

    /**
     * __construct
     */
    private function __construct()
    {
    }

    /**
     * __clone
     */
    final public function __clone(){
        throw new \Exception('Cloning this object is unavailable.');
    }
}
