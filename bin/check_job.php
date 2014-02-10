<?php
require_once('MmsBinActions.php');

use WindowsAzure\MediaServices\Models\Job;
use WindowsAzure\MediaServices\Models\AccessPolicy;
use WindowsAzure\MediaServices\Models\Asset;
use WindowsAzure\MediaServices\Models\Locator;

class MmsBinCheckJobActions extends MmsBinActions
{
    function __construct()
    {
        parent::__construct();

        $this->logFile = dirname(__FILE__) . '/check_job_log';

        $this->log(date('[Y/m/d H:i:s]') . ' start check job');

        $medias = array();

        try {
            // ジョブの状態が$QUEUED　or $SCHEDULED or $PROCESSINGのメディアに関してジョブの状況を確認する
            $query = sprintf('SELECT * FROM media WHERE job_state = \'%s\' OR job_state = \'%s\' OR job_state = \'%s\'',
                            Job::STATE_QUEUED,
                            Job::STATE_SCHEDULED,
                            Job::STATE_PROCESSING);
            $result = $this->db->query($query);
            while ($res = $result->fetchArray(SQLITE3_ASSOC)) {
                $medias[] = $res;
            }
        } catch (Exception $e) {
            $this->log($e->getMessage());
        }

        foreach ($medias as $media) {
            try {
                $url = $this->tryDeliverMedia($media['id'], $media['job_id'], $media['job_state']);

                 // URLが発行されればメール送信
                if (!is_null($url)) {
                    $this->sendEmail($media['id'], $media['user_id']);
                }
            } catch (Exception $e) {
                $this->log($e->getMessage());
                continue;
            }
        }

        $this->log(date('[Y/m/d H:i:s]') . ' end check job');
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
        } catch (Exception $e) {
            $this->log('メディアサービスよりジョブを取得中のエラー: ' . $e->getMessage());
        }

        if (!is_null($job)) {
            // ジョブのステータスを更新
            if ($jobState == $job->getState()) {
                // 進捗に変化がなければ終了
                return $url;
            }

            // クエリを作成
            $query = '';
            try {
                // ジョブのステータスを更新
                $query .= sprintf("UPDATE media SET job_state = '%s', updated_at = %s WHERE id = '%s';",
                                $job->getState(),
                                'datetime(\'now\', \'localtime\')',
                                $mediaId);

                // ジョブが完了の場合、URL発行プロセス
                if ($job->getState() == Job::STATE_FINISHED) {
                    // エンコード完了日時を更新
                    $query .= sprintf("UPDATE media SET job_start_at = '%s', job_end_at = '%s', updated_at = %s WHERE id = '%s';",
                                    date('Y-m-d H:i:s', strtotime('+9 hours', $job->getStartTime()->getTimestamp())),
                                    date('Y-m-d H:i:s', strtotime('+9 hours', $job->getEndTime()->getTimestamp())),
                                    'datetime(\'now\', \'localtime\')',
                                    $mediaId);

                    // 読み取りアクセス許可を持つAccessPolicyの作成
                    $accessPolicy = new AccessPolicy('StreamingPolicy');
                    $accessPolicy->setDurationInMinutes(25920000);
                    $accessPolicy->setPermissions(AccessPolicy::PERMISSIONS_READ);
                    $accessPolicy = $mediaServicesWrapper->createAccessPolicy($accessPolicy);

                    // ジョブのアセットを取得
                    $assets = $mediaServicesWrapper->getJobOutputMediaAssets($job);

                    foreach ($assets as $asset) {
                        if ($asset->getOptions() == Asset::OPTIONS_NONE) {
                            $url = $this->createUrl($asset->getId(), $asset->getName(), $accessPolicy->getId(), $mediaId);

                            $query .= sprintf("INSERT INTO task (media_id, name, url, created_at, updated_at) VALUES ('%s', '%s', '%s', %s, %s);",
                                            $mediaId,
                                            $asset->getName(),
                                            $url,
                                            'datetime(\'now\', \'localtime\')',
                                            'datetime(\'now\', \'localtime\')');
                        }
                    }
                }
            } catch (Exception $e) {
                $this->log($e->getMessage());
            }

            if ($query != '') {
                // トランザクションの開始
                $this->db->exec('BEGIN DEFERRED;');
                try {
                    // コミット
                    $query .= 'COMMIT;';
                    $this->log('$query: ' . $query);
                    if (!$this->db->exec($query)) {
                        $egl = error_get_last();
                        $e = new Exception('SQLの実行でエラーが発生しました' . $egl['message']);
                        throw $e;
                    }
                } catch (Exception $e) {
                    $this->log($e->getMessage());

                    // ロールバック
                    $this->db->exec('ROLLBACK;');
                    $url = null;
                }
            }
        }

        $this->log('発行されたURL: ' . $url);
        $this->log("\n--------------------\n" . 'end function: ' . __FUNCTION__ . "\n--------------------\n");

        return $url;
    }

    /**
     * Create locator
     *
     * @param string        $assetId
     * @param string        $assetName
     * @param string        $accessPolicyId
     * @param string        $mediaId
     * @return string
     */
    private function createUrl($assetId, $assetName, $accessPolicyId, $mediaId)
    {
        // コンテンツストリーミング用の配信元URLの作成
        $locator = new Locator($assetId, $accessPolicyId, Locator::TYPE_ON_DEMAND_ORIGIN);
        $locator->setName('StreamingLocator_' . $assetId);
        $locator->setStartTime(new \DateTime('now -5 minutes'));
        $locator = $this->getMediaServicesWrapper()->createLocator($locator);

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

        return $url;
    }

    private function sendEmail($mediaId, $userId)
    {
        $query = sprintf('SELECT email FROM user WHERE id = \'%s\';', $userId);
        $email = $this->db->querySingle($query);
        $this->log('$email:' . $email);

        // 送信
        if ($email) {
            $subject = 'ストリーミングURLが発行さされました';
            $message = 'http://pmmedia.cloudapp.net/media/' . $mediaId;
            $headers = 'From: webmaster@pmmedia.cloudapp.net' . "\r\n"
            . 'Reply-To: webmaster@pmmedia.cloudapp.net';
            if (!mail($email, $subject, $message, $headers)) {
                $egl = error_get_last();
                $this->log('メール送信に失敗しました' . $egl['message']);
            }
        }
    }
}

new MmsBinCheckJobActions();

?>