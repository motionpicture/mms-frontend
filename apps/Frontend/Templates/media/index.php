<?php require dirname(__FILE__) . '/../header.php' ?>

<script src="/js/media/index.js"></script>

<style type="text/css">
table td {
    text-align: right;
}
</style>

<div class="row">
    <div class="main">
        <h1 class="page-header">メディア一覧</h1>

        <form class="navbar-form" role="search">
            <div class="form-group">
                <p>
                    <?php foreach (\Mms\Lib\JobState::getAll() as $state) { ?>
                    <label class="checkbox inline">
                      <input type="checkbox" name="job_state[]" value="<?php echo $state ?>"<?php if (in_array($state, $searchConditions['job_state'])) { ?> checked<?php } ?>> <?php echo \Mms\Lib\JobState::toString($state) ?>
                    </label>
                    <?php } ?>
                </p>
                <p>
                    <?php foreach ($categories as $category) { ?>
                    <label class="checkbox inline">
                        <input type="checkbox" name="category[]" value="<?php echo $category['id'] ?>"<?php if (in_array($category['id'], $searchConditions['category'])) { ?> checked<?php } ?>> <?php echo $category['name'] ?>
                    </label>
                    <?php } ?>
                </p>
                <p>
                    <input type="text" name="word" value="<?php echo $searchConditions['word'] ?>" class="form-control" placeholder="Search">
                </p>
                <p>
                    <button type="submit" class="btn btn-default">検索</button>
                </p>
            </div>
        </form>

        <div class="table-responsive">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <?php require dirname(__FILE__) . '/_tableHeaders.php' ?>
                    </tr>
                </thead>

                <tbody>
                    <?php foreach ($medias as $key => $media) { ?>
                    <?php if ($key == $perPage) {break;} ?>
                    <tr>
                        <td>
                            <label>
                                <input type="checkbox" name="media_id" value="<?php echo $media['id'] ?>">
                            </label>
                        </td>
                        <td><?php echo $media['mcode'] ?></td>
                        <td><input class="form-control" type="text" name="movie_name" value="<?php echo $media['movie_name'] ?>"></td>
                        <td><?php echo $media['category_name'] ?></td>
                        <td><?php echo $media['version'] ?></td>
                        <td><input class="form-control" type="text" name="start_at" value="<?php echo $media['start_at'] ?>"></td>
                        <td><input class="form-control" type="text" name="end_at" value="<?php echo $media['end_at'] ?>"></td>
                        <td><?php echo floor($media['size'] / 1000000) ?></td>
                        <td><?php echo $media['playtime_string'] ?></td>
                        <td><?php echo $media['user_id'] ?></td>
                        <td><?php echo \Mms\Lib\JobState::toString($media['job_state']) ?></td>
                        <td><?php echo $media['updated_at'] ?></td>
                        <?php if ($app->config('debug')) { ?>
                        <td><?php echo $media['asset_id'] ?></td>
                        <td><?php echo $media['job_id'] ?></td>
                        <?php } ?>
                        <td>
                            <span class="media-id hide"><?php echo $media['id'] ?></span>
                            <span class="media-code hide"><?php echo $media['code'] ?></span>
                            <span class="movie-name hide"><?php echo $media['movie_name'] ?></span>
                            <span class="start-at hide"><?php echo $media['start_at'] ?></span>
                            <span class="end-at hide"><?php echo $media['end_at'] ?></span>
                            <span class="asset-id hide"><?php echo $media['asset_id'] ?></span>
                            <span class="job-id hide"><?php echo $media['job_id'] ?></span>
                            <span class="job-state hide"><?php echo $media['job_state'] ?></span>
                            <span class="deleted_at hide"><?php echo $media['deleted_at'] ?></span>
                            <a href="/media/<?php echo $media['code'] ?>" class="btn btn-default"><span class="ladda-label">詳細</span></a>
                            <a href="javascript:void(0)" class="update-media-by-code btn btn-primary ladda-button" data-style="zoom-in"><span class="ladda-label">更新</span></a>
                            <a href="javascript:void(0)" class="download-media btn btn-default">ダウンロード</a>

                            <?php if ($media['job_id'] && $media['job_state'] != '') { ?>
                            <?php if ($app->config('debug')) { ?>
                            <a href="javascript:void(0)" class="reencode-media btn btn-primary ladda-button" data-style="zoom-in"><span class="ladda-label">再エンコード</span></a>
                            <?php } ?>
                            <?php } ?>
                        </td>
                    </tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>

        <ul class="pager">
            <?php $previous = '/medias?' . http_build_query(array_merge($_GET, array('page' => $searchConditions['page'] - 1))); ?>
            <?php $next = '/medias?' . http_build_query(array_merge($_GET, array('page' => $searchConditions['page'] + 1))); ?>
            <li class="previous<?php if ($searchConditions['page'] < 2) { ?> disabled<?php } ?>"><a href="<?php echo $previous ?>">&larr; 前へ</a></li>
            <li class="next<?php if (count($medias) <= $perPage) { ?> disabled<?php } ?>"><a href="<?php echo $next ?>">次へ &rarr;</a></li>
        </ul>

        <div class="batch input-group">
            <span class="input-group-addon">選択したメディアをまとめて</span>
            <select name="action" class="form-control">
                <option value =""></option>
                <option value ="update-by-code">更新</option>
                <option value ="download">ダウンロード</option>
            </select>
            <span class="input-group-btn">
                <button type="button" class="btn btn-primary">する</button>
            </span>
        </div>

    </div>
</div>

<div class="row">
</div>

<?php require dirname(__FILE__) . '/../footer.php' ?>
