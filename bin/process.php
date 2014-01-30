<?php
require_once('MmsBinActions.php');

class MmsBinProcessActions extends MmsBinActions
{
    function __construct()
    {
        parent::__construct();

        $this->logFile = dirname(__FILE__) . '/process_log';
    }

    /**
     * media serviceのjobを作成する
     *
     * @param string $filepath
     * @return object $job
     * @throws Exception
     */
    function createJob($filepath)
    {
        $this->log($filepath);

        $mediaContext = $this->mediaContext;

        try {
            // 資産を作成する
            $asset = $mediaContext->getAssetReference();
            $asset->name = 'NewAssets' . date('YmdHis');
            $asset->options = AssetOptions::$STORAGE_ENCRYPTED;
            $asset->create();

            $this->log($asset);

            // AccessPolicy を設定する
            $accessPolicy = $mediaContext->getAccessPolicyReference();
            $accessPolicy->name = 'NewUploadPolicy';
            $accessPolicy->durationInMinutes = '60';
            $accessPolicy->permissions = AccessPolicyPermission::$WRITE;
            $accessPolicy->create();

            $this->log($accessPolicy);

            // アップロードURLを取得する
            $locator = $mediaContext->getLocatorReference();
            $locator->accessPolicyId = $accessPolicy->id;
            $locator->assetId = $asset->id;
            $locator->startTime = gmdate('m\/d\/Y H:i:s A', strtotime('-5 minutes'));
            $locator->type = LocatorType::$SAS;
            $locator->create();

            $this->log($locator);

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
                            'H264 Broadband 720p'
            );
            $toAdaptiveBitrateTask->AddInputMediaAsset($asset);
            $toAdaptiveBitrateTask->AddNewOutputMediaAsset(
                            $taskName,
                            AssetOptions::$STORAGE_ENCRYPTED
            );

            // タスクを追加(MP4ビデオをスムーズストリーミングに変換)
            $taskName = 'smooth_streaming';
            $configurationFile  = dirname(__FILE__) . '/config/MediaPackager_MP4ToSmooth.xml';
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
            $configurationFile  = dirname(__FILE__) . '/config/MediaPackager_SmoothToHLS.xml';
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
            $configurationFile  = dirname(__FILE__) . '/config/MediaEncryptor_PlayReadyProtection.xml';
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
            $configurationFile  = dirname(__FILE__) . '/config/MediaPackager_SmoothToHLS.xml';
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
        } catch (Exception $e) {
            $this->log($e);

            throw $e;
        }

        $this->log($job);

        return $job;
    }

    /**
     * DBのメディアをジョブ情報で更新する
     *
     * @param string $filepath
     * @param string $jobId
     * @param string $jobState
     * @throws Exception
     */
    function updateMedia($filepath, $jobId, $jobState)
    {
        $this->log($filepath);
        $this->log($jobId);
        $this->log($jobState);

        // ジョブ情報をDBに登録
        try {
            $db = $this->db;

            // すでにデータがあるか確認
            $id = pathinfo($filepath, PATHINFO_FILENAME);
            $query = sprintf('SELECT * FROM media WHERE id = \'%s\';', $id);
            $media = $db->querySingle($query, true);

            if (!isset($media['id'])) {
                // ディレクトリからユーザーIDを取得
                $pathParts = pathinfo($filepath);
                $pathParts = pathinfo($pathParts['dirname']);
                $userId = $path_parts['filename'];

                $query = sprintf("INSERT INTO media (id, user_id, job_id, job_state, created_at, updated_at) VALUES ('%s', '%s', '%s', '%s', datetime('now'), datetime('now'))",
                                $id,
                                $userId,
                                $jobId,
                                $jobState);
                $db->exec($query);
            } else {
                $query = sprintf("UPDATE media SET job_id = '%s', job_state = '%s', updated_at = datetime('now') WHERE id = '%s';",
                                $jobId,
                                $jobState,
                                $id);
                $db->exec($query);
            }
        } catch (Exception $e) {
            $this->log($e);

            throw($e);
        }
    }
}

$processAction = new MmsBinProcessActions();

$processAction->log('start process ' . gmdate('Y-m-d H:i:s'));

$filepath = fgets(STDIN);
// $filepath = 'C:\Develop\www\workspace\mms\src\uploads\test\000000_2.MOV';
$filepath = str_replace(array("\r\n", "\r", "\n"), '', $filepath);

$job = $processAction->createJob($filepath);

$processAction->updateMedia($filepath, $job->id, $job->state);

$processAction->log('end process ' . gmdate('Y-m-d H:i:s'));

?>