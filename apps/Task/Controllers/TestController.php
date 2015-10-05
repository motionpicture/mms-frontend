<?php
namespace Mms\Task\Controllers;

class TestController extends BaseController
{
    public function batch($a, $b)
    {
        $this->logger->log(__METHOD__);
        $this->logger->log("a:" . var_export($a, true));
        $this->logger->log("b:" . var_export($b, true));

        $to = 'ilovegadd@gmail.com';
        $body = "テストバッチから送信されたメールです。<br>testテストバッチから送信されたメールです。<br>テストバッチから送信されたメールです。";
        $this->sendErrorMail($to, $body);

        return;
    }

    public function initDb()
    {
        try {
            $query = file_get_contents(__DIR__ . '/../../../db/initialize.sql');
            $this->logger->log("query:{$query}");
            $this->db->exec($query);
        } catch (\Exception $e) {
            $this->logger->log("initDb throw exception. message:{$e->getMessage()}");
        }
    }
}