﻿<?php

require_once('MmsActions.php');

$mms = new MmsActions();
list($message, $defaults) = $mms->form();
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
        <a href="new.php">メディア登録</a>
        <a href="index.php">メディア一覧</a>
        <a href="editUser.php">アカウント編集</a>
    </nav>
    こんにちは <?php echo $_SERVER['PHP_AUTH_USER'] ?>さん
</header>
</div>

<section class="page-content">
    <h2>メディア登録</h2>

    <?php if ($message) { ?><p class="error" style="color: #FF0000;"><?php echo $message ?></p><?php } ?>

    <form enctype="multipart/form-data" method="POST">
        <input type="hidden" name="MAX_FILE_SIZE" value="100000000" />

        <div class="field">
            <p>作品コード:</p>
            <input type="text" name="mcode" value="<?php echo $defaults['mcode'] ?>">
        </div>

        <div class="field">
            <p>カテゴリー:</p>
            <select name="category_id">
            <option value ="">カテゴリーを選択してください</option>
            <?php foreach ($mms->categories as $id => $name) { ?>
            <option value ="<?php echo $id ?>"<?php if ($defaults['category_id'] == $id) {echo ' selected';} ?>><?php echo $name ?></option>
            <?php } ?>
            </select>
        </div>

        <div class="field">
            <p>ファイル:</p>
            <input type="file" name="file" value="">
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