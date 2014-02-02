<?php
ini_set('display_errors', 1);

// デフォルトタイムゾーン
date_default_timezone_set('Asia/Tokyo');

require_once dirname(__FILE__) . '/../lib/MmsDb.php';
require_once dirname(__FILE__) . '/../vendor/autoload.php';

use WindowsAzure\Common\Internal\MediaServicesSettings;

class MmsBinActions
{
    public $db;
    public $logFile;
    private static $mediaServicesWrapper = null;

    function __construct()
    {
        $this->db = new MmsDb();

        $this->logFile = dirname(__FILE__) . '/mms_bin.log';
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

    function log($content)
    {
        file_put_contents($this->logFile, print_r($content, true) . "\n", FILE_APPEND);
    }

    function debug($content)
    {
        echo '<pre>';
        print_r($content);
        echo '</pre>';
    }
}
?>
