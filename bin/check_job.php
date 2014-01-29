<?php
$logFile = dirname(__FILE__) . '/check_job_log';
file_put_contents($logFile, "start check_job\n", FILE_APPEND);

require_once('base.php');

require_once(dirname(__FILE__) . '/../vendor/WindowsAzureMediaServices/WindowsAzureMediaServicesContext.php');

try {
    $mediaContext = new WindowsAzureMediaServicesContext(
        'testmvtkms',
        'Vi3fX70rZKrtk/DM6TRoJ/XpxmkC29LNOzWimE06rx4=',
        null,
        null
    );
    $mediaContext->checkForRedirection();

    $db = new MyDB();

    $medias = array();
    $statement = $db->prepare('SELECT * FROM media');
    $result = $statement->execute();
    while ($res = $result->fetchArray(SQLITE3_ASSOC)) {
        $medias[] = $res;
    }

    foreach ($medias as $media) {
        if ($media['job_id'] && $media['job_state'] != JobState::$FINISHED) {
            // ジョブのステータスを更新
            updateJobState($media['id'], $media['job_id']);

            // URLを発行
            deliverMedia($media['id'], $media['job_id']);
        }
    }
} catch(Exception $e) {
    file_put_contents($logFile, $e->getMessage() . "\n", FILE_APPEND);
    throw($e);
}

file_put_contents($logFile, "end check_job\n", FILE_APPEND);

function updateJobState($mediaId, $jobId)
{
    global $logFile, $mediaContext, $db;

    file_put_contents($logFile, "start updateJobState\n", FILE_APPEND);
    file_put_contents($logFile, 'mediaId: ' . $mediaId . "\n", FILE_APPEND);
    file_put_contents($logFile, 'jobId: ' . $jobId . "\n", FILE_APPEND);

    $job = $mediaContext->getJobReference($jobId);
    $job->get();

    file_put_contents($logFile, print_r($job, true), FILE_APPEND);

    // ジョブのステータスを更新
    $query = sprintf("UPDATE media SET job_state = '%s', updated_at = datetime('now') WHERE id = '%s';",
                    $job->state,
                    $mediaId);
    $db->exec($query);

    file_put_contents($logFile, "end updateJobState", FILE_APPEND);
}

function deliverMedia($mediaId, $jobId)
{
    global $logFile, $mediaContext, $db;

    file_put_contents($logFile, "start deliverMedia\n", FILE_APPEND);
    file_put_contents($logFile, 'mediaId: ' . $mediaId . "\n", FILE_APPEND);
    file_put_contents($logFile, 'jobId: ' . $jobId . "\n", FILE_APPEND);

    $job = $mediaContext->getJobReference($jobId);
    $job->get();

    file_put_contents($logFile, print_r($job, true), FILE_APPEND);

    if ($job->state != JobState::$FINISHED) {
        return;
    }

    // エンコード完了日時を更新
    $query = sprintf("UPDATE media SET encoded_at = '%s', updated_at = datetime('now') WHERE id = '%s';",
                    date('Y-m-d H:i:s', strtotime($job->endTime)),
                    $mediaId);
    $db->exec($query);

    // 読み取りアクセス許可を持つAccessPolicyの作成
    $accessPolicy = $mediaContext->getAccessPolicyReference();
    $accessPolicy->name = 'StreamingPolicy';
    $accessPolicy->durationInMinutes = '25920000';
    $accessPolicy->permissions = AccessPolicyPermission::$READ;
    $accessPolicy->create();

    // ジョブのアセットを取得
    $assets = $job->ListOutputMediaAssets();

    foreach ($assets as $asset) {
        if ($asset->options == AssetOptions::$NONE) {
            // コンテンツ ストリーミング用の配信元 URL の作成
            $locator = $mediaContext->getLocatorReference();
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
            $db->exec($query);
        }
    }

    file_put_contents($logFile, "end deliverMedia\n", FILE_APPEND);
}

?>