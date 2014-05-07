<?php
namespace Mms\Bin;

require_once('BaseContext.php');

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
use WindowsAzure\Blob\Models\CreateBlobBlockOptions;

set_time_limit(0);
ini_set('max_execution_time', 600);
ini_set('memory_limit', '1024M');

define('CHUNK_SIZE', 1024 * 1024); // Block Size = 1 MB

class UploadedFile extends BaseContext
{
    private static $filePath = null;
    private static $mediaId = null;

    /**
     * __construct
     *
     * @see http://php.net/manual/ja/features.file-upload.common-pitfalls.php
     */
    function __construct($filePath)
    {
        parent::__construct();

        $this->logFile = dirname(__FILE__) . '/../log/bin/process/process_' . date('Ymd') . '.log';

        self::$filePath = $filePath;

        $this->log('$filePath: ' . $filePath);
    }

    public function process()
    {
        try {
            if (!is_file(self::$filePath)) {
              $e = new \Exception('file does not exists.');
              throw $e;
            }

            $this->createMedia();

            $job = $this->createJob();

            if (!is_null($job)) {
                $this->updateMedia($job->getId(), $job->getState());

                // 開発環境以外では元ファイル削除
                if (!$this->getIsDev()) {
                    unlink(self::$filePath);
                }
            }

        } catch (\Exception $e) {
            $this->log($e->getMessage());
        }
    }

    /**
     * ファイルパスからメディアプロパティの配列を返す
     *
     * @param string $path ファイルパス
     * @return \Mms\Lib\Models\Media
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

        // バージョンを確定
        $query = "SELECT MAX(version) AS max_version FROM media WHERE mcode = '{$mcode}' AND category_id = '{$categoryId}'";
        $statement = $this->db->query($query);
        $maxVersion = $statement->fetchColumn();
        if (is_null($maxVersion)) {
            // 初めての場合バージョン1から
            $version = 1;
        } else {
            $version = $maxVersion + 1;
        }

        $options = array(
            'mcode'      => $mcode,
            'categoryId' => $categoryId,
            'userId'     => $userId,
            'size'       => $size,
            'extension'  => $extension,
            'version'    => $version
        );

        // 再生時間を取得
        $getID3 = new \getID3;
        $fileInfo = $getID3->analyze(self::$filePath);
        if (isset($fileInfo['playtime_string'])) {
            $options['playtimeString'] = $fileInfo['playtime_string'];
        }
        if (isset($fileInfo['playtime_seconds'])) {
            $options['playtimeSeconds'] = $fileInfo['playtime_seconds'];
        }

        // 作品名を取得
        $options['movieName'] = '';
        try {
            $option = [
                'soap' => [
                    'endPoint' => 'https://www.movieticket.jp',
                ],
                'blob' => [
                    'name' => 'testmovieticketfrontend',
                    'key' => 'c93s/ZXgTySSgB6FrCWvOXalfRxKQFd96s61X8TwMUc3jmjAeRyBY9jSMvVQXh4U9gIRNNH6mCkn44ZG/T3OXA==',
                ],
                'sendgrid' => [
                    'api_user' => 'azure_2fa68dcc38c9589d53104d96bc2798ed@azure.com',
                    'api_key' => 'pwmk27ud',
                    'from' => 'info@movieticket.jp',
                    'fromname' => 'ムビチケ',
                ],
            ];

            $factory = new \MvtkService\Factory($option);
            $service = $factory->createInstance('Film');
            $params = [
                'skhnCd' => $mcode,
                'dvcTyp' => \MvtkService\Common::DVC_TYP_PC,
            ];
            $film = $service->GetFilmDetail($params);
            $film = $film->toArray();
            $options['movieName'] = $film['SKHN_NM'];
        } catch (\Exception $e) {
            $this->log('fail in getting movie name: ' . $e->getMessage());
        }

        // メディアオブジェクト生成
        $media = \Mms\Lib\Models\Media::createFromOptions($options);

        $this->log('$media: ' . print_r($media, true));
        $this->log("\n--------------------\n" . 'end function: ' . __FUNCTION__ . "\n--------------------\n");

        return $media;
    }

    /**
     * DBへメディアを登録する
     */
    private function createMedia()
    {
        $this->log("\n--------------------\n" . 'start function: ' . __FUNCTION__ . "\n--------------------\n");

        try {
            $media = $this->path2media(self::$filePath);

            $query = sprintf(
                "INSERT INTO media (id, code, mcode, size, extension, version, user_id, movie_name, playtime_string, playtime_seconds, category_id, created_at, updated_at) VALUES ('%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', datetime('now', 'localtime'), datetime('now', 'localtime'));",
                $media->getId(),
                $media->getCode(),
                $media->getMcode(),
                $media->getSize(),
                $media->getExtension(),
                $media->getVersion(),
                $media->getUserId(),
                $media->getMovieName(),
                $media->getPlaytimeString(),
                $media->getPlaytimeSeconds(),
                $media->getCategoryId()
            );
            $this->log('$query:' . $query);
            $this->db->exec($query);

            self::$mediaId = $media->getId();

            $this->log('media has been created. mediaId: ' . self::$mediaId);
        } catch (\Exception $e) {
            $this->log('fail in creating media: ' . $e->getMessage());
            throw $e;
        }

        $this->log("\n--------------------\n" . 'end function: ' . __FUNCTION__ . "\n--------------------\n");
    }

