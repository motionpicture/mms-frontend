<?php

require_once('MmsActions.php');

$mms = new MmsActions();
list($media, $urls) = $mms->show();
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
    <h2>メディア詳細</h2>
    <?php if ($media) { ?>
    <table border="1">
        <tbody>
            <tr>
                <td>作品コード</td>
                <td><?php echo $media['mcode'] ?></td>
            </tr>
            <tr>
                <td>作品名</td>
                <td></td>
            </tr>
            <tr>
                <td>バージョン</td>
                <td><?php echo $media['version'] ?></td>
            </tr>
            <tr>
                <td>サイズ</td>
                <td><?php echo $media['size'] ?></td>
            </tr>
            <tr>
                <td>カテゴリー</td>
                <td><?php echo $media['category_id'] ?></td>
            </tr>
            <tr>
                <td>登録者</td>
                <td><?php echo $media['user_id'] ?></td>
            </tr>
            <tr>
                <td>ジョブID</td>
                <td><?php echo ($media['job_id'] != '') ? $media['job_id'] : 'ジョブ未登録' ?></td>
            </tr>
            <tr>
                <td>ジョブ進捗</td>
                <td><?php echo ($media['job_state'] != '') ? JobState::GetJobStateString($media['job_state']) : '' ?></td>
            </tr>
            <tr>
                <td>登録日時</td>
                <td><?php echo $media['created_at'] ?></td>
            </tr>
            <tr>
                <td>エンコード完了日時</td>
                <td><?php echo $media['encoded_at'] ?></td>
            </tr>
            <tr>
                <td>Smooth Streaming URL</td>
                <td><?php echo $urls['smooth_streaming'] ?></td>
            </tr>
            <tr>
                <td>Http Live Streaming URL</td>
                <td><?php echo $urls['http_live_streaming'] ?></td>
            </tr>
        </tbody>
    </table><!-- /.movies -->
    <?php } else { ?>動画が存在しないか、あるいは確認する権限がありません
    <?php } ?>

    <?php if ($urls['smooth_streaming']) { ?>
    <!-- http://technet.microsoft.com/ja-jp/library/dd775198.aspx -->
    <h2>Smooth Streaming(Silverlight)</h2>
    <object data="data:application/x-silverlight-2," type="application/x-silverlight-2" width="640px" height="480px">
        <param name="source" value="smoothstreamingplayer-2.2010.1001.1/SmoothStreamingPlayer.xap"/>
        <param name="onError" value="onSilverlightError" />
        <param name="background" value="white" />
        <param name="minRuntimeVersion" value="4.0.50401.0" />
        <param name="autoUpgrade" value="true" />
        <param name="InitParams" value="selectedcaptionstream=textstream_eng,mediaurl=<?php echo $urls['smooth_streaming'] ?>" />
        <a href="http://go.microsoft.com/fwlink/?LinkID=149156&v=4.0.50401.0" style="text-decoration:none">
            <img src="http://go.microsoft.com/fwlink/?LinkId=161376" alt="Get Microsoft Silverlight" style="border-style:none"/>
        </a>
    </object>
    <?php } ?>

    <?php if ($urls['http_live_streaming']) { ?>
    <h2>Http Live Streaming</h2>
    <video width="640"
           height="480"
           src="<?php echo $urls['http_live_streaming'] ?>"
           autoplay="true"
           controls="true">HLS</video>
    <?php } ?>
</section><!-- /.page-content -->

<footer><span>&copy; 2013</span></footer>

</div><!-- /.page-inner -->
</body>
</html>
