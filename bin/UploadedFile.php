<?php
namespace Mms\Bin;

require_once('BaseContext.php');

use WindowsAzure\MediaServices\Models\Asset;
use WindowsAzure\MediaServices\Models\AccessPolicy;
use WindowsAzure\MediaServices\Models\Locator;
use WindowsAzure\MediaServices\Models\Job;
use WindowsAzure\MediaServices\Models\Task;
use WindowsAzure\MediaServices\Models\TaskOptions;

use WindowsAzure\Blob\Models\Block;
use WindowsAzure\Blob\Models\BlobBlockType;
use WindowsAzure\Blob\Models\CreateBlobBlockOptions;

set_time_limit(0);
ini_set('memory_limit', '1024M');

class UploadedFile extends BaseContext
{
    const CHUNK_SIZE = 1048576; // 1024 * 1024

    // 入力ファイルパス
    private static $filePath = null;

    /**
     * __construct
     *
     * @see http://php.net/manual/ja/features.file-upload.common-pitfalls.php
     */
    function __construct($userSettings, $filePath)
    {
        parent::__construct($userSettings);

        if (!$filePath) {
            throw new \Exception('$filePath is required.');
        }

        self::$filePath = $filePath;
    }

    /**
     * アップロードされたファイルに対して処理を施す
     *
     * 1.DBにメディアを登録
     * 2.メディアサーバーへアップロード
     *
     * @return array
     */
    public function path2asset()
    {
        $mediaId = null;
        $assetId = null;

        try {
            if (!is_file(self::$filePath)) {
                $e = new \Exception('file does not exists.');
                throw $e;
            }

            $mediaId = $this->createMedia();
            list($assetId, $isComleted) = $this->ingestAsset($mediaId);

            if (!is_null($assetId) && !$isComleted) {
                $assetId = null;
                // TODO アセット削除
            }
        } catch (\Exception $e) {
            $message = 'process throw exception. filePath:' . self::$filePath . ' message:' . $e->getMessage();
            $this->logger->log($message);
            $this->reportError($message);

            $mediaId = null;
            $assetId = null;
        }

        return [$mediaId, $assetId];
    }

    /**
     * ファイルパスからメディアプロパティの配列を返す
     *
     * @param string $path ファイルパス
     * @return \Mms\Lib\Models\Media
     */
    private function path2media($path)
    {
        $this->logger->log("\n--------------------\n" . 'start function: ' . __FUNCTION__ . "\n--------------------\n");
        $this->logger->log('$path: ' . print_r($path, true));

        $fileName = pathinfo($path, PATHINFO_FILENAME);

        // アップロードされた場合、作品コード_カテゴリーID.拡張子というファイル
        $fileNameParts = explode('_', $fileName);
        $mcode = $fileNameParts[0];
        $categoryId = $fileNameParts[1];
        $userId = pathinfo(pathinfo($path, PATHINFO_DIRNAME), PATHINFO_FILENAME);
        $size = (filesize($path)) ? filesize($path) : '';
        $extension = pathinfo($path, PATHINFO_EXTENSION);
        $startAt = '';
        $endAt = '';

        // バージョンを確定
        $query = "SELECT MAX(version) AS max_version FROM media WHERE mcode = '{$mcode}' AND category_id = '{$categoryId}'";
        $statement = $this->db->query($query);
        $maxVersion = $statement->fetchColumn();
        if (is_null($maxVersion)) {
            // 初めての場合バージョン1から
            $version = 1;
        } else {
            $version = $maxVersion + 1;
            // 既存バージョンの公開開始終了日時を引き継ぐ
            $query = "SELECT start_at, end_at FROM media WHERE mcode = '{$mcode}' AND category_id = '{$categoryId}' AND version = '{$maxVersion}'";
            $statement = $this->db->query($query);
            $maxVersionMedia = $statement->fetch();
            $startAt = $maxVersionMedia['start_at'];
            $endAt = $maxVersionMedia['end_at'];
        }

        $options = [
            'mcode'      => $mcode,
            'categoryId' => $categoryId,
            'userId'     => $userId,
            'size'       => $size,
            'extension'  => $extension,
            'version'    => $version,
            'startAt'    => $startAt,
            'endAt'      => $endAt
        ];

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
            $this->logger->log('fail in getting movie name. message:' . $e->getMessage());
        }

        // メディアオブジェクト生成
        $media = \Mms\Lib\Models\Media::createFromOptions($options);

        $this->logger->log('$media: ' . print_r($media, true));
        $this->logger->log("\n--------------------\n" . 'end function: ' . __FUNCTION__ . "\n--------------------\n");

