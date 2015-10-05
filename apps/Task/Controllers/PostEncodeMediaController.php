<?php
namespace Mms\Task\Controllers;

use \WindowsAzure\MediaServices\Models\Job;
use \WindowsAzure\MediaServices\Models\AccessPolicy;
use \WindowsAzure\MediaServices\Models\Asset;
use \WindowsAzure\MediaServices\Models\Locator;

set_time_limit(0);
ini_set('memory_limit', '1024M');

/**
 * メディアサービスにてエンコード完了済みのメディアという文脈
 *
 * @package   Mms\Task\Controllers
 * @author    Tetsu Yamazaki <yamazaki@motionpicture.jp>
 */
class PostEncodeMediaController extends BaseController
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
//     function __construct($userSettings, $mediaId, $assetId, $jobId)
//     {
//         parent::__construct($userSettings);

//         if (!$mediaId || !$assetId || !$jobId) {
//             throw new \Exception('mediaId and assetId and jobId are required.');
//         }

//         self::$mediaId = $mediaId;
//         self::$assetId = $assetId;
//         self::$jobId = $jobId;
//     }

    /**
     * 公開終了日時の過ぎたメディアに関して
     * 削除状態に変更する
     */
    public function close()
    {
        // 未削除、かつ、公開終了日時の過ぎたメディアを取得
        $mediaIds = [];
        try {
            $where = "deleted_at = ''"
                   . " AND start_at IS NOT NULL AND end_at IS NOT NULL"
                   . " AND start_at <> '' AND end_at <> ''"
                   . " AND start_at < datetime('now', 'localtime') AND end_at < datetime('now', 'localtime')";
            $query = "SELECT id FROM media WHERE {$where}";
            $statement = $this->db->query($query);
            while ($res = $statement->fetch()) {
                $mediaIds[] = $res['id'];
            }
        } catch (\Exception $e) {
            $this->logger->log("selecting medias throw exception. message:{$e->getMessage()}");
            return;
        }
        $this->logger->log('mediaIds:' . print_r($mediaIds, true));

        $count4updateMedia = 0;
        if (!empty($mediaIds)) {
            try {
                // メディア削除
                $query = "UPDATE media SET updated_at = datetime('now', 'localtime'), deleted_at = datetime('now', 'localtime') WHERE id IN ('" . implode("','", $mediaIds) . "')";
                $this->logger->log("query:$query");
                $count4updateMedia = $this->db->exec($query);
            } catch (\Exception $e) {
                $this->logger->log("deleteWithTasks throw exception. message:{$e->getMessage()}");
            }
        }

        $this->logger->log("count4updateMedia:{$count4updateMedia}");
    }

    /**
     * 削除されたメディアに関して、ジョブやタスクをリセットする
     */
    public function reset4deleted()
    {
        $medias = [];

        try {
            // 削除済み、かつ、ジョブ未リセット状態のメディアを取得する
            $query = "SELECT id, asset_id, job_id FROM media WHERE deleted_at <> '' AND job_id <> ''";
            $result = $this->db->query($query);
            $medias = $result->fetchAll();
        } catch (\Exception $e) {
            $this->logger->log("selecting medias throw exception. message:{$e->getMessage()}");
        }

        $this->logger->log('medias2reset:' . print_r($medias, true));

        foreach ($medias as $media) {
            self::$mediaId = $media['id'];
            self::$assetId = $media['asset_id'];
            self::$jobId = $media['job_id'];

            $this->post2pre();
        }
    }

    /**
     * エンコード済みのメディアを、エンコード前の状態に戻す
     */
    public function reset()
    {
        $medias = [];

        try {
            // エンコード前の状態に戻したいメディアを取得する
            $query = "SELECT id, asset_id, job_id FROM media WHERE asset_id <> '' AND job_id <> '' AND job_state = '' AND deleted_at = ''";
            $result = $this->db->query($query);
            $medias = $result->fetchAll();
        } catch (\Exception $e) {
            $this->logger->log("selecting medias throw exception. message:{$e->getMessage()}");
        }

        $this->logger->log('medias2reset:' . count($medias));

        foreach ($medias as $media) {
            self::$mediaId = $media['id'];
            self::$assetId = $media['asset_id'];
            self::$jobId = $media['job_id'];

            $this->post2pre();
        }
    }

    /**
     * エンコード前の状態に戻す
     *
     * @return boolean
     */
    private function post2pre()
    {
        $this->logger->log('start function: ' . __FUNCTION__);

        // 失敗orキャンセル済みor完了のジョブをキャンセルしようとすると例外が投げられるので、チェックしてからキャンセル
        $result = false;
        try {
            $mediaServicesWrapper = $this->azureContext->getMediaServicesWrapper();
            $jobState = $mediaServicesWrapper->getJobStatus(self::$jobId);
            if ($jobState == Job::STATE_QUEUED
             || $jobState == Job::STATE_SCHEDULED
             || $jobState == Job::STATE_PROCESSING) {
                $mediaServicesWrapper->cancelJob(self::$jobId);
            }
        } catch (\Exception $e) {
            $message = 'reset throw exception. message:' . $e->getMessage();
            $this->logger->log($message);
            $this->reportError($message);
        }

        // 資産削除
        $this->deleteOutputAssets(self::$jobId);

        // DBリセット
        $result = $this->resetMedias([self::$mediaId]);

        $this->logger->log('post2pre $result:' . var_export($result, true));
        $this->logger->log('end function: ' . __FUNCTION__);

        return $result;
    }

    /**
     * inputアセットを生成しなおす
     */
    public function recreateInputAssets()
    {
        $medias = [];

        try {
            // エンコード前の状態に戻したいメディアを取得する
            $query = "SELECT id, asset_id, job_id FROM media WHERE asset_id <> '' AND deleted_at = ''";
            $result = $this->db->query($query);
            $medias = $result->fetchAll();
        } catch (\Exception $e) {
            $this->logger->log("selecting medias throw exception. message:{$e->getMessage()}");
        }

        $this->logger->log('medias2recreate:' . count($medias));

        foreach ($medias as $media) {
            try {
                self::$mediaId = $media['id'];
                self::$assetId = $media['asset_id'];
                self::$jobId = $media['job_id'];

                $this->recreateInputAsset();
            } catch (\Exception $e) {
                $this->logger->log("fail in recreateInputAsset: mediaId:{$media['id']} message:{$e->getMessage()}");
            }
        }
    }
    /**
     * inputアセットを生成しなおす
     * 
     * @throws \Exception
     */
    private function recreateInputAsset()
    {
        $this->logger->log('start function: ' . __FUNCTION__);

        try {
            // 旧メディアサービス
            $settings = new \WindowsAzure\Common\Internal\MediaServicesSettings(
                'pmmediasvcms',
                'YTL/qhDoPXKDYLz9DimqBiwH9B+RQWU4Vi8GHZ4mIFQ=',
                \WindowsAzure\Common\Internal\Resources::MEDIA_SERVICES_URL,
                \WindowsAzure\Common\Internal\Resources::MEDIA_SERVICES_OAUTH_URL
            );
            $oldMediaServicesWrapper = \WindowsAzure\Common\ServicesBuilder::getInstance()->createMediaServicesService($settings);

            $inputAsset = $oldMediaServicesWrapper->getAsset(self::$assetId);
        } catch (\Exception $e) {
            $this->logger->log("getAsset throw exception. message:{$e->getMessage()}");
            throw $e;
        }

        try {
            $mediaServicesWrapper = $this->azureContext->getMediaServicesWrapper();

            // 資産を作成する
            $destinationAsset = new Asset(Asset::OPTIONS_NONE);
            $destinationAsset->setName($inputAsset->getName());
            $destinationAsset = $mediaServicesWrapper->createAsset($destinationAsset);
            $destinationAssetId = $destinationAsset->getId();

            $this->logger->log('destinationAsset has been created. asset:' . $destinationAsset->getId());
        } catch (\Exception $e) {
            $this->logger->log('createAsset throw exception. message:' . $e->getMessage());
            throw $e;
        }

        try {
            $blobServicesWrapper = $this->azureContext->getBlobServicesWrapper();

            $destinationContainer = basename($destinationAsset->getUri());
            $sourceContainer = basename($inputAsset->getUri());

            // 元のアセット内のブロブファイルリストを取得
            $listBlobsResult = $blobServicesWrapper->listBlobs($sourceContainer);
            $listBlobs = $listBlobsResult->getBlobs();
            $this->logger->log('$listBlobs:' . print_r($listBlobs, true));

            foreach ($listBlobs as $blob) {
                $copyBlobResult = $blobServicesWrapper->copyBlob(
                    $destinationContainer,
                    $blob->getName(),
                    $sourceContainer,
                    $blob->getName()
                );

                $this->logger->log('copyBlobResult:' . print_r($copyBlobResult, true));
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

        try {
            // アセットIDを更新
            $query = "UPDATE media SET asset_id = '{$destinationAssetId}', job_state = '', job_id = '', updated_at = datetime('now', 'localtime') WHERE id = '" . self::$mediaId . "'";
            $this->logger->log("query:{$query}");
            $this->db->exec($query);
        } catch (\Exception $e) {
        }

        $this->logger->log('end function: ' . __FUNCTION__);
    }
}

?>