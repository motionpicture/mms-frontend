<?php
namespace Mms\Task\Controllers;

use \WindowsAzure\MediaServices\Models\Job;
use \WindowsAzure\MediaServices\Models\AccessPolicy;
use \WindowsAzure\MediaServices\Models\Asset;
use \WindowsAzure\MediaServices\Models\Locator;

set_time_limit(0);
ini_set('memory_limit', '1024M');

/**
 * メディアサービスにてエンコード中のメディアという文脈
 *
 * @package   Mms\Task\Controllers
 * @author    Tetsu Yamazaki <yamazaki@motionpicture.jp>
 */
class InEncodeMediaController extends BaseController
{
    /**
     * azureでアップロード済みのメディアID
     *
     * @var string
     */
    private static $id = null;

    /**
     * 登録済みのジョブID
     *
     * @var string
     */
    private static $jobId = null;

    /**
     * ジョブ進捗
     *
     * @var string
     */
    private static $jobState = null;

    /**
     * ジョブの状態が$QUEUED　or $SCHEDULED or $PROCESSINGのメディアに関して
     * ジョブ進捗を確信し、完了していればURLを発行する
     */
    public function check()
    {
        $media = false;

        try {
            // ジョブの状態が$QUEUED　or $SCHEDULED or $PROCESSINGのメディアを取得する
            $query = sprintf('SELECT * FROM media WHERE job_state = \'%s\' OR job_state = \'%s\' OR job_state = \'%s\' ORDER BY updated_at ASC LIMIT 1',
                            \WindowsAzure\MediaServices\Models\Job::STATE_QUEUED,
                            \WindowsAzure\MediaServices\Models\Job::STATE_SCHEDULED,
                            \WindowsAzure\MediaServices\Models\Job::STATE_PROCESSING);
            $result = $this->db->query($query);
            $media = $result->fetch();
        } catch (\Exception $e) {
            $this->logger->log("selecting medias throw exception. message:{$e->getMessage()}");
        }

        if (!$media) {
            $this->logger->log('no medias to check.');
            return false;
        }

        $this->logger->log("now checking job state... id:{$media['id']}");

        try {
            self::$id = $media['id'];
            self::$jobId = $media['job_id'];
            self::$jobState = $media['job_state'];

            $url = $this->tryDeliverMedia();

            // URLが発行されればメール送信
            if (!is_null($url)) {
                $this->sendEmail($media);
            }
        } catch (\Exception $e) {
            $message = "tryDeliverMedia throw exception. mediaId:{$media['id']} message:{$e->getMessage()}";
            $this->logger->log($message);
            $this->reportError($message);
        }
    }

    /**
     * ジョブの進捗を確認しステータス更新、完了していればURLを発行する
     *
     * @return string $url 発行されたURLをひとつ
     */
    private function tryDeliverMedia()
    {
        $this->logger->log('start function: ' . __FUNCTION__);

        $job = null;
        $url = null;

        $mediaServicesWrapper = $this->azureContext->getMediaServicesWrapper();

        try {
            // メディアサービスよりジョブを取得
            $job = $mediaServicesWrapper->getJob(self::$jobId);
        } catch (\Exception $e) {
            $message = '$mediaServicesWrapper->getJob() throw exception. jobId:' . self::$jobId . ' message:' . $e->getMessage();
            $this->logger->log($message);
            $this->reportError($message);
        }

        if (!is_null($job)) {
            // ジョブのステータスを更新
            if (self::$jobState == $job->getState()) {
                // 進捗に変化がなければ終了
                $this->logger->log('job state unchanging.');
                return $url;
            }

            // トランザクションの開始
            $this->db->beginTransaction();

            try {
                // ジョブが完了の場合、URL発行プロセス
                if ($job->getState() == Job::STATE_FINISHED) {
                    // 念のため、すでにURL発行されていれば全て削除
                    $query = "DELETE FROM task WHERE media_id = '" . self::$id . "'";
                    $this->logger->log('$query: ' . $query);
                    $this->db->exec($query);

                    // ジョブに関する情報更新
                    $query = sprintf("UPDATE media SET job_state = '%s', job_start_at = '%s', job_end_at = '%s', updated_at = %s WHERE id = '%s';",
                                    $job->getState(),
                                    date('Y-m-d H:i:s', strtotime('+9 hours', $job->getStartTime()->getTimestamp())),
                                    date('Y-m-d H:i:s', strtotime('+9 hours', $job->getEndTime()->getTimestamp())),
                                    'datetime(\'now\', \'localtime\')',
                                    self::$id);
                    $this->logger->log('$query: ' . $query);
                    $this->db->exec($query);

                    // ジョブのアウトプットアセットを取得
                    $assets = $mediaServicesWrapper->getJobOutputMediaAssets($job->getId());
                    foreach ($assets as $asset) {
                        // 必要なタスクのみURLを発行する
                        $assetNames4deliver = $this->getAssetNames4deliver();

                        if (in_array($asset->getName(), $assetNames4deliver)) {
                            $urls = $this->createUrls($asset->getId(), $asset->getName(), self::$id);

                            // タスク追加
                            foreach ($urls as $name => $url) {
                                $query = sprintf("INSERT INTO task (media_id, name, url, created_at, updated_at) VALUES ('%s', '%s', '%s', %s, %s)",
                                    self::$id,
                                    $name,
                                    $url,
                                    'datetime(\'now\', \'localtime\')',
                                    'datetime(\'now\', \'localtime\')');
                                $this->logger->log('$query: ' . $query);
                                $this->db->exec($query);
                            }
                        }
                    }
                // 未完了の場合、ステータスの更新のみ
                } else {
                    $this->logger->log('job state changed.');
                    $query = sprintf("UPDATE media SET job_state = '%s', updated_at = %s WHERE id = '%s'",
                                    $job->getState(),
                                    'datetime(\'now\', \'localtime\')',
                                    self::$id);
                    $this->logger->log('$query: ' . $query);
                    $this->db->exec($query);
                }

                $this->db->commit();
            } catch (\Exception $e) {
                $this->db->rollBack();
                $message = 'delivering url for streaming throw exception. message:' . $e->getMessage();
                $this->logger->log($message);
                $this->reportError($message);
            }
        }

        $this->logger->log('$url:' . print_r($url, true));
        $this->logger->log('end function: ' . __FUNCTION__);

        return $url;
    }

