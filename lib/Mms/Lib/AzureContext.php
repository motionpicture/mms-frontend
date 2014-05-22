<?php
namespace Mms\Lib;

class AzureContext
{
    private static $instance = null;

    private function __construct()
    {
    }

    public static function getInstance($mode)
    {
        if (is_null(self::$instance)) {
            // azure設定値
            $azureIniArray = parse_ini_file(__DIR__ . '/../../../config/azure.ini', true);
            self::$azureConfig = $azureIniArray[$mode];
            self::$instance = new self;
        }

        return self::$instance;
    }

    public static function deleteInstance()
    {
        self::$instance = null;
        return self::$instance;
    }

    private static $azureConfig;
    private static $mediaServicesWrapper = null;
    private static $blobServicesWrapper = null;
    private static $blobAuthenticationScheme = null;

    /**
     * WindowsAzureメディアサービスを取得する
     *
     * @return WindowsAzure\MediaServices\Internal\IMediaServices
     */
    public function getMediaServicesWrapper()
    {
        if (is_null(self::$mediaServicesWrapper)) {
            // メディアサービス
            $settings = new \WindowsAzure\Common\Internal\MediaServicesSettings(
                self::$azureConfig['media_service_account_name'],
                self::$azureConfig['media_service_account_key'],
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
        if (is_null(self::$blobServicesWrapper)) {
            $connectionString =  sprintf(
                'DefaultEndpointsProtocol=%s;AccountName=%s;AccountKey=%s',
                'https',
                self::$azureConfig['storage_account_name'],
                self::$azureConfig['storage_account_key']
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
        if (is_null(self::$blobAuthenticationScheme)) {
            self::$blobAuthenticationScheme = new \WindowsAzure\Common\Internal\Authentication\SharedKeyAuthScheme(
                self::$azureConfig['storage_account_name'],
                self::$azureConfig['storage_account_key']
            );
        }

        return self::$blobAuthenticationScheme;
    }
}
?>
