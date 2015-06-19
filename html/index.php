<?php
session_cache_limiter(false);
session_start();

require_once dirname(__FILE__) . '/../slim_apps/Frontend/Lib/Slim.php';

$app = new \Mms\Frontend\Lib\Slim([
    'log.enable' => true,
//     'log.path'    => '/../log',
//     'log.level'    => 8,
    'templates.path' => dirname(__FILE__) . '/../slim_apps/Frontend/Templates',
]);

$app->hook('slim.before', function () use ($app) {
    $app->view->setData([
        'app' => $app
    ]);
});

/**
 * HOME
 */
$app->get('/', function () use ($app) {
    $app->redirect('/medias', 303);
})->name('homepage');

/**
 * メディア登録
 */
$app->get('/media/new', function () use ($app) {
    $message = null;
    $defaults = [
        ini_get('session.upload_progress.name') => uniqid('newMedia'),
        'mcode' => '',
        'category_id' => ''
    ];

    // 作品コードがURLで指定される場合
    if (isset($_GET['mcode'])) {
        $defaults['mcode'] = $_GET['mcode'];
    }

    // カテゴリーを取得
    $categories = [];
    try {
        $query = 'SELECT id, name FROM category';
        $statement = $app->db->query($query);
        $categories = $statement->fetchAll();
    } catch (\Exception $e) {
        $this->log($e);
        throw $e;
    }

    $app->render(
        'media/new.php',
        [
            'message'    => $message,
            'defaults'   => $defaults,
            'categories' => $categories
        ]
    );
})->name('media_new');

$app->post('/media/new', function () use ($app) {
    $message = null;
    $defaults = [
        ini_get('session.upload_progress.name') => '',
        'mcode' => '',
        'category_id' => ''
    ];

    // カテゴリーを取得
    $categories = [];
    try {
        $query = 'SELECT * FROM category';
        $statement = $app->db->query($query);
        $categories = $statement->fetchAll();
    } catch (\Exception $e) {
        $this->log($e);
        throw $e;
    }

    $app->log->debug(print_r($_POST, true));

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
        return $app->render(
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

        $uploaddir = dirname(__FILE__) . sprintf('/../uploads/%s/', $_SERVER['PHP_AUTH_USER']);
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
        $app->log->debug(print_r($e, true));
        throw $e;
    }

    if ($isSaved) {
        $app->flash('info', '動画がアップロードされました。まもなく一覧に反映されます。');
        $app->redirect('/medias', 303);
    }
})->name('media_create');

/**
 * メディア登録進捗
 */
$app->get('/media/new/progress/:name', function ($name) use ($app) {
    $key = ini_get('session.upload_progress.prefix') . $name;
    echo isset($_SESSION[$key]) ? json_encode($_SESSION[$key]) : json_encode(null);
    return;
})->name('media_create_progress');

/**
 * メディア一覧
 */
$app->get('/medias', function () use ($app) {
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
        $statement = $app->db->query($query);
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
            $quotedWord = $app->db->quote('%' . $searchConditions['word'] . '%');
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

        $statement = $app->db->query($query);
        $medias = $statement->fetchAll();
    } catch (\Exception $e) {
        $app->log->debug(print_r($e, true));

        throw $e;
    }

    $app->render(
        'media/index.php',
        [
            'medias'           => $medias,
            'searchConditions' => $searchConditions,
            'categories'       => $categories,
            'perPage'          => $perPage
        ]
    );
})->name('medias');;

/**
 * メディア詳細
 */
$app->get('/media/:code', function ($code) use ($app) {
    $app->log->debug('$code: ' . $code);

    $medias = [];

    try {
        $query = "SELECT media.*, category.name AS category_name FROM media"
               . " INNER JOIN category ON media.category_id = category.id"
               . " WHERE media.code = '{$code}' AND media.deleted_at = ''"
               . " ORDER BY media.version DESC";

        $statement = $app->db->query($query);
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
                $statement = $app->db->query($query);
                $tasks = $statement->fetchAll();

                foreach ($tasks as $task) {
                    $medias[$key]['urls'][$task['name']] = $task['url'];
                }
            }
        }
    } catch (\Exception $e) {
        $app->log->debug(print_r($e, true));
        throw $e;
    }

    $app->log->debug('$medias: ' . print_r($medias, true));

    return $app->render(
        'media/show.php',
        [
            'medias' => $medias
        ]
    );
})->name('media_by_code');

/**
 * メディアダウンロード
 */
