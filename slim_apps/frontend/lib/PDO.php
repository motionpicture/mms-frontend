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
                throw new \Exception('incorrect application mode.');
            }

            try {
                self::$pdo = new \PDO('sqlite:' . dirname(__FILE__) . '/../../../db/mms.db');
                self::$pdo->setAttribute(\PDO::ATTR_PERSISTENT, true);
                self::$pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
                self::$pdo->setAttribute(\PDO::ATTR_DEFAULT_FETCH_MODE, \PDO::FETCH_ASSOC);
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
