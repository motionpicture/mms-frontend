<?php require dirname(__FILE__) . '/../header.php' ?>

<script src="/js/media/show.js"></script>

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
                                <th>公開開始日時</th>
                                <td><input class="form-control" type="text" name="start_at" value="<?php echo $media['start_at'] ?>"></td>
                            </tr>
                            <tr>
                                <th>公開終了日時</th>
                                <td><input class="form-control" type="text" name="end_at" value="<?php echo $media['end_at'] ?>"></td>
                            </tr>
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
                                <td><?php echo $jobState::toString($media['job_state']) ?></td>
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
                                    <input class="form-control" type="text" name="media-url" value="<?php echo ($media['urls']['smooth_streaming']) ? $media['urls']['smooth_streaming'] . '(format=mpd-time-csf)' : '' ?>" readonly>
                                    <?php if ($media['urls']['smooth_streaming']) { ?>
                                    <span style="position: relative" class="copy-url">
                                        <a href="javascript:void(0)" class="btn btn-default">COPY</a>
                                    </span>
                                    <?php } ?>
                                    <span class="url hidden"><?php echo ($media['urls']['smooth_streaming']) ? $media['urls']['smooth_streaming'] . '(format=mpd-time-csf)' : '' ?></span>
                                </td>
                            </tr>
                        </tbody>
                    </table><!-- /.movies -->
                </div>
            </div>

            <div class="col-xs-12 col-sm-6 col-lg-6">
                <?php if ($media['urls']['smooth_streaming']) { ?>
                <?php $url4smoothStreaming = $media['urls']['smooth_streaming'] ?>
                <div class="row placeholders">
                    <script src="/js/dash.all.js"></script>
                    <script>
                    // Videoエレメントの設定と、Dash Playerのアタッチ
                    function setupVideo() {
                        var url = "<?php echo $url4smoothStreaming ?>(format=mpd-time-csf)";
                        var context = new Dash.di.DashContext();
                        var player = new MediaPlayer(context);
                        player.startup();
                        player.attachView(document.querySelector('#mpegDashPlayer'));
                        player.attachSource(url);
                    }
                    window.addEventListener('load', setupVideo, false);
                    </script>
                    <video width="400" height="300" id="mpegDashPlayer" src="" autoplay controls>MPEG DASH</video>
                    <h4>MPEG DASH on HTML5</h4>
                    <span class="text-muted"></span>
                </div>

                <!--
                <div class="row placeholders">
                    <object data="data:application/x-silverlight-2," type="application/x-silverlight-2" width="400px" height="300px">
                        <param name="source" value="/smoothstreamingplayer-2.2010.1001.1/SmoothStreamingPlayer.xap"/>
                        <param name="onError" value="onSilverlightError" />
                        <param name="background" value="white" />
                        <param name="minRuntimeVersion" value="4.0.50401.0" />
                        <param name="autoUpgrade" value="true" />
                        <param name="InitParams" value="selectedcaptionstream=textstream_eng,mediaurl=<?php echo $url4smoothStreaming ?>(format=mpd-time-csf)" />
                        <a href="http://go.microsoft.com/fwlink/?LinkID=149156&v=4.0.50401.0" style="text-decoration:none">
                            <img src="http://go.microsoft.com/fwlink/?LinkId=161376" alt="Get Microsoft Silverlight" style="border-style:none"/>
                        </a>
                    </object>

                    <h4>MPEG DASH on Silverlight</h4>
                    <span class="text-muted"></span>
                </div>
                -->

                <div class="row placeholders">
                    <object data="data:application/x-silverlight-2," type="application/x-silverlight-2" width="400px" height="300px">
                        <param name="source" value="/smoothstreamingplayer-2.2010.1001.1/SmoothStreamingPlayer.xap"/>
                        <param name="onError" value="onSilverlightError" />
                        <param name="background" value="white" />
                        <param name="minRuntimeVersion" value="4.0.50401.0" />
                        <param name="autoUpgrade" value="true" />
                        <param name="InitParams" value="selectedcaptionstream=textstream_eng,mediaurl=<?php echo $url4smoothStreaming ?>" />
                        <a href="http://go.microsoft.com/fwlink/?LinkID=149156&v=4.0.50401.0" style="text-decoration:none">
                            <img src="http://go.microsoft.com/fwlink/?LinkId=161376" alt="Get Microsoft Silverlight" style="border-style:none"/>
                        </a>
                    </object>

                    <h4>Smooth Streaming on Silverlight</h4>
                    <span class="text-muted">http://technet.microsoft.com/ja-jp/library/dd775198.aspx</span>
                </div>

                <div class="row placeholders">
                    <video width="400"
                           height="300"
                           src="<?php echo $url4smoothStreaming ?>(format=m3u8-aapl)"
                           autoplay="true"
                           controls="true">HLS</video>

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
