<?php
require_once('MmsBinActions.php');

use WindowsAzure\Common\ServiceException;

use WindowsAzure\MediaServices\Models\Asset;
use WindowsAzure\MediaServices\Models\AccessPolicy;
use WindowsAzure\MediaServices\Models\Locator;
use WindowsAzure\MediaServices\Models\Job;
use WindowsAzure\MediaServices\Models\Task;
use WindowsAzure\MediaServices\Models\TaskOptions;

use WindowsAzure\Common\Internal\Utilities;
use WindowsAzure\Common\Internal\Resources;
use WindowsAzure\Common\Internal\Validate;
use WindowsAzure\Common\Internal\Http\HttpCallContext;
use WindowsAzure\Common\Models\ServiceProperties;
use WindowsAzure\Common\Internal\ServiceRestProxy;
use WindowsAzure\MediaServices\Internal\IMediaServices;
use WindowsAzure\Common\Internal\Atom\Feed;
use WindowsAzure\Common\Internal\Atom\Entry;
use WindowsAzure\Common\Internal\Atom\Content;
use WindowsAzure\Common\Internal\Atom\AtomLink;
use WindowsAzure\Blob\Models\BlobType;
use WindowsAzure\Common\Internal\Http\HttpClient;
use WindowsAzure\Common\Internal\Http\Url;
use WindowsAzure\Common\Internal\Http\BatchRequest;
use WindowsAzure\Common\Internal\Http\BatchResponse;
use WindowsAzure\MediaServices\Models\StorageAccount;

use WindowsAzure\Blob\Models\Block;
use WindowsAzure\Blob\Models\BlobBlockType;

set_time_limit(0);
ini_set('max_execution_time', 600);
ini_set('memory_limit', '500000000M');

define('CHUNK_SIZE', 1024 * 1024); // Block Size = 1 MB

class MmsBinProcessActions extends MmsBinActions
{
    private static $filePath = null;
    private static $media = null;
    private static $mediaId = null;

    // WindowsAzure\MediaServices\Models\Job $job
    private static $job = null;

    /**
     * __construct
     *
     * @see http://php.net/manual/ja/features.file-upload.common-pitfalls.php
     */
    function __construct($filePath)
    {
        parent::__construct();

        $this->logFile = dirname(__FILE__) . '/process_log';

        self::$filePath = $filePath;

        $this->log(date('[Y/m/d H:i:s]') . ' start process');

        $this->createMedia();

        $this->createJob();

        $this->updateMedia();

        $this->log(date('[Y/m/d H:i:s]') . ' end process');
    }

    /**
     * ファイルパスからメディアプロパティの配列を返す
     *
     * @param string $path ファイルパス
     * @return array
     */
    private function path2media($path)
    {
        $this->log("\n--------------------\n" . 'start function: ' . __FUNCTION__ . "\n--------------------\n");
        $this->log('$path: ' . print_r($path, true));

        $fileName = pathinfo($path, PATHINFO_FILENAME);

        // アップロードされた場合、作品コード_カテゴリーID.拡張子というファイル
        $fileNameParts = explode('_', $fileName);
        $mcode = $fileNameParts[0];
        $categoryId = $fileNameParts[1];
        $userId = pathinfo(pathinfo($path, PATHINFO_DIRNAME), PATHINFO_FILENAME);
        $size = (filesize($path)) ? filesize($path) : '';
        $extension = pathinfo($path, PATHINFO_EXTENSION);

        $media = [
            'mcode'       => $mcode,
            'category_id' => $categoryId,
            'user_id'     => $userId,
            'size'        => $size,
            'extension'   => $extension,
        ];

        $this->log('$media: ' . print_r($media, true));
        $this->log("\n--------------------\n" . 'end function: ' . __FUNCTION__ . "\n--------------------\n");

        return $media;
    }

    /**
     * DBへメディアを登録する
     */
    function createMedia()
    {
        $this->log("\n--------------------\n" . 'start function: ' . __FUNCTION__ . "\n--------------------\n");

        // トランザクションの開始
        $this->db->exec('BEGIN DEFERRED;');

        try {
            $media = $this->path2media(self::$filePath);

            $query = sprintf('SELECT MAX(version) AS max_version FROM media WHERE mcode = \'%s\' AND category_id = \'%s\';',
                            $media['mcode'],
                            $media['category_id']);
            $maxVersion = $this->db->querySingle($query);
            // バージョンを確定
            $media['version'] = $maxVersion + 1;
            // 作品コード、カテゴリー、バージョンからIDを生成
            $media['id'] = implode('_', array($media['mcode'], $media['category_id'], $media['version']));

            $this->log($media);

            $query = sprintf("INSERT INTO media (id, mcode, size, extension, version, user_id, category_id, created_at, updated_at) VALUES ('%s', '%s', '%s', '%s', '%s', '%s', '%s', %s, %s);",
                            $media['id'],
                            $media['mcode'],
                            $media['size'],
                            $media['extension'],
                            $media['version'],
                            $media['user_id'],
                            $media['category_id'],
                            'datetime(\'now\', \'localtime\')',
                            'datetime(\'now\', \'localtime\')');
            $this->log('$query:' . $query);
            if (!$this->db->exec($query)) {
                $egl = error_get_last();
                $e = new Exception('SQLの実行でエラーが発生しました' . $egl['message']);
                throw $e;
            }

            // コミット
            $this->db->exec('COMMIT;');
            self::$mediaId = $media['id'];

            $this->log('メディアを登録しました mediaId: ' . self::$mediaId);
        } catch (Exception $e) {
            $this->log('メディアの登録に失敗しました: ' . $e->getMessage());

            // ロールバック
            $this->db->exec('ROLLBACK;');
        }

        $this->log("\n--------------------\n" . 'end function: ' . __FUNCTION__ . "\n--------------------\n");
    }

