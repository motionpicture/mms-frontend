<?php
namespace Mms\Bin;

class PDO
{
    private static $pdo = null;

    public static function getInstance()
    {
        if (is_null(self::$pdo)) {
            try {
                self::$pdo = new \PDO('sqlite:' . dirname(__FILE__) . '/../db/mms.db');
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
