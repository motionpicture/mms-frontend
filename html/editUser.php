<?php

require_once('MmsActions.php');

$mms = new MmsActions();
list($message, $defaults) = $mms->editUser();
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
    <h2>アカウント編集</h2>

    <?php if ($message) { ?><p class="error" style="color: #FF0000;"><?php echo $message ?></p><?php } ?>

    <form enctype="multipart/form-data" method="POST">
        <div class="field">
            <p>メールアドレス</p>
            <input type="text" name="email" value="<?php echo $defaults['email'] ?>">
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