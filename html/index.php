<?php
// 環境取得
$modeFile = dirname(__FILE__) . '/../mode.php';
if (false === is_file($modeFile)) {
    exit('The application "mode file" does not exist.');
}
require_once($modeFile);
if (empty($mode)) {
    exit('The application "mode" does not exist.');
}

require_once dirname(__FILE__) . '/../slim_apps/frontend/lib/Slim.php';

use WindowsAzure\MediaServices\Models\Job;

$app = new \Mms\Frontend\Slim([
    'debug'       => true,
    'log.enable' => true,
//     'log.path'    => '/../log',
//     'log.level'    => 8,
    'log.writer' => new \Slim\LogWriter(fopen(dirname(__FILE__) . '/../log/mms_slim.log', 'a+')),
    'templates.path' => dirname(__FILE__) . '/../slim_apps/frontend/templates',
    'mode'           => $mode
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
    } catch (Exception $e) {
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
});

$app->post('/media/new', function () use ($app) {
    $message = null;
    $defaults = [
        'mcode' => '',
        'category_id' => ''
    ];

    // カテゴリーを取得
    $categories = [];
    try {
        $query = 'SELECT * FROM category';
        $statement = $app->db->query($query);
        $categories = $statement->fetchAll();
    } catch (Exception $e) {
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
            $e = new Exception('ファイルのアップロードでエラーが発生しました' . $egl['message']);
            throw $e;
        }

        chmod($uploadfile, 0644);

        $isSaved = true;
    } catch (Exception $e) {
        $app->log->debug(print_r($e, true));
        throw $e;
    }

    if ($isSaved) {
        $app->redirect('/medias', 303);
    }
});

/**
 * メディア一覧
 */
$app->get('/medias', function () use ($app) {
    $medias = [];

    $searchConditions = [
        'word'      => '',
        'job_state' => JobState::getAll(),
        'category'  => []
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
    } catch (Exception $e) {
        throw $e;
    }

    try {
        // メディアを取得
        $query = "SELECT m1.*, category.name AS category_name";
        $subQuery4versions = "SELECT GROUP_CONCAT(m3.version, ',') FROM media AS m3"
                           . " GROUP BY m3.code"
                           . " HAVING m3.code = m1.code";
        $query .= ", ({$subQuery4versions}) AS versions";
        $query .= " FROM media AS m1"
                . ' INNER JOIN category ON m1.category_id = category.id'
                . ' WHERE m1.id IS NOT NULL';

        // 最新バージョンのメディアのみ取得
        $query .= " AND m1.version = (SELECT MAX(m2.version) FROM media AS m2 WHERE m1.code =  m2.code)";

        // 検索条件を追加
        if (isset($_GET['word']) && $_GET['word'] != '') {
            $searchConditions['word'] = $_GET['word'];
            $query .= sprintf(' AND m1.id LIKE \'%%%s%%\'', $searchConditions['word']);
        }

        if (isset($_GET['job_state']) && count($_GET['job_state']) > 0) {
            $searchConditions['job_state'] = $_GET['job_state'];
            $query .= sprintf(' AND m1.job_state IN (\'%s\')', implode('\',\'', $searchConditions['job_state']));
        }

        if (isset($_GET['category']) && count($_GET['category']) > 0) {
            $searchConditions['category'] = $_GET['category'];
            $query .= sprintf(' AND m1.category_id IN (\'%s\')', implode('\',\'', $searchConditions['category']));
        }

        $query .= ' ORDER BY m1.updated_at DESC';

        $statement = $app->db->query($query);
        $medias = $statement->fetchAll();
    } catch (Exception $e) {
        $app->log->debug(print_r($e, true));

        throw $e;
    }

    $app->render(
        'media/index.php',
        [
            'medias'           => $medias,
            'jobState'         => new JobState,
            'searchConditions' => $searchConditions,
            'categories'      => $categories
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
        $query = "SELECT media.*, category.name AS category_name FROM media INNER JOIN category ON media.category_id = category.id WHERE media.code = '{$code}' ORDER BY media.version DESC";

        $statement = $app->db->query($query);
        $medias = $statement->fetchAll();

        foreach ($medias as $key => $media) {
            $medias[$key]['urls'] = array();

            // ストリーミングURLの取得
            $query = "SELECT url FROM task WHERE media_id = '{$media['id']}' AND name = 'smooth_streaming' ORDER BY updated_at DESC";
            $statement = $app->db->query($query);
            $url = $statement->fetchColumn();

            $medias[$key]['urls']['smooth_streaming'] = $url;
        }
    } catch (Exception $e) {
        $app->log->debug(print_r($e, true));
        throw $e;
    }

    $app->log->debug('$medias: ' . print_r($medias, true));

    return $app->render(
        'media/show.php',
        [
            'medias'   => $medias,
            'jobState' => new JobState,
        ]
    );
})->name('media_by_code');

/**
 * メディアダウンロード
 */
$app->get('/media/:id/download', function ($id) use ($app) {
    $app->log->debug($id);

    try {
        $query = "SELECT * FROM media WHERE id = '{$id}'";
        $statement = $app->db->query($query);
        $media = $statement->fetch();

        if (isset($media['id']) && $media['job_id']) {
            $mediaServicesWrapper = $app->getMediaServicesWrapper();

            // 読み取りアクセス許可を持つAccessPolicyの作成
            $accessPolicy = new WindowsAzure\MediaServices\Models\AccessPolicy('DownloadPolicy');
            $accessPolicy->setDurationInMinutes(10);
            $accessPolicy->setPermissions(WindowsAzure\MediaServices\Models\AccessPolicy::PERMISSIONS_READ);
            $accessPolicy = $mediaServicesWrapper->createAccessPolicy($accessPolicy);

            // ジョブのアセットを取得
            $assets = $mediaServicesWrapper->getJobInputMediaAssets($media['job_id']);

            $app->log->debug(print_r($assets, true));

            $asset = $assets[0];

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
                throw(new Exception("Cannot read the file(".$path.")"));
            }

            // ロケーター削除
            $mediaServicesWrapper->deleteLocator($locator);

            exit;
        }
    } catch (Exception $e) {
        $app->log->debug(print_r($e, true));
        throw $e;
    }

    throw new Exception('予期せぬエラー');
})->name('media_download');

