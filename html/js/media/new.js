$(function(){
    $('form').submit(function(){
        $('form').before('<p>アップロードしています...</p><p id="progress">0%</p>');
        $('p.error, form').hide();

        // 進捗表示
        var progress = $('#progress');
        var name = $('#session_upload_progress_name').val();
        var f = function(){
            $.getJSON('/media/new/progress/' + name, function(data){
                console.log(data);
                if (data != null) {
                    var rate = Math.round(100 * (data['bytes_processed'] / data['content_length']));
                    progress.text(rate + "%");

                    if (!data['done']) {
                        setTimeout(f, 500);
                    }
                }
            });
        }

        setTimeout(f, 500);
    });
});
