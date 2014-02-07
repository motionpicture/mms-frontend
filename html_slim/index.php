<?php
require_once dirname(__FILE__) . '/../lib/MmsSlim.php';

use WindowsAzure\MediaServices\Models\Job;

$app = new \MmsSlim([
    'debug'       => true,
    'log.enable' => true,
//     'log.path'    => '/../log',
//     'log.level'    => 8,
    'log.writer' => new \Slim\LogWriter(fopen(dirname(__FILE__) . '/../log/mms_slim.log', 'a+')),
    'templates.path' => dirname(__FILE__) . '/../templates'
]);

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

    // カテゴリーを取得
    $categories = array();
    try {
        $query = 'SELECT * FROM category';
        $result = $app->db->query($query);
        while ($res = $result->fetchArray(SQLITE3_ASSOC)) {
            $categories[$res['id']] = $res['name'];
        }
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
    $categories = array();
    try {
        $query = 'SELECT * FROM category';
        $result = $app->db->query($query);
        while ($res = $result->fetchArray(SQLITE3_ASSOC)) {
            $categories[$res['id']] = $res['name'];
        }
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
        $id = implode('_', array(
            $_POST['mcode'],
            $_POST['category_id'],
            uniqid()
        ));

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
        // ユーザーのメディアを取得
        $query = 'SELECT * FROM category ORDER BY id ASC;';

        $result = $app->db->query($query);
        while ($res = $result->fetchArray(SQLITE3_ASSOC)) {
            $categories[] = $res;
            $searchConditions['category'][] = $res['id'];
        }
    } catch (Exception $e) {
    }

    try {
        // ユーザーのメディアを取得
        $query = 'SELECT media.*, category.name AS category_name FROM media'
                . ' INNER JOIN category ON media.category_id = category.id'
                . ' WHERE media.id IS NOT NULL';

        // 検索条件を追加
        if (isset($_GET['word']) && $_GET['word'] != '') {
            $searchConditions['word'] = $_GET['word'];
            $query .= sprintf(' AND media.id LIKE \'%%%s%%\'', $searchConditions['word']);
        }

        if (isset($_GET['job_state']) && count($_GET['job_state']) > 0) {
            $searchConditions['job_state'] = $_GET['job_state'];
            $query .= sprintf(' AND media.job_state IN (\'%s\')', implode('\',\'', $searchConditions['job_state']));
        }

        if (isset($_GET['category']) && count($_GET['category']) > 0) {
            $searchConditions['category'] = $_GET['category'];
            $query .= sprintf(' AND media.category_id IN (\'%s\')', implode('\',\'', $searchConditions['category']));
        }

        $query .= ' ORDER BY updated_at DESC;';

        $result = $app->db->query($query);
        while ($res = $result->fetchArray(SQLITE3_ASSOC)) {
            $medias[] = $res;
        }
    } catch (Exception $e) {
        $app->log->debug(print_r($e, true));

        throw($e);
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
$app->get('/media/:id', function ($id) use ($app) {
    $app->log->debug($id);

    $media = null;
    $urls = [
        'smooth_streaming' => ''
    ];

    try {
        $query = sprintf('SELECT media.*, category.name AS category_name FROM media INNER JOIN category ON media.category_id = category.id WHERE media.id = \'%s\';', $id);
        $media = $app->db->querySingle($query, true);

        if (isset($media['id'])) {
            // smooth streaming用のURL
            $query = sprintf('SELECT url FROM task WHERE media_id = \'%s\' AND name = \'smooth_streaming\' ORDER BY updated_at DESC;', $id);
            $url = $app->db->querySingle($query);
            $urls['smooth_streaming'] = $url;
        } else {
            $media = null;
        }
    } catch (Exception $e) {
        $app->log->debug(print_r($e, true));

        throw($e);
    }

    $media['movie_name'] = '';
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
            'skhnCd' => $media['mcode'],
            'dvcTyp' => \MvtkService\Common::DVC_TYP_PC,
        ];
        $film = $service->GetFilmDetail($params);
        $film = $film->toArray();
        $media['movie_name'] = $film['SKHN_NM'];
    } catch (Exception $e) {
        $app->log->debug(print_r($e, true));
        $media['movie_name'] = $e->getMessage();
    }

    $app->log->debug(print_r($media, true));
    $app->log->debug(print_r($urls, true));

    return $app->render(
        'media/show.php',
        [
            'media' => $media,
            'urls' => $urls,
            'jobState'   => new JobState,
        ]
    );
})->name('media');