    /**
     * media serviceのjobを作成する
     *
     * @return object
     *
     */
    private function createJob()
    {
        $this->log("\n--------------------\n" . 'start function: ' . __FUNCTION__ . "\n--------------------\n");

        $filepath = self::$filePath;
        if (is_null($filepath)) {
            return null;
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

            $this->log('asset has been created: ' . print_r($asset, true));
        } catch (\Exception $e) {
            $this->log('fail in creating asset: ' . $e->getMessage());
            throw $e;
        }

        try {
            // ファイルのアップロードを実行する
            $extension = pathinfo($filepath, PATHINFO_EXTENSION);
            $fileName = sprintf('%s.%s', self::$mediaId, $extension);
            $this->upload2storage(basename($asset->getUri()), $fileName, $filepath);

            $isUploaded = true;
        } catch (\Exception $e) {
            $this->log('fail in commiting blob: ' . $e->getMessage());
            throw $e;
        }

        try {
            // アップロード失敗の場合、アセットの削除
            if (!$isUploaded && !is_null($asset)) {
                $mediaServicesWrapper->deleteAsset($asset);
            }
        } catch (\Exception $e) {
            $this->log('fail in deleting asset: ' . $e->getMessage());
            throw $e;
        }

        // アップロード失敗していれば終了
        if (!$isUploaded) {
            return;
        }

        try {
            // ファイル メタデータの生成
            $mediaServicesWrapper->createFileInfos($asset);

            $tasks = $this->prepareTasks();

            $job = new Job();
            $job->setName('job_for_' . self::$mediaId);
            $job = $mediaServicesWrapper->createJob($job, array($asset), $tasks);

            $this->log('job has been created: ' . print_r($job, true));
        } catch (\Exception $e) {
            $this->log('fail in creating job: ' . $e->getMessage());
            throw $e;
        }

        $this->log("\n--------------------\n" . 'end function: ' . __FUNCTION__ . "\n--------------------\n");

        return $job;
    }

    /**
     * ジョブにセットするタスクリストを用意する
     *
     * ジョブを作成する上で最も肝になる部分
     * 更新する場合
     * タスクの順序や、JobInputAssetとJobOutputAssetのキーナンバーに、気をつけること
     *
     * @see http://msdn.microsoft.com/ja-jp/library/dn629573.aspx
     * @return multitype:\WindowsAzure\MediaServices\Models\Task
     */
    private function prepareTasks()
    {
        $this->log("\n--------------------\n" . 'start function: ' . __FUNCTION__ . "\n--------------------\n");

        $tasks = array();
        $mediaServicesWrapper = $this->getMediaServicesWrapper();

        // dynamic_packaging
//         $mediaProcessor = $mediaServicesWrapper->getLatestMediaProcessor('Windows Azure Media Encoder');
//         $taskBody = $this->getMediaServicesTaskBody(
//             'JobInputAsset(0)',
//             'JobOutputAsset(' . count($tasks) .')',
//             Asset::OPTIONS_NONE,
//             \Mms\Lib\Models\Task::NAME_DYNAMIC_PACKAGING
//         );
//         $task = new Task($taskBody, $mediaProcessor->getId(), TaskOptions::NONE);
//         $task->setConfiguration('H264 Smooth Streaming 1080p');
//         $tasks[] = $task;

        // adaptive bitrate mp4
        $mediaProcessor = $mediaServicesWrapper->getLatestMediaProcessor('Windows Azure Media Encoder');
        $taskBody = $this->getMediaServicesTaskBody(
            'JobInputAsset(0)',
            'JobOutputAsset(0)',
            Asset::OPTIONS_NONE,
            \Mms\Lib\Models\Task::NAME_ADAPTIVE_BITRATE_MP4
        );
        $this->log('$taskBody: ' . $taskBody);
        $task = new Task($taskBody, $mediaProcessor->getId(), TaskOptions::NONE);
        $task->setConfiguration('H264 Adaptive Bitrate MP4 Set 1080p');
        $tasks[] = $task;

        $this->log('tasks has been prepared. tasks count: ' . count($tasks));

        $this->log("\n--------------------\n" . 'end function: ' . __FUNCTION__ . "\n--------------------\n");

        return $tasks;
    }

