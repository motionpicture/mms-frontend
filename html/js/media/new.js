$(function(){
    // 登録ボタン
    $('button[type="submit"]').on('click', function(e){
        console.log(e);
        $('form').before('<p>アップロードしています...</p>');
        $('p.error, form').hide();
        return;
    });
});