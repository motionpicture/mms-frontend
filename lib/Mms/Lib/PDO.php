<?php

namespace Mms\Lib;

class PDO
{
    private static $instance = null;

    private function __construct() {
    }

    public static function getInstance($mode)
    {
        if (is_null(self::$instance)) {
            if ($mode == 'development') {
            } else if ($mode == 'staging') {
            } else if ($mode == 'production') {
            } else {
              throw new \Exception('incorrect application mode.');
            }

            try {
                self::$instance = new \PDO('sqlite:' . __DIR__ . '/../../../db/mms.db');
                self::$instance->setAttribute(\PDO::ATTR_PERSISTENT, true);
                self::$instance->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
                self::$instance->setAttribute(\PDO::ATTR_DEFAULT_FETCH_MODE, \PDO::FETCH_ASSOC);
            } catch (\PDOException $e) {
                throw $e;
            }
        }

        return self::$instance;
    }

    public static function deleteInstance()
    {
        self::$pdo = null;
        return self::$pdo;
    }
}
