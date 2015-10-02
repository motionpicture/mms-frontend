<?php require dirname(__FILE__) . '/../header.php' ?>

<script src="/js/media/show.js"></script>
<script src="/js/dash.all.js"></script>
<script src="/js/Silverlight.js"></script>

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
            <a href="javascript:void(0)" class="delete-media btn btn-danger ladda-button" data-style="zoom-in"><span class="ladda-label">このバージョンを削除</span></a>
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
                <?php $playerId = 'mpegDashPlayer_ver' . $media['version'] ?>
                <?php require __DIR__ . '/player/mpegDash.php' ?>
                <div class="row placeholders">
                  <?php echo $code ?>
                  <h4>MPEG DASH on HTML5</h4>
                  <span class="text-muted">IE11（Windows 8.1限定）以降、Chrome v23以降、Windows8 Applicationが対応。</span>
                </div>
                <pre><code>&lt;script src="/js/dash.all.js"&gt;
<?php echo htmlspecialchars($code) ?></code></pre>
                <?php } ?>

                <?php if ($media['urls'][\Mms\Lib\Models\Task::NAME_SMOOTH_STREAMING]) { ?>
                <?php $playerId = 'smoothStreamingPlayer_ver' . $media['version'] ?>
                <?php require __DIR__ . '/player/smoothStreaming.php' ?>
                <div class="row placeholders">
                    <?php echo $code ?>
                    <h4>Smooth Streaming on Silverlight</h4>
                    <span class="text-muted">http://msdn.microsoft.com/ja-jp/library/cc838126(v=vs.95).aspx</span>
                    <span class="text-muted">スマホやタブレット非対応。</span>
                </div>
                <pre><code>&lt;script src="/js/Silverlight.js"&gt;</script>
<?php echo htmlspecialchars($code) ?></code></pre>
                <?php } ?>

                <?php if ($media['urls'][\Mms\Lib\Models\Task::NAME_HLS]) { ?>
                <?php $playerId = 'hlsPlayer_ver' . $media['version'] ?>
                <?php require __DIR__ . '/player/httpLiveStreaming.php' ?>
                <div class="row placeholders">
                    <?php echo $code ?>
                    <h4>Http Live Streaming on HTML5</h4>
                    <span class="text-muted">MacOS X10.6以降のSafari、iOS、Androidが対応。</span>
                </div>
                <pre><code><?php echo htmlspecialchars($code) ?></code></pre>
                <?php } ?>

            </div>
        </div>

        <?php } ?>
    </div>
</div>

<?php require dirname(__FILE__) . '/../footer.php' ?>
