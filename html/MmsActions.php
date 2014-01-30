<?php
ini_set('display_errors', 1);

require_once('MmsDb.php');

require_once(dirname(__FILE__) . '/../vendor/WindowsAzureMediaServices/WindowsAzureMediaServicesContext.php');

class MmsActions
{
    private $db;
    private $mediaContext;

    function __construct()
    {
        $this->db = new MmsDb();

//         $this->mediaContext = new WindowsAzureMediaServicesContext(
//             'testmvtkms',
//             'Vi3fX70rZKrtk/DM6TRoJ/XpxmkC29LNOzWimE06rx4=',
//             null,
//             null
//         );

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

        if ($_SERVER['REQUEST_METHOD'] == 'POST') {
            if (!$_POST['mcode']) {
                $message = '作品コードを入力してください';
            }

            if ($_FILES['file']['size'] <= 0) {
                $message = 'ファイルを選択してください';
            }

            if ($message) {
                return $message;
            }

            try {
                // トランザクションの開始
                $this->db->exec('BEGIN DEFERRED;');

                // 同作品のデータがあるか確認
                $query = sprintf('SELECT COUNT(*) AS count FROM media WHERE mcode = \'%s\';',
                                $_POST['mcode']);
                $count = $this->db->querySingle($query);

                // バージョンを確定
                $version = $count;

                // 作品コードとバージョンからIDを生成
                $id = $_POST['mcode'] . '_' . $version;

                $isSaved = false;

                $query = sprintf(
                    "INSERT INTO media (id, mcode, version, size, user_id, created_at, updated_at) VALUES ('%s', '%s', '%s', '%s', '%s', datetime('now'), datetime('now'))",
                    $id,
                    $_POST['mcode'],
                    $version,
                    $_FILES['file']['size'],
                    $_SERVER['PHP_AUTH_USER']
                );

                if (!$this->db->exec($query)) {
                    throw new Exception('SQLの実行でエラーが発生しました');
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

        return $message;
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
        $urls = array();

        try {
            $id = $_GET['id'];

            $query = sprintf('SELECT * FROM media WHERE id = \'%s\' AND user_id = \'%s\';',
                             $id,
                             $_SERVER['PHP_AUTH_USER']);
            $media = $this->db->querySingle($query, true);

            if (isset($media['id'])) {
                // smooth streaming用のURL
                $query = sprintf('SELECT url FROM task WHERE media_id = \'%s\' AND name = \'smooth_streaming\';', $id);
                $url = $this->db->querySingle($query);
                $urls['smooth_streaming'] = $url;

                // HLS用のURL
                $query = sprintf('SELECT url FROM task WHERE media_id = \'%s\' AND name = \'http_live_streaming\';', $id);
                $url = $this->db->querySingle($query);
                $urls['http_live_streaming'] = $url;
            } else {
                $media = null;
            }
        } catch (Exception $e) {
            $this->log($e);

            throw($e);
        }

        $this->log($media);
        $this->log($urls);

        return array($media, $urls);
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
