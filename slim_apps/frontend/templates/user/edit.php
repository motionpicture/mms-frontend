<?php require dirname(__FILE__) . '/../header.php' ?>

<div class="container-fluid">
    <div class="row">
        <div class="main">
            <h1 class="page-header">アカウント編集</h1>

            <?php if ($message) { ?><p class="error" style="color: #FF0000;"><?php echo $message ?></p><?php } ?>

            <form class="navbar-form" role="search" enctype="multipart/form-data" method="POST">
                <input type="hidden" name="MAX_FILE_SIZE" value="1000000000" />

                <div class="form-group">
                    <p>
                        <input type="text" name="email" class="form-control" value="<?php echo $defaults['email'] ?>" placeholder="メールアドレス">
                    </p>

                    <p>
                        <button type="submit" class="btn btn-default">登録</button>
                    <p>
                </div>

            </form>
        </div>
    </div>
</div>

<?php require dirname(__FILE__) . '/../footer.php' ?>
