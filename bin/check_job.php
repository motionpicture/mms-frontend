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
    }

    /**
     * ジョブの進捗を確認しステータス更新、完了していればURLを発行する
     *
     * @param string $mediaId
     * @param string $jobId
     * @return string $url 発行されたURLをひとつ
     * @throws Exception
     */
    function tryDeliverMedia($mediaId, $jobId)
    {
        $this->log('start tryDeliverMedia');
        $this->log($mediaId);
        $this->log($jobId);

        $url = null;

        $mediaServicesWrapper = $this->getMediaServicesWrapper();

        try {
            // メディアサービスよりジョブを取得
            $job = $mediaServicesWrapper->getJob($jobId);
        } catch (Exception $e) {
            $this->log($e->getMessage());
            throw $e;
        }

        try {
            // トランザクションの開始
            $query = 'BEGIN DEFERRED;';
            $this->log('$query:' . $query);
            $this->db->exec($query);

            // ジョブのステータスを更新
            $query = sprintf("UPDATE media SET job_state = '%s', updated_at = %s WHERE id = '%s';",
                            $job->getState(),
                            'datetime(\'now\', \'localtime\')',
                            $mediaId);
            $this->log('$query:' . $query);
            if (!$this->db->exec($query)) {
                $egl = error_get_last();
                $e = new Exception('SQLの実行でエラーが発生しました' . $egl['message']);
                throw $e;
            }

            // ジョブが完了の場合、URL発行プロセス
            if ($job->getState() == Job::STATE_FINISHED) {
                // エンコード完了日時を更新
                $query = sprintf("UPDATE media SET job_start_at = '%s', job_end_at = '%s', updated_at = %s WHERE id = '%s';",
                                date('Y-m-d H:i:s', strtotime('+9 hours', $job->getStartTime()->getTimestamp())),
                                date('Y-m-d H:i:s', strtotime('+9 hours', $job->getEndTime()->getTimestamp())),
                                'datetime(\'now\', \'localtime\')',
                                $mediaId);
                $this->log('$query:' . $query);
                if (!$this->db->exec($query)) {
                    $egl = error_get_last();
                    $e = new Exception('SQLの実行でエラーが発生しました' . $egl['message']);
                    throw $e;
                }

                // 読み取りアクセス許可を持つAccessPolicyの作成
                $accessPolicy = new AccessPolicy('StreamingPolicy');
                $accessPolicy->setDurationInMinutes(25920000);
                $accessPolicy->setPermissions(AccessPolicy::PERMISSIONS_READ);
                $accessPolicy = $mediaServicesWrapper->createAccessPolicy($accessPolicy);

                // ジョブのアセットを取得
                $assets = $mediaServicesWrapper->getJobOutputMediaAssets($job);

                $this->log(print_r($assets, true));

                foreach ($assets as $asset) {
                    if ($asset->getOptions() == Asset::OPTIONS_NONE) {
                        // コンテンツストリーミング用の配信元URLの作成
                        $locator = new Locator($asset, $accessPolicy, Locator::TYPE_ON_DEMAND_ORIGIN);
                        $locator->setName('StreamingLocator_' . $asset->getId());
                        $locator->setStartTime(new \DateTime('now -5 minutes'));
                        $locator = $mediaServicesWrapper->createLocator($locator);

                        // URLを生成
                        switch ($asset->getName()) {
                            case 'http_live_streaming':
                            case 'http_live_streaming_playready':
                                $url = sprintf("%s%s-m3u8-aapl.ism/Manifest(format=m3u8-aapl)", $locator->getPath(), $mediaId);
                                break;
                            default:
                                $url = sprintf('%s%s.ism/Manifest', $locator->getPath(), $mediaId);
                                break;
                        }

                        $query = sprintf("INSERT INTO task (media_id, name, url, created_at, updated_at) VALUES ('%s', '%s', '%s', %s, %s)",
                                        $mediaId,
                                        $asset->getName(),
                                        $url,
                                        'datetime(\'now\', \'localtime\')',
                                        'datetime(\'now\', \'localtime\')');
                        $this->log('$query:' . $query);
                        if (!$this->db->exec($query)) {
                            $egl = error_get_last();
                            $e = new Exception('SQLの実行でエラーが発生しました' . $egl['message']);
                            throw $e;
                        }
                    }
                }
            }

            // コミット
            $query = 'COMMIT;';
            $this->log('$query:' . $query);
            if (!$this->db->exec($query)) {
                $egl = error_get_last();
                $e = new Exception('SQLの実行でエラーが発生しました' . $egl['message']);
                throw $e;
            }
        } catch (Exception $e) {
            $this->log($e);

            // ロールバック
            $this->db->exec('ROLLBACK;');
            $url = null;
            throw $e;
        }

        $this->log('$url:' . $url);
        $this->log('end tryDeliverMedia');

        return $url;
    }
}


$checkJobAction = new MmsBinCheckJobActions();

$checkJobAction->log('start check job ' . date('Y-m-d H:i:s'));

$medias = array();

try {
    // ジョブの状態が$QUEUED　or $SCHEDULED or $PROCESSINGのメディアに関してジョブの状況を確認する
    $query = sprintf('SELECT id, job_id, user_id FROM media WHERE job_state = \'%s\' OR job_state = \'%s\' OR job_state = \'%s\'',
                    Job::STATE_QUEUED,
                    Job::STATE_SCHEDULED,
                    Job::STATE_PROCESSING);
    $result = $checkJobAction->db->query($query);
    while ($res = $result->fetchArray(SQLITE3_ASSOC)) {
        $medias[] = $res;
    }
} catch (Exception $e) {
    $checkJobAction->log($e);
    throw $e;
}

foreach ($medias as $media) {
    try {
        // ジョブのステータスを更新とURLを発行
        $url = $checkJobAction->tryDeliverMedia($media['id'], $media['job_id']);

        // URLが発行されればメール送信
        if (!is_null($url)) {
            $query = sprintf('SELECT email FROM user WHERE id = \'%s\';', $media['user_id']);
            $email = $checkJobAction->db->querySingle($query);
            $checkJobAction->log('$email:' . $email);

            // 送信
            if ($email) {
                $subject = 'ストリーミングURLが発行さされました';
                $message = 'http://pmmedia.cloudapp.net/media/' . $media['id'];
                $headers = 'From: webmaster@pmmedia.cloudapp.net' . "\r\n"
                . 'Reply-To: webmaster@pmmedia.cloudapp.net';
                if (!mail($email, $subject, $message, $headers)) {
                    $egl = error_get_last();
                    $this->log('メール送信に失敗しました' . $egl['message']);
                }
            }
        }
    } catch (Exception $e) {
        $checkJobAction->log($e);
        continue;
    }
}

$checkJobAction->log('end check job ' . date('Y-m-d H:i:s'));
?>