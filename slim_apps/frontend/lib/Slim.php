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
    public $azureConfig;
    private static $mediaServicesWrapper = null;

    /**
     * Constructor
     * @param  array $userSettings Associative array of application settings
     */
    public function __construct(array $userSettings = array())
    {
        parent::__construct($userSettings);

        $this->db = \Mms\Lib\PDO::getInstance($userSettings['mode']);

        // azure設定値
        $azureIniArray = parse_ini_file(dirname(__FILE__) . '/../../../config/azure.ini', true);
        $this->azureConfig = $azureIniArray[$userSettings['mode']];

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
                $e = new Exception('SQLの実行でエラーが発生しました' . $egl['message']);
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
        if (!isset(self::$mediaServicesWrapper)) {
            // メディアサービス
            $settings = new MediaServicesSettings(
                $this->azureConfig['media_service_account_name'],
                $this->azureConfig['media_service_account_key'],
                \WindowsAzure\Common\Internal\Resources::MEDIA_SERVICES_URL,
                \WindowsAzure\Common\Internal\Resources::MEDIA_SERVICES_OAUTH_URL
            );
            self::$mediaServicesWrapper = \WindowsAzure\Common\ServicesBuilder::getInstance()->createMediaServicesService($settings);
        }

        return self::$mediaServicesWrapper;
    }
}
?>