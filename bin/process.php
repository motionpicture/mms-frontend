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

            $query = sprintf("INSERT INTO media (id, mcode, size, version, user_id, category_id, created_at, updated_at) VALUES ('%s', '%s', '%s', '%s', '%s', '%s', datetime('now'), datetime('now'))",
                            $id,
                            $mcode,
                            $size,
                            $version,
                            $userId,
                            $categoryId);
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
     * @return object $job
     * @throws Exception
     */
    function createJob($filepath)
    {
        $this->log('$filepath:' . $filepath);

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

            if (!isset($media['id'])) {
                // ディレクトリからユーザーIDを取得
                $pathParts = pathinfo($filepath);
                $pathParts = pathinfo($pathParts['dirname']);
                $userId = $pathParts['filename'];

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
                $this->log('$id:' . $id);

                // サイズ
                $size = (filesize($filepath)) ? filesize($filepath) : '';

                $query = sprintf("INSERT INTO media (id, mcode, size, version, user_id, category_id, job_id, job_state, created_at, updated_at) VALUES ('%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', datetime('now'), datetime('now'))",
                                $id,
                                $mcode,
                                $size,
                                $version,
                                $userId,
                                $categoryId,
                                $jobId,
                                $jobState);
                $this->log('$query:' . $query);
                if (!$db->exec($query)) {
                    $egl = error_get_last();
                    $e = new Exception('SQLの実行でエラーが発生しました' . $egl['message']);
                    throw $e;
                }
            } else {
                $query = sprintf("UPDATE media SET job_id = '%s', job_state = '%s', updated_at = datetime('now') WHERE id = '%s';",
                                $jobId,
                                $jobState,
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

$processAction->log('start process ' . gmdate('Y-m-d H:i:s'));

$filepath = fgets(STDIN);
// $filepath = 'C:\Develop\www\workspace\mms\src\uploads\test\000000_3_1.MOV';
$filepath = str_replace(array("\r\n", "\r", "\n"), '', $filepath);

$filepath = $processAction->createMediaIfNotExist($filepath);

$job = $processAction->createJob($filepath);

$processAction->updateMedia($filepath, $job->id, $job->state);

$processAction->log('end process ' . gmdate('Y-m-d H:i:s'));

?>