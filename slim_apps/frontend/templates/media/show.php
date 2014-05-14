<?php require dirname(__FILE__) . '/../header.php' ?>

<script src="/js/media/show.js"></script>
<script src="/js/dash.all.js"></script>
<script src="/js/Silverlight.js"></script>

<script>
// ユーザーエージェントの判別
var ua = {};
ua.name = window.navigator.userAgent.toLowerCase();
ua.isiPhone = ua.name.indexOf('iphone') >= 0;
ua.isiPod = ua.name.indexOf('ipod') >= 0;
ua.isiPad = ua.name.indexOf('ipad') >= 0;
ua.isiOS = (ua.isiPhone || ua.isiPod || ua.isiPad);
ua.isAndroid = ua.name.indexOf('android') >= 0;
ua.isTablet = (ua.isiPad || (ua.isAndroid && ua.name.indexOf('mobile') < 0));
</script>

<div class="row">
    <div class="main">
        <h1 class="page-header">メディア詳細</h1>
        <p>
            <a href="javascript:void(0)" class="update-media-by-code btn btn-primary ladda-button" data-style="zoom-in"><span class="ladda-label">更新</span></a>
        </p>

        <div class="row">
            <span class="media-code hide"><?php echo $medias[0]['code'] ?></span>
            <div class="col-xs-12 col-sm-12 col-lg-12">
                <div class="table-responsive">
                    <table class="table table-bordered table-striped">
                        <tbody>
                            <tr>
                                <th>作品コード</th>
                                <td><?php echo $medias[0]['mcode'] ?></td>
                            </tr>
                            <tr>
                                <th>作品名</th>
                                <td><input class="form-control" type="text" name="movie_name" value="<?php echo $medias[0]['movie_name'] ?>"></td>
                            </tr>
                            <tr>
                                <th>カテゴリー</th>
                                <td><?php echo $medias[0]['category_name'] ?></td>
                            </tr>
                            <tr>
                                <th>公開開始日時</th>
                                <td><input class="form-control" type="text" name="start_at" value="<?php echo $medias[0]['start_at'] ?>"></td>
                            </tr>
                            <tr>
                                <th>公開終了日時</th>
                                <td><input class="form-control" type="text" name="end_at" value="<?php echo $medias[0]['end_at'] ?>"></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <?php foreach ($medias as $media) { ?>
        <h3>ver.<?php echo $media['version'] ?></h3>
        <p>
            <a href="javascript:void(0)" class="update-media btn btn-primary ladda-button" data-style="zoom-in"><span class="ladda-label">このバージョンを更新</span></a>
            <a href="javascript:void(0)" class="delete-media btn btn-danger ladda-button" data-style="zoom-in"><span class="ladda-label">削除</span></a>
        </p>

        <div class="row">
            <span class="media-id hide"><?php echo $media['id'] ?></span>
            <span class="media-version hide"><?php echo $media['version'] ?></span>
            <div class="col-xs-12 col-sm-6 col-lg-6">
                <div class="table-responsive">
                    <table class="table table-bordered table-striped">
                        <tbody>
                            <tr>
                                <th>サイズ</th>
                                <td><?php echo $media['size'] ?>バイト</td>
                            </tr>
                            <tr>
                                <th>再生時間</th>
                                <td><?php echo $media['playtime_string'] ?></td>
                            </tr>
                            <tr>
                                <th>登録者</th>
                                <td><?php echo $media['user_id'] ?></td>
                            </tr>
                            <tr>
                                <th>ジョブ進捗</th>
                                <td><?php echo \Mms\Lib\JobState::toString($media['job_state']) ?></td>
                            </tr>
                            <tr>
                                <th>ジョブ開始日時</th>
                                <td><?php echo $media['job_start_at'] ?></td>
                            </tr>
                            <tr>
                                <th>ジョブ完了or失敗orキャンセル日時</th>
                                <td><?php echo $media['job_end_at'] ?></td>
                            </tr>
                            <tr>
                                <th>登録日時</th>
                                <td><?php echo $media['created_at'] ?></td>
                            </tr>
                            <tr>
                                <th>MPEG DASH URL</th>
                                <td>
                                    <input class="form-control" type="text" name="media-url" value="<?php echo $media['urls'][\Mms\Lib\Models\Task::NAME_MPEG_DASH] ?>" readonly>
                                    <?php if ($media['urls'][\Mms\Lib\Models\Task::NAME_MPEG_DASH]) { ?>
                                    <span style="position: relative" class="copy-url">
                                        <a href="javascript:void(0)" class="btn btn-default">COPY</a>
                                    </span>
                                    <?php } ?>
                                    <span class="url hidden"><?php echo $media['urls'][\Mms\Lib\Models\Task::NAME_MPEG_DASH] ?></span>
                                </td>
                            </tr>
                            <tr>
                                <th>Smooth Streaming URL</th>
                                <td>
                                    <input class="form-control" type="text" name="media-url" value="<?php echo $media['urls'][\Mms\Lib\Models\Task::NAME_SMOOTH_STREAMING] ?>" readonly>
                                    <?php if ($media['urls'][\Mms\Lib\Models\Task::NAME_SMOOTH_STREAMING]) { ?>
                                    <span style="position: relative" class="copy-url">
                                        <a href="javascript:void(0)" class="btn btn-default">COPY</a>
                                    </span>
                                    <?php } ?>
                                    <span class="url hidden"><?php echo $media['urls'][\Mms\Lib\Models\Task::NAME_SMOOTH_STREAMING] ?></span>
                                </td>
                            </tr>
                            <tr>
                                <th>HLS URL</th>
                                <td>
                                    <input class="form-control" type="text" name="media-url" value="<?php echo $media['urls'][\Mms\Lib\Models\Task::NAME_HLS] ?>" readonly>
                                    <?php if ($media['urls'][\Mms\Lib\Models\Task::NAME_HLS]) { ?>
                                    <span style="position: relative" class="copy-url">
                                        <a href="javascript:void(0)" class="btn btn-default">COPY</a>
                                    </span>
                                    <?php } ?>
                                    <span class="url hidden"><?php echo $media['urls'][\Mms\Lib\Models\Task::NAME_HLS] ?></span>
                                </td>
                            </tr>
                        </tbody>
                    </table><!-- /.movies -->
                </div>
            </div>

            <div class="col-xs-12 col-sm-6 col-lg-6">
                <?php if ($media['urls'][\Mms\Lib\Models\Task::NAME_MPEG_DASH]) { ?>
                <?php $mpegDashPlayerId = 'mpegDashPlayer_ver' . $media['version'] ?>
                <script>
                $(function(){
                    // Media Source Extension対応ブラウザのみ
                    // https://dvcs.w3.org/hg/html-media/raw-file/default/media-source/media-source.html
                    var url = '<?php echo $media['urls'][\Mms\Lib\Models\Task::NAME_MPEG_DASH] ?>';
                    if (typeof MediaSource == 'function') {
                        var context = new Dash.di.DashContext();
                        var player = new MediaPlayer(context);
                        player.startup();
                        player.attachView($('#<?php echo $mpegDashPlayerId ?>').get(0));
                        player.setAutoPlay(false);
                        player.attachSource(url);
                    } else {
                    	$('#<?php echo $mpegDashPlayerId ?>').replaceWith('<p>mpeg dash非対応ブラウザです</p>');
                    }
                });
                </script>
                <div class="row placeholders">
                    <video width="280"
                           height="210"
                           id="<?php echo $mpegDashPlayerId ?>"
                           controls>MPEG DASH</video>
                    <h4>MPEG DASH on HTML5</h4>
                    <span class="text-muted"></span>
                </div>
                <?php } ?>

                <?php if ($media['urls'][\Mms\Lib\Models\Task::NAME_SMOOTH_STREAMING]) { ?>
                <?php $smoothStreamingPlayerId = 'smoothStreamingPlayer_ver' . $media['version'] ?>
                <script>
                $(function(){
                    // スマホ端末ではpreloadできないため、自動的にボタンをオンにできない
                    if (!ua.isiOS && !ua.isAndroid && !ua.isTablet) {
                        Silverlight.createObject(
                            '/smoothstreamingplayer-2.2010.1001.1/SmoothStreamingPlayer.xap',
                            $('#<?php echo $smoothStreamingPlayerId ?>').get(0),
                            'silverlight_smoothStreamingPlayer_ver<?php echo $media['version'] ?>',
                            {
                                width: '280',
                                height: '210',
                                autoUpgrade: 'true',
                                minRuntimeVersion: '4.0.50401.0',
                                background: '#FFFFFF',
                            },
                            {
                                onError: null,
                                onLoad: null
                            },
                            'mediaurl=<?php echo $media['urls'][\Mms\Lib\Models\Task::NAME_SMOOTH_STREAMING] ?>,autoplay=false',
                            null
                        );
                    } else {
                    	$('#<?php echo $smoothStreamingPlayerId ?>').replaceWith('<p>Silverlight非対応ブラウザです</p>');
                    }
                });
                </script>
                <div class="row placeholders">
                    <div id="<?php echo $smoothStreamingPlayerId ?>"></div>
                    <h4>Smooth Streaming on Silverlight</h4>
                    <span class="text-muted">http://msdn.microsoft.com/ja-jp/library/cc838126(v=vs.95).aspx</span>
                </div>
                <?php } ?>

                <?php if ($media['urls'][\Mms\Lib\Models\Task::NAME_HLS]) { ?>
                <?php $hlsPlayerId = 'hlsPlayer_ver' . $media['version'] ?>
                <script>
                $(function(){
                    var url = '<?php echo $media['urls'][\Mms\Lib\Models\Task::NAME_HLS] ?>';
                    // スマホ端末ではpreloadできないため、自動的にボタンをオンにできない
                    if (ua.isiOS || ua.isAndroid || ua.isTablet) {
                        $('#<?php echo $hlsPlayerId ?>').attr('src', url);
                    } else {
                        $('#<?php echo $hlsPlayerId ?>').replaceWith('<p>Http Live Streaming非対応ブラウザです</p>');
                    }
                });
                </script>
                <div class="row placeholders">
                    <video width="280"
                           height="210"
                           id = "<?php echo $hlsPlayerId ?>"
                           controls>HLS</video>
                    <h4>Http Live Streaming on HTML5</h4>
                    <span class="text-muted"></span>
                </div>
                <?php } ?>

            </div>
        </div>

        <?php } ?>
    </div>
</div>

<?php require dirname(__FILE__) . '/../footer.php' ?>