        return $media;
    }

    /**
     * DBへメディアを登録する
     *
     * @return string
     */
    private function createMedia()
    {
        $this->logger->log("\n--------------------\n" . 'start function: ' . __FUNCTION__ . "\n--------------------\n");

        $mediaId = null;

        try {
            $media = $this->path2media(self::$filePath);

            $query = vsprintf(
                "INSERT INTO media (id, code, mcode, category_id, version, size, extension, user_id, movie_name, playtime_string, playtime_seconds, job_id, job_state, job_start_at, job_end_at, start_at, end_at, created_at, updated_at) VALUES ('%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', datetime('now', 'localtime'), datetime('now', 'localtime'))",
                $media->toArray()
            );
            $this->logger->log('$query:' . $query);
            $this->db->exec($query);

            $mediaId = $media->getId();

            $this->logger->log('media has been created. mediaId: ' . $mediaId);
        } catch (\Exception $e) {
            $this->logger->log('fail in creating media: ' . $e->getMessage());
            throw $e;
        }

        $this->logger->log("\n--------------------\n" . 'end function: ' . __FUNCTION__ . "\n--------------------\n");

        return $mediaId;
    }

    /**
     * 資産をインジェストする
     *
     * @see http://msdn.microsoft.com/ja-jp/library/jj129593.aspx
     * @return array
     */
    private function ingestAsset($mediaId)
    {
        $this->logger->log("\n--------------------\n" . 'start function: ' . __FUNCTION__ . "\n--------------------\n");
        $this->logger->log('args: ' . print_r(func_get_args(), true));

        $asset = null;
        $assetId = null;
        $isCompleted = false;

        try {
            $mediaServicesWrapper = $this->azureContext->getMediaServicesWrapper();

            // 資産を作成する
            $asset = new Asset(Asset::OPTIONS_NONE);
            $asset->setName($mediaId);
            $asset = $mediaServicesWrapper->createAsset($asset);
            $assetId = $asset->getId();

            $this->logger->log('asset has been created. asset:' . $assetId);
        } catch (\Exception $e) {
            $this->logger->log('createAsset throw exception. message:' . $e->getMessage());
        }

        try {
            // ファイルのアップロードを実行する
            $extension = pathinfo(self::$filePath, PATHINFO_EXTENSION);
            $fileName = sprintf('%s.%s', $mediaId, $extension);
            $this->upload2storage(basename($asset->getUri()), $fileName, self::$filePath);

            $isUploaded = true;
        } catch (\Exception $e) {
            $this->logger->log('upload2storage throw exception. message:' . $e->getMessage());
        }

        try {
            // ファイル メタデータの生成
            $mediaServicesWrapper->createFileInfos($asset);

            // ここまできて初めて、アセットの準備が完了したことになる
            $isCompleted = true;
            $this->logger->log('inputAsset has been prepared completely. asset:' . $assetId);
        } catch (\Exception $e) {
            $this->logger->log('createFileInfos throw exception. message:' . $e->getMessage());
        }

        $this->logger->log("\n--------------------\n" . 'end function: ' . __FUNCTION__ . "\n--------------------\n");

        return [$assetId, $isCompleted];
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
        $this->logger->log("\n--------------------\n" . 'start function: ' . __FUNCTION__ . "\n--------------------\n");
        $this->logger->log('args: ' . print_r(func_get_args(), true));

        $blobRestProxy = $this->azureContext->getBlobServicesWrapper();

        $content = fopen($path, 'rb');
        $counter = 1;
        $blockIds = [];
        while (!feof($content)) {
            $blockId = str_pad($counter, 6, '0', STR_PAD_LEFT);
            $block = new Block();
            $block->setBlockId(base64_encode($blockId));
            $block->setType(BlobBlockType::UNCOMMITTED_TYPE);
            array_push($blockIds, $block);

            $data = fread($content, self::CHUNK_SIZE);
            $this->createBlobBlock($containerName, $blobName, base64_encode($blockId), $data);

            $this->logger->log('BlobBlock has been created. counter: ' . $counter);
            $counter++;
        }
        fclose($content);
        $result = $blobRestProxy->commitBlobBlocks($containerName, $blobName, $blockIds);

        $this->logger->log('BlobBlocks has been commit. result: ' . print_r($result, true));
        $this->logger->log("\n--------------------\n" . 'end function: ' . __FUNCTION__ . "\n--------------------\n");
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
        $this->logger->log("\n--------------------\n" . 'start function: ' . __FUNCTION__ . "\n--------------------\n");
        $this->logger->log('$container: ' . $container);
        $this->logger->log('$blob: ' . $blob);
        $this->logger->log('$blockId: ' . $blockId);
        $this->logger->log('$content length: ' . strlen($content));
        $this->logger->log('$options: ' . print_r($options, true));

        $blobRestProxy = $this->azureContext->getBlobServicesWrapper();
        $url = sprintf('%s/%s/%s',
                        $blobRestProxy->getUri(),
                        $container,
                        $blob);

        $headers = [
            'content-type'   => 'application/x-www-form-urlencoded',
            'content-length' => strlen($content),
            'user-agent'     => "Azure-SDK-For-PHP/0.4.0",
            'x-ms-version'   => '2011-08-18',
            'date'           => gmdate('D, d M Y H:i:s T', time()),
        ];

        $queryParams = [
            'comp'    => 'block',
            'blockid' => base64_encode($blockId)
        ];

        $httpMethod = 'PUT';

        $url .= '?' . http_build_query($queryParams);

        $authSchema = $this->azureContext->getBlobAuthenticationScheme();
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
        $this->logger->debug('result of creating blob block: ' . print_r($result, true));
        fclose($fp);

        // 一時ファイルを削除
        unlink($tmpFile);

        if (!curl_errno($ch)) {
            $info = curl_getinfo($ch);
            $this->logger->log($info);

            if ($info['http_code'] != '201') {
                $object = simplexml_load_string($result);
                $this->logger->log($object);

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

        $this->logger->log("\n--------------------\n" . 'end function: ' . __FUNCTION__ . "\n--------------------\n");
    }
}

?>