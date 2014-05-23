$(function(){
    // デフォルトの日時は、本日の00:00:00
    var today = new Date(); 
    var year = today.getFullYear();
    var month = today.getMonth() + 1;
    var day = today.getDate();
    if (month) {
        month = '0' + month;
    }
    if (day < 10) {
        day = '0' + day;
    }
    var defaultDateTime = year + '-' + month + '-' + day + ' 00:00:00';

    $('input[name="start_at"], input[name="end_at"]').datetimepicker({
        format: 'YYYY-MM-DD HH:mm:00',
        pickDate: true,
        pickTime: true,
        useMinutes: true,
        useSeconds: false,
        useCurrent: false, // デフォルトを現在日時にしない
        minuteStepping: 10,
//        minDate: 1/1/1900,
//        maxDate: (today +100 years),
        showToday: true,
        language:'ja',
        defaultDate: ''
    }).on('dp.show', function(e){
        // 値なしの場合、デフォルト値を指定
        if ($(this).val() == '') {
            $(this).data('DateTimePicker').setValue(defaultDateTime);
        }
    });

    // テキストボックス監視
    $('.table-responsive input[name="movie_name"]').on('change', function(e){
        var newValue = $(this).val();
        var defaultValue = $('span.movie-name', $(this).parent().parent()).text();
        console.log(newValue);
        console.log(defaultValue);
        if (newValue != defaultValue) {
            $(this).addClass('editing');
        } else {
            $(this).parent().removeClass('has-error');
            $(this).removeClass('editing');
        }

        return false;
    });

    // テキストボックス監視
    $('.table-responsive input[name="start_at"]').on('change', function(e){
        var newValue = $(this).val();
        var defaultValue = $('span.start-at', $(this).parent().parent()).text();
        console.log(newValue);
        console.log(defaultValue);
        if (newValue != defaultValue) {
            $(this).addClass('editing');
        } else {
            $(this).removeClass('editing');
        }

        return false;
    });

    // テキストボックス監視
    $('.table-responsive input[name="end_at"]').on('change', function(e){
        var newValue = $(this).val();
        var defaultValue = $('span.end-at', $(this).parent().parent()).text();
        console.log(newValue);
        console.log(defaultValue);
        if (newValue != defaultValue) {
            $(this).addClass('editing');
        } else {
            $(this).removeClass('editing');
        }

        return false;
    });

    // ページ離脱
    $(window).on('beforeunload', function(){
        // 編集中項目があれば警告
        var countEditing = $('.table-responsive input[type="text"].editing').length;
        if (countEditing > 0) {
            $('.table-responsive input[type="text"].editing').each(function(index){
                $(this).parent().addClass('has-error');
            });

            return '保存されていない編集中の項目があります';
        }
    });

    // メディアコードごとに更新ボタン
    $('.update-media-by-code').on('click', function(e){
        var ladda = Ladda.create(this);
        ladda.start();

        var thisBtn = this;
        var rootRow = $(thisBtn).parent().parent();
        var mediaCode = $('span.media-code', rootRow).text();
        var movieName = $('input[name="movie_name"]', rootRow).val();
        var startAt = $('input[name="start_at"]', rootRow).val();
        var endAt = $('input[name="end_at"]', rootRow).val();
        var data = {
            movie_name: movieName,
            start_at: startAt,
            end_at: endAt
        };
        console.log('mediaCode: ' + mediaCode);
        console.log(data);

        $.ajax({
            type: 'post',
            url: '/media/' + mediaCode + '/update_by_code',
            data: data,
            dataType: 'json',
            complete: function(response) {
                ladda.stop();
            },
            success: function(response) {
                console.log(JSON.stringify(response));

                if (response.success) {
                    console.log('update media by code success');
                    alertTop('メディアを更新しました');

                    // デフォルト値に反映
                    $('span.movie-name', rootRow).text(movieName);
                    $('span.start-at', rootRow).text(startAt);
                    $('span.end-at', rootRow).text(endAt);

                    // 編集中解除
                    $('input[type="text"]', rootRow).parent().removeClass('has-error');
                    $('input[type="text"]', rootRow).removeClass('editing');
                } else {
                    console.log('update media by code fail');
                    alertTop('メディアの更新に失敗しました', 'danger');
                }

            },
            error: function(XMLHttpRequest, textStatus, errorThrown) {
                console.log("XMLHttpRequest : " + XMLHttpRequest.status);
                console.log("textStatus : " + textStatus);
                console.log("errorThrown : " + errorThrown.message);
                alertTop('メディアの更新に失敗しました', 'danger');
            }
        });

        return false;
    });

    $('.reencode-media').on('click', function(e){
        var ladda = Ladda.create(this);
        ladda.start();

        var thisBtn = this;
        var rootRow = $(thisBtn).parent().parent();
        var mediaId = $('span.media-id', rootRow).text();
        var data = {
        };
        console.log('mediaId: ' + mediaId);
        console.log(data);

        $.ajax({
            type: 'post',
            url: '/media/' + mediaId + '/reencode',
            data: data,
            dataType: 'json',
            complete: function(response) {
                ladda.stop();
            },
            success: function(response) {
                console.log(JSON.stringify(response));

                if (response.success) {
                    console.log('reencode media success');
                    alertTop(mediaId + 'の再エンコードを開始しました');
                } else {
                    console.log('reencode media fail');
                    alertTop(mediaId + 'の再エンコードに失敗しました', 'danger');
                }

            },
            error: function(XMLHttpRequest, textStatus, errorThrown) {
                console.log("XMLHttpRequest : " + XMLHttpRequest.status);
                console.log("textStatus : " + textStatus);
                console.log("errorThrown : " + errorThrown.message);
                alertTop(mediaId + 'の再エンコードに失敗しました', 'danger');
            }
        });

        return false;
    });

    // ダウンロードボタン
    $('.download-media').on('click', function(e){
        var thisBtn = this;
        var rootRow = $(thisBtn).parent().parent();
        var mediaId = $('span.media-id', rootRow).text();
        console.log('mediaId: ' + mediaId);
        var url = "http://" + location.host + "/media/" + mediaId + "/download";
        console.log('url: ' + url);
        var html = "<html><head><meta http-equiv=\"refresh\" content=\"0; url=" + url + "\"></head><body></body></html>"
        var downloadWindow = window.open('');
        downloadWindow.document.write(html);
        downloadWindow.document.close();

        return false;
    });

    // ページャー無効リンク
    $('.pager .disabled a').click(function(){
        console.log($(this));
        return false;
    })

    // 全てのメディア選択orリセット
    $('.select-all-medias').on('change', function(e){
        console.log($(this).prop('checked'));
        $('.table-responsive input[name="media_id"]').prop('checked', $(this).prop('checked'));
    })

    // バッチアクション
    $('.batch button').on('click', function(e){
        var action = $('.batch select[name="action"]').val();
        console.log('action: ' + action);

        var mediaIds=[];
        $('.table-responsive input[name="media_id"]:checked').each(function(){
            mediaIds.push($(this).val());
        });
        console.log('mediaIds: ' + mediaIds);

        if (mediaIds.length < 1) {
            alert('メディアを選択してください');
            return false;
        }

        if (action == 'download') {
            var url = "http://" + location.host + "/medias/download?ids=" + mediaIds;
            console.log('url: ' + url);
            var html = "<html><head><meta http-equiv=\"refresh\" content=\"0; url=" + url + "\"></head><body></body></html>"
            var downloadWindow = window.open('');
            downloadWindow.document.write(html);
            downloadWindow.document.close();
        } else if (action == 'update-by-code') {
            var ladda = Ladda.create(this);
            ladda.start();

            var medias = [];
            $('.table-responsive input[name="media_id"]:checked').each(function(){
            var rootRow = $(this).parent().parent().parent();
                var media = {
                    code:       $('span.media-code', rootRow).text(),
                    movie_name: $('input[name="movie_name"]', rootRow).val(),
                    start_at:   $('input[name="start_at"]', rootRow).val(),
                    end_at:     $('input[name="end_at"]', rootRow).val()
                };

                medias.push(media);
            });

            console.log(medias);
            var data = {medias: medias}
            $.ajax({
                type: 'post',
                url: '/medias/update_by_code',
                data: data,
                dataType: 'json',
                complete: function(response) {
                    ladda.stop();
                },
                success: function(response) {
                    console.log(JSON.stringify(response));

                    if (response.success) {
                        console.log('update medias by code success');
                        alertTop('メディアを更新しました');

                        // TODO デフォルト値に反映
                        $('.table-responsive input[name="media_id"]:checked').each(function(){
                            var rootRow = $(this).parent().parent().parent();
                            $('span.movie-name', rootRow).text($('input[name="movie_name"]', rootRow).val());
                            $('span.start-at', rootRow).text($('input[name="start_at"]', rootRow).val());
                            $('span.end-at', rootRow).text($('input[name="end_at"]', rootRow).val());
                        });

                        // 編集中解除
                        $('.table-responsive input[type="text"]').parent().removeClass('has-error');
                        $('.table-responsive input[type="text"]').removeClass('editing');
                    } else {
                        console.log('update medias by code fail');
                        alertTop('メディアの更新に失敗しました', 'danger');
                    }

                },
                error: function(XMLHttpRequest, textStatus, errorThrown) {
                    console.log("XMLHttpRequest : " + XMLHttpRequest.status);
                    console.log("textStatus : " + textStatus);
                    console.log("errorThrown : " + errorThrown.message);
                    alertTop('メディアの更新に失敗しました', 'danger');
                }
            });
        } else {
            alert('アクションを選択してください');
        }

        return false;
    });

    // ページャー無効リンク
    $('.pager .disabled a').click(function(){
        console.log($(this));
        return false;
    })
});