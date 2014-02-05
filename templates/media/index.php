<?php require dirname(__FILE__) . '/../header.php' ?>

<section class="page-content">
    <h2>メディア一覧</h2>
    <table border="1">
        <thead>
            <tr>
                <th>名前</th>
                <th>作品コード</th>
                <th>カテゴリー</th>
                <th>バージョン</th>
                <th>サイズ</th>
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
                <td><?php echo $media['size'] ?></div></td>
                <td><?php echo $media['user_id'] ?></div></td>
                <td><?php echo ($media['job_id'] != '') ? $media['job_id'] : 'ジョブ未登録' ?></td>
                <td><?php echo $media['job_state'] ?></td>
                <td><?php echo $media['updated_at'] ?></td>
                <td>
                    <?php if ($media['job_id']) { ?>
                    <input type="button" value="Down load" onClick="location.href='/media/<?php echo $media['id'] ?>/download'">
                    <?php } ?>
                </td>
            </tr>
            <?php } ?>
        </tbody>
    </table><!-- /.movies -->
</section><!-- /.page-content -->

<?php require dirname(__FILE__) . '/../footer.php' ?>