$app->get('/media/:id/download', function ($id) use ($app) {
    $app->log->debug($id);

    try {
        $query = "SELECT id, asset_id, extension FROM media WHERE id = '{$id}'";
        $statement = $app->db->query($query);
        $media = $statement->fetch();

        $mediaServicesWrapper = $app->getMediaServicesWrapper();

        // 特定のAssetに対して、同時に5つを超える一意のLocatorを関連付けることはできない
        // 万が一SASロケーターがあれば削除
        $oldLocators = $mediaServicesWrapper->getAssetLocators($media['asset_id']);
        foreach ($oldLocators as $oldLocator) {
            if ($oldLocator->getType() == WindowsAzure\MediaServices\Models\Locator::TYPE_SAS) {
                // 期限切れであれば削除
                $expiration = strtotime('+9 hours', $oldLocator->getExpirationDateTime()->getTimestamp());
                if ($expiration < strtotime('now')) {
                    $mediaServicesWrapper->deleteLocator($oldLocator);
                    $app->log->debug('SAS locator has been deleted. $locator: '. print_r($oldLocator, true));
                }
            }
        }

        // 読み取りアクセス許可を持つAccessPolicyの作成
        $accessPolicy = new WindowsAzure\MediaServices\Models\AccessPolicy('DownloadPolicy');
        $accessPolicy->setDurationInMinutes(10); // 10分間有効
        $accessPolicy->setPermissions(WindowsAzure\MediaServices\Models\AccessPolicy::PERMISSIONS_READ);
        $accessPolicy = $mediaServicesWrapper->createAccessPolicy($accessPolicy);

        // アセットを取得
        $asset = $mediaServicesWrapper->getAsset($media['asset_id']);
        $app->log->debug('$asset:' . print_r($asset, true));

        // ダウンロードURLの作成
        $locator = new WindowsAzure\MediaServices\Models\Locator(
            $asset,
            $accessPolicy,
            WindowsAzure\MediaServices\Models\Locator::TYPE_SAS
        );
        $locator->setName('DownloadLocator_' . $asset->getId());
        $locator->setStartTime(new \DateTime('now -5 minutes'));
        $locator = $mediaServicesWrapper->createLocator($locator);

        $app->log->debug(print_r($locator, true));

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
        $app->log->debug(print_r($e, true));
        throw $e;
    }

    throw new \Exception('予期せぬエラー');
})->name('media_download');

/**
 * コードからメディア更新
 */
$app->post('/media/:code/update_by_code', function ($code) use ($app) {
    $app->log->debug('$code: ' . $code);

    $isSuccess = false;
    $message = '予期せぬエラー';
    $count4update = 0;

    try {
        $values = [];
        $values['movie_name'] = $app->db->quote($_POST['movie_name']);
        $values['start_at'] = $app->db->quote($_POST['start_at']);
        $values['end_at'] = $app->db->quote($_POST['end_at']);

        $query = "UPDATE media SET"
               . " movie_name = {$values['movie_name']}"
               . ", start_at = {$values['start_at']}"
               . ", end_at = {$values['end_at']}"
               . ", updated_at = datetime('now', 'localtime')"
               . " WHERE code = '{$code}' AND deleted_at = ''";
        $app->log->debug('$query:' . $query);
        $count4update = $app->db->exec($query);
        $isSuccess = true;
        $message = '';
    } catch (\Exception $e) {
        $message = $e->getMessage();
        $app->log->debug('fail in updating media by code/ code:'. $code . ' / message:' . $message);
    }

    $app->log->debug('$count4update: ' . $count4update);

    echo json_encode([
        'success'      => $isSuccess,
        'message'      => $message,
        'update_count' => $count4update
    ]);
    return;
})->name('media_update_by_code');

/**
 * メディア削除
 */
$app->post('/media/:id/delete', function ($id) use ($app) {
    $app->log->debug('$id: ' . $id);

    $isSuccess = false;
    $message = '予期せぬエラー';
    $count4updateMedia = 0;

    try {
        $query = "UPDATE media SET updated_at = datetime('now', 'localtime'), deleted_at = datetime('now', 'localtime') WHERE id = '{$id}'";
        $app->log->debug('$query:' . $query);
        $count4updateMedia = $app->db->exec($query);

        if ($count4updateMedia > 0) {
            $isSuccess = true;
            $message = '';
        }
    } catch (\Exception $e) {
        $app->log->debug(print_r($e, true));
        $message = $e->getMessage();
    }

    $app->log->debug('$count4updateMedia:' . $count4updateMedia);

    echo json_encode([
        'success' => $isSuccess,
        'message' => $message
    ]);
    return;
})->name('media_delete');

