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
                <div class="row placeholders">
                    <script src="/js/dash.all.js"></script>
                    <script>
                    // Videoエレメントの設定と、Dash Playerのアタッチ
                    function setupVideo() {
                        var url = "<?php echo $media['urls'][\Mms\Lib\Models\Task::NAME_MPEG_DASH] ?>";
                        var context = new Dash.di.DashContext();
                        var player = new MediaPlayer(context);
                        player.startup();
                        player.attachView(document.querySelector('#mpegDashPlayer_ver<?php echo $media['version'] ?>'));
                        player.attachSource(url);
                    }
                    window.addEventListener('load', setupVideo, false);
                    </script>
                    <video width="400" height="300" id="mpegDashPlayer_ver<?php echo $media['version'] ?>" src="" autoplay controls>MPEG DASH</video>
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
                        <param name="InitParams" value="selectedcaptionstream=textstream_eng,mediaurl=<?php echo $media['urls'][\Mms\Lib\Models\Task::NAME_MPEG_DASH] ?>" />
                        <a href="http://go.microsoft.com/fwlink/?LinkID=149156&v=4.0.50401.0" style="text-decoration:none">
                            <img src="http://go.microsoft.com/fwlink/?LinkId=161376" alt="Get Microsoft Silverlight" style="border-style:none"/>
                        </a>
                    </object>

                    <h4>MPEG DASH on Silverlight</h4>
                    <span class="text-muted"></span>
                </div>
                -->
                <?php } ?>

                <?php if ($media['urls'][\Mms\Lib\Models\Task::NAME_SMOOTH_STREAMING]) { ?>
                <div class="row placeholders">
                    <object data="data:application/x-silverlight-2," type="application/x-silverlight-2" width="400px" height="300px">
                        <param name="source" value="/smoothstreamingplayer-2.2010.1001.1/SmoothStreamingPlayer.xap"/>
                        <param name="onError" value="onSilverlightError" />
                        <param name="background" value="white" />
                        <param name="minRuntimeVersion" value="4.0.50401.0" />
                        <param name="autoUpgrade" value="true" />
                        <param name="InitParams" value="selectedcaptionstream=textstream_eng,mediaurl=<?php echo $media['urls'][\Mms\Lib\Models\Task::NAME_SMOOTH_STREAMING] ?>" />
                        <a href="http://go.microsoft.com/fwlink/?LinkID=149156&v=4.0.50401.0" style="text-decoration:none">
                            <img src="http://go.microsoft.com/fwlink/?LinkId=161376" alt="Get Microsoft Silverlight" style="border-style:none"/>
                        </a>
                    </object>

                    <h4>Smooth Streaming on Silverlight</h4>
                    <span class="text-muted">http://technet.microsoft.com/ja-jp/library/dd775198.aspx</span>
                </div>
                <?php } ?>

                <?php if ($media['urls'][\Mms\Lib\Models\Task::NAME_HLS]) { ?>
                <div class="row placeholders">
                    <video width="400"
                           height="300"
                           src="<?php echo $media['urls'][\Mms\Lib\Models\Task::NAME_HLS] ?>"
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
