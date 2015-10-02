<?php
namespace Mms\Frontend\Controllers;

class BaseController
{
    protected $app;
    protected $pdo;

    public function __construct()
    {
        $this->app = \Mms\Frontend\Lib\Slim::getInstance();
        $this->pdo = \Mms\Lib\PDO::getInstance();
    }
}
