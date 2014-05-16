<?php
namespace Mms\Bin;

ini_set('display_errors', 1);

// デフォルトタイムゾーン
date_default_timezone_set('Asia/Tokyo');

require_once dirname(__FILE__) . '/../vendor/autoload.php';

spl_autoload_register(function ($class) {
    require_once dirname(__FILE__) . '/../lib/' . strtr($class, '\\', DIRECTORY_SEPARATOR) . '.php';
});

class BaseContext
{
    public $db;
    public $logger;
    public $azureContext;
    public $userSettings;
    private static $isDev = false;
    private static $mode;

    function __construct($userSettings = [])
    {
        $this->userSettings = $userSettings;
        self::$mode = $userSettings['mode'];

        if (self::$mode == 'development') {
            self::$isDev = true;
        }

        $this->logger = \Mms\Lib\Logger::getInstance();
        $this->logger->initialize(
            $userSettings['logFile'],
            self::$isDev,
            self::$isDev
        );

        $this->azureContext = new \Mms\Lib\AzureContext(self::$mode);

        $this->db = \Mms\Lib\PDO::getInstance(self::$mode);
    }

    /**
     * 環境文字列を取得する
     *
     * @return string
     */
    public function getMode()
    {
        return self::$mode;
    }

    /**
     * 開発環境かどうかを取得する
     *
     * @return boolean
     */
    public function getIsDev()
    {
        return self::$isDev;
    }

    /**
     * エラー通知
     *
     * @param string $message
     */
    function reportError($message)
    {
        $email = 'ilovegadd@gmail.com';
        $subject = '[ムビチケ動画管理システム]エラー通知';
        $headers = 'From: webmaster@pmmedia.cloudapp.net' . "\r\n"
                 . 'Reply-To: webmaster@pmmedia.cloudapp.net';
        if (!mail($email, $subject, $message, $headers)) {
            $egl = error_get_last();
            $this->log('reportError throw exception. message:' . $egl['message']);
        }
    }
}
?>
