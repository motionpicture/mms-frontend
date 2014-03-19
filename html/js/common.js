if (!('console' in window)) {
    window.console = {};
    window.console.log = function(str){return str};
}

$(function() {
    // ページトップのアラート
    var timerAlertTop;
    window.alertTop = function(message){
        clearInterval(timerAlertTop);
        $('.alert-top').hide()
        $('.alert-top .alert .message').text(message);
        $('.alert-top').show();
        timerAlertTop = setTimeout(function(){
            $('.alert-top').hide()
        }, 5000);
        return;
    };

    $('.alert-top .close').on('click', function(e){
        $('.alert-top').hide();
        $('.alert-top .alert .messge').text('');
    });
});
