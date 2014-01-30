<?php
require_once('MmsBinActions.php');

class MmsBinCheckJobActions extends MmsBinActions
{
    function __construct()
    {
        parent::__construct();

        $this->logFile = dirname(__FILE__) . '/check_job_log';
    }

    function updateJobState($mediaId, $jobId)
    {
        $this->log('start updateJobState');
        $this->log($mediaId);
        $this->log($jobId);

        $job = $this->mediaContext->getJobReference($jobId);
        $job->get();

        // ジョブのステータスを更新
        $query = sprintf("UPDATE media SET job_state = '%s', updated_at = datetime('now') WHERE id = '%s';",
                        $job->state,
                        $mediaId);
        $this->db->exec($query);

        $this->log('end updateJobState');
    }

    function deliverMedia($mediaId, $jobId)
    {
        $this->log('start deliverMedia');
        $this->log($mediaId);
        $this->log($jobId);

        $job = $this->mediaContext->getJobReference($jobId);
        $job->get();

        if ($job->state != JobState::$FINISHED) {
            return;
        }

        // エンコード完了日時を更新
        $query = sprintf("UPDATE media SET encoded_at = '%s', updated_at = datetime('now') WHERE id = '%s';",
                        date('Y-m-d H:i:s', strtotime($job->endTime)),
                        $mediaId);
        $this->db->exec($query);

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
                $this->db->exec($query);
            }
        }

        $this->log('end deliverMedia');
    }
}


$checkJobAction = new MmsBinCheckJobActions();

$checkJobAction->log('start check job ' . gmdate('Y-m-d H:i:s'));

try {
    $db = $checkJobAction->db;

    // ジョブの状態が$QUEUED　or $SCHEDULED or $PROCESSINGのメディアに関してジョブの状況を確認する
    $medias = array();
    $query = sprintf('SELECT * FROM media WHERE job_state = \'%s\' OR job_state = \'%s\' OR job_state = \'%s\'',
                    JobState::$QUEUED,
                    JobState::$SCHEDULED,
                    JobState::$PROCESSING);
    $result = $db->query($query);
    while ($res = $result->fetchArray(SQLITE3_ASSOC)) {
        $medias[] = $res;
    }

    foreach ($medias as $media) {
        // ジョブのステータスを更新
        $checkJobAction->updateJobState($media['id'], $media['job_id']);

        // URLを発行
        $checkJobAction->deliverMedia($media['id'], $media['job_id']);
    }
} catch (Exception $e) {
    $checkJobAction->log($e);

    throw($e);
}

$checkJobAction->log('end check job ' . gmdate('Y-m-d H:i:s'));

?>