    /**
     * ジョブにセットするタスクリストを用意する
     *
     * ジョブを作成する上で最も肝になる部分
     * 更新する場合
     * タスクの順序や、JobInputAssetとJobOutputAssetのキーナンバーに、気をつけること
     *
     * 1. ダイナミックパッケージング
     * 2. 入力ファイルを一連の複数ビットレート MP4 にエンコードする。
     * 3. 複数ビットレート MP4をスムーズストリームにパッケージする。
     * 4. スムーズ ストリームを暗号化する。
     * 5. 暗号化されたスムーズ ストリームをHLSにパッケージしてPlayReadyで暗号化されたHLSを取得する。
     *
     * @see http://msdn.microsoft.com/ja-jp/library/dn629573.aspx
     * @return multitype:\WindowsAzure\MediaServices\Models\Task
     */
    private function prepareTasks2()
    {
      $this->log("\n--------------------\n" . 'start function: ' . __FUNCTION__ . "\n--------------------\n");

      $tasks = array();
      $mediaServicesWrapper = $this->getMediaServicesWrapper();

       // adaptive bitrate mp4
      $mediaProcessor = $mediaServicesWrapper->getLatestMediaProcessor('Windows Azure Media Encoder');
      $taskBody = $this->getMediaServicesTaskBody(
          'JobInputAsset(0)',
          'JobOutputAsset(0)',
          Asset::OPTIONS_NONE,
          \Mms\Lib\Models\Task::NAME_ADAPTIVE_BITRATE_MP4
      );
      $this->log('$taskBody: ' . $taskBody);
      $task = new Task($taskBody, $mediaProcessor->getId(), TaskOptions::NONE);
      $task->setConfiguration('H264 Adaptive Bitrate MP4 Set 720p');
      $tasks[] = $task;

      // smooth streaming
      $mediaProcessor = $mediaServicesWrapper->getLatestMediaProcessor('Windows Azure Media Packager');
      $taskBody = $this->getMediaServicesTaskBody(
          'JobOutputAsset(0)',
          'JobOutputAsset(1)',
          Asset::OPTIONS_NONE,
          \Mms\Lib\Models\Task::NAME_SMOOTH_STREAMING
      );
      $this->log('$taskBody: ' . $taskBody);
      $task = new Task($taskBody, $mediaProcessor->getId(), TaskOptions::NONE);
      $configurationFile  = dirname(__FILE__) . '/config/MediaPackager_MP4ToSmooth.xml';
      $task->setConfiguration(file_get_contents($configurationFile));
      $tasks[] = $task;

      // http_live_streaming
      $mediaProcessor = $mediaServicesWrapper->getLatestMediaProcessor('Windows Azure Media Packager');
      $taskBody = $this->getMediaServicesTaskBody(
          'JobOutputAsset(1)',
          'JobOutputAsset(2)',
          Asset::OPTIONS_NONE,
          \Mms\Lib\Models\Task::NAME_HLS
      );
//         $task = new Task($taskBody, $mediaProcessor->getId(), TaskOptions::PROTECTED_CONFIGURATION);
      $task = new Task($taskBody, $mediaProcessor->getId(), TaskOptions::NONE);
      $configurationFile  = dirname(__FILE__) . '/config/MediaPackager_SmoothToHLS.xml';
      $task->setConfiguration(file_get_contents($configurationFile));
      $tasks[] = $task;

      // PlayReady
      $mediaProcessor = $mediaServicesWrapper->getLatestMediaProcessor('Windows Azure Media Encryptor');
      $taskBody = $this->getMediaServicesTaskBody(
          'JobOutputAsset(1)',
          'JobOutputAsset(3)',
          Asset::OPTIONS_COMMON_ENCRYPTION_PROTECTED,
          \Mms\Lib\Models\Task::NAME_SMOOTH_STREAMING_PLAYREADY
      );
      $this->log('$taskBody: ' . $taskBody);
      // テスト段階では、TaskOptions::PROTECTED_CONFIGURATIONだとkeyIdを設定しなさい、と怒られる
//         $task = new Task($taskBody, $mediaProcessor->getId(), TaskOptions::PROTECTED_CONFIGURATION);
      $task = new Task($taskBody, $mediaProcessor->getId(), TaskOptions::NONE);
      $configurationFile  = dirname(__FILE__) . '/config/MediaEncryptor_PlayReadyProtection.xml';
      $task->setConfiguration(file_get_contents($configurationFile));
      $tasks[] = $task;

      // http_live_streaming_playready
      $mediaProcessor = $mediaServicesWrapper->getLatestMediaProcessor('Windows Azure Media Packager');
      $taskBody = $this->getMediaServicesTaskBody(
          'JobOutputAsset(3)',
          'JobOutputAsset(4)',
          Asset::OPTIONS_COMMON_ENCRYPTION_PROTECTED,
          \Mms\Lib\Models\Task::NAME_HLS_PLAYREADY
      );
//         $task = new Task($taskBody, $mediaProcessor->getId(), TaskOptions::PROTECTED_CONFIGURATION);
      $task = new Task($taskBody, $mediaProcessor->getId(), TaskOptions::NONE);
      $configurationFile  = dirname(__FILE__) . '/config/MediaPackager_SmoothToHLS.xml';
      $task->setConfiguration(file_get_contents($configurationFile));
      $tasks[] = $task;

      $this->log('tasks has been prepared. tasks count: ' . count($tasks));

      $this->log("\n--------------------\n" . 'end function: ' . __FUNCTION__ . "\n--------------------\n");

      return $tasks;
    }