/**
 * メディアダウンロード
 */
$app->get('/media/:id/download', function ($id) use ($app) {
    $app->log->debug($id);

    try {
        $query = sprintf('SELECT * FROM media WHERE id = \'%s\';', $id);
        $media = $app->db->querySingle($query, true);

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

            // 既存のSASロケーターを全て削除
            // Server does not support setting more than 5 shared access policy identifiers on a single container..
//             $locators = $mediaServicesWrapper->getAssetLocators($asset);
//             foreach ($locators as $locator) {
//                 $mediaServicesWrapper->deleteLocator($locator);
//             }

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
 * メディア削除
 */
$app->get('/media/:id/delete', function ($id) use ($app) {
    $app->log->debug($id);

    $isDeleted = false;
    try {
        $query = sprintf('DELETE FROM media WHERE id = \'%s\';', $id);
        $app->log->debug('$query:' . $query);
        if (!$app->db->exec($query)) {
            $egl = error_get_last();
            $e = new Exception('SQLの実行でエラーが発生しました' . $egl['message']);
            throw $e;
        }

        // ジョブがあればアセットも削除
        $query = sprintf('SELECT * FROM media WHERE id = \'%s\';', $id);
        $media = $app->db->querySingle($query, true);
        if (isset($media['id']) && $media['job_id']) {
            $mediaServicesWrapper = $app->getMediaServicesWrapper();

            // ジョブのアセットを取得
            $inputAssets = $mediaServicesWrapper->getJobInputMediaAssets($media['job_id']);
            $outputAssets = $mediaServicesWrapper->getJobOutputMediaAssets($media['job_id']);

            // アセット削除
            foreach ($inputAssets as $asset) {
                $mediaServicesWrapper->deleteAsset($asset);
            }
            foreach ($outputAssets as $asset) {
                $mediaServicesWrapper->deleteAsset($asset);
            }
        }

        $isDeleted = true;
    } catch (Exception $e) {
        $app->log->debug(print_r($e, true));
        throw $e;
    }

    if ($isDeleted) {
        $app->redirect('/medias', 303);
    }

    throw new Exception('予期せぬエラー');
})->name('media_delete');

/**
 * アカウント編集
 */
$app->get('/user/edit', function () use ($app) {
    $message = null;

    $query = sprintf('SELECT * FROM user WHERE id = \'%s\';',
                    $_SERVER['PHP_AUTH_USER']);
    $user = $app->db->querySingle($query, true);

    $defaults = array(
        'email' => $user['email'],
    );

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

    $query = sprintf('SELECT * FROM user WHERE id = \'%s\';',
                    $_SERVER['PHP_AUTH_USER']);
    $user = $app->db->querySingle($query, true);

    $defaults = array(
        'email' => $user['email'],
    );

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

    $isSaved = false;
    try {
        // トランザクションの開始
        $app->db->exec('BEGIN DEFERRED;');

        $isSaved = false;

        $query = sprintf("UPDATE user SET email = '%s', updated_at = datetime('now') WHERE id = '%s';",
                        $_POST['email'],
                        $user['id']);
        $app->log->debug('$query:' . $query);
        if (!$app->db->exec($query)) {
            $egl = error_get_last();
            $e = new Exception('SQLの実行でエラーが発生しました' . $egl['message']);
            throw $e;
        }

        // コミット
        $app->db->exec('COMMIT;');
        $isSaved = true;
    } catch (Exception $e) {
        $app->log->debug(print_r($e, true));

        // ロールバック
        $app->db->exec('ROLLBACK;');
        throw $e;
    }

    if ($isSaved) {
        $app->redirect('/user/edit', 303);
    }
});

$app->run();

class JobState
{
    public static function getAll(){
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

    public static function toString($state){
        if ($state == Job::STATE_QUEUED) {
            return '待機中';
        } else if($state == Job::STATE_SCHEDULED) {
            return 'スケジュール済み';
        } else if($state == Job::STATE_PROCESSING) {
            return '進行中';
        } else if($state == Job::STATE_FINISHED) {
            return '完了';
        } else if($state == Job::STATE_ERROR) {
            return 'エラー';
        } else if($state == Job::STATE_CANCELED) {
            return 'キャンセル済み';
        } else if($state == Job::STATE_CANCELING) {
            return 'キャンセル中';
        }
    }
}
?>