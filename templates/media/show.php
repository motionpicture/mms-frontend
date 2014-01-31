<?php require dirname(__FILE__) . '/../header.php' ?>

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
                <td><?php echo $media['movie_name'] ?></td>
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
                <td>ジョブ開始日時</td>
                <td><?php echo $media['job_start_time'] ?></td>
            </tr>
            <tr>
                <td>ジョブ完了or失敗orキャンセル日時</td>
                <td><?php echo $media['job_end_time'] ?></td>
            </tr>
            <tr>
                <td>登録日時</td>
                <td><?php echo $media['created_at'] ?></td>
            </tr>
            <tr>
                <td>MPEG DASH URL</td>
                <td><?php echo ($urls['smooth_streaming']) ? $urls['smooth_streaming'] . '(format=mpd-time-csf)' : '' ?></td>
            </tr>
            <tr>
                <td>Smooth Streaming URL</td>
                <td><?php echo $urls['smooth_streaming'] ?></td>
            </tr>
            <tr>
                <td>Http Live Streaming URL</td>
                <td><?php echo ($urls['smooth_streaming']) ? $urls['smooth_streaming'] . '(format=m3u8-aapl)' : '' ?></td>
            </tr>
        </tbody>
    </table><!-- /.movies -->
    <?php } else { ?>動画が存在しないか、あるいは確認する権限がありません
    <?php } ?>

    <?php if ($urls['smooth_streaming']) { ?>
    <script src="/js/dash.all.js"></script>
    <script>
    // Videoエレメントの設定と、Dash Playerのアタッチ
    function setupVideo() {
        var url = "<?php echo $urls['smooth_streaming'] ?>(format=mpd-time-csf)";
        var context = new Dash.di.DashContext();
        var player = new MediaPlayer(context);
        player.startup();
        player.attachView(document.querySelector('#mpegDashPlayer'));
        player.attachSource(url);
    }
    window.addEventListener('load', setupVideo, false);
    </script>
    <h2>MPEG DASH</h2>
        <video id="mpegDashPlayer"
               width="640"
               height="480"
               src=""
               autoplay
               controls>MPEG DASH</video>

    <!-- http://technet.microsoft.com/ja-jp/library/dd775198.aspx -->
    <h2>Smooth Streaming(Silverlight)</h2>
    <object data="data:application/x-silverlight-2," type="application/x-silverlight-2" width="640px" height="480px">
        <param name="source" value="/smoothstreamingplayer-2.2010.1001.1/SmoothStreamingPlayer.xap"/>
        <param name="onError" value="onSilverlightError" />
        <param name="background" value="white" />
        <param name="minRuntimeVersion" value="4.0.50401.0" />
        <param name="autoUpgrade" value="true" />
        <param name="InitParams" value="selectedcaptionstream=textstream_eng,mediaurl=<?php echo $urls['smooth_streaming'] ?>" />
        <a href="http://go.microsoft.com/fwlink/?LinkID=149156&v=4.0.50401.0" style="text-decoration:none">
            <img src="http://go.microsoft.com/fwlink/?LinkId=161376" alt="Get Microsoft Silverlight" style="border-style:none"/>
        </a>
    </object>

    <h2>Http Live Streaming</h2>
    <video width="640"
           height="480"
           src="<?php echo $urls['smooth_streaming'] ?>(format=m3u8-aapl)"
           autoplay="true"
           controls="true">HLS</video>
    <?php } ?>
</section><!-- /.page-content -->

<?php require dirname(__FILE__) . '/../footer.php' ?>
