<?php require dirname(__FILE__) . '/../header.php' ?>

<section class="page-content">
    <h2>アカウント編集</h2>

    <?php if ($message) { ?><p class="error" style="color: #FF0000;"><?php echo $message ?></p><?php } ?>

    <form enctype="multipart/form-data" method="POST">
        <div class="field">
            <p>メールアドレス</p>
            <input type="text" name="email" value="<?php echo $defaults['email'] ?>">
        </div>

        <div class="field">
            <input type="submit" value="登録">
        </div>
    </form>

</section><!-- /.page-content -->

<?php require dirname(__FILE__) . '/../footer.php' ?>