/**
 * メディア更新
 */
$app->post('/media/:id/update', function ($id) use ($app) {
    $app->log->debug('$id: ' . $id);

    $isSuccess = false;
    $message = '予期せぬエラー';

    // メディアを取得
    $query = "SELECT * FROM media WHERE id = '{$id}'";
    $statement = $app->db->query($query);
    $media = $statement->fetch();

    $count4update = 0;
    if (isset($media['id'])) {
        try {
            $values = [];
            $values['start_at'] = $app->db->quote($_POST['start_at']);
            $values['end_at'] = $app->db->quote($_POST['end_at']);

            $query = "UPDATE media SET start_at = {$values['start_at']}, end_at = {$values['end_at']}, updated_at = datetime('now', 'localtime') WHERE id = '{$id}';";
            $app->log->debug('$query:' . $query);
            $count4update = $app->db->exec($query);
            $isSuccess = true;
            $message = '';
        } catch (Exception $e) {
            $message = $e->getMessage();
            $app->log->debug('fail in updating media by id $id: '. $id . ' / $message: ' . $message);
        }
    }

    $app->log->debug('$count4update:' . $count4update);

    echo json_encode([
        'success' => $isSuccess,
        'message' => $message
    ]);
    return;
})->name('media_update_by_id');

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

        $query = "UPDATE media SET movie_name = {$values['movie_name']}, updated_at = datetime('now', 'localtime') WHERE code = '{$code}';";
        $app->log->debug('$query:' . $query);
        $count4update = $app->db->exec($query);
        $isSuccess = true;
        $message = '';
    } catch (Exception $e) {
        $message = $e->getMessage();
        $app->log->debug('fail in updating media by id $id: '. $id . ' / $message: ' . $message);
    }

    $app->log->debug('$count4update: ' . $count4update);

    echo json_encode([
        'success' => $isSuccess,
        'message' => $message
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
    $count4deleteMedia = 0;
    $count4deleteTask = 0;

    // メディアを取得
    $query = "SELECT * FROM media WHERE id = '{$id}'";
    $statement = $app->db->query($query);
    $media = $statement->fetch();

    if (isset($media['id'])) {
        // トランザクションの開始
        $app->db->beginTransaction();
        try {
            $query = "DELETE FROM media WHERE id = '{$id}'";
            $app->log->debug('$query:' . $query);
            $count4deleteMedia = $app->db->exec($query);

            $query = "DELETE FROM task WHERE media_id = '{$id}'";
            $app->log->debug('$query:' . $query);
            $count4deleteTask = $app->db->exec($query);

            $app->db->commit();
            $isSuccess = true;
            $message = '';
        } catch (Exception $e) {
            $app->log->debug(print_r($e, true));
            $app->db->rollBack();
            $message = $e->getMessage();
        }

        // ジョブがあればアセットも削除
        if ($isSuccess) {
            try {
                if ($media['job_id']) {
                    $mediaServicesWrapper = $app->getMediaServicesWrapper();

                    // ジョブのアセットを取得
                    $inputAssets = $mediaServicesWrapper->getJobInputMediaAssets($media['job_id']);
                    $outputAssets = $mediaServicesWrapper->getJobOutputMediaAssets($media['job_id']);

                    $app->log->debug('$inputAssets:' . print_r($inputAssets, true));
                    $app->log->debug('$outputAssets:' . print_r($outputAssets, true));

                    // アセット削除
                    foreach ($inputAssets as $asset) {
                        $mediaServicesWrapper->deleteAsset($asset);
                    }
                    foreach ($outputAssets as $asset) {
                        $mediaServicesWrapper->deleteAsset($asset);
                    }
                }
            } catch (Exception $e) {
                $app->log->debug('fail in deleting assets. message: ' . $e->getMessage());
            }
        }
    }

    $app->log->debug('$count4deleteMedia: ' . $count4deleteMedia);
    $app->log->debug('$count4deleteTask: ' . $count4deleteTask);

    echo json_encode([
        'success' => $isSuccess,
        'message' => $message
    ]);
    return;
})->name('media_delete');

