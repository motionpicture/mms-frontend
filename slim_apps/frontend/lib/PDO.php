<?php
namespace Mms\Frontend;

class PDO
{
    private static $pdo = null;

    public static function getInstance($mode)
    {
        if (is_null(self::$pdo)) {
            if ($mode == 'development') {
            } else if ($mode == 'staging') {
            } else if ($mode == 'production') {
            } else {
                throw new Exception('incorrect application mode.');
            }

            try {
                self::$pdo = new \PDO('sqlite:' . dirname(__FILE__) . '/../../../db/mms.db');
            } catch (\PDOException $e) {
                throw $e;
            }
        }

        return self::$pdo;
    }

    public static function deleteInstance()
    {
        self::$pdo = null;
        return self::$pdo;
    }
}