/**
 * 削除されたメディアの復活
 */
$app->get('/media/:id/restore', function ($id) use ($app) {
    $app->log->debug('$id: ' . $id);

    $isSuccess = false;
    $message = '予期せぬエラー';

    try {
        $query = "UPDATE media SET updated_at = datetime('now', 'localtime'), deleted_at = '' WHERE id = '{$id}' AND deleted_at <> ''";
        $app->log->debug('$query:' . $query);
        $count4updateMedia = $app->db->exec($query);

        if ($count4updateMedia > 0) {
            $isSuccess = true;
            $message = '';
        }
    } catch (\Exception $e) {
        $app->log->debug($e->getMessage());
        $message = $e->getMessage();
    }

    echo json_encode([
        'success' => $isSuccess,
        'message' => $message
    ]);
    return;
})->name('media_restore');

/**
 * メディア再エンコード
 * job_stateを空にしておけば、クーロンが働いて再エンコード処理まで施してくれる
 */
$app->post('/media/:id/reencode', function ($id) use ($app) {
    $app->log->debug('$id: ' . $id);

    $isSuccess = false;
    $message = '予期せぬエラー';

    try {
        $query = "UPDATE media SET updated_at = datetime('now', 'localtime'), job_state = '' WHERE id = '{$id}'";
        $app->log->debug('$query:' . $query);
        $count4updateMedia = $app->db->exec($query);

        if ($count4updateMedia > 0) {
          $isSuccess = true;
          $message = '';
        }
    } catch (\Exception $e) {
        $app->log->debug($e->getMessage());
        $message = $e->getMessage();
    }

    echo json_encode([
        'success' => $isSuccess,
        'message' => $message
    ]);
    return;
})->name('media_reencode');

/**
 * コードからメディアをまとめて更新
 */
$app->post('/medias/update_by_code', function () use ($app) {
    $isSuccess = false;
    $message = '予期せぬエラー';
    $count4update = 0;

    if (isset($_POST['medias']) || is_array($_POST['medias'])) {
        $app->db->beginTransaction();
        try {
            $medias = $_POST['medias'];
            foreach ($medias as $media) {
                if (isset($media['code'])) {
                    $values = [];
                    $values['code'] = $app->db->quote($media['code']);
                    $values['movie_name'] = $app->db->quote($media['movie_name']);
                    $values['start_at'] = $app->db->quote($media['start_at']);
                    $values['end_at'] = $app->db->quote($media['end_at']);
                    $query = "UPDATE media SET"
                           . " movie_name = {$values['movie_name']}"
                           . ", start_at = {$values['start_at']}"
                           . ", end_at = {$values['end_at']}"
                           . ", updated_at = datetime('now', 'localtime')"
                           . " WHERE code = {$values['code']} AND deleted_at = ''";
                    $app->log->debug('$query:' . $query);
                    $count4update += $app->db->exec($query);
                }
            }

            $app->db->commit();
            $isSuccess = true;
            $message = '';
        } catch (\Exception $e) {
            $app->db->rollBack();
            $message = $e->getMessage();
            $app->log->error('fail in updating medias. message:' . $message);
        }
    }

    $app->log->debug('$count4update: ' . $count4update);

    echo json_encode([
        'success'      => $isSuccess,
        'message'      => $message,
        'update_count' => $count4update
    ]);

    return;
})->name('medias_update_by_code');

/**
 * メディアをまとめてダウンロード
 */
