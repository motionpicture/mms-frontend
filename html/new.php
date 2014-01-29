<?php

require_once('base.php');

$message = null;
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $message = isValid();

    if (!$message) {
        try {
            $db = new MyDB();

            // トランザクションの開始
            $db->exec('BEGIN DEFERRED;');

            // 同作品のデータがあるか確認
            $query = sprintf('SELECT COUNT(*) AS count FROM media WHERE mcode = \'%s\' ORDER BY version DESC;', $_POST['mcode']);
            $count = $db->querySingle($query);

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

            if (!$db->exec($query)) {
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
                throw Exception('ファイルのアップロードでエラーが発生しました');
            }

            chmod($uploadfile, 0644);

            $isSaved = true;
        } catch (Exception $e) {
            // ロールバック
            $db->exec('ROLLBACK;');
            throw $e;
        }

        if ($isSaved) {
            // コミット
            $db->exec('COMMIT;');
            header('Location: index.php');
        }
    }
}

function isValid()
{
    if (!$_POST['mcode']) {
        return '作品コードを入力してください';
    }

    if ($_FILES['file']['size'] <= 0) {
        return 'ファイルを選択してください';
    }

    return '';
}
?>

<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="utf-8">
<title>メディア管理</title>
</head>
<body>
<div class="page-inner">

<div class="header">
<header>
    <nav>
        <h1>メディア管理</h1>
        <a href="new.php"><span class="title">メディア登録</span></a>
        <a href="index.php"><span class="title">メディア一覧</span></a>
    </nav>
    こんにちは <?php echo $_SERVER['PHP_AUTH_USER'] ?>さん
<!--     <a href="#"><span class="title">ログアウト</span><span class="ui-icon logout"></span></a> -->
</header>
</div>

<section class="page-content">
    <h2>メディア編集</h2>

    <?php if ($message) { ?><p class="error" style="color: #FF0000;"><?php echo $message ?></p><?php } ?>

    <form enctype="multipart/form-data" method="POST">
        <input type="hidden" name="MAX_FILE_SIZE" value="100000000" />

        <div class="field">
            <p><span>作品コード:</span></p><input type="text" name="mcode" value="<?php if ($_SERVER['REQUEST_METHOD'] == 'POST'){echo $_POST['mcode'];} ?>">
        </div>
        <div class="field">
            <p><span>ファイル:</span></p><input type="file" name="file" value="">
        </div>
        <div class="field">
            <input type="submit" value="登録">
        </div>
    </form>

</section><!-- /.page-content -->

<footer><span>&copy; 2013</span></footer>

</div><!-- /.page-inner -->
</body>
</html>