    /**
     * media serviceのjobを作成する
     *
     */
    function createJob()
    {
        $this->log("\n--------------------\n" . 'start function: ' . __FUNCTION__ . "\n--------------------\n");

        $filepath = self::$filePath;
        if (is_null($filepath)) {
            return;
        }

        $asset = null;
        $isUploaded = false;
        $job = null;

        try {
            $mediaServicesWrapper = $this->getMediaServicesWrapper();

            // 資産を作成する
            $asset = new Asset(Asset::OPTIONS_NONE);
            $asset->setName(self::$mediaId);
            $asset = $mediaServicesWrapper->createAsset($asset);

            $this->log('アセットを作成しました: ' . print_r($asset, true));
        } catch (Exception $e) {
            $this->log('アセットの作成に失敗しました: ' . $e->getMessage());
        }

        try {
            // ファイルのアップロードを実行する
            $extension = pathinfo($filepath, PATHINFO_EXTENSION);
            $fileName = sprintf('%s.%s', self::$mediaId, $extension);
            $this->upload(basename($asset->getUri()), $fileName, $filepath);

            $isUploaded = true;
        } catch (Exception $e) {
            $this->log('ブロブのコミットに失敗しました: ' . $e->getMessage());
        }

        try {
            // アップロード失敗の場合、アセットの削除
            if (!$isUploaded && !is_null($asset)) {
                $mediaServicesWrapper->deleteAsset($asset);
            }
        } catch (Exception $e) {
            $this->log($e->getMessage());
        }

        // アップロード失敗していれば終了
        if (!$isUploaded) {
            return;
        }

        try {
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

            $job = new Job();
            $job->setName('job_for_' . self::$mediaId);
            $job = $mediaServicesWrapper->createJob($job, array($asset), array($task));
            self::$job = $job;

            $this->log('ジョブを作成しました: ' . print_r(self::$job, true));
        } catch (Exception $e) {
            $this->log('ジョブの作成に失敗しました: ' . $e->getMessage());
        }

        $this->log("\n--------------------\n" . 'end function: ' . __FUNCTION__ . "\n--------------------\n");
    }

    /**
     * media serviceのjobを作成する(仮)
     *
     * TODO 大きいファイルサイズに対応できていないので一旦未使用
     *
     */
    function createJob2()
    {
        $this->log("\n--------------------\n" . 'start function: ' . __FUNCTION__ . "\n--------------------\n");
        $filepath = self::$filePath;

        if (is_null($filepath)) {
            return;
        }

        $asset = null;
        $accessPolicy = null;
        $locator = null;
        $isUploaded = false;
        $job = null;

        try {
            $mediaServicesWrapper = $this->getMediaServicesWrapper();

            // 資産を作成する
            $asset = new Asset(Asset::OPTIONS_NONE);
            $asset->setName(self::$mediaId);
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
        } catch (Exception $e) {
            $this->log($e->getMessage());
        }

        try {
            // ファイルのアップロードを実行する
            $extension = pathinfo($filepath, PATHINFO_EXTENSION);
            $fileName = sprintf('%s.%s', self::$mediaId, $extension);
//             $mediaServicesWrapper->uploadAssetFile($locator, $fileName, file_get_contents($filepath));
            $this->uploadAssetFile($locator, $fileName, $filepath);

            $isUploaded = true;
        } catch (Exception $e) {
            $this->log($e->getMessage());
        }

        try {
            // アップロード URLの取り消し
            // AccessPolicyの削除
            if (!is_null($locator)) {
                $mediaServicesWrapper->deleteLocator($locator);
            }
            if (!is_null($accessPolicy)) {
                $mediaServicesWrapper->deleteAccessPolicy($accessPolicy);
            }

            // アップロード失敗の場合、アセットの削除
            if (!$isUploaded && !is_null($asset)) {
                $mediaServicesWrapper->deleteAsset($asset);
            }
        } catch (Exception $e) {
            $this->log($e->getMessage());
        }

        // アップロード失敗していれば終了
        if (!$isUploaded) {
            return;
        }

        try {
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
            $job->setName('job_for_' . self::$mediaId);
            $job = $mediaServicesWrapper->createJob($job, array($asset), array($task));
            self::$job = $job;
        } catch (Exception $e) {
            $this->log($e->getMessage());
        }

        $this->log('$job: ' . print_r(self::$job, true));
        $this->log("\n--------------------\n" . 'end function: ' . __FUNCTION__ . "\n--------------------\n");
    }

