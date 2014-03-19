<?php
namespace Mms\Bin;

require_once('BaseContext.php');

use WindowsAzure\MediaServices\Models\Job;
use WindowsAzure\MediaServices\Models\AccessPolicy;
use WindowsAzure\MediaServices\Models\Asset;
use WindowsAzure\MediaServices\Models\Locator;

class JobState extends BaseContext
{
    function __construct()
    {
        parent::__construct();

        $this->logFile = dirname(__FILE__) . '/../log/check_job_' . date('Ymd') . '.log';
    }

    /**
     * ジョブ進捗を確認して更新する
     */
    public function update()
    {
        $medias = [];

        try {
            // ジョブの状態が$QUEUED　or $SCHEDULED or $PROCESSINGのメディアに関してジョブの状況を確認する
            $query = sprintf('SELECT * FROM media WHERE job_state = \'%s\' OR job_state = \'%s\' OR job_state = \'%s\'',
                            Job::STATE_QUEUED,
                            Job::STATE_SCHEDULED,
                            Job::STATE_PROCESSING);
            $result = $this->db->query($query);
            $medias = $result->fetchAll();
        } catch (\Exception $e) {
            $this->log($e->getMessage());
        }

        foreach ($medias as $media) {
            $this->log("\n--------------------\n" . $media['id'] . ' checking job state...' . "\n--------------------\n");
            try {
                $url = $this->tryDeliverMedia($media['id'], $media['job_id'], $media['job_state']);

                // URLが発行されればメール送信
                if (!is_null($url)) {
                    $this->sendEmail($media['code'], $media['user_id']);
                }
            } catch (\Exception $e) {
                $this->log($e->getMessage());
            }
        }
    }

    /**
     * ジョブの進捗を確認しステータス更新、完了していればURLを発行する
     *
     * @param string $mediaId
     * @param string $jobId
     * @param string $jobState
     * @return string $url 発行されたURLをひとつ
     */
    private function tryDeliverMedia($mediaId, $jobId, $jobState)
    {
        $this->log("\n--------------------\n" . 'start function: ' . __FUNCTION__ . "\n--------------------\n");
        $this->log('args: ' . print_r(func_get_args(), true));

        $job = null;
        $url = null;

        $mediaServicesWrapper = $this->getMediaServicesWrapper();

        try {
            // メディアサービスよりジョブを取得
            $job = $mediaServicesWrapper->getJob($jobId);
        } catch (\Exception $e) {
            $this->log('fail in getting job from media service: ' . $e->getMessage());
        }

        if (!is_null($job)) {
            // ジョブのステータスを更新
            if ($jobState == $job->getState()) {
                // 進捗に変化がなければ終了
                return $url;
            }

            // トランザクションの開始
            $this->db->beginTransaction();

            try {
                // ジョブが完了の場合、URL発行プロセス
                if ($job->getState() == Job::STATE_FINISHED) {
                    // ジョブに関する情報更新
                    $query = sprintf("UPDATE media SET job_state = '%s', job_start_at = '%s', job_end_at = '%s', updated_at = %s WHERE id = '%s';",
                                    $job->getState(),
                                    date('Y-m-d H:i:s', strtotime('+9 hours', $job->getStartTime()->getTimestamp())),
                                    date('Y-m-d H:i:s', strtotime('+9 hours', $job->getEndTime()->getTimestamp())),
                                    'datetime(\'now\', \'localtime\')',
                                    $mediaId);
                    $this->log('$query: ' . $query);
                    $this->db->exec($query);

                    // ジョブのアウトプットアセットを取得
                    $assets = $mediaServicesWrapper->getJobOutputMediaAssets($job->getId());
                    foreach ($assets as $asset) {
                        if ($asset->getOptions() == Asset::OPTIONS_NONE) {
                            $url = $this->createUrl($asset->getId(), $asset->getName(), $mediaId);

                            $query = sprintf("INSERT INTO task (media_id, name, url, created_at, updated_at) VALUES ('%s', '%s', '%s', %s, %s);",
                                            $mediaId,
                                            $asset->getName(),
                                            $url,
                                            'datetime(\'now\', \'localtime\')',
                                            'datetime(\'now\', \'localtime\')');
                            $this->log('$query: ' . $query);
                            $this->db->exec($query);
                        }
                    }
                // 未完了の場合、ステータスの更新のみ
                } else {
                    $query = sprintf("UPDATE media SET job_state = '%s', updated_at = %s WHERE id = '%s';",
                                    $job->getState(),
                                    'datetime(\'now\', \'localtime\')',
                                    $mediaId);
                    $this->log('$query: ' . $query);
                    $this->db->exec($query);
                }

                $this->db->commit();
            } catch (\Exception $e) {
                $this->log('fail in delivering url for streaming: ' . $e->getMessage());
                $this->db->rollBack();
                $url = null;
            }
        }

        $this->log("\n--------------------\n" . 'end function: ' . __FUNCTION__ . "\n--------------------\n");

        return $url;
    }

