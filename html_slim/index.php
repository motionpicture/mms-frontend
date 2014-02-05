<?php
require_once dirname(__FILE__) . '/../lib/MmsSlim.php';

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
    $app->redirect('/medias');
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
        while($res = $result->fetchArray(SQLITE3_ASSOC)){
            $categories[$res['id']] = $res['name'];
        }
    } catch (Exception $e) {
        $this->log($e);

        throw($e);
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
        while($res = $result->fetchArray(SQLITE3_ASSOC)){
            $categories[$res['id']] = $res['name'];
        }
    } catch (Exception $e) {
        $this->log($e);

        throw($e);
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

    try {
        // トランザクションの開始
        $app->db->exec('BEGIN DEFERRED;');

        // 同作品同カテゴリーのデータがあるか確認
        $query = sprintf('SELECT COUNT(*) AS count FROM media WHERE mcode = \'%s\' AND category_id = \'%s\';',
                        $_POST['mcode'],
                        $_POST['category_id']);
        $count = $app->db->querySingle($query);
        // バージョンを確定
        $version = $count;
        // 作品コード、カテゴリー、バージョンからIDを生成
        $id = implode('_', array($_POST['mcode'], $_POST['category_id'], $version));

        $query = sprintf(
            "INSERT INTO media (id, mcode, version, size, extension, user_id, category_id, created_at, updated_at) VALUES ('%s', '%s', '%s', '%s', '%s', '%s', '%s', %s, %s);",
            $id,
            $_POST['mcode'],
            $version,
            $_FILES['file']['size'],
            pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION),
            $_SERVER['PHP_AUTH_USER'],
            $_POST['category_id'],
            'datetime(\'now\', \'localtime\')',
            'datetime(\'now\', \'localtime\')'
        );
        $app->log->debug('$query:' . $query);
        if (!$app->db->exec($query)) {
            $egl = error_get_last();
            $e = new Exception('SQLの実行でエラーが発生しました' . $egl['message']);
            throw $e;
        }

        $app->db->exec('COMMIT;');

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

        $app->redirect('/medias');
    } catch (Exception $e) {
        $app->log->debug(print_r($e, true));

        // ロールバック
        $app->db->exec('ROLLBACK;');
        throw $e;
    }
});

/**
 * メディア一覧
 */
$app->get('/medias', function () use ($app) {
    $medias = [];

    try {
        // ユーザーのメディアを取得
        $query = 'SELECT media.*, category.name AS category_name FROM media INNER JOIN category ON media.category_id = category.id ORDER BY updated_at DESC;';
        $result = $app->db->query($query);
        while ($res = $result->fetchArray(SQLITE3_ASSOC)) {
            $medias[] = $res;
        }
    } catch (Exception $e) {
        $app->log->debug(print_r($e, true));

        throw($e);
    }

    $app->log->debug(print_r($medias, true));

    $app->render(
        'media/index.php',
        [
            'medias'     => $medias
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
        'smooth_streaming' => '',
        'http_live_streaming' => '',
    ];

    try {
        $query = sprintf('SELECT media.*, category.name AS category_name FROM media INNER JOIN category ON media.category_id = category.id WHERE media.id = \'%s\';', $id);
        $media = $app->db->querySingle($query, true);

        if (isset($media['id'])) {
            // smooth streaming用のURL
            $query = sprintf('SELECT url FROM task WHERE media_id = \'%s\' AND name = \'smooth_streaming\' ORDER BY updated_at DESC;', $id);
            $url = $app->db->querySingle($query);
            $urls['smooth_streaming'] = $url;

            // HLS用のURL
            $query = sprintf('SELECT url FROM task WHERE media_id = \'%s\' AND name = \'http_live_streaming\' ORDER BY updated_at DESC;', $id);
            $url = $app->db->querySingle($query);
            $urls['http_live_streaming'] = $url;
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
            'urls' => $urls
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

        throw($e);
    }

    throw(new Exception('予期せぬエラー'));
})->name('media_download');

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
        $app->redirect('/user/edit');
    } catch (Exception $e) {
        $app->log->debug(print_r($e, true));

        // ロールバック
        $app->db->exec('ROLLBACK;');
        throw $e;
    }
});

$app->run();
?>