<?php
namespace Mms\Api\Controllers;

class BaseController
{
    protected $app;
    protected $pdo;

    public function __construct()
    {
        $this->app = \Mms\Api\Lib\Slim::getInstance();
        $this->pdo = \Mms\Lib\PDO::getInstance();
    }
}
