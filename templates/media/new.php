<?php require dirname(__FILE__) . '/../header.php' ?>

<section class="page-content">
    <h2>メディア登録</h2>

    <?php if ($message) { ?><p class="error" style="color: #FF0000;"><?php echo $message ?></p><?php } ?>

    <form enctype="multipart/form-data" method="POST">
        <input type="hidden" name="MAX_FILE_SIZE" value="1000000000" />

        <div class="field">
            <p>作品コード:</p>
            <input type="text" name="mcode" value="<?php echo $defaults['mcode'] ?>">
        </div>

        <div class="field">
            <p>カテゴリー:</p>
            <select name="category_id">
            <option value ="">カテゴリーを選択してください</option>
            <?php foreach ($categories as $id => $name) { ?>
            <option value ="<?php echo $id ?>"<?php if ($defaults['category_id'] == $id) {echo ' selected';} ?>><?php echo $name ?></option>
            <?php } ?>
            </select>
        </div>

        <div class="field">
            <p>ファイル:</p>
            <input type="file" name="file" value="">
        </div>

        <div class="field">
            <input type="submit" value="登録">
        </div>
    </form>

</section><!-- /.page-content -->

<?php require dirname(__FILE__) . '/../footer.php' ?>
