<?php

require_once('base.php');

require_once('..//vendor/WindowsAzureMediaServices/WindowsAzureMediaServicesContext.php');

try {
    $db = new MyDB();

    // ユーザーのメディアを取得
    $medias = array();
    $query = sprintf('SELECT * FROM media WHERE user_id = \'%s\' ORDER BY updated_at DESC;', $_SERVER['PHP_AUTH_USER']);
    $result = $db->query($query);
    while($res = $result->fetchArray(SQLITE3_ASSOC)){
      $medias[] = $res;
    }
} catch(Exception $e) {
  throw($e);
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
    <h2>メディア一覧</h2>
    <table border="1">
        <thead>
            <tr>
                <th>名前</th>
                <th>作品コード</th>
                <th>サイズ</th>
                <th>バージョン</th>
                <th>ジョブID</th>
                <th>ジョブ進捗</th>
                <th>エンコード完了日時</th>
                <th>更新日時</th>
            </tr>
        </thead>

        <tbody>
            <?php foreach ($medias as $media) { ?>
            <tr>
                <td><a href="detail.php?id=<?php echo $media['id'] ?>"><?php echo $media['id'] ?></a></td>
                <td><?php echo $media['mcode'] ?></td>
                <td><?php echo $media['size'] ?></div></td>
                <td><?php echo $media['version'] ?></div></td>
                <td><?php echo $media['job_id'] ?></td>
                <td><?php echo JobState::GetJobStateString($media['job_state']) ?></td>
                <td><?php echo $media['encoded_at'] ?></td>
                <td><?php echo $media['updated_at'] ?></td>
            </tr>
            <?php } ?>
        </tbody>
    </table><!-- /.movies -->
</section><!-- /.page-content -->

<footer><span>&copy; 2013</span></footer>

</div><!-- /.page-inner -->
</body>
</html>

