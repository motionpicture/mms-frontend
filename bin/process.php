<?php
require_once('MmsBinActions.php');

use WindowsAzure\MediaServices\Models\Asset;
use WindowsAzure\MediaServices\Models\AccessPolicy;
use WindowsAzure\MediaServices\Models\Locator;
use WindowsAzure\MediaServices\Models\Job;
use WindowsAzure\MediaServices\Models\Task;
use WindowsAzure\MediaServices\Models\TaskOptions;

class MmsBinProcessActions extends MmsBinActions
{
    function __construct()
    {
        parent::__construct();

        set_time_limit(0);

        $this->logFile = dirname(__FILE__) . '/process_log';
    }

    /**
     * メディアに未登録のファイルであれば登録して新しいパスを返す
     *
     * @param string $filepath もとのファイルパス
     * @return string $newFilePath 新しいファイルパス
     * @throws Exception
     */
    function createMediaIfNotExist($filepath)
    {
        $this->log('$filepath:' . $filepath);

        // すでにデータがあるか確認
        $id = pathinfo($filepath, PATHINFO_FILENAME);
        $query = sprintf('SELECT * FROM media WHERE id = \'%s\';', $id);
        $media = $this->db->querySingle($query, true);

        // あれば何もせず終了
        if (isset($media['id'])) {
            return $filepath;
        }

        // なければ新規登録
        $isSaved = false;
        $newFilePath = '';

        try {
            // ディレクトリからユーザーIDを取得
            $userId = pathinfo(pathinfo($filepath, PATHINFO_DIRNAME), PATHINFO_FILENAME);

            // 同作品同カテゴリーのデータがあるか確認(フォーム以外からアップロードされた場合、作品コード_カテゴリーID.拡張子というファイル)
            $idParts = explode('_', $id);
            $mcode = $idParts[0];
            $categoryId = $idParts[1];
            $query = sprintf('SELECT COUNT(*) AS count FROM media WHERE mcode = \'%s\' AND category_id = \'%s\';',
                            $mcode,
                            $categoryId);
            $count = $this->db->querySingle($query);
            // バージョンを確定
            $version = $count;
            // 作品コード、カテゴリー、バージョンからIDを生成
            $id = implode('_', array($mcode, $categoryId, $version));

            // サイズ
            $size = (filesize($filepath)) ? filesize($filepath) : '';

            // トランザクションの開始
            $this->db->exec('BEGIN DEFERRED;');

            $query = sprintf("INSERT INTO media (id, mcode, size, version, user_id, category_id, created_at, updated_at) VALUES ('%s', '%s', '%s', '%s', '%s', '%s', %s, %s)",
                            $id,
                            $mcode,
                            $size,
                            $version,
                            $userId,
                            $categoryId,
                            'datetime(\'now\', \'localtime\')',
                            'datetime(\'now\', \'localtime\')');
            $this->log('$query:' . $query);
            if (!$this->db->exec($query)) {
                $egl = error_get_last();
                $e = new Exception('SQLの実行でエラーが発生しました' . $egl['message']);
                throw $e;
            }

            // 新しいファイルパスへリネーム
            $newFilePath = sprintf('%s/%s.%s',
                            pathinfo($filepath, PATHINFO_DIRNAME),
                            $id,
                            pathinfo($filepath, PATHINFO_EXTENSION));
            $this->log($newFilePath);
            if (!rename($filepath, $newFilePath)) {
                $egl = error_get_last();
                $e = new Exception('ファイルのリネームでエラーが発生しました' . $egl['message']);
                throw $e;
            }
            chmod($newFilePath, 0644);

            $isSaved = true;
        } catch (Exception $e) {
            $this->log($e);

            // ロールバック
            $this->db->exec('ROLLBACK;');
            throw($e);
        }

        if ($isSaved) {
            // コミット
            $this->db->exec('COMMIT;');
        }

        $this->log('$newFilePath:' . $newFilePath);

        return $newFilePath;
    }