    /**
     * アセットに対してストリーミングURLを生成する
     *
     * @param string        $assetId
     * @param string        $assetName
     * @param string        $mediaId
     * @return string
     */
    private function createUrl($assetId, $assetName, $mediaId)
    {
        $this->log("\n--------------------\n" . 'start function: ' . __FUNCTION__ . "\n--------------------\n");
        $this->log('args: ' . print_r(func_get_args(), true));

        $mediaServicesWrapper = $this->getMediaServicesWrapper();

        // 特定のAssetに対して、同時に 5 つを超える一意のLocatorを関連付けることはできない
        // 万が一OnDemandOriginロケーターがあれば削除
        $locators = $mediaServicesWrapper->getAssetLocators($assetId);
        foreach ($locators as $locator) {
            if ($locator->getType() == Locator::TYPE_ON_DEMAND_ORIGIN) {
                $mediaServicesWrapper->deleteLocator($locator);
                $this->log('OnDemandOrigin locator has been deleted. $locator: '. print_r($locator, true));
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
        switch ($assetName) {
            case 'http_live_streaming':
            case 'http_live_streaming_playready':
                $url = sprintf('%s%s-m3u8-aapl.ism/Manifest(format=m3u8-aapl)', $locator->getPath(), $mediaId);
                break;
            default:
                $url = sprintf('%s%s.ism/Manifest', $locator->getPath(), $mediaId);
                break;
        }

        $this->log('url has been created: ' . $url);
        $this->log("\n--------------------\n" . 'end function: ' . __FUNCTION__ . "\n--------------------\n");

        return $url;
    }

    /**
     * ストリームURL発行お知らせメールを送信する
     *
     * @param string $mediaCode
     * @param string $userId
     */
    private function sendEmail($mediaCode, $userId)
    {
        $this->log("\n--------------------\n" . 'start function: ' . __FUNCTION__ . "\n--------------------\n");
        $this->log('args: ' . print_r(func_get_args(), true));

        $query = sprintf('SELECT email FROM user WHERE id = \'%s\';', $userId);
        $statement = $this->db->query($query);
        $email = $statement->fetchColumn();
        $this->log('$email:' . $email);

        // 送信
        if ($email) {
            $subject = 'ストリーミングURLが発行さされました';
            $message = 'http://pmmedia.cloudapp.net/media/' . $mediaCode;
            $headers = 'From: webmaster@pmmedia.cloudapp.net' . "\r\n"
            . 'Reply-To: webmaster@pmmedia.cloudapp.net';
            if (!mail($email, $subject, $message, $headers)) {
                $egl = error_get_last();
                $this->log('メール送信に失敗しました' . $egl['message']);
            }
        }

        $this->log("\n--------------------\n" . 'end function: ' . __FUNCTION__ . "\n--------------------\n");
    }
}

?>