    /**
     * URL発行のターゲットとなるアセット名リストを取得する
     *
     * @return array
     */
    private function getAssetNames4deliver()
    {
        return [
            \Mms\Lib\Models\Task::toAssetName(self::$id, \Mms\Lib\Models\Task::NAME_ADAPTIVE_BITRATE_MP4),
//             \Mms\Lib\Models\Task::toAssetName(self::$id, \Mms\Lib\Models\Task::NAME_SMOOTH_STREAMING),
//             \Mms\Lib\Models\Task::toAssetName(self::$id, \Mms\Lib\Models\Task::NAME_SMOOTH_STREAMING_PLAYREADY),
//             \Mms\Lib\Models\Task::toAssetName(self::$id, \Mms\Lib\Models\Task::NAME_HLS),
//             \Mms\Lib\Models\Task::toAssetName(self::$id, \Mms\Lib\Models\Task::NAME_HLS_PLAYREADY)
        ];
    }

    /**
     * アセットに対してストリーミングURLを生成する
     * 動的パッケージングを使用しているため、ひとつのアセットから複数のストリームタイプのURLを生成できる可能性がある
     * @see http://msdn.microsoft.com/ja-jp/library/jj889436.aspx
     *
     * @param string $assetId
     * @param string $assetName
     * @return array
     */
    private function createUrls($assetId, $assetName)
    {
        $this->logger->log('start function: ' . __FUNCTION__);
        $this->logger->log('args: ' . print_r(func_get_args(), true));

        $mediaServicesWrapper = $this->azureContext->getMediaServicesWrapper();

        // 特定のAssetに対して、同時に5つを超える一意のLocatorを関連付けることはできない
        // 万が一OnDemandOriginロケーターがあれば削除
        $locators = $mediaServicesWrapper->getAssetLocators($assetId);
        foreach ($locators as $locator) {
            if ($locator->getType() == Locator::TYPE_ON_DEMAND_ORIGIN) {
                $mediaServicesWrapper->deleteLocator($locator);
                $this->logger->log('OnDemandOrigin locator has been deleted. $locator: '. print_r($locator, true));
            }
        }

        // 読み取りアクセス許可を持つAccessPolicyの作成
        $accessPolicy = new AccessPolicy('StreamingPolicy');
        $accessPolicy->setDurationInMinutes(25920000);
        $accessPolicy->setPermissions(AccessPolicy::PERMISSIONS_READ);
        $accessPolicy = $mediaServicesWrapper->createAccessPolicy($accessPolicy);

        // コンテンツストリーミング用の配信元URLの作成
        $locator = new Locator($assetId, $accessPolicy, Locator::TYPE_ON_DEMAND_ORIGIN);
        $locator->setName('StreamingLocator_' . $assetId);
        $locator->setStartTime(new \DateTime('now -5 minutes'));
        $locator = $mediaServicesWrapper->createLocator($locator);

        // URLを生成
        $urls = [];
        switch ($assetName) {
            // adaptive bitrate mp4のアセットからは、mpeg dash, smooth streaming, hlsの3つのURLを生成
            case \Mms\Lib\Models\Task::toAssetName(self::$id, \Mms\Lib\Models\Task::NAME_ADAPTIVE_BITRATE_MP4):
                $urls[\Mms\Lib\Models\Task::NAME_MPEG_DASH] = sprintf('%s%s.ism/Manifest(format=mpd-time-csf)', $locator->getPath(), self::$id);
                $urls[\Mms\Lib\Models\Task::NAME_SMOOTH_STREAMING] = sprintf('%s%s.ism/Manifest', $locator->getPath(), self::$id);
                $urls[\Mms\Lib\Models\Task::NAME_HLS] = sprintf('%s%s.ism/Manifest(format=m3u8-aapl)', $locator->getPath(), self::$id);
                break;
//             case \Mms\Lib\Models\Task::toAssetName(self::$id, \Mms\Lib\Models\Task::NAME_HLS):
//             case \Mms\Lib\Models\Task::toAssetName(self::$id, \Mms\Lib\Models\Task::NAME_HLS_PLAYREADY):
//                 $urls[$assetName] = sprintf('%s%s-m3u8-aapl.ism/Manifest(format=m3u8-aapl)', $locator->getPath(), self::$id);
//                 break;
            default:
                break;
        }

        $this->logger->log('urls have been created: ' . print_r($urls, true));
        $this->logger->log('end function: ' . __FUNCTION__);

        return $urls;
    }
}

?>