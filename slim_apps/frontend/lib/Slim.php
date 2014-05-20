<?php
namespace Mms\Frontend\Lib;

// デフォルトタイムゾーン
date_default_timezone_set('Asia/Tokyo');

require_once dirname(__FILE__) . '/../../../vendor/autoload.php';

spl_autoload_register(function ($class) {
    require_once dirname(__FILE__) . '/../../../lib/' . strtr($class, '\\', DIRECTORY_SEPARATOR) . '.php';
});

use WindowsAzure\Common\Internal\MediaServicesSettings;

class Slim extends \Slim\Slim
{
    public $db;
    public $logFile;
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
        } else {
            $userSettings['debug'] = false;
        }

        // ログファイル指定
        $this->logFile = dirname(__FILE__) . '/../../../log/mms_slim_' . $mode . '_' . date('Ymd') . '.log';
        $userSettings['log.writer'] = new \Slim\LogWriter(fopen($this->logFile, 'a+'));

        parent::__construct($userSettings);

        $this->db = \Mms\Lib\PDO::getInstance($userSettings['mode']);

        $this->azureContext = new \Mms\Lib\AzureContext($userSettings['mode']);

        $this->tryCreateUser();

        $this->log->debug(print_r($_SERVER, true));
    }

    private function tryCreateUser()
    {
        // DBにベーシック認証ユーザーが存在しなかれば登録
        $query = sprintf('SELECT COUNT(id) as count FROM user WHERE id = \'%s\';',
                        $_SERVER['PHP_AUTH_USER']);
        $statement = $this->db->query($query);
        $count = $statement->fetchColumn();

        if ($count == 0) {
            $query = sprintf("INSERT INTO user (id, created_at, updated_at) VALUES ('%s', datetime('now'), datetime('now'))",
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