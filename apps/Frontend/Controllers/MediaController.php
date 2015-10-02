<?php
namespace Mms\Frontend\Controllers;

class MediaController extends BaseController
{
    public function index()
    {
        $medias = [];

        $searchConditions = [
            'word'      => '',
            'job_state' => \Mms\Lib\JobState::getAll(),
            'category'  => [],
            'page'      => 1,
            'orderby'   => 'updated_at',
            'sort'      => 'desc',
        ];

        $categories = [];
        try {
            // カテゴリーを取得
            $query = 'SELECT id, name FROM category ORDER BY id ASC;';
            $statement = $this->pdo->query($query);
            while ($res = $statement->fetch()) {
                $categories[] = $res;
                $searchConditions['category'][] = $res['id'];
            }
        } catch (\Exception $e) {
            throw $e;
        }

        $perPage = 20;

        try {
            // メディアを取得
            $query = "SELECT m1.*, category.name AS category_name";
            $subQuery4versions = "SELECT GROUP_CONCAT(m3.version, ',') FROM media AS m3"
                               . " GROUP BY m3.code"
                               . " HAVING m3.code = m1.code";
            $query .= ", ({$subQuery4versions}) AS versions";
            $query .= " FROM media AS m1"
                    . " INNER JOIN category ON m1.category_id = category.id"
                    . " WHERE m1.deleted_at = ''";

            // 最新バージョンのメディアのみ取得
            $query .= " AND m1.version = (SELECT MAX(m2.version) FROM media AS m2 WHERE m1.code =  m2.code AND m2.deleted_at = '')";

            // 検索条件を追加
            if (isset($_GET['word']) && $_GET['word'] != '') {
                $searchConditions['word'] = $_GET['word'];
                $quotedWord = $this->pdo->quote('%' . $searchConditions['word'] . '%');
                $query .=  " AND (m1.id || m1.movie_name || category_name) LIKE {$quotedWord}";
            }

            if (isset($_GET['job_state']) && count($_GET['job_state']) > 0) {
                $searchConditions['job_state'] = $_GET['job_state'];
                $query .= sprintf(' AND m1.job_state IN (\'%s\')', implode('\',\'', $searchConditions['job_state']));
            }

            if (isset($_GET['category']) && count($_GET['category']) > 0) {
                $searchConditions['category'] = $_GET['category'];
                $query .= sprintf(' AND m1.category_id IN (\'%s\')', implode('\',\'', $searchConditions['category']));
            }

            // ソート条件
            if (isset($_GET['orderby']) && $_GET['orderby'] != '') {
                $searchConditions['orderby'] = $_GET['orderby'];
            }
            if (isset($_GET['sort']) && $_GET['sort'] != '') {
                $searchConditions['sort'] = $_GET['sort'];
            }
            $query .= " ORDER BY m1.{$searchConditions['orderby']} {$searchConditions['sort']}";

            // ページャーで次のページがあるかどうか判定するために、1つ余計に取得しておく
            $limit = $perPage + 1;
            $query .= " LIMIT {$limit}";

            // ページ指定あればオフセット
            if (isset($_GET['page']) && $_GET['page'] != '') {
                $searchConditions['page'] = (int)$_GET['page'];
                $offset = $perPage * ($searchConditions['page'] - 1);
                $query .= " OFFSET {$offset}";
            }

            $statement = $this->pdo->query($query);
            $medias = $statement->fetchAll();
        } catch (\Exception $e) {
            $this->app->log->debug(print_r($e, true));

            throw $e;
        }

        $this->app->render(
            'media/index.php',
            [
                'medias'           => $medias,
                'searchConditions' => $searchConditions,
                'categories'       => $categories,
                'perPage'          => $perPage
            ]
        );
    }

