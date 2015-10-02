<?php require dirname(__FILE__) . '/../header.php' ?>

<div class="row">
    <div class="main">
        <h1 class="page-header">アカウント編集</h1>

        <?php if ($message) { ?><p class="error" style="color: #FF0000;"><?php echo $message ?></p><?php } ?>

        <form enctype="multipart/form-data" method="POST">
            <div class="form-group">
                <p>
                    <input type="text" name="email" class="form-control" value="<?php echo $defaults['email'] ?>" placeholder="メールアドレス">
                </p>

                <p>
                    <button type="submit" class="btn btn-primary">登録</button>
                <p>
            </div>

        </form>
    </div>
</div>

<?php require dirname(__FILE__) . '/../footer.php' ?>
