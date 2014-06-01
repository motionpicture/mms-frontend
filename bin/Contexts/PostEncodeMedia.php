<?php
namespace Mms\Bin\Contexts;

require_once __DIR__ . '/../BaseContext.php';

use WindowsAzure\MediaServices\Models\Job;
use WindowsAzure\MediaServices\Models\AccessPolicy;
use WindowsAzure\MediaServices\Models\Asset;
use WindowsAzure\MediaServices\Models\Locator;

set_time_limit(0);
ini_set('memory_limit', '1024M');

/**
 * メディアサービスにてエンコード完了済みのメディアという文脈
 *
 * @package   Mms\Bin\Contexts
 * @author    Tetsu Yamazaki <yamazaki@motionpicture.jp>
 */
class PostEncodeMedia extends \Mms\Bin\BaseContext
{
    /**
     * azureでアップロード済みのメディアID
     *
     * @var string
     */
    private static $mediaId = null;

    /**
     * アップロード先のアセットID
     *
     * @var string
     */
    private static $assetId = null;

    /**
     * 登録済みのジョブID
     *
     * @var string
     */
    private static $jobId = null;

    /**
     * __construct
     *
     * @param string $mediaId メディアID
     * @param string $assetId アセットID
     * @param string $jobId   ジョブID
     */
    function __construct($userSettings, $mediaId, $assetId, $jobId)
    {
        parent::__construct($userSettings);

        if (!$mediaId || !$assetId || !$jobId) {
            throw new \Exception('mediaId and assetId and jobId are required.');
        }

        self::$mediaId = $mediaId;
        self::$assetId = $assetId;
        self::$jobId = $jobId;
    }

    /**
     * エンコード前の状態に戻す
     *
     * @return boolean
     */
    public function post2pre()
    {
        $this->logger->log("\n--------------------\n" . 'start function: ' . __FUNCTION__ . "\n--------------------\n");

        // ジョブキャンセル&資産削除
        $result = false;
        try {
            // 失敗orキャンセル済みor完了のジョブをキャンセルしようとすると例外が投げられるので、チェックしてからキャンセル
            $mediaServicesWrapper = $this->azureContext->getMediaServicesWrapper();
            $jobState = $mediaServicesWrapper->getJobStatus(self::$jobId);
            if ($jobState == Job::STATE_QUEUED
             || $jobState == Job::STATE_SCHEDULED
             || $jobState == Job::STATE_PROCESSING) {
                $mediaServicesWrapper->cancelJob(self::$jobId);
            }

            $this->deleteOutputAssets(self::$jobId);
            $result = $this->resetMedias([self::$mediaId]);
        } catch (\Exception $e) {
            $message = 'reset throw exception. message:' . $e->getMessage();
            $this->logger->log($message);
            $this->reportError($message);
        }

        $this->logger->log('post2pre $result:' . print_r($result, true));
        $this->logger->log("\n--------------------\n" . 'end function: ' . __FUNCTION__ . "\n--------------------\n");

        return $result;
    }

    /*
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
    */
}

?>