    private function getMediaServicesTaskBody($inputAsset, $outputAsset, $outputAssetOptions, $outputAssetName) {
        return '<?xml version="1.0" encoding="utf-8"?><taskBody><inputAsset>' . $inputAsset . '</inputAsset><outputAsset assetCreationOptions="' . $outputAssetOptions . '" assetName="' . $outputAssetName . '">' . $outputAsset . '</outputAsset></taskBody>';
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
    private function upload2storage($containerName, $blobName, $path)
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
            $this->createBlobBlock($containerName, $blobName, base64_encode($blockId), $data);

            $counter++;
        }
        fclose($content);
        $result = $blobRestProxy->commitBlobBlocks($containerName, $blobName, $blockIds);

        $this->log('result of commiting blob: ' . print_r($result, true));
        $this->log("\n--------------------\n" . 'end function: ' . __FUNCTION__ . "\n--------------------\n");
    }

    /**
     * Creates a new block to be committed as part of a block blob.
     *
     * @param string                        $container name of the container
     * @param string                        $blob      name of the blob
     * @param string                        $blockId   must be less than or equal to
     * 64 bytes in size. For a given blob, the length of the value specified for the
     * blockid parameter must be the same size for each block.
     * @param string                        $content   the blob block contents
     * @param Models\CreateBlobBlockOptions $options   optional parameters
     *
     * @return none
     *
     * @see http://msdn.microsoft.com/en-us/library/windowsazure/dd135726.aspx
     */
    private function createBlobBlock($container, $blob, $blockId, $content, $options = null) {
        $this->log("\n--------------------\n" . 'start function: ' . __FUNCTION__ . "\n--------------------\n");
        $this->log('$container: ' . $container);
        $this->log('$blob: ' . $blob);
        $this->log('$blockId: ' . $blockId);
        $this->log('$content length: ' . strlen($content));
        $this->log('$options: ' . print_r($options, true));

        $blobRestProxy = $this->getBlobServicesWrapper();
        $url = sprintf('%s/%s/%s',
                        $blobRestProxy->getUri(),
                        $container,
                        $blob);

        $headers = [
            'content-type' => 'application/x-www-form-urlencoded',
            'content-length' => strlen($content),
            'user-agent' => "Azure-SDK-For-PHP/0.4.0",
            'x-ms-version' => '2011-08-18',
            'date' => gmdate('D, d M Y H:i:s T', time()),
        ];

        $queryParams = [
            'comp' => 'block',
            'blockid' => base64_encode($blockId)
        ];

        $httpMethod = 'PUT';

        $url .= '?' . http_build_query($queryParams);

        $authSchema = $this->getBlobAuthenticationScheme();
        $sharedKey = $authSchema->getAuthorizationHeader($headers, $url, $queryParams, $httpMethod);
        $headers['authorization'] = $sharedKey;

        // PUTするためのファイルポインタ作成
        // なければ作成
        $tmpDir = dirname(__FILE__) . DIRECTORY_SEPARATOR . 'tmp';
        if (!file_exists($tmpDir)) {
          mkdir($tmpDir, 0777);
          chmod($tmpDir, 0777);
        }
        $tmpFile = $tmpDir . DIRECTORY_SEPARATOR . pathinfo($blob, PATHINFO_FILENAME);
        $fp = fopen($tmpFile, 'w+');
        if ($fp === false) {
            $egl = error_get_last();
            $e = new \Exception('cannot open file: ' . $egl['message']);
            throw $e;
        }
        fwrite($fp, $content);
        fclose($fp);

        $fp = fopen($tmpFile, 'rb');
        if ($fp === false) {
            $egl = error_get_last();
            $e = new \Exception('cannot open file: ' . $egl['message']);
            throw $e;
        }

        $curlHeaders = [];
        foreach ($headers as $name => $value) {
            $canonicalName = implode('-', array_map('ucfirst', explode('-', $name)));
            $curlHeaders[]  = $canonicalName . ': ' . $value;
        }

        $ch = curl_init($url);
        $options = [
            CURLOPT_HTTPHEADER     => $curlHeaders,
            CURLOPT_PUT            => true,
            CURLOPT_INFILE         => $fp,
            CURLOPT_INFILESIZE     => strlen($content),
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_BINARYTRANSFER => 1,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSLVERSION     => 3,
            CURLOPT_CONNECTTIMEOUT => 300,
            CURLOPT_TIMEOUT        => 0
        ];
        curl_setopt_array($ch, $options);

        $result = curl_exec($ch);
        $this->log('result of creating blob block: ' . print_r($result, true));
        fclose($fp);

        // 一時ファイルを削除
        unlink($tmpFile);

        if (!curl_errno($ch)) {
            $info = curl_getinfo($ch);
            $this->log($info);

            if ($info['http_code'] != '201') {
                $object = simplexml_load_string($result);
                $this->log($object);

                $e = new \Exception('upload error: ' . $object->Code . ' ' . $object->Message);
                curl_close($ch);
                throw $e;
            }

            curl_close($ch);
        } else {
            $e = new \Exception(curl_error($ch));
            curl_close($ch);
            throw $e;
        }

        $this->log("\n--------------------\n" . 'end function: ' . __FUNCTION__ . "\n--------------------\n");
    }

    /**
     * DBのメディアをジョブ情報で更新する
     *
     * @param string $jobId
     * @param string $jobState
     * @return none
     */
    private function updateMedia($jobId, $jobState)
    {
        $this->log("\n--------------------\n" . 'start function: ' . __FUNCTION__ . "\n--------------------\n");
        $this->log('args: ' . print_r(func_get_args(), true));

        if (is_null($jobId) || is_null($jobState)) {
            $e = new \Exception('job id & job state are required.');
            $this->log('fail in updating media: ' . $e->getMessage());
            throw $e;
        }

        // ジョブ情報をDBに登録
        try {
            $query = sprintf("UPDATE media SET job_id = '%s', job_state = '%s', updated_at = %s WHERE id = '%s';",
                            $jobId,
                            $jobState,
                            'datetime(\'now\', \'localtime\')',
                            self::$mediaId);
            $this->log('$query:' . $query);
            $this->db->exec($query);
        } catch (\Exception $e) {
            $this->log('fail in updating media: ' . $e->getMessage());
            throw $e;
        }

        $this->log("\n--------------------\n" . 'end function: ' . __FUNCTION__ . "\n--------------------\n");
    }
}

?>