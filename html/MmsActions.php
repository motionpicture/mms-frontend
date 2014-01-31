<?php
ini_set('display_errors', 1);

require_once('MmsDb.php');

require_once(dirname(__FILE__) . '/../lib/WindowsAzureMediaServices/WindowsAzureMediaServicesContext.php');

class MmsActions
{
    public $db;
    public $mediaContext;
    public $categories;

    function __construct()
    {
        $this->db = new MmsDb();

//         $this->mediaContext = new WindowsAzureMediaServicesContext(
//             'testmvtkms',
//             'Vi3fX70rZKrtk/DM6TRoJ/XpxmkC29LNOzWimE06rx4=',
//             null,
//             null
//         );

        // カテゴリーを取得
        $categories = array();
        try {
            $query = 'SELECT * FROM category';
            $result = $this->db->query($query);
            while($res = $result->fetchArray(SQLITE3_ASSOC)){
                $categories[$res['id']] = $res['name'];
            }
        } catch (Exception $e) {
            $this->log($e);

            throw($e);
        }
        $this->categories = $categories;

        // DBにベーシック認証ユーザーが存在しなかれば登録
        $query = sprintf('SELECT * FROM user WHERE id = \'%s\';',
                        $_SERVER['PHP_AUTH_USER']);
        $user = $this->db->querySingle($query, true);
        if (!isset($user['id'])) {
            $query = sprintf("INSERT INTO user (id, created_at, updated_at) VALUES ('%s', datetime('now'), datetime('now'))",
                            $_SERVER['PHP_AUTH_USER']);
            $this->log($query);
            if (!$this->db->exec($query)) {
                $egl = error_get_last();
                $e = new Exception('SQLの実行でエラーが発生しました' . $egl['message']);
                throw $e;
            }
        }

        $this->log($_SERVER);
    }

    /**
     * メディア登録
     *
     * @return string エラーメッセージ
     */
    function form()
    {
        $message = null;
        $defaults = array(
            'mcode' => '',
            'category_id' => ''
        );

        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $this->log($_POST);

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
                return array($message, $defaults);
            }

            try {
                // トランザクションの開始
                $this->db->exec('BEGIN DEFERRED;');

                // 同作品同カテゴリーのデータがあるか確認
                $query = sprintf('SELECT COUNT(*) AS count FROM media WHERE mcode = \'%s\' AND category_id = \'%s\';',
                                $_POST['mcode'],
                                $_POST['category_id']);
                $count = $this->db->querySingle($query);
                // バージョンを確定
                $version = $count;
                // 作品コード、カテゴリー、バージョンからIDを生成
                $id = implode('_', array($_POST['mcode'], $_POST['category_id'], $version));

                $isSaved = false;

                $query = sprintf(
                    "INSERT INTO media (id, mcode, version, size, user_id, category_id, created_at, updated_at) VALUES ('%s', '%s', '%s', '%s', '%s', '%s', datetime('now'), datetime('now'))",
                    $id,
                    $_POST['mcode'],
                    $version,
                    $_FILES['file']['size'],
                    $_SERVER['PHP_AUTH_USER'],
                    $_POST['category_id']
                );

                if (!$this->db->exec($query)) {
                    $egl = error_get_last();
                    $e = new Exception('SQLの実行でエラーが発生しました' . $egl['message']);
                    throw $e;
                }

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
                $this->log($e);

                // ロールバック
                $this->db->exec('ROLLBACK;');
                throw $e;
            }

            if ($isSaved) {
                // コミット
                $this->db->exec('COMMIT;');
                header('Location: index.php');
            }
        }

        $this->log($message);
        $this->log($defaults);

        return array($message, $defaults);
    }

    /**
     * メディア一覧
     *
     * @return array メディア一覧
     */
    function index()
    {
        $medias = array();

        try {
            // ユーザーのメディアを取得
            $query = sprintf('SELECT * FROM media WHERE user_id = \'%s\' ORDER BY updated_at DESC;',
                            $_SERVER['PHP_AUTH_USER']);
            $result = $this->db->query($query);
            while($res = $result->fetchArray(SQLITE3_ASSOC)){
                $medias[] = $res;
            }
        } catch (Exception $e) {
            $this->log($e);

            throw($e);
        }

        $this->log($medias);

        return $medias;
    }

    /**
     * メディア詳細
     *
     * @return array メディア詳細とURL配列
     */
    function show()
    {
        $media = null;
        $urls = array(
            'smooth_streaming' => '',
            'http_live_streaming' => '',
        );

        try {
            $id = $_GET['id'];

            $query = sprintf('SELECT * FROM media WHERE id = \'%s\' AND user_id = \'%s\';',
                             $id,
                             $_SERVER['PHP_AUTH_USER']);
            $media = $this->db->querySingle($query, true);

            if (isset($media['id'])) {
                // smooth streaming用のURL
                $query = sprintf('SELECT url FROM task WHERE media_id = \'%s\' AND name = \'smooth_streaming\' ORDER BY updated_at DESC;', $id);
                $url = $this->db->querySingle($query);
                $urls['smooth_streaming'] = $url;

                // HLS用のURL
                $query = sprintf('SELECT url FROM task WHERE media_id = \'%s\' AND name = \'http_live_streaming\' ORDER BY updated_at DESC;', $id);
                $url = $this->db->querySingle($query);
                $urls['http_live_streaming'] = $url;
            } else {
                $media = null;
            }
        } catch (Exception $e) {
            $this->log($e);

            throw($e);
        }

        $media['movie_name'] = '';
        try {
            require_once(dirname(__FILE__) . '/../vendor/autoload.php');

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
            $this->log($e);
            $media['movie_name'] = $e->getMessage();
        }

        $this->log($media);
        $this->log($urls);

        return array($media, $urls);
    }

    /**
     * ユーザー編集
     *
     * @return string エラーメッセージ
     */
    function editUser()
    {
        $message = null;

        $query = sprintf('SELECT * FROM user WHERE id = \'%s\';',
                        $_SERVER['PHP_AUTH_USER']);
        $user = $this->db->querySingle($query, true);

        $defaults = array(
            'email' => $user['email'],
        );

        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            $this->log($_POST);

            $defaults = $_POST;

            if (!$_POST['email']) {
                $message .= '<br>メールアドレスを入力してください';
            }
            if ($message) {
                return array($message, $defaults);
            }

            try {
                // トランザクションの開始
                $this->db->exec('BEGIN DEFERRED;');

                $isSaved = false;

                $query = sprintf("UPDATE user SET email = '%s', updated_at = datetime('now') WHERE id = '%s';",
                                $_POST['email'],
                                $user['id']);
                $this->log('$query:' . $query);
                if (!$this->db->exec($query)) {
                    $egl = error_get_last();
                    $e = new Exception('SQLの実行でエラーが発生しました' . $egl['message']);
                    throw $e;
                }

                $isSaved = true;
            } catch (Exception $e) {
                $this->log($e);

                // ロールバック
                $this->db->exec('ROLLBACK;');
                throw $e;
            }

            if ($isSaved) {
                // コミット
                $this->db->exec('COMMIT;');
                header('Location: editUser.php');
            }
        }

        $this->log($message);
        $this->log($defaults);

        return array($message, $defaults);
    }

    function log($content)
    {
        file_put_contents(dirname(__FILE__) . '/../log/mms.log', print_r($content, true) . "\n", FILE_APPEND);
    }

    function debug($content)
    {
        echo '<pre>';
        print_r($content);
        echo '</pre>';
    }
}
?>
