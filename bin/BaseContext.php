<?php
namespace Mms\Bin;

ini_set('display_errors', 1);

// デフォルトタイムゾーン
date_default_timezone_set('Asia/Tokyo');

require_once dirname(__FILE__) . '/../vendor/autoload.php';

// 動画管理システムのライブラリ
spl_autoload_register(function ($class) {
    require_once dirname(__FILE__) . '/../lib/' . strtr($class, '\\', DIRECTORY_SEPARATOR) . '.php';
});

class BaseContext
{
    public $db;
    public $logFile;
    public $azureConfig;
    private static $mediaServicesWrapper = null;
    private static $blobServicesWrapper = null;
    private static $blobAuthenticationScheme = null;
    private static $isDev = false;
    private static $mode;

    function __construct()
    {
        // 環境取得
        $modeFile = dirname(__FILE__) . '/../mode.php';
        if (false === is_file($modeFile)) {
            exit('The application "mode file" does not exist.');
        }
        require_once($modeFile);
        if (empty($mode)) {
            exit('The application "mode" does not exist.');
        }

        self::$mode = $mode;
        $this->db = \Mms\Lib\PDO::getInstance($mode);

        $this->logFile = dirname(__FILE__) . '/../log/bin/mms_bin_' . $mode . '_' . date('Ymd') . '.log';

        if ($mode == 'development') {
            self::$isDev = true;
        }

        // azure設定値
        $azureIniArray = parse_ini_file(dirname(__FILE__) . '/../config/azure.ini', true);
        if ($this->getIsDev()) {
            $this->azureConfig = $azureIniArray['development'];
        } else {
            $this->azureConfig = $azureIniArray['production'];
        }
    }

    /**
     * WindowsAzureメディアサービスを取得する
     *
     * @return WindowsAzure\MediaServices\Internal\IMediaServices
     */
    public function getMediaServicesWrapper()
    {
        if (!isset(self::$mediaServicesWrapper)) {
            // メディアサービス
            $settings = new \WindowsAzure\Common\Internal\MediaServicesSettings(
                $this->azureConfig['media_service_account_name'],
                $this->azureConfig['media_service_account_key'],
                \WindowsAzure\Common\Internal\Resources::MEDIA_SERVICES_URL,
                \WindowsAzure\Common\Internal\Resources::MEDIA_SERVICES_OAUTH_URL
            );
            self::$mediaServicesWrapper = \WindowsAzure\Common\ServicesBuilder::getInstance()->createMediaServicesService($settings);
        }

        return self::$mediaServicesWrapper;
    }

    /**
     * WindowsAzureストレージサービスを取得する
     *
     * @return WindowsAzure\Blob\Internal\IBlob
     */
    public function getBlobServicesWrapper()
    {
        if (!isset(self::$blobServicesWrapper)) {
            $connectionString =  sprintf(
                'DefaultEndpointsProtocol=%s;AccountName=%s;AccountKey=%s',
                'https',
                $this->azureConfig['storage_account_name'],
                $this->azureConfig['storage_account_key']
            );
            self::$blobServicesWrapper = \WindowsAzure\Common\ServicesBuilder::getInstance()->createBlobService($connectionString);
        }

        return self::$blobServicesWrapper;
    }

    /**
     * SharedKeyAuthSchemeを取得する
     *
     * @return WindowsAzure\Common\Internal\Authentication\SharedKeyAuthScheme
     */
    public function getBlobAuthenticationScheme()
    {
        if (!isset(self::$blobAuthenticationScheme)) {
            self::$blobAuthenticationScheme = new \WindowsAzure\Common\Internal\Authentication\SharedKeyAuthScheme(
                $this->azureConfig['storage_account_name'],
                $this->azureConfig['storage_account_key']
            );
        }

        return self::$blobAuthenticationScheme;
    }

    /**
     * ログ出力
     *
     * @param string $content
     * @return none
     */
    function log($content)
    {
        $log = print_r($content, true) . "\n";

        // ディレクトリがなければ再帰的に作成
        if (!file_exists(dirname($this->logFile))) {
            mkdir(dirname($this->logFile), 0777, true);
            chmod(dirname($this->logFile), 0777);
        }

        file_put_contents($this->logFile, $log, FILE_APPEND);

        if ($this->getIsDev()) {
            echo $log;
        }
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
     * デバッグ出力
     *
     * @param string $content
     * @return none
     */
    function debug($content)
    {
        // 開発環境のみログ出力
        if ($this->getIsDev()) {
            $this->log($content);
        }
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
