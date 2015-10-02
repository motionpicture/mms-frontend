<?php
namespace Mms\Frontend\Controllers;

class MediasController extends BaseController
{
    public function updateByCode()
    {
        header('Content-Type: application/json; charset=UTF-8');

        $isSuccess = false;
        $message = '予期せぬエラー';
        $count4update = 0;

        if (isset($_POST['medias']) || is_array($_POST['medias'])) {
            $this->pdo->beginTransaction();
            try {
                $medias = $_POST['medias'];
                foreach ($medias as $media) {
                    if (isset($media['code'])) {
                        $values = [];
                        $values['code'] = $this->pdo->quote($media['code']);
                        $values['movie_name'] = $this->pdo->quote($media['movie_name']);
                        $values['start_at'] = $this->pdo->quote($media['start_at']);
                        $values['end_at'] = $this->pdo->quote($media['end_at']);
                        $query = "UPDATE media SET"
                               . " movie_name = {$values['movie_name']}"
                               . ", start_at = {$values['start_at']}"
                               . ", end_at = {$values['end_at']}"
                               . ", updated_at = datetime('now', 'localtime')"
                               . " WHERE code = {$values['code']} AND deleted_at = ''";
                        $this->app->log->debug('$query:' . $query);
                        $count4update += $this->pdo->exec($query);
                    }
                }
    
                $this->pdo->commit();
                $isSuccess = true;
                $message = '';
            } catch (\Exception $e) {
                $this->pdo->rollBack();
                $message = $e->getMessage();
                $this->app->log->error('fail in updating medias. message:' . $message);
            }
        }

        $this->app->log->debug('$count4update: ' . $count4update);

        echo json_encode([
            'success'      => $isSuccess,
            'message'      => $message,
            'update_count' => $count4update
        ]);

        return;
    }

    public function download()
    {
        $mediaIds = [];
        if (isset($_GET['ids']) && $_GET['ids']) {
            $mediaIds = explode(',', $_GET['ids']);
        }
        $this->app->log->debug('$mediaIds:' . print_r($mediaIds, true));

        if (count($mediaIds) < 1) {
            throw new \Exception('メディアIDを指定してください');
        }

        $zip = new \ZipArchive();

        $tmpZipFile = sprintf('%s_%s_%s.zip',
            __DIR__ . '/../tmp/' . 'medias_download',
            date('Ymd'),
            uniqid()
        );
        if (!file_exists(dirname($tmpZipFile))) {
            mkdir(dirname($tmpZipFile), 0777, true);
            chmod(dirname($tmpZipFile), 0777);
        }
        $result = $zip->open($tmpZipFile, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
        if ($result !== true) {
            throw new \Exception('ダウンロードに失敗しました');
        }

        $mediaServicesWrapper = $this->app->getMediaServicesWrapper();

        foreach ($mediaIds as $mediaId) {
            set_time_limit(0);

            try {
                $query = "SELECT id, asset_id, extension FROM media WHERE id = '{$mediaId}'";
                $statement = $this->pdo->query($query);
                $media = $statement->fetch();

                // 特定のAssetに対して、同時に5つを超える一意のLocatorを関連付けることはできない
                // 万が一SASロケーターがあれば削除
                $oldLocators = $mediaServicesWrapper->getAssetLocators($media['asset_id']);
                foreach ($oldLocators as $oldLocator) {
                    if ($oldLocator->getType() == WindowsAzure\MediaServices\Models\Locator::TYPE_SAS) {
                        // 期限切れであれば削除
                        $expiration = strtotime('+9 hours', $oldLocator->getExpirationDateTime()->getTimestamp());
                        if ($expiration < strtotime('now')) {
                            $mediaServicesWrapper->deleteLocator($oldLocator);
                            $this->app->log->debug('SAS locator has been deleted. $locator: '. print_r($oldLocator, true));
                        }
                    }
                }

                // 読み取りアクセス許可を持つAccessPolicyの作成
                $accessPolicy = new \WindowsAzure\MediaServices\Models\AccessPolicy('DownloadPolicy');
                $accessPolicy->setDurationInMinutes(30); // 10分間有効
                $accessPolicy->setPermissions(\WindowsAzure\MediaServices\Models\AccessPolicy::PERMISSIONS_READ);
                $accessPolicy = $mediaServicesWrapper->createAccessPolicy($accessPolicy);

                // アセットを取得
                $asset = $mediaServicesWrapper->getAsset($media['asset_id']);

                // ダウンロードURLの作成
                $locator = new \WindowsAzure\MediaServices\Models\Locator(
                    $asset,
                    $accessPolicy,
                    \WindowsAzure\MediaServices\Models\Locator::TYPE_SAS
                );
                $locator->setName('DownloadLocator_' . $asset->getId());
                $locator->setStartTime(new \DateTime('now -5 minutes'));
                $locator = $mediaServicesWrapper->createLocator($locator);

                // ロケーターからファイルパスを作成
                $name = sprintf('%s.%s', $media['id'], $media['extension']);
                $path = sprintf('%s/%s%s',
                    $locator->getBaseUri(),
                    $name,
                    $locator->getContentAccessComponent());

                // ファイルをZIPに追加
                $startTime = microtime(true);
                $startMem = memory_get_usage();
                $zip->addFromString($name, file_get_contents($path));
                $endTime = microtime(true);
                $endMem = memory_get_usage();
                $this->app->log->debug('MEM:' . $startMem . '-' . $endMem . '(' . ($endMem - $startMem) . ') / peak: ' . memory_get_peak_usage());
                $this->app->log->debug("time:" . ($endTime - $startTime));

                // ロケーター削除
                $mediaServicesWrapper->deleteLocator($locator);
            } catch (\Exception $e) {
                $this->app->log->error('creating DL URL throw exception. mediaId:' . $mediaId . ' message:' . $e->getMessage());
            }
        }

        $zip->close();

        // ストリームに出力
        header('Content-Description: File Transfer');
        header('Content-Type: application/octet-stream');
        header('Content-Disposition: attachment; filename=' . basename($tmpZipFile));
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . filesize($tmpZipFile));

        // 出力バッファレベル
        $this->app->log->debug('ob_get_level():' .  ob_get_level());
        while (@ob_end_flush());
        $this->app->log->debug('ob_get_level():' .  ob_get_level());

        $this->app->log->info('filesize:' . filesize($tmpZipFile));

        // @see http://www.php.net/manual/ja/function.readfile.php
        readfile($tmpZipFile);

        // 一時ファイル削除
        $result = unlink($tmpZipFile);
        $this->app->log->info('unlink result:' . print_r($result, true));

        exit;
    }
}