    /**
     * Upload asset file to storage.
     *
     * @param WindowsAzure\MediaServices\Models\Locator $locator Write locator for
     * file upload
     *
     * @param string $name    Uploading filename
     * @param string $path    Uploading content path
     *
     * @return none
     */
    private function uploadAssetFile($locator, $name, $path)
    {
        $url = $locator->getBaseUri() . '/' .  $name . $locator->getContentAccessComponent();
        $headers = array(
            'Content-Type: application/octet-stream',
            'x-ms-version: 2011-08-18',
            'x-ms-blob-type: BlockBlob',
            'Expect: 100-continue'
        );

        $fp = fopen($path, 'rb');
        if ($fp === false) {
            $egl = error_get_last();
            $e = new Exception('ファイルを開くことができませんでした' . $egl['message']);
            throw $e;
        }
        $ch = curl_init($url);
        $options = [
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_PUT            => true,
            CURLOPT_INFILE         => $fp,
            CURLOPT_INFILESIZE     => filesize($path),
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_BINARYTRANSFER => 1,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSLVERSION     => 3,
            CURLOPT_CONNECTTIMEOUT => 300,
            CURLOPT_TIMEOUT        => 0
        ];
        curl_setopt_array($ch, $options);

        $result = curl_exec($ch);

        if (!curl_errno($ch)) {
            $info = curl_getinfo($ch);

            if ($info['http_code'] != '201') {
                $this->log($info);
                $object = simplexml_load_string($result);
                $this->log($object);

                $e = new Exception('upload error: ' . $object->Code . ' ' . $object->Message);
                curl_close($ch);
                throw $e;
            }

            curl_close($ch);
        } else {
            $e = new Exception(curl_error($ch));
            curl_close($ch);
            throw $e;
        }
        fclose($fp);
    }

    /**
     * ストレージサービスを使用してアップロードする
     *
     * @see http://msdn.microsoft.com/ja-jp/library/windowsazure/dd179439.aspx
     * @param string $containerName    コンテナー名
     * @param string $name             ブロブ名
     * @param string $path             Uploading content path
     *
     * @return none
     */
    private function upload($containerName, $blobName, $path)
    {
        $this->log("\n--------------------\n" . 'start function: ' . __FUNCTION__ . "\n--------------------\n");
        $this->log('$containerName: ' . $containerName);
        $this->log('$blobName: ' . $blobName);
        $this->log('$path: ' . $path);

        $blobRestProxy = $this->getBlobServicesWrapper();

        $content = fopen($path, 'rb');
        $counter = 1;
        $blockIds = [];
        while (!feof($content)) {
            $blockId = str_pad($counter, 6, '0', STR_PAD_LEFT);
            $block = new Block();
            $block->setBlockId(base64_encode($blockId));
            $block->setType(BlobBlockType::UNCOMMITTED_TYPE);
            array_push($blockIds, $block);
            $data = fread($content, CHUNK_SIZE);
            $blobRestProxy->createBlobBlock($containerName, $blobName, base64_encode($blockId), $data);
            $counter++;
        }
        fclose($content);
        $result = $blobRestProxy->commitBlobBlocks($containerName, $blobName, $blockIds);

        $this->log('ブロブコミットの結果: ' . print_r($result, true));
        $this->log("\n--------------------\n" . 'end function: ' . __FUNCTION__ . "\n--------------------\n");
    }

    /**
     * DBのメディアをジョブ情報で更新する
     *
     * @return none
     */
    function updateMedia()
    {
        $this->log("\n--------------------\n" . 'start function: ' . __FUNCTION__ . "\n--------------------\n");

        if (is_null(self::$job) || is_null(self::$mediaId)) {
            return;
        }

        // ジョブ情報をDBに登録
        try {
            $query = sprintf("UPDATE media SET job_id = '%s', job_state = '%s', updated_at = %s WHERE id = '%s';",
                            self::$job->getId(),
                            self::$job->getState(),
                            'datetime(\'now\', \'localtime\')',
                            self::$mediaId);
            $this->log('$query:' . $query);
            if (!$this->db->exec($query)) {
                $egl = error_get_last();
                $e = new Exception('SQLの実行でエラーが発生しました' . $egl['message']);
                throw $e;
            }
        } catch (Exception $e) {
            $this->log('メディアの更新に失敗しました: ' . $e->getMessage());
        }

        $this->log("\n--------------------\n" . 'end function: ' . __FUNCTION__ . "\n--------------------\n");
    }
}

$filepath = fgets(STDIN);
$filepath = str_replace(array("\r\n", "\r", "\n"), '', $filepath);

$processAction = new MmsBinProcessActions($filepath);

?>