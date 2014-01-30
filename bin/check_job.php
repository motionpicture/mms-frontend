<?php
require_once('MmsBinActions.php');

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

        // メディアサービスよりジョブを取得
        $job = $this->mediaContext->getJobReference($jobId);
        $job->get();

        try {
            // トランザクションの開始
            $query = 'BEGIN DEFERRED;';
            $this->log('$query:' . $query);
            $this->db->exec($query);

            // ジョブのステータスを更新
            $query = sprintf("UPDATE media SET job_state = '%s', updated_at = datetime('now') WHERE id = '%s';",
                            $job->state,
                            $mediaId);
            $this->log('$query:' . $query);
            if (!$this->db->exec($query)) {
                $egl = error_get_last();
                $e = new Exception('SQLの実行でエラーが発生しました' . $egl['message']);
                throw $e;
            }

            // ジョブが完了の場合、URL発行プロセス
            if ($job->state == JobState::$FINISHED) {
                // エンコード完了日時を更新
                $query = sprintf("UPDATE media SET encoded_at = '%s', updated_at = datetime('now') WHERE id = '%s';",
                                date('Y-m-d H:i:s', strtotime($job->endTime)),
                                $mediaId);
                $this->log('$query:' . $query);
                if (!$this->db->exec($query)) {
                    $egl = error_get_last();
                    $e = new Exception('SQLの実行でエラーが発生しました' . $egl['message']);
                    throw $e;
                }

                // 読み取りアクセス許可を持つAccessPolicyの作成
                $accessPolicy = $this->mediaContext->getAccessPolicyReference();
                $accessPolicy->name = 'StreamingPolicy';
                $accessPolicy->durationInMinutes = '25920000';
                $accessPolicy->permissions = AccessPolicyPermission::$READ;
                $accessPolicy->create();

                // ジョブのアセットを取得
                $assets = $job->ListOutputMediaAssets();

                foreach ($assets as $asset) {
                    if ($asset->options == AssetOptions::$NONE) {
                        // コンテンツ ストリーミング用の配信元 URL の作成
                        $locator = $this->mediaContext->getLocatorReference();
                        $locator->accessPolicyId = $accessPolicy->id;
                        $locator->assetId = $asset->id;
                        $locator->startTime = gmdate('m\/d\/Y H:i:s A', strtotime('-5 minutes'));
                        $locator->type = LocatorType::$ON_DEMAND_ORIGIN;
                        $locator->create();

                        // URLを生成
                        switch ($asset->name) {
                            case 'http_live_streaming':
                            case 'http_live_streaming_playready':
                                $url = sprintf("%s%s-m3u8-aapl.ism/Manifest(format=m3u8-aapl)", $locator->path, $mediaId);
                                break;
                            default:
                                $url = sprintf("%s%s.ism/Manifest", $locator->path, $mediaId);
                                break;
                        }

                        $query = sprintf("INSERT INTO task (media_id, name, url, created_at, updated_at) VALUES ('%s', '%s', '%s', datetime('now'), datetime('now'))",
                                        $mediaId,
                                        $asset->name,
                                        $url);
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

$checkJobAction->log('start check job ' . gmdate('Y-m-d H:i:s'));

try {
    $db = $checkJobAction->db;

    // ジョブの状態が$QUEUED　or $SCHEDULED or $PROCESSINGのメディアに関してジョブの状況を確認する
    $medias = array();
    $query = sprintf('SELECT id, job_id, user_id FROM media WHERE job_state = \'%s\' OR job_state = \'%s\' OR job_state = \'%s\'',
                    JobState::$QUEUED,
                    JobState::$SCHEDULED,
                    JobState::$PROCESSING);
    $result = $db->query($query);
    while ($res = $result->fetchArray(SQLITE3_ASSOC)) {
        $medias[] = $res;
    }

    foreach ($medias as $media) {
        // ジョブのステータスを更新とURLを発行
        $url = $checkJobAction->tryDeliverMedia($media['id'], $media['job_id']);

        // URLが発行されればメール送信
        if ($url) {
            $query = sprintf('SELECT email FROM user WHERE id = \'%s\';', $media['user_id']);
            $email = $checkJobAction->db->querySingle($query);
            $checkJobAction->log($email);

            // 送信
            $subject = 'ストリーミングURLが発行さされました';
            $message = 'http://pmmedia.cloudapp.net/detail.php?id=' . $media['id'];
            $headers = 'From: webmaster@pmmedia.cloudapp.net' . "\r\n"
                     . 'Reply-To: webmaster@pmmedia.cloudapp.net';
            mail($email, $subject, $message, $headers);
        }
    }
} catch (Exception $e) {
    $checkJobAction->log($e);

    throw($e);
}

$checkJobAction->log('end check job ' . gmdate('Y-m-d H:i:s'));

?>