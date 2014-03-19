<?php require dirname(__FILE__) . '/../header.php' ?>

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
                <?php foreach ($jobState::getAll() as $state) { ?>
                <label class="checkbox inline">
                  <input type="checkbox" name="job_state[]" value="<?php echo $state ?>"<?php if (in_array($state, $searchConditions['job_state'])) { ?> checked<?php } ?>> <?php echo $jobState::toString($state) ?>
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
                        <th>名前</th>
                        <th>作品コード</th>
                        <th>作品名</th>
                        <th>カテゴリー</th>
                        <th>バージョン</th>
                        <th>サイズ(MB)</th>
                        <th>再生時間</th>
                        <th>ユーザー</th>
                        <th>ジョブ進捗</th>
                        <th>更新日時</th>
                        <th></th>
                    </tr>
                </thead>

                <tbody>
                    <?php foreach ($medias as $media) { ?>
                    <tr>
                        <td><a href="/media/<?php echo $media['code'] ?>"><?php echo $media['id'] ?></a></td>
                        <td><?php echo $media['mcode'] ?></td>
                        <td><?php echo $media['movie_name'] ?></td>
                        <td><?php echo $media['category_name'] ?></td>
                        <td><?php echo $media['versions'] ?></div></td>
                        <td><?php echo floor($media['size'] / 1000000) ?></div></td>
                        <td><?php echo $media['playtime_string'] ?></div></td>
                        <td><?php echo $media['user_id'] ?></div></td>
                        <td><?php echo ($media['job_id'] != '') ? $jobState::toString($media['job_state']) : 'ジョブ未登録' ?></td>
                        <td><?php echo $media['updated_at'] ?></td>
                        <td>
                            <?php if ($media['job_id']) { ?>
                            <button type="button" class="btn btn-default" onclick="location.href='/media/<?php echo $media['id'] ?>/download'">動画ファイルをダウンロード</button>
                            <?php } ?>
                        </td>
                    </tr>
                    <?php } ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require dirname(__FILE__) . '/../footer.php' ?>
