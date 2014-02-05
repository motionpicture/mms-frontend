<?php
// デフォルトタイムゾーン
date_default_timezone_set('Asia/Tokyo');

require_once('MmsDb.php');
require_once dirname(__FILE__) . '/../vendor/autoload.php';

use WindowsAzure\Common\Internal\MediaServicesSettings;

class MmsSlim extends \Slim\Slim
{
    public $db;
    private static $mediaServicesWrapper = null;

    /**
     * Constructor
     * @param  array $userSettings Associative array of application settings
     */
    public function __construct(array $userSettings = array())
    {
        parent::__construct($userSettings);

        $this->db = new MmsDb();

        // DBにベーシック認証ユーザーが存在しなかれば登録
        $query = sprintf('SELECT * FROM user WHERE id = \'%s\';',
                        $_SERVER['PHP_AUTH_USER']);
        $user = $this->db->querySingle($query, true);
        if (!isset($user['id'])) {
            $query = sprintf("INSERT INTO user (id, created_at, updated_at) VALUES ('%s', datetime('now'), datetime('now'))",
                            $_SERVER['PHP_AUTH_USER']);
            $this->log->debug($query);
            if (!$this->db->exec($query)) {
                $egl = error_get_last();
                $e = new Exception('SQLの実行でエラーが発生しました' . $egl['message']);
                throw $e;
            }
        }

        $this->log->debug(print_r($_SERVER, true));
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
                'testmvtkms',
                'Vi3fX70rZKrtk/DM6TRoJ/XpxmkC29LNOzWimE06rx4=',
                'https://media.windows.net/API/',
                'https://wamsprodglobal001acs.accesscontrol.windows.net/v2/OAuth2-13'
            );
            self::$mediaServicesWrapper = WindowsAzure\Common\ServicesBuilder::getInstance()->createMediaServicesService($settings);
        }

        return self::$mediaServicesWrapper;
    }
}
?>