<?php
namespace Mms\Frontend\Lib;

// デフォルトタイムゾーン
date_default_timezone_set('Asia/Tokyo');

require_once dirname(__FILE__) . '/../../../vendor/autoload.php';

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

use WindowsAzure\Common\Internal\MediaServicesSettings;

class Slim extends \Slim\Slim
{
    public $azureContext;

    /**
     * Constructor
     * @param  array $userSettings Associative array of application settings
     */
    public function __construct(array $userSettings = array())
    {
        // 環境取得
        $modeFile = dirname(__FILE__) . '/../../../mode.php';
        if (false === is_file($modeFile)) {
            exit('The application "mode file" does not exist.');
        }
        require_once($modeFile);
        if (empty($mode)) {
            exit('The application "mode" does not exist.');
        }

        $userSettings['mode'] = $mode;

        // デバッグモード
        if ($mode == 'development') {
            $userSettings['debug'] = true;
            $userSettings['log.level'] = \Slim\Log::DEBUG;
        } else {
            $userSettings['debug'] = false;
            $userSettings['log.level'] = \Slim\Log::INFO;
        }

        // ログファイル指定
//        $this->logFile = dirname(__FILE__) . '/../../../log/mms_slim_' . $mode . '_' . date('Ymd') . '.log';
        $userSettings['log.writer'] = new \Slim\Extras\Log\DateTimeFileWriter(array(
            'path' => __DIR__ . "/../../../log/{$mode}",
            'name_format' => '\M\m\s\F\r\o\n\t\e\n\dYmd',
            'extension' => 'log',
            'message_format' => '%label% - %date% - %message%'
        ));

        parent::__construct($userSettings);

        $this->azureContext = \Mms\Lib\AzureContext::getInstance($userSettings['mode']);

        $this->tryCreateUser();
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

        // エラーハンドラー
        $this->error(function (\Exception $e) use ($context) {
            $context->log->error('route:{router}', array(
                'exception' => $e,
                'router' => print_r($context->router->getCurrentRoute()->getName(), true)
            ));

            return $context->render(
                'error.php',
                array(
                    'message' => $e->getMessage()
                )
            );
        });

        // 404
        $this->notFound(function () use ($context) {
            return $context->render(
                'notFound.php'
            );
        });

        parent::run();
    }

    private function tryCreateUser()
    {
        $pdo = \Mms\Lib\PDO::getInstance();

        // DBにベーシック認証ユーザーが存在しなかれば登録
        $query = sprintf('SELECT COUNT(id) as count FROM user WHERE id = \'%s\';',
                        $_SERVER['PHP_AUTH_USER']);
        $statement = $pdo->query($query);
        $count = $statement->fetchColumn();

        if ($count == 0) {
            $query = sprintf("INSERT INTO user (id, created_at, updated_at) VALUES ('%s', datetime('now', 'localtime'), datetime('now', 'localtime'))",
                            $_SERVER['PHP_AUTH_USER']);
            $this->log->debug($query);
            $result =  $this->db->exec($query);
            if ($result === false || $result === 0) {
                $egl = error_get_last();
                $e = new \Exception('SQLの実行でエラーが発生しました' . $egl['message']);
                throw $e;
            }
        }
    }

    /**
     * WindowsAzureメディアサービスを取得する
     *
     * @return WindowsAzure\MediaServices\Internal\IMediaServices
     */
    public function getMediaServicesWrapper()
    {
        return $this->azureContext->getMediaServicesWrapper();
    }
}
?>