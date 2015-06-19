$(function(){
    $('form').submit(function(){
        $('form').before('<p>アップロードしています...</p><p id="progress">0%</p>');
        $('p.error, form').hide();

        // 進捗表示
        var progress = $('#progress');
        var f = function(){
            $.getJSON('/media/new/progress', function(data){
                console.log(data);
                if (data != null) {
                    var rate = Math.round(100 * (data['bytes_processed'] / data['content_length']));
                    progress.text(rate + "%");

                    if (!data['done']) {
                        setTimeout(f, 200);
                    }
                }
            });
        }

        setTimeout(f, 500);
    });
});
