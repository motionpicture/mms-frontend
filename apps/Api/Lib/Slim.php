<?php
namespace Mms\Api\Lib;

// デフォルトタイムゾーン
date_default_timezone_set('Asia/Tokyo');

require_once __DIR__ . '/../../../vendor/autoload.php';

spl_autoload_register(function ($class) {
    $file = __DIR__ . '/../../../lib/' . strtr($class, '\\', DIRECTORY_SEPARATOR) . '.php';
    if (is_readable($file)) {
        require_once $file;
        return;
    }

    $file = __DIR__ . '/../../' . strtr(str_replace('Mms\\', '', $class), '\\', DIRECTORY_SEPARATOR) . '.php';
    if (is_readable($file)) {
        require_once $file;
        return;
    }
});

use \WindowsAzure\Common\Internal\MediaServicesSettings;

class Slim extends \Slim\Slim
{
    public $settei;

    /**
     * Constructor
     * @param  array $userSettings Associative array of application settings
     */
    public function __construct($userSettings = [])
    {
        $this->settei = \Mms\Lib\Settei::getInstance();

        $userSettings['mode'] = $this->settei->getMode();

        // デバッグモード
        if ($this->settei->isDev()) {
            $userSettings['debug'] = true;
            $userSettings['log.level'] = \Slim\Log::DEBUG;
        } else {
            $userSettings['debug'] = false;
            $userSettings['log.level'] = \Slim\Log::INFO;
        }

        // ログファイル指定
        $logDirectory = "{$this->settei->get('log_directory')}/{$this->settei->getMode()}/Api";
        if (!file_exists($logDirectory)) {
            mkdir($logDirectory, 0777, true);
            chmod($logDirectory, 0777);
        }
        $userSettings['log.writer'] = new \Slim\Extras\Log\DateTimeFileWriter([
            'path' => $logDirectory,
            'name_format' => '\M\m\s\A\p\iYmd',
            'extension' => 'log',
            'message_format' => '%label% - %date% - %message%'
        ]);

        parent::__construct($userSettings);

        if ($this->settei->isDev()) {
            $this->response->headers->set('Content-Type', 'text/html; charset=UTF-8');
        } else {
            $this->response->headers->set('Content-Type', 'application/json; charset=UTF-8');
        }
    }

    public function output($status, $message, $options = [])
    {
        $response = [
            'result' => [
                'status'  => $status,
                'message' => $message
            ]
        ];

        $response = array_merge($response, $options);

        return $this->render(
            'json.php',
            [
                'response' => $response
            ]
        );
    }

    /**
     * Run
     *
     * This method invokes the middleware stack, including the core Slim application;
     * the result is an array of HTTP status, header, and body. These three items
     * are returned to the HTTP client.
     */
    public function run()
    {
        $context = $this;

        // エラーハンドラー(非デバッグモードの場合のみ動作する)
        $this->error(function (\Exception $e) use ($context) {
            $context->log->error('route:{router}', [
                'exception' => $e,
                'router' => print_r($context->router->getCurrentRoute()->getName(), true)
            ]);

            return $this->output('FAILURE', $e->getMessage());
        });

        // 404
        $this->notFound(function () use ($context) {
            return $this->output('FAILURE', '404 Page Not Found');
        });

        parent::run();
    }
}
?>