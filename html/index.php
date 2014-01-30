<?php

require_once('MmsActions.php');

$mms = new MmsActions();
$medias = $mms->index();
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
    <h2>メディア一覧</h2>
    <table border="1">
        <thead>
            <tr>
                <th>名前</th>
                <th>作品コード</th>
                <th>サイズ</th>
                <th>バージョン</th>
                <th>カテゴリー</th>
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
                <td><?php echo $media['category_id'] ?></div></td>
                <td><?php echo ($media['job_id'] != '') ? $media['job_id'] : 'ジョブ未登録' ?></td>
                <td><?php echo ($media['job_state'] != '') ? JobState::GetJobStateString($media['job_state']) : '' ?></td>
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