$app->get('/medias/download', function () use ($app) {
    $mediaIds = [];
    if (isset($_GET['ids']) && $_GET['ids']) {
        $mediaIds = explode(',', $_GET['ids']);
    }
    $app->log->debug('$mediaIds:' . print_r($mediaIds, true));

    if (count($mediaIds) < 1) {
        throw new \Exception('メディアIDを指定してください');
    }

    $zip = new ZipArchive();

    $tmpZipFile = sprintf('%s_%s_%s.zip',
        __DIR__ . '/../tmp/' . 'medias_download',
        date('Ymd'),
        uniqid()
    );
    if (!file_exists(dirname($tmpZipFile))) {
        mkdir(dirname($tmpZipFile), 0777, true);
        chmod(dirname($tmpZipFile), 0777);
    }
    $result = $zip->open($tmpZipFile, ZIPARCHIVE::CREATE | ZIPARCHIVE::OVERWRITE);
    if ($result !== true) {
        throw new \Exception('ダウンロードに失敗しました');
    }

    $mediaServicesWrapper = $app->getMediaServicesWrapper();

    foreach ($mediaIds as $mediaId) {
        set_time_limit(0);

        try {
            $query = "SELECT id, asset_id, extension FROM media WHERE id = '{$mediaId}'";
            $statement = $app->db->query($query);
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
                        $app->log->debug('SAS locator has been deleted. $locator: '. print_r($oldLocator, true));
                    }
                }
            }

            // 読み取りアクセス許可を持つAccessPolicyの作成
            $accessPolicy = new WindowsAzure\MediaServices\Models\AccessPolicy('DownloadPolicy');
            $accessPolicy->setDurationInMinutes(30); // 10分間有効
            $accessPolicy->setPermissions(WindowsAzure\MediaServices\Models\AccessPolicy::PERMISSIONS_READ);
            $accessPolicy = $mediaServicesWrapper->createAccessPolicy($accessPolicy);

            // アセットを取得
            $asset = $mediaServicesWrapper->getAsset($media['asset_id']);

            // ダウンロードURLの作成
            $locator = new WindowsAzure\MediaServices\Models\Locator(
                $asset,
                $accessPolicy,
                WindowsAzure\MediaServices\Models\Locator::TYPE_SAS
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
            $app->log->debug('MEM:' . $startMem . '-' . $endMem . '(' . ($endMem - $startMem) . ') / peak: ' . memory_get_peak_usage());
            $app->log->debug("time:" . ($endTime - $startTime));

            // ロケーター削除
            $mediaServicesWrapper->deleteLocator($locator);
        } catch (\Exception $e) {
            $app->log->error('creating DL URL throw exception. mediaId:' . $mediaId . ' message:' . $e->getMessage());
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
    $app->log->debug('ob_get_level():' .  ob_get_level());
    while (@ob_end_flush());
    $app->log->debug('ob_get_level():' .  ob_get_level());

    $app->log->info('filesize:' . filesize($tmpZipFile));

    // @see http://www.php.net/manual/ja/function.readfile.php
    readfile($tmpZipFile);

    // 一時ファイル削除
    $result = unlink($tmpZipFile);
    $app->log->info('unlink result:' . print_r($result, true));

    exit;
})->name('medias_download');

/**
 * アカウント編集
 */
$app->get('/user/edit', function () use ($app) {
    $message = null;

    $query = sprintf('SELECT email FROM user WHERE id = \'%s\';',
                    $_SERVER['PHP_AUTH_USER']);
    $query = "SELECT email FROM user WHERE id = '{$_SERVER['PHP_AUTH_USER']}'";
    $statement = $app->db->query($query);
    $email = $statement->fetchColumn();

    $defaults = [
        'email' => $email,
    ];

    $app->log->debug($message);
    $app->log->debug(print_r($defaults, true));

    return $app->render(
        'user/edit.php',
        [
            'message' => $message,
            'defaults' => $defaults
        ]
    );
})->name('user_edit');

/**
 * アカウント編集
 */
$app->post('/user/edit', function () use ($app) {
    $message = null;

    $app->log->debug(print_r($_POST, true));

    $defaults = $_POST;

    if (!$_POST['email']) {
        $message .= '<br>メールアドレスを入力してください';
    }

    if ($message) {
        $app->log->debug($message);
        $app->log->debug(print_r($defaults, true));

        return $app->render(
            'user/edit.php',
            [
                'message' => $message,
                'defaults' => $defaults
            ]
        );
    }

    try {
        $email = $app->db->quote($_POST['email']);
        $query = "UPDATE user SET email = {$email}, updated_at = datetime('now', 'localtime') WHERE id = '{$_SERVER['PHP_AUTH_USER']}'";
        $app->log->debug('$query:' . $query);
        $app->db->exec($query);
    } catch (\Exception $e) {
        $app->log->debug(print_r($e, true));
        throw $e;
    }

    $app->redirect('/user/edit', 303);
})->name('user_update');

/**
 * Error Handler
 */
$app->error(function (\Exception $e) use ($app) {
    $app->log->error('route:{router}', [
        'exception' => $e,
        'router' => print_r($app->router->getCurrentRoute()->getName(), true)
    ]);

    return $app->render(
        'error.php',
        [
            'message' => $e->getMessage()
        ]
    );
});

/**
 * Not Found Handler
*/
// $app->notFound(function () use ($app) {
// });

$app->run();

?>