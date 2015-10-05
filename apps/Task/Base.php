<?php
namespace Mms\Task;

require_once __DIR__ . '/../../vendor/autoload.php';

spl_autoload_register(function ($class) {
    $file = __DIR__ . '/../../lib/' . strtr($class, '\\', DIRECTORY_SEPARATOR) . '.php';
    if (is_readable($file)) {
        require_once $file;
        return;
    }

    $file = __DIR__ . '/../../apps/' . strtr(str_replace('Mms\\', '', $class), '\\', DIRECTORY_SEPARATOR) . '.php';
    if (is_readable($file)) {
        require_once $file;
        return;
    }
});

class Base
{
    /**
     * タスクを実行する
     * 
     * @param string $argv スクリプトに渡された引数の配列
     */
    public static function execute($argv)
    {
        $startTime = microtime(true);
        $startMem = memory_get_usage();
        $executedAt = time();

        // スクリプトに渡された引数より必要な値を取り出す
        $class = $argv[1];
        $method = $argv[2];
        $args = array_slice($argv, 3);

        $className = "\\Mms\\Task\\Controllers\\{$class}Controller";
        $controller = new $className;
        $config = $controller->config;
        $logger = $controller->logger;
        $controller->executedAt = $executedAt;

        $logFile = "{$config->get('log_directory')}/{$config->getMode()}/Task/{$class}" . ucfirst($method) . "/" . date('Ymd');
        $logPrefix = 'Task executed ' . date('Y-m-d H:i:s', $executedAt);
        $logger->initialize($logFile, $config->isDev(), $config->isDev(), $logPrefix);
        $logger->log('--------------------------------------------------------------------------------');
        $logger->log('start');

        set_error_handler(function($errno, $errstr, $errfile, $errline, $errcontext) use ($logger)
        {
            $logger->log('has got some errors.');
            $logger->error($errno, $errstr, $errfile, $errline, $errcontext);
        });

        register_shutdown_function(function() use ($logger)
        {
            $logger->log('shutdown.');
            $logger->shutdown();
        });

        try {
            if (!method_exists($className, $method)) {
                throw new \Exception('method does not exist.');
            }

            call_user_func_array(array($controller, $method), $args);
        } catch (\Exception $e) {
            $logger->log("called method has thrown an exception. message:{$e->getMessage()}");
        }

        $endMem = memory_get_usage();
        $logger->log("MEM:" . ($endMem - $startMem) . "({$startMem}-{$endMem}) / peak:" . memory_get_peak_usage());
        $logger->log("Total time:" . (microtime(true) - $startTime));
    }
}
