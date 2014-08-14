<?php

namespace Mms\Lib;

class Logger
{
    private static $instance = null;

    public static function getInstance()
    {
        if (is_null(self::$instance)) {
            self::$instance = new self;
        }

        return self::$instance;
    }

    public static function deleteInstance()
    {
        self::$instance = null;
        return self::$instance;
    }

    private $logFile;
    private $isDev;
    private $isDisplayOutput;

    private function __construct() {
    }

    public function initialize($logFile, $isDev = false, $isDisplayOutput = false)
    {
        $this->logFile = $logFile;
        $this->isDev = $isDev;
        $this->isDisplayOutput = $isDisplayOutput;

        // ディレクトリがなければ再帰的に作成
        if (!file_exists(dirname($logFile))) {
            mkdir(dirname($this->logFile), 0777, true);
            chmod(dirname($this->logFile), 0777);
        }
    }

    /**
     * ログ出力
     *
     * @param string $content
     * @return none
     */
    public function log($content)
    {
        if (!is_string($content)) {
          $content = print_r($content, true);
        }

        $log = $content . "\n";

        file_put_contents($this->logFile, $log, FILE_APPEND);

        if ($this->isDisplayOutput) {
            echo $log;
        }
    }

    /**
     * デバッグ出力
     */
    public function debug($content)
    {
        if ($this->isDev) {
            $this->log($content);
        }
    }
}
