<?php
ini_set('display_errors', 1);

// デフォルトタイムゾーン
date_default_timezone_set('Asia/Tokyo');

require_once dirname(__FILE__) . '/../vendor/autoload.php';

use WindowsAzure\Common\Internal\MediaServicesSettings;

class MmsBinActions
{
    public $db;
    public $logFile;
    private static $mediaServicesWrapper = null;
    private static $blobServicesWrapper = null;
    private static $blobAuthenticationScheme = null;
    private static $isDev = false;

    function __construct()
    {
        $this->db = new PDO('sqlite:' . dirname(__FILE__) . '/../db/mms.db');

        $this->logFile = dirname(__FILE__) . '/../log/mms_bin.log';

        $options = getopt('', array('env:'));
        if (isset($options['env']) && $options['env'] == 'dev') {
            self::$isDev = true;
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
                WindowsAzure\Common\Internal\Resources::MEDIA_SERVICES_URL,
                WindowsAzure\Common\Internal\Resources::MEDIA_SERVICES_OAUTH_URL
            );
            self::$mediaServicesWrapper = WindowsAzure\Common\ServicesBuilder::getInstance()->createMediaServicesService($settings);
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
                'testmvtkmsst',
                '+aoUiBttXAZovixNHuNxnkNaMbj2ZWDBzJvkG+FQ0EMmwbGtvEgryoqlQDkq+OxmQomRDQCKZitgeGfAk299Lg=='
            );
            self::$blobServicesWrapper = WindowsAzure\Common\ServicesBuilder::getInstance()->createBlobService($connectionString);
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
            self::$blobAuthenticationScheme = new WindowsAzure\Common\Internal\Authentication\SharedKeyAuthScheme(
                'testmvtkmsst',
                '+aoUiBttXAZovixNHuNxnkNaMbj2ZWDBzJvkG+FQ0EMmwbGtvEgryoqlQDkq+OxmQomRDQCKZitgeGfAk299Lg=='
            );
        }

        return self::$blobAuthenticationScheme;
    }

    function log($content)
    {
        $log = print_r($content, true) . "\n";
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
