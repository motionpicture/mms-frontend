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
        <a href="new.php"><span class="title">メディア登録</span></a>
        <a href="index.php"><span class="title">メディア一覧</span></a>
    </nav>
    こんにちは <?php echo $_SERVER['PHP_AUTH_USER'] ?>さん
<!--     <a href="#"><span class="title">ログアウト</span><span class="ui-icon logout"></span></a> -->
</header>
</div>

<section class="page-content">
    <h2>メディア詳細</h2>
    <?php if ($media) { ?>
    <?php $mms->debug($media); ?>
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
