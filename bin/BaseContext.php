<?php
namespace Mms\Bin;

ini_set('display_errors', 1);

// デフォルトタイムゾーン
date_default_timezone_set('Asia/Tokyo');

require_once dirname(__FILE__) . '/../vendor/autoload.php';

// 動画管理システムのライブラリ
spl_autoload_register(function ($class) {
    require_once dirname(__FILE__) . '/../lib/' . $class . '.php';
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

        $this->db = \Mms\Lib\PDO::getInstance($mode);

        $this->logFile = dirname(__FILE__) . '/../log/bin/mms_bin_' . date('Ymd') . '.log';

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

    public function getIsDev()
    {
        return self::$isDev;
    }

    function debug($content)
    {
        echo '<pre>';
        print_r($content);
        echo '</pre>';
    }
}
?>
