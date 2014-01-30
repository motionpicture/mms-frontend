<?php

require_once('MmsActions.php');

$mms = new MmsActions();
$message = $mms->form();
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
    <h2>メディア登録</h2>

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