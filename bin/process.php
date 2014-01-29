<?php
file_put_contents('process_log', "start process \n", FILE_APPEND);

$filepath = fgets(STDIN);

file_put_contents('process_log', $filepath . "\n", FILE_APPEND);

// $filepath = '../uploads/111111_1.MOV';

require_once('base.php');

require_once('..//vendor/WindowsAzureMediaServices/WindowsAzureMediaServicesContext.php');

$mediaContext = new WindowsAzureMediaServicesContext(
    'testmvtkms',
    'Vi3fX70rZKrtk/DM6TRoJ/XpxmkC29LNOzWimE06rx4=',
    null,
    null
);
$mediaContext->checkForRedirection();


// 資産を作成する
$asset = $mediaContext->getAssetReference();
$asset->name = 'NewAssets' . date('YmdHis');
$asset->options = AssetOptions::$STORAGE_ENCRYPTED;
$asset->create();

file_put_contents('process_log', print_r($asset ,true), FILE_APPEND);

// AccessPolicy を設定する
$accessPolicy = $mediaContext->getAccessPolicyReference();
$accessPolicy->name = 'NewUploadPolicy';
$accessPolicy->durationInMinutes = '60';
$accessPolicy->permissions = AccessPolicyPermission::$WRITE;
$accessPolicy->create();

file_put_contents('process_log', print_r($accessPolicy ,true), FILE_APPEND);

// アップロードURLを取得する
$locator = $mediaContext->getLocatorReference();
$locator->accessPolicyId = $accessPolicy->id;
$locator->assetId = $asset->id;
$locator->startTime = gmdate('m\/d\/Y H:i:s A', strtotime('-5 minutes'));
$locator->type = LocatorType::$SAS;
$locator->create();

file_put_contents('process_log', print_r($locator ,true), FILE_APPEND);

// ファイルのアップロードを実行する
$locator->upload(
    basename($filepath),
    $filepath
);


// アップロード URLの取り消し
$locator->delete();


// AccessPolicyの削除
$accessPolicy->delete();


// ファイル メタデータの生成
$asset->createFileInfos();


// エンコードジョブを作成
$job = $mediaContext->getJobReference();
$job->name = 'process asset_' . $asset->id . '_' . date('YmdHis');

// タスクを追加(アダプティブビットレートに変換)
$taskName = 'mp4';
$toAdaptiveBitrateTask = $job->AddNewTask(
    $taskName,
    'nb:mpid:UUID:70bdc2c3-ebf4-42a9-8542-5afc1e55d217',
    'H264 Broadband 1080p'
);
$toAdaptiveBitrateTask->AddInputMediaAsset($asset);
$toAdaptiveBitrateTask->AddNewOutputMediaAsset(
    $taskName,
    AssetOptions::$STORAGE_ENCRYPTED
);

// タスクを追加(MP4ビデオをスムーズストリーミングに変換)
$taskName = 'smooth_streaming';
$configurationFile  = './config/MediaPackager_MP4ToSmooth.xml';
$configuration = file_get_contents($configurationFile);
$toSmoothStreamingTask = $job->AddNewTask(
    $taskName,
    'nb:mpid:UUID:a2f9afe9-7146-4882-a5f7-da4a85e06a93',
     $configuration
);
$toSmoothStreamingTask->AddInputMediaAsset($toAdaptiveBitrateTask->outputMediaAssets[0]);
$toSmoothStreamingTask->AddNewOutputMediaAsset(
    $taskName,
    AssetOptions::$NONE
);

// タスクを追加(HLSに変換)
$taskName = 'http_live_streaming';
$configurationFile  = './config/MediaPackager_SmoothToHLS.xml';
$configuration = file_get_contents($configurationFile);
$toHLSTask = $job->AddNewTask(
    $taskName,
    'nb:mpid:UUID:a2f9afe9-7146-4882-a5f7-da4a85e06a93',
     $configuration
);
$toHLSTask->AddInputMediaAsset($toSmoothStreamingTask->outputMediaAssets[0]);
$toHLSTask->AddNewOutputMediaAsset(
    $taskName,
    AssetOptions::$NONE
);

// タスクを追加(PlayReadyで保護)
$taskName = 'smooth_streaming_playready';
$configurationFile  = './config/MediaEncryptor_PlayReadyProtection.xml';
$configuration = file_get_contents($configurationFile);
$playReadyTask = $job->AddNewTask(
    $taskName,
    'nb:mpid:UUID:38a620d8-b8dc-4e39-bb2e-7d589587232b',
     $configuration
);
$playReadyTask->AddInputMediaAsset($toSmoothStreamingTask->outputMediaAssets[0]);
$playReadyTask->AddNewOutputMediaAsset(
    $taskName,
    AssetOptions::$NONE
);

// タスクを追加(PlayReadyでHLSに変換)
$taskName = 'http_live_streaming_playready';
$configurationFile  = './config/MediaPackager_SmoothToHLS.xml';
$configuration = file_get_contents($configurationFile);
$toHLSByPlayReadyTask = $job->AddNewTask(
    $taskName,
    'nb:mpid:UUID:a2f9afe9-7146-4882-a5f7-da4a85e06a93',
     $configuration
);
$toHLSByPlayReadyTask->AddInputMediaAsset($playReadyTask->outputMediaAssets[0]);
$toHLSByPlayReadyTask->AddNewOutputMediaAsset(
    $taskName,
    AssetOptions::$NONE
);

$job->submit();

file_put_contents('process_log', print_r($job ,true), FILE_APPEND);

// ジョブ情報をDBに登録
try {
    $db = new MyDB();

    // すでにデータがあるか確認
    $id = pathinfo($filepath, PATHINFO_FILENAME);
    $query = sprintf('SELECT * FROM media WHERE id = \'%s\';', $id);
    $media = $db->querySingle($query, true);

    if (!isset($media['id'])) {
        $query = sprintf("INSERT INTO media (id, job_id, job_state, created_at, updated_at) VALUES ('%s', '%s', '%s', datetime('now'), datetime('now'))",
                         $id,
                         $job->id,
                         $job->state);
        $db->exec($query);
    } else {
        $query = sprintf("UPDATE media SET job_id = '%s', job_state = '%s', updated_at = datetime('now') WHERE id = '%s';",
                        $job->id,
                        $job->state,
                        $id);
        $db->exec($query);
    }
} catch(Exception $e) {
    throw($e);
}

file_put_contents('process_log', "end process \n", FILE_APPEND);

?>