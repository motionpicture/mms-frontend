<?php
namespace Mms\Bin\Contexts;

require_once __DIR__ . '/../BaseContext.php';

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

/**
 * 管理サイトあるいはFTPにてアップロードされたファイルという文脈
 *
 * @package   Mms\Bin\Contexts
 * @author    Tetsu Yamazaki <yamazaki@motionpicture.jp>
 */
class UploadedFile extends \Mms\Bin\BaseContext
{
    /**
     * 大きなサイズのファイルを小分けにしてアップロードする際の、分割ファイルサイズ
     *
     * @var int
     */
    const CHUNK_SIZE = 1048576; // 1024 * 1024

    /**
     * アップロードされたファイルパス
     *
     * @var string
     */
    private static $filePath = null;

    /**
     * アップロードしたユーザー
     *
     * @var array
     */
    private static $user = null;

    /**
     * __construct
     *
     * @see http://php.net/manual/ja/features.file-upload.common-pitfalls.php
     */
    function __construct($userSettings, $filePath = null)
    {
        parent::__construct($userSettings);

        if (!$filePath) {
            throw new \Exception('filePath is required.');
        }

        if (!is_file($filePath)) {
            throw new \Exception('file does not exists.');
        }

        // ユーザーの存在確認
        $userId = pathinfo(pathinfo($filePath, PATHINFO_DIRNAME), PATHINFO_FILENAME);
        $query = "SELECT * FROM user WHERE id = '{$userId}'";
        $statement = $this->db->query($query);
        $user = $statement->fetch();
        if (!$user) {
            throw new \Exception('user:' . $userId . ' does not exist.');
        }
        $this->logger->debug('user:' . print_r($user, true));

        self::$filePath = $filePath;
        self::$user = $user;
    }

