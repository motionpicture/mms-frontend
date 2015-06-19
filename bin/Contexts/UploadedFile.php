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
            if (!$isComleted) {
                throw new \Exception('ingestAsset not completed.');
            }
        } catch (\Exception $e) {
            $message = 'process throw exception. filePath:' . self::$filePath . ' message:' . $e->getMessage();
            $this->logger->log($message);
            $this->reportError($message);

            // アップロードユーザーにも通知
            $user = self::$user;
            $message = $user['id'] . '様<br><br>以下のファイルをメディアサービスへアップロードすることができませんでした。<br>おそれいりますが、再度ファイルのアップロードをお願いいたします。<br><br>' . pathinfo(self::$filePath, PATHINFO_BASENAME);
            $this->sendErrorMail($user['email'], $message);
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

                $this->logger->log('creating BlockBlob... filePath:' . self::$filePath);
                $content = fopen(self::$filePath, 'rb');
                $blobServicesWrapper = $this->azureContext->getBlobServicesWrapper();
                $result = $blobServicesWrapper->createBlockBlob(basename($asset->getUri()), $fileName, $content);
                $this->logger->debug('BlockBlob has been created. result:' . var_export($result, true));
                fclose($content);

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
}

?>