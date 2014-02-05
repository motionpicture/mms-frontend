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

define('CHUNK_SIZE', 1024 * 1024); // Block Size = 1 MB

class MmsBinProcessActions extends MmsBinActions
{
    /**
     * __construct
     *
     * @see http://php.net/manual/ja/features.file-upload.common-pitfalls.php
     */
    function __construct()
    {
        parent::__construct();

        set_time_limit(0);
        ini_set('max_execution_time', 600);
        ini_set('memory_limit', '500000000M');

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
        $this->log('$query:' . $query);
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

            $query = sprintf("INSERT INTO media (id, mcode, size, extension, version, user_id, category_id, created_at, updated_at) VALUES ('%s', '%s', '%s', '%s', '%s', '%s', '%s', %s, %s);",
                            $id,
                            $mcode,
                            $size,
                            pathinfo($filepath, PATHINFO_EXTENSION),
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
     */
    function createJob($filepath)
    {
        $this->log('$filepath:' . $filepath);

        $asset = null;
        $accessPolicy = null;
        $locator = null;
        $isUploaded = false;
        $job = null;

        try {
            $mediaServicesWrapper = $this->getMediaServicesWrapper();

            // 資産を作成する
            $asset = new Asset(Asset::OPTIONS_NONE);
            $asset->setName(basename($filepath));
            $asset = $mediaServicesWrapper->createAsset($asset);

            $this->log($asset);

            /*
            // ストレージサービスを使用してアップロードする場合、このプロセスは不要
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
            */
        } catch (Exception $e) {
            $this->log($e->getMessage());
        }

        try {
            // ファイルのアップロードを実行する
            $fileName = basename($filepath);
//             $mediaServicesWrapper->uploadAssetFile($locator, $fileName, file_get_contents($filepath));
//             $this->uploadAssetFile($locator, $fileName, $filepath);
//             $this->uploadAssetFile2($locator, $fileName, $filepath);
            $this->upload(basename($asset->getUri()), $fileName, $filepath);

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
            return $job;
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
    private function uploadAssetFile2($locator, $name, $path)
    {
        $body = file_get_contents($path);
        Validate::isA(
            $locator,
            'WindowsAzure\Mediaservices\Models\Locator',
            'locator'
        );
        Validate::isString($name, 'name');
        Validate::notNull($body, 'body');

        $method     = Resources::HTTP_PUT;
        $urlFile    = $locator->getBaseUri() . '/' . $name;
        $url        = new Url($urlFile . $locator->getContentAccessComponent());

        $filters    = array();
        $statusCode = Resources::STATUS_CREATED;
        $headers    = array(
            Resources::CONTENT_TYPE   => Resources::BINARY_FILE_TYPE,
            Resources::X_MS_VERSION   => Resources::STORAGE_API_LATEST_VERSION,
            Resources::X_MS_BLOB_TYPE => BlobType::BLOCK_BLOB,
        );

        $httpClient = new HttpClient();
//         $httpClient->setConfig([
//                         'connect_timeout'   => 1800,
//                         ]);
        $httpClient->setMethod($method);
        $httpClient->setHeaders($headers);
        $httpClient->setExpectedStatusCode($statusCode);
        $httpClient->setBody($body);
        $httpClient->send($filters, $url);
    }

    /**
     * ストレージサービスを使用してアップロードする
     *
     * @param string $containerName    コンテナー名
     * @param string $name             ブロブ名
     * @param string $path             Uploading content path
     *
     * @return none
     */
    private function upload($containerName, $blobName, $path)
    {
        try {
            $connectionString =  sprintf('DefaultEndpointsProtocol=%s;AccountName=%s;AccountKey=%s',
                                   'http',
                                   'testmvtkmsst',
                                   '+aoUiBttXAZovixNHuNxnkNaMbj2ZWDBzJvkG+FQ0EMmwbGtvEgryoqlQDkq+OxmQomRDQCKZitgeGfAk299Lg==');
            $blobRestProxy = WindowsAzure\Common\ServicesBuilder::getInstance()->createBlobService($connectionString);
        } catch(ServiceException $e) {
        }

        try {
            $content = fopen($path, 'rb');
            $counter = 1;
            $blockIds = [];
            while (!feof($content)) {
                $blockId = str_pad($counter, 6, '0', STR_PAD_LEFT);
                $block = new Block();
                $block->setBlockId(base64_encode($blockId));
                $block->setType(BlobBlockType::UNCOMMITTED_TYPE);
                array_push($blockIds, $block);
//                 echo $blockId . " | " . base64_encode($blockId) . " | " . count($blockIds);
//                 echo "\n";
//                 echo "-----------------------------------------";
                $data = fread($content, CHUNK_SIZE);
//                 echo "Read " . strlen($data) . " of data from file";
//                 echo "\n";
//                 echo "-----------------------------------------";
//                 echo "\n";
//                 echo "-----------------------------------------";
//                 echo "Uploading block #: " . $blockId + " into blob storage. Please wait.";
//                 echo "-----------------------------------------";
//                 echo "\n";
                $blobRestProxy->createBlobBlock($containerName, $blobName, base64_encode($blockId), $data);
//                 echo "Uploaded block: " . $blockId . " into blob storage. Please wait";
//                 echo "\n";
//                 echo "-----------------------------------------";
//                 echo "\n";
                $counter++;
            }
            fclose($content);
//             echo "Now committing block list. Please wait.";
//             echo " -----------------------------------------";
//             echo " \n ";
//             echo "hello";
            $blobRestProxy->commitBlobBlocks($containerName, $blobName, $blockIds);
//             echo " -----------------------------------------";
//             echo " \n ";
//             echo "Blob created successfully.";
        } catch (Exception $e) {
            // @see http://msdn.microsoft.com/ja-jp/library/windowsazure/dd179439.aspx
            throw($e);
        }
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
$filepath = str_replace(array("\r\n", "\r", "\n"), '', $filepath);

$filepath = $processAction->createMediaIfNotExist($filepath);

$job = $processAction->createJob($filepath);

if (!is_null($job)) {
    $processAction->updateMedia($filepath, $job->getId(), $job->getState());
}

$processAction->log('end process ' . date('Y-m-d H:i:s'));

?>