    public function create()
    {
        $message = null;
        $defaults = [
            ini_get('session.upload_progress.name') => uniqid('newMedia'),
            'mcode' => '',
            'category_id' => ''
        ];

        if ($this->app->request()->isPost()) {
            $defaults = [
                ini_get('session.upload_progress.name') => '',
                'mcode' => '',
                'category_id' => ''
            ];

            // カテゴリーを取得
            $categories = [];
            try {
                $query = 'SELECT * FROM category';
                $statement = $this->pdo->query($query);
                $categories = $statement->fetchAll();
            } catch (\Exception $e) {
                $this->log($e);
                throw $e;
            }

            $this->app->log->debug(print_r($_POST, true));

            $defaults = $_POST;

            if (!$_POST['mcode']) {
                $message .= '<br>作品コードを入力してください';
            }
            if (!$_POST['category_id']) {
                $message .= '<br>カテゴリーを選択してください';
            }
            if ($_FILES['file']['size'] <= 0) {
                $message .= '<br>ファイルを選択してください';
            }
            if ($message) {
                return $this->app->render(
                    'media/new.php',
                    [
                        'message'    => $message,
                        'defaults'   => $defaults,
                        'categories' => $categories
                    ]
                );
            }

            $isSaved = false;
            try {
                // アップロードファイルの一時的なID
                $id = implode('_', [
                    $_POST['mcode'],
                    $_POST['category_id'],
                    uniqid()
                ]);

                $uploaddir = __DIR__ . sprintf('/../../../uploads/%s/', $_SERVER['PHP_AUTH_USER']);
                // なければ作成
                if (!file_exists($uploaddir)) {
                    mkdir($uploaddir, 0777);
                    chmod($uploaddir, 0777);
                }
                $fileName = basename($_FILES['file']['name']);
                $extension = pathinfo($fileName, PATHINFO_EXTENSION);
                $uploadedFileName = $id . '.' . $extension;
                $uploadfile = $uploaddir . $uploadedFileName;

                if (!move_uploaded_file($_FILES['file']['tmp_name'], $uploadfile)) {
                    $egl = error_get_last();
                    $e = new \Exception('ファイルのアップロードでエラーが発生しました' . $egl['message']);
                    throw $e;
                }

                chmod($uploadfile, 0644);

                $isSaved = true;
            } catch (\Exception $e) {
                $this->app->log->debug(print_r($e, true));
                throw $e;
            }

            if ($isSaved) {
                $this->app->flash('info', '動画がアップロードされました。まもなく一覧に反映されます。');
                $this->app->redirect('/medias', 303);
            }
        }

        // 作品コードがURLで指定される場合
        if (isset($_GET['mcode'])) {
            $defaults['mcode'] = $_GET['mcode'];
        }

        // カテゴリーを取得
        $categories = [];
        $query = 'SELECT id, name FROM category';
        $statement = $this->pdo->query($query);
        $categories = $statement->fetchAll();

        $this->app->render(
            'media/new.php',
            [
                'message'    => $message,
                'defaults'   => $defaults,
                'categories' => $categories
            ]
        );
    }

    public function createProgress($name)
    {
        $key = ini_get('session.upload_progress.prefix') . $name;
        echo isset($_SESSION[$key]) ? json_encode($_SESSION[$key]) : json_encode(null);
        return;
    }

    public function updateByCode($code)
    {
        header('Content-Type: application/json; charset=UTF-8');

        $isSuccess = false;
        $message = '予期せぬエラー';
        $count4update = 0;

        try {
            $values = [];
            $values['movie_name'] = $this->pdo->quote($_POST['movie_name']);
            $values['start_at'] = $this->pdo->quote($_POST['start_at']);
            $values['end_at'] = $this->pdo->quote($_POST['end_at']);

            $query = "UPDATE media SET"
                   . " movie_name = {$values['movie_name']}"
                   . ", start_at = {$values['start_at']}"
                   . ", end_at = {$values['end_at']}"
                   . ", updated_at = datetime('now', 'localtime')"
                   . " WHERE code = '{$code}' AND deleted_at = ''";
            $this->app->log->debug('$query:' . $query);
            $count4update = $this->pdo->exec($query);
            $isSuccess = true;
            $message = '';
        } catch (\Exception $e) {
            $message = $e->getMessage();
            $this->app->log->debug('fail in updating media by code/ code:'. $code . ' / message:' . $message);
        }

        $this->app->log->debug('$count4update: ' . $count4update);

        echo json_encode([
            'success'      => $isSuccess,
            'message'      => $message,
            'update_count' => $count4update
        ]);
        return;
    }

    public function show($code)
    {
        $medias = [];

        try {
            $query = "SELECT media.*, category.name AS category_name FROM media"
                   . " INNER JOIN category ON media.category_id = category.id"
                   . " WHERE media.code = '{$code}' AND media.deleted_at = ''"
                   . " ORDER BY media.version DESC";

            $statement = $this->pdo->query($query);
            $medias = $statement->fetchAll();

            foreach ($medias as $key => $media) {
                $medias[$key]['urls'] = [
                    \Mms\Lib\Models\Task::NAME_MPEG_DASH => '',
                    \Mms\Lib\Models\Task::NAME_SMOOTH_STREAMING => '',
                    \Mms\Lib\Models\Task::NAME_HLS => ''
                ];

                // ジョブが完了していれば、タスクからURLを取得
                if ($media['job_id'] && \Mms\Lib\JobState::isFinished($media['job_state'])) {
                    $query = "SELECT name, url FROM task WHERE media_id = '{$media['id']}'";
                    $statement = $this->pdo->query($query);
                    $tasks = $statement->fetchAll();

                    foreach ($tasks as $task) {
                        $medias[$key]['urls'][$task['name']] = $task['url'];
                    }
                }
            }
        } catch (\Exception $e) {
            $this->app->log->debug(print_r($e, true));
            throw $e;
        }

        $this->app->log->debug('$medias: ' . print_r($medias, true));

        return $this->app->render(
            'media/show.php',
            [
                'medias' => $medias
            ]
        );
    }