/**
 * メディアのストリーミングURL
 */
$app->get('/media/stream/:mcode/:category', function ($mcode, $category) use ($app) {
    $app->log->debug('args: ' . print_r(func_get_args(), true));

    $maxVersion = '';

    try {
        $query = "SELECT MAX(version) AS max_version FROM media WHERE mcode = '{$mcode}' AND category_id = '{$category}'";
        $statement = $app->db->query($query);
        $maxVersion = $statement->fetchColumn();
    } catch (Exception $e) {
        $app->log->debug(print_r($e, true));
        throw $e;
    }

    if ($maxVersion != '') {
        $to = sprintf('/media/stream/%s/%s/%s', $mcode, $category, $maxVersion);
        $app->redirect($to, 303);
    }

    throw new Exception('予期せぬエラー');
})->name('media_stream');

/**
 * メディアのストリーミングURL(バージョン指定)
 */
$app->get('/media/stream/:mcode/:category/:version', function ($mcode, $category, $version) use ($app) {
    $url = '';

    try {
        $id = sprintf('%s_%s_%s', $mcode, $category, $version);

        // ストリーミングURLの取得
        $query = "SELECT url FROM task WHERE media_id = '{$id}' AND name = 'smooth_streaming' ORDER BY updated_at DESC";
        $statement = $app->db->query($query);
        $url = $statement->fetchColumn();
    } catch (Exception $e) {
        $app->log->debug(print_r($e, true));
        throw $e;
    }

    if ($url != '') {
        $app->redirect($url, 303);
    }

    echo 'please wait...';
    exit;
})->name('media_stream_by_version');

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
});

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
        $query = "UPDATE user SET email = {$email}, updated_at = datetime('now') WHERE id = '{$_SERVER['PHP_AUTH_USER']}'";
        $app->log->debug('$query:' . $query);
        $app->db->exec($query);
    } catch (Exception $e) {
        $app->log->debug(print_r($e, true));
        throw $e;
    }

    $app->redirect('/user/edit', 303);
});

$app->run();

class JobState
{
    public static function getAll()
    {
        return [
            Job::STATE_QUEUED,
            Job::STATE_SCHEDULED,
            Job::STATE_PROCESSING,
            Job::STATE_FINISHED,
            Job::STATE_ERROR,
            Job::STATE_CANCELED,
            Job::STATE_CANCELING
        ];
    }

    public static function toString($state)
    {
        if ($state == Job::STATE_QUEUED) {
            return '待機中';
        } else if ($state == Job::STATE_SCHEDULED) {
            return 'スケジュール済み';
        } else if ($state == Job::STATE_PROCESSING) {
            return '進行中';
        } else if ($state == Job::STATE_FINISHED) {
            return '完了';
        } else if ($state == Job::STATE_ERROR) {
            return 'エラー';
        } else if ($state == Job::STATE_CANCELED) {
            return 'キャンセル済み';
        } else if ($state == Job::STATE_CANCELING) {
            return 'キャンセル中';
        }
    }
}
?>