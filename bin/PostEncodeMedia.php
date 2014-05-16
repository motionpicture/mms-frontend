<?php
namespace Mms\Bin;

require_once('BaseContext.php');

use WindowsAzure\MediaServices\Models\Job;
use WindowsAzure\MediaServices\Models\AccessPolicy;
use WindowsAzure\MediaServices\Models\Asset;
use WindowsAzure\MediaServices\Models\Locator;

set_time_limit(0);
ini_set('memory_limit', '1024M');

class PostEncodeMedia extends BaseContext
{
    private static $mediaId = null;
    private static $jobId = null;
    private static $inputAsset = null;

    function __construct($userSettings, $mediaId, $jobId)
    {
        parent::__construct();

        self::$mediaId = $mediaId;
        self::$jobId = $jobId;

        try {
            $mediaServicesWrapper = $this->azureContext->getMediaServicesWrapper();

            // ジョブのアセットを取得
            $inputAssets = $mediaServicesWrapper->getJobInputMediaAssets($jobId);
            $this->debug('$inputAssets:' . print_r($inputAssets, true));

            self::$inputAsset = $inputAssets[0];
            $this->logger->log('$inputAsset:' . print_r(self::$inputAsset, true));
        } catch (Exception $e) {
            $this->logger->log('deleteAssets throw exception. jobId:' . $jobId . ' message:' . $e->getMessage());
        }
    }

    public function reencode()
    {
        $preEncodeMedia = new \Mms\Bin\PreEncodeMedia(
            $this->$userSettings,
            self::$mediaId,
            self::$inputAsset->getId()
        );
        $preEncodeMedia->encode();
    }

    public function recreateInputAsset()
    {
        $this->logger->log("\n--------------------\n" . 'start function: ' . __FUNCTION__ . "\n--------------------\n");
        $this->logger->log('args: ' . print_r(func_get_args(), true));

        try {
            $mediaServicesWrapper = $this->azureContext->getMediaServicesWrapper();

            // 資産を作成する
            $destinationAsset = new Asset(Asset::OPTIONS_NONE);
            $destinationAsset->setName(self::$inputAsset->getName());
            $destinationAsset = $mediaServicesWrapper->createAsset($destinationAsset);

            $this->logger->log('destinationAsset has been created. asset:' . $destinationAsset->getId());
        } catch (\Exception $e) {
            $this->logger->log('createAsset throw exception. message:' . $e->getMessage());
            throw $e;
        }

        try {
          $blobRestProxy = $this->getBlobServicesWrapper();

          $destinationContainer = basename($destinationAsset->getUri());
          $sourceContainer = basename(self::$inputAsset->getUri());

          // 元のアセット内のブロブファイルリストを取得
          $listBlobsResult = $blobRestProxy->listBlobs($sourceContainer);
          $listBlobs = $listBlobsResult->getBlobs();
          $this->logger->log('$listBlobs:' . print_r($listBlobs, true));

          foreach ($listBlobs as $blob) {
              $copyBlobResult = $blobRestProxy->copyBlob(
                  $destinationContainer,
                  $blob->getName(),
                  $sourceContainer,
                  $blob->getName()
              );

              $this->logger->log('$copyBlobResult:' . print_r($copyBlobResult, true));
          }
        } catch (\Exception $e) {
            $this->logger->log('copyBlob throw exception. message:' . $e->getMessage());
            throw $e;
        }

        try {
            // ファイル メタデータの生成
            $mediaServicesWrapper->createFileInfos($destinationAsset);

            $this->logger->log('destinationAsset has been prepared. destinationAsset:' . $destinationAsset->getId());
        } catch (\Exception $e) {
            $this->logger->log('createFileInfos throw exception. message:' . $e->getMessage());
            throw $e;
        }

        $this->logger->log("\n--------------------\n" . 'end function: ' . __FUNCTION__ . "\n--------------------\n");
    }
}

?>