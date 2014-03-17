<?php
namespace Mms\Frontend;

// デフォルトタイムゾーン
date_default_timezone_set('Asia/Tokyo');

require_once dirname(__FILE__) . '/../../../vendor/autoload.php';
require_once dirname(__FILE__) . '/PDO.php';

use WindowsAzure\Common\Internal\MediaServicesSettings;

class Slim extends \Slim\Slim
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

        $this->db = PDO::getInstance($userSettings['mode']);

//         $this->init();

        $this->tryCreateUser();

        $this->log->debug(print_r($_SERVER, true));
    }

    function init()
    {
        $query = <<< EOF
DROP TABLE media;
DROP TABLE task;
DROP TABLE category;
DROP TABLE user;

CREATE TABLE `media` (
    `id` text NOT NULL PRIMARY KEY,
    `code` text NOT NULL,
    `mcode` text NOT NULL,
    `category_id` integer NOT NULL,
    `version` integer NOT NULL DEFAULT '0',
    `size` integer DEFAULT NULL,
    `extension` text NOT NULL,
    `user_id` text NOT NULL,
    `movie_name` text DEFAULT NULL,
    `movie_ename` text DEFAULT NULL,
    `playtime_string` text DEFAULT NULL,
    `playtime_seconds` real DEFAULT NULL,
    `job_id` text DEFAULT NULL,
    `job_state` text DEFAULT NULL,
    `job_start_at` text DEFAULT NULL,
    `job_end_at` text DEFAULT NULL,
    `start_at` text DEFAULT NULL,
    `end_at` text DEFAULT NULL,
    `created_at` text NOT NULL,
    `updated_at` text NOT NULL
);

CREATE TABLE `task` (
    `media_id` text NOT NULL,
    `name` text NOT NULL,
    `url` text DEFAULT NULL,
    `created_at` text NOT NULL,
    `updated_at` text NOT NULL
);

CREATE TABLE `category` (
    `id` integer NOT NULL PRIMARY KEY AUTOINCREMENT,
    `name` text NOT NULL,
    `created_at` text NOT NULL,
    `updated_at` text NOT NULL
);

CREATE TABLE `user` (
    `id` text NOT NULL PRIMARY KEY,
    `name` text DEFAULT NULL,
    `email` text DEFAULT NULL,
    `created_at` text NOT NULL,
    `updated_at` text NOT NULL
);

BEGIN;
INSERT INTO category (name, created_at, updated_at) VALUES('特報', datetime('now'), datetime('now'));
INSERT INTO category (name, created_at, updated_at) VALUES('予告編', datetime('now'), datetime('now'));
INSERT INTO category (name, created_at, updated_at) VALUES('本編', datetime('now'), datetime('now'));
INSERT INTO category (name, created_at, updated_at) VALUES('その他', datetime('now'), datetime('now'));
COMMIT;
EOF;
        $result =  $this->db->exec($query);
        if ($result === false || $result === 0) {
            $egl = error_get_last();
            $e = new Exception('SQLの実行でエラーが発生しました' . $egl['message']);
            throw $e;
        }
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