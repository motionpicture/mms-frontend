<?php require dirname(__FILE__) . '/../header.php' ?>

<script src="/js/media/new.js"></script>

<div class="row">
    <div class="main">
        <h1 class="page-header">メディア登録</h1>

        <?php if ($message) { ?><p class="error" style="color: #FF0000;"><?php echo $message ?></p><?php } ?>

        <form class="" role="search" enctype="multipart/form-data" method="POST">
            <input type="hidden" name="MAX_FILE_SIZE" value="2000000000" />

            <div class="form-group">
                <p>
                    <input type="text" name="mcode" class="form-control" value="<?php echo $defaults['mcode'] ?>" placeholder="作品コード">
                </p>

                <p>
                    <select name="category_id" class="form-control">
                    <option value ="">カテゴリーを選択してください</option>
                    <?php foreach ($categories as $category) { ?>
                    <option value ="<?php echo $category['id'] ?>"<?php if ($defaults['category_id'] == $category['id']) {echo ' selected';} ?>><?php echo $category['name'] ?></option>
                    <?php } ?>
                    </select>
                </p>

                <p>
                    <input type="file" name="file" class="" value="">
                </p>

                <p>
                    <button type="submit" class="btn btn-primary">登録</button>
                <p>
            </div>

        </form>
    </div>
</div>

<?php require dirname(__FILE__) . '/../footer.php' ?>
