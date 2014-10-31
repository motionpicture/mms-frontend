<!DOCTYPE html>
<html lang="ja">
<head>
<meta charset="utf-8">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>メディア管理</title>

<!-- Bootstrap -->
<link href="/css/bootstrap.min.css" rel="stylesheet">
<link href="/css/bootstrap-datetimepicker.css" rel="stylesheet">
<link href="/css/ladda-themeless.min.css" rel="stylesheet">
<link href="/css/dashboard.css" rel="stylesheet">
<link href="/css/mms.css" rel="stylesheet">

<!-- HTML5 Shim and Respond.js IE8 support of HTML5 elements and media queries -->
<!-- WARNING: Respond.js doesn't work if you view the page via file:// -->
<!--[if lt IE 9]>
<script src="https://oss.maxcdn.com/libs/html5shiv/3.7.0/html5shiv.js"></script>
<script src="https://oss.maxcdn.com/libs/respond.js/1.4.2/respond.min.js"></script>
<![endif]-->

<!-- jQuery (necessary for Bootstrap's JavaScript plugins) -->
<script src="/js/jquery.js"></script>
<!-- http://www.steamdev.com/zclip/ -->
<script src="/js/jquery.zclip.min.js"></script>

<script src="/js/moment.js"></script>
<script src="/js/bootstrap.js"></script>

<!-- http://eonasdan.github.io/bootstrap-datetimepicker/ -->
<script src="/js/bootstrap-datetimepicker.js"></script>
<script src="/js/locales/bootstrap-datetimepicker.ja.js"></script>

<script src="/js/spin.min.js"></script>
<script src="/js/ladda.min.js"></script>

<script src="/js/common.js"></script>
<?php if ($flash['info']) { ?>
<script>
// 画面上部メッセージの表示
$(function() {
    alertTop('<?php echo $flash['info'] ?>', 'info');
});
</script>
<?php } ?>
</head>

<body>
    <div class="navbar navbar-inverse navbar-fixed-top" role="navigation">
        <div class="container-fluid">
            <div class="navbar-header">
                  <button type="button" class="navbar-toggle" data-toggle="collapse" data-target=".navbar-collapse">
                        <span class="sr-only">Toggle navigation</span>
                        <span class="icon-bar"></span>
                        <span class="icon-bar"></span>
                        <span class="icon-bar"></span>
                  </button>
                  <a class="navbar-brand" href="/">メディア管理</a>
            </div>
            <div class="navbar-collapse collapse">
                  <ul class="nav navbar-nav navbar-right">
                        <li><a href="/media/new">メディア登録</a></li>
                        <li><a href="/medias">メディア一覧</a></li>
                        <li><a href="/user/edit">アカウント編集</a></li>
                  </ul>
                  こんにちは <?php echo $_SERVER['PHP_AUTH_USER'] ?>さん
<!--           <form class="navbar-form navbar-right"> -->
<!--             <input type="text" class="form-control" placeholder="Search..."> -->
<!--           </form> -->
            </div>
        </div>
    </div>

    <div class="alert-top" style="display: none;">
        <div class="col-xs-12 col-sm-4 col-lg-4"></div>
        <div class="col-xs-12 col-sm-4 col-lg-4">
            <div class="alert alert-dismissable">
                <button type="button" class="close">&times;</button>
                <span class="message"></span>
            </div>
        </div>
        <div class="col-xs-12 col-sm-4 col-lg-4"></div>
    </div>

    <div class="container-fluid">