    /**
     * アップロードされたファイルに対して処理を施す
     *
     * 1.パスからメディアオブジェクトを生成
     * 2.メディアサーバーへアップロード
     * 3.DBにメディアを登録
     *
     * @return array
     */
    public function path2asset()
    {
        $this->logger->log("\n--------------------\n" . 'start function: ' . __FUNCTION__ . "\n--------------------\n");

        $mediaId = null;
        $assetId = null;
        $isComleted = false;

        try {
            // パスからメディアオブジェクトへ変換
            $media = $this->path2media(self::$filePath);
            $mediaId = $media->getId();

            // メディアサービスへ資産としてアップロードする
            list($assetId, $isComleted) = $this->ingestAsset($mediaId);
        } catch (\Exception $e) {
            $message = 'process throw exception. filePath:' . self::$filePath . ' message:' . $e->getMessage();
            $this->logger->log($message);
            $this->reportError($message);

            // アップロードユーザーにも通知
            $user = self::$user;
            $message = $user['id'] . '様<br><br>以下のファイルをメディアサービスへアップロードすることができませんでした。<br>おそれいりますが、再度ファイルのアップロードをお願いいたします。<br><br>' . pathinfo(self::$filePath, PATHINFO_BASENAME);
            $this->sendErrorMail($user['email'], $message);

            $mediaId = null;
            $assetId = null;
        }

        try {
            // メディア登録
            if (!is_null($mediaId) && !is_null($assetId) && $isComleted) {
                $media->setAssetId($assetId);

                $query = vsprintf(
                    "INSERT INTO media (id, code, mcode, category_id, version, size, extension, user_id, movie_name, playtime_string, playtime_seconds, asset_id, job_id, job_state, job_start_at, job_end_at, start_at, end_at, created_at, updated_at, deleted_at) VALUES ('%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', datetime('now', 'localtime'), datetime('now', 'localtime'), '')",
                    $media->toArray()
                );
                $this->logger->log('$query:' . $query);
                $this->db->exec($query);

                $this->logger->log('media has been created. mediaId:' . $mediaId);
            }
        } catch (\Exception $e) {
            $message = 'inserting into media throw exception. $mediaId:' . $mediaId . ' $$assetId:' . $assetId . ' message:' . $e->getMessage();
            $this->logger->log($message);
            $this->reportError($message);

            // アップロードユーザーにも通知
            $user = self::$user;
            $message = $user['id'] . '様<br><br>以下のファイルを正常にエンコードタスクにかけることができませんでした。<br>おそれいりますが、再度ファイルのアップロードをお願いいたします。<br><br>ファイル名:' . pathinfo(self::$filePath, PATHINFO_BASENAME);
            $this->sendErrorMail($user['email'], $message);

            $mediaId = null;
            $assetId = null;
        }

        if (!is_null($assetId) && !$isComleted) {
            $assetId = null;
            // TODO アセット削除
        }

        // 元ファイル削除
        if (!$this->getIsDev() && (!is_null($mediaId) && !is_null($assetId))) {
            unlink(self::$filePath);
        }

        $this->logger->log("\n--------------------\n" . 'end function: ' . __FUNCTION__ . "\n--------------------\n");

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
        if (!isset($fileNameParts[0]) || !isset($fileNameParts[1])) {
            throw new \Exception('Path:' . $path . ' is not validate.');
        }

        $mcode = $fileNameParts[0];
        $categoryId = $fileNameParts[1];
        $userId = pathinfo(pathinfo($path, PATHINFO_DIRNAME), PATHINFO_FILENAME);
        $size = (filesize($path)) ? filesize($path) : '';
        $extension = pathinfo($path, PATHINFO_EXTENSION);
        $startAt = '';
        $endAt = '';

        // カテゴリー存在チェック
        $query = "SELECT COUNT(id) AS count FROM category WHERE id = '{$categoryId}'";
        $statement = $this->db->query($query);
        $count = $statement->fetchColumn();
        if ($count < 1) {
            throw new \Exception('Category Id:' . $categoryId . ' does not exist.');
        }

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
            $query = "SELECT start_at, end_at FROM media WHERE mcode = '{$mcode}' AND category_id = '{$categoryId}' AND version = '{$maxVersion}' AND deleted_at = ''";
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
        } else {
            $this->logger->log('$getID3->analyze $fileInfo:' . print_r($fileInfo, true));
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
        $this->logger->log('$options: ' . print_r($options, true));
        $media = \Mms\Lib\Models\Media::createFromOptions($options);

        $this->logger->log('$media: ' . print_r($media, true));
        $this->logger->log("\n--------------------\n" . 'end function: ' . __FUNCTION__ . "\n--------------------\n");

        return $media;
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

        if (!is_null($assetId)) {
            $isUploaded = false;
            try {
                // ファイルのアップロードを実行する
                $extension = pathinfo(self::$filePath, PATHINFO_EXTENSION);
                $fileName = sprintf('%s.%s', $mediaId, $extension);
                $this->upload2storage(basename($asset->getUri()), $fileName, self::$filePath);

                $isUploaded = true;
                $this->logger->log('file has been uploaded. filePath:' . self::$filePath);
            } catch (\Exception $e) {
                $this->logger->log('upload2storage throw exception. message:' . $e->getMessage());
            }

            $this->logger->log('$isUploaded:' . print_r($isUploaded, true));

            if ($isUploaded) {
                try {
                    // ファイル メタデータの生成
                    $mediaServicesWrapper->createFileInfos($asset);

                    // ここまできて初めて、アセットの準備が完了したことになる
                    $isCompleted = true;
                    $this->logger->log('inputAsset has been prepared completely. asset:' . $assetId);
                } catch (\Exception $e) {
                   $this->logger->log('createFileInfos throw exception. message:' . $e->getMessage());
                }
            }
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
     * @see http://msdn.microsoft.com/ja-jp/library/azure/dd135726.aspx
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

        // PUTするためのファイルポインタ作成(なければ作成)
        $tmpDir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'tmp';
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
            $this->logger->log('curl_getinfo:' . print_r($info, true));

            // 操作が正常に終了すると、ステータス コード 201 (Created) が返される
            if ($info['http_code'] != '201') {
                $object = simplexml_load_string($result);
                $this->logger->log('curl_getinfo http_code is not 201. $result:' . print_r($object, true));

                $e = new \Exception('upload error: ' . $object->Code . ' ' . $object->Message);
                curl_close($ch);
                throw $e;
            }

            curl_close($ch);
        } else {
            $message = 'curl_errno is not 0. no:' . curl_error($ch);
            $e = new \Exception($message);
            curl_close($ch);
            throw $e;
        }

        $this->logger->log("\n--------------------\n" . 'end function: ' . __FUNCTION__ . "\n--------------------\n");
    }
}

?>