    public function delete($id)
    {
        header('Content-Type: application/json; charset=UTF-8');

        $isSuccess = false;
        $message = '予期せぬエラー';
        $count4updateMedia = 0;

        try {
            $query = "UPDATE media SET updated_at = datetime('now', 'localtime'), deleted_at = datetime('now', 'localtime') WHERE id = '{$id}'";
            $this->app->log->debug('$query:' . $query);
            $count4updateMedia = $this->pdo->exec($query);

            if ($count4updateMedia > 0) {
                $isSuccess = true;
                $message = '';
            }
        } catch (\Exception $e) {
            $message = $e->getMessage();
        }

        $this->app->log->debug('$count4updateMedia:' . $count4updateMedia);

        echo json_encode([
            'success' => $isSuccess,
            'message' => $message
        ]);
        return;
    }

    public function download($id)
    {
        try {
            $query = "SELECT id, asset_id, extension FROM media WHERE id = '{$id}'";
            $statement = $this->pdo->query($query);
            $media = $statement->fetch();

            $mediaServicesWrapper = $this->app->getMediaServicesWrapper();

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
            $accessPolicy->setDurationInMinutes(10); // 10分間有効
            $accessPolicy->setPermissions(\WindowsAzure\MediaServices\Models\AccessPolicy::PERMISSIONS_READ);
            $accessPolicy = $mediaServicesWrapper->createAccessPolicy($accessPolicy);

            // アセットを取得
            $asset = $mediaServicesWrapper->getAsset($media['asset_id']);
            $this->app->log->debug('$asset:' . print_r($asset, true));

            // ダウンロードURLの作成
            $locator = new \WindowsAzure\MediaServices\Models\Locator(
                $asset,
                $accessPolicy,
                \WindowsAzure\MediaServices\Models\Locator::TYPE_SAS
            );
            $locator->setName('DownloadLocator_' . $asset->getId());
            $locator->setStartTime(new \DateTime('now -5 minutes'));
            $locator = $mediaServicesWrapper->createLocator($locator);

            $this->app->log->debug(print_r($locator, true));

            $fileName = sprintf('%s.%s', $media['id'], $media['extension']);
            $path = sprintf('%s/%s%s',
                            $locator->getBaseUri(),
                            $fileName,
                            $locator->getContentAccessComponent());

            header('Content-Disposition: attachment; filename="' . $fileName . '"');
            header('Content-Type: application/octet-stream');
            if (!readfile($path)) {
                throw(new \Exception("Cannot read the file(".$path.")"));
            }

            // ロケーター削除
            $mediaServicesWrapper->deleteLocator($locator);

            exit;
        } catch (\Exception $e) {
            throw $e;
        }

        throw new \Exception('予期せぬエラー');
    }

    public function restore($id)
    {
        header('Content-Type: application/json; charset=UTF-8');

        $isSuccess = false;
        $message = '予期せぬエラー';

        try {
            $query = "UPDATE media SET updated_at = datetime('now', 'localtime'), deleted_at = '' WHERE id = '{$id}' AND deleted_at <> ''";
            $this->app->log->debug('$query:' . $query);
            $count4updateMedia = $this->pdo->exec($query);

            if ($count4updateMedia > 0) {
                $isSuccess = true;
                $message = '';
            }
        } catch (\Exception $e) {
            $message = $e->getMessage();
        }

        echo json_encode([
            'success' => $isSuccess,
            'message' => $message
        ]);
        return;
    }

    public function reencode($id)
    {
        // job_stateを空にしておけば、クーロンが働いて再エンコード処理まで施してくれる
        header('Content-Type: application/json; charset=UTF-8');

        $isSuccess = false;
        $message = '予期せぬエラー';

        try {
            $query = "UPDATE media SET updated_at = datetime('now', 'localtime'), job_state = '' WHERE id = '{$id}'";
            $this->app->log->debug('$query:' . $query);
            $count4updateMedia = $this->pdo->exec($query);

            if ($count4updateMedia > 0) {
              $isSuccess = true;
              $message = '';
            }
        } catch (\Exception $e) {
            $message = $e->getMessage();
        }

        echo json_encode([
            'success' => $isSuccess,
            'message' => $message
        ]);
        return;
    }
}