    /**
     * media serviceのjobを作成する
     *
     * @param string $filepath
     * @return WindowsAzure\MediaServices\Models\Job $job
     * @throws Exception
     */
    function createJob($filepath)
    {
        $this->log('$filepath:' . $filepath);

        $job = null;

        try {
            $mediaServicesWrapper = $this->getMediaServicesWrapper();

            // 資産を作成する
            $asset = new Asset(Asset::OPTIONS_STORAGE_ENCRYPTED);
            $asset->setName('NewAssets' . date('YmdHis'));
            $asset = $mediaServicesWrapper->createAsset($asset);

            $this->log($asset);

            // AccessPolicy を設定する
            $accessPolicy = new AccessPolicy('NewUploadPolicy');
            $accessPolicy->setDurationInMinutes(30);
            $accessPolicy->setPermissions(AccessPolicy::PERMISSIONS_WRITE);
            $accessPolicy = $mediaServicesWrapper->createAccessPolicy($accessPolicy);

            $this->log($accessPolicy);

            // アップロードURLを取得する
            $locator = new Locator($asset, $accessPolicy, Locator::TYPE_SAS);
            $locator->setName('NewUploadLocator');
            $locator->setStartTime(new \DateTime('now -5 minutes'));
            $locator = $mediaServicesWrapper->createLocator($locator);

            $this->log($locator);

            // ファイルのアップロードを実行する
            $fileName = basename($filepath);
            $mediaServicesWrapper->uploadAssetFile($locator, $fileName, file_get_contents($filepath));

            // アップロード URLの取り消し
            // AccessPolicyの削除
            $mediaServicesWrapper->deleteLocator($locator);
            $mediaServicesWrapper->deleteAccessPolicy($accessPolicy);

            // ファイル メタデータの生成
            $mediaServicesWrapper->createFileInfos($asset);

            // エンコードジョブを作成
            // タスクを追加(スムーズストリーミングに変換)
            $mediaProcessor = $mediaServicesWrapper->getLatestMediaProcessor('Windows Azure Media Encoder');
            $taskBody = '<?xml version="1.0" encoding="utf-8"?><taskBody><inputAsset>JobInputAsset(0)</inputAsset><outputAsset assetCreationOptions="0" assetName="smooth_streaming">JobOutputAsset(0)</outputAsset></taskBody>';
            $task = new Task(
                $taskBody,
                $mediaProcessor->getId(),
                TaskOptions::NONE
            );
            $task->setConfiguration('H264 Smooth Streaming 1080p');

            /*
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
            */

            $job = new Job();
            $job->setName('process asset_' . $asset->getId() . '_' . date('YmdHis'));
            $job = $mediaServicesWrapper->createJob($job, array($asset), array($task));
        } catch (Exception $e) {
            $this->log($e->getMessage());

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
        $this->log('$filepath:' . $filepath);
        $this->log('$jobId:' . $jobId);
        $this->log('$jobState:' . $jobState);

        // ジョブ情報をDBに登録
        try {
            $db = $this->db;

            // すでにデータがあるか確認
            $id = pathinfo($filepath, PATHINFO_FILENAME);
            $query = sprintf('SELECT * FROM media WHERE id = \'%s\';', $id);
            $media = $db->querySingle($query, true);

            if (isset($media['id'])) {
                $query = sprintf("UPDATE media SET job_id = '%s', job_state = '%s', updated_at = %s WHERE id = '%s';",
                                $jobId,
                                $jobState,
                                'datetime(\'now\', \'localtime\')',
                                $id);
                $this->log('$query:' . $query);
                if (!$db->exec($query)) {
                    $egl = error_get_last();
                    $e = new Exception('SQLの実行でエラーが発生しました' . $egl['message']);
                    throw $e;
                }
            }
        } catch (Exception $e) {
            $this->log($e);

            throw($e);
        }
    }
}

$processAction = new MmsBinProcessActions();

$processAction->log('start process ' . date('Y-m-d H:i:s'));

$filepath = fgets(STDIN);
// $filepath = 'C:\Develop\www\workspace\mms\src\uploads\test\054055_2_0.MOV';
$filepath = str_replace(array("\r\n", "\r", "\n"), '', $filepath);

$filepath = $processAction->createMediaIfNotExist($filepath);

$job = $processAction->createJob($filepath);

$processAction->updateMedia($filepath, $job->getId(), $job->getState());

$processAction->log('end process ' . date('Y-m-d H:i:s'));

?>