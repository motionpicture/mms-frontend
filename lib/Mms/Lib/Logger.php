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

    private $file;
    private $isDev;
    private $isDisplayOutput;
    private $prefix;

    private function __construct() {
    }

    public function initialize($file, $isDev = false, $isDisplayOutput = false, $prefix = null)
    {
        $this->file = $file;
        $this->isDev = $isDev;
        $this->isDisplayOutput = $isDisplayOutput;
        $this->prefix = $prefix;

        // ディレクトリがなければ再帰的に作成
        if (!file_exists(dirname($file))) {
            mkdir(dirname($this->file), 0777, true);
            chmod(dirname($this->file), 0777);
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

        $log = "{$this->prefix} {$content}\n";

        file_put_contents(
            $this->file,
            $log,
            FILE_APPEND | LOCK_EX
        );

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

    /**
     * ログ追加
     */
    public function error($errno, $errstr, $errfile, $errline, $errcontext)
    {
        switch ($errno) {
            case E_ERROR:
                $content = "[E_ERROR] {$errstr} {$errfile} {$errline}";
                break;

            case E_WARNING:
                $content = "[E_WARNING] {$errstr} {$errfile} {$errline}";
                break;

            case E_PARSE:
                $content = "[E_PARSE] {$errstr} {$errfile} {$errline}";
                break;

            case E_NOTICE:
                $content = "[E_NOTICE] {$errstr} {$errfile} {$errline}";
                break;

            case E_STRICT:
                $content = "[E_STRICT] {$errstr} {$errfile} {$errline}";
                break;

            case E_RECOVERABLE_ERROR:
                $content = "[E_RECOVERABLE_ERROR] {$errstr} {$errfile} {$errline}";
                break;

            case E_DEPRECATED:
                $content = "[E_DEPRECATED] {$errstr} {$errfile} {$errline}";
                break;
        }

        $this->log($content);
    }

    /**
     * シャットダウン時のログ出力
     * 
     * @see http://php.net/manual/en/errorfunc.constants.php
     */
    public function shutdown()
    {
        $e = error_get_last();
        if ($e['type'] == E_ERROR
         || $e['type'] == E_PARSE
         || $e['type'] == E_CORE_ERROR
         || $e['type'] == E_USER_ERROR ) {
            $message = 'script has been shutdown because of a fatal error. error:' . print_r($e, true);

            $this->log(__METHOD__ . " message:{$message}");
        }
    }
}
