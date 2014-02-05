<?php require dirname(__FILE__) . '/../header.php' ?>

<style type="text/css">
table td {
    text-align: right;
}
</style>

<section class="page-content">
    <h2>メディア一覧</h2>

    <form id="form">
        <p>
            <?php foreach ($jobState::getAll() as $state) { ?>
            <input type="checkbox" name="job_state[]" value="<?php echo $state ?>"<?php if (in_array($state, $searchConditions['job_state'])) { ?> checked<?php } ?>>
            <label><?php echo $jobState::toString($state) ?></label>
            <?php } ?>
        </p>
        <p>
            <input type="text" name="word" value="<?php echo $searchConditions['word'] ?>" placeholder="キーワード">
        </p>
        <p>
            <input type="submit" value="検索">
        </p>
    </form>

    <table border="1">
        <thead>
            <tr>
                <th>名前</th>
                <th>作品コード</th>
                <th>カテゴリー</th>
                <th>バージョン</th>
                <th>サイズ(MB)</th>
                <th>ユーザー</th>
                <th>ジョブID</th>
                <th>ジョブ進捗</th>
                <th>更新日時</th>
                <th></th>
            </tr>
        </thead>

        <tbody>
            <?php foreach ($medias as $media) { ?>
            <tr>
                <td><a href="/media/<?php echo $media['id'] ?>"><?php echo $media['id'] ?></a></td>
                <td><?php echo $media['mcode'] ?></td>
                <td><?php echo $media['category_name'] ?></div></td>
                <td><?php echo $media['version'] ?></div></td>
                <td><?php echo floor($media['size'] / 1000000) ?></div></td>
                <td><?php echo $media['user_id'] ?></div></td>
                <td><?php echo ($media['job_id'] != '') ? $media['job_id'] : 'ジョブ未登録' ?></td>
                <td><?php echo $jobState::toString($media['job_state']) ?></td>
                <td><?php echo $media['updated_at'] ?></td>
                <td>
                    <?php if ($media['job_id']) { ?>
                    <input type="button" value="動画ファイルをダウンロード" onClick="location.href='/media/<?php echo $media['id'] ?>/download'">
                    <?php } ?>
                </td>
            </tr>
            <?php } ?>
        </tbody>
    </table><!-- /.movies -->
</section><!-- /.page-content -->

<?php require dirname(__FILE__) . '/../footer.php' ?>
