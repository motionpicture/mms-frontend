<?php require dirname(__FILE__) . '/../header.php' ?>

<section class="page-content">
    <h2>メディア一覧</h2>
    <table border="1">
        <thead>
            <tr>
                <th>名前</th>
                <th>作品コード</th>
                <th>サイズ</th>
                <th>バージョン</th>
                <th>カテゴリー</th>
                <th>ジョブID</th>
                <th>ジョブ進捗</th>
                <th>エンコード完了日時</th>
                <th>更新日時</th>
            </tr>
        </thead>

        <tbody>
            <?php foreach ($medias as $media) { ?>
            <tr>
                <td><a href="/media/<?php echo $media['id'] ?>"><?php echo $media['id'] ?></a></td>
                <td><?php echo $media['mcode'] ?></td>
                <td><?php echo $media['size'] ?></div></td>
                <td><?php echo $media['version'] ?></div></td>
                <td><?php echo $media['category_id'] ?></div></td>
                <td><?php echo ($media['job_id'] != '') ? $media['job_id'] : 'ジョブ未登録' ?></td>
                <td><?php echo ($media['job_state'] != '') ? JobState::GetJobStateString($media['job_state']) : '' ?></td>
                <td><?php echo $media['encoded_at'] ?></td>
                <td><?php echo $media['updated_at'] ?></td>
            </tr>
            <?php } ?>
        </tbody>
    </table><!-- /.movies -->
</section><!-- /.page-content -->

<?php require dirname(__FILE__) . '/../footer.php' ?>
