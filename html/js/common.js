if (!('console' in window)) {
    window.console = {};
    window.console.log = function(str){return str};
}

// ページトップのアラート
var timerAlertTop;
window.alertTop = function(message, type){
    if (type === undefined) {
      type = 'info';
    }

    clearInterval(timerAlertTop);
    $('.alert-top').hide()
    $('.alert-top .alert').removeClass('alert-info');
    $('.alert-top .alert').removeClass('alert-danger');

    if (type == 'info') {
      $('.alert-top .alert').addClass('alert-info');
    } else if (type == 'danger') {
      $('.alert-top .alert').addClass('alert-danger');
    }

    $('.alert-top .alert .message').text(message);
    $('.alert-top').show();
    timerAlertTop = setTimeout(function(){
        $('.alert-top').hide()
    }, 5000);

    return;
};

$(document).on('click', '.alert-top .close', function(e){
    $('.alert-top').hide();
    $('.alert-top .alert .messge').text('');
});
