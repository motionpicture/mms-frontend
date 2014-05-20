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

    $('.update-media').on('click', function(e){
        var ladda = Ladda.create(this);
        ladda.start();

        var thisBtn = this;
        var rootRow = $(thisBtn).parent().parent();
        var mediaId = $('span.media-id', rootRow).text();
        var startAt = $('input[name="start_at"]', rootRow).val();
        var endAt = $('input[name="end_at"]', rootRow).val();
        var data = {
            start_at: startAt,
            end_at: endAt
        };
        console.log('mediaId: ' + mediaId);
        console.log(data);

        $.ajax({
            type: 'post',
            url: '/media/' + mediaId + '/update',
            data: data,
            dataType: 'json',
            complete: function(response) {
                ladda.stop();
            },
            success: function(response) {
                console.log(JSON.stringify(response));

                if (response.success) {
                    console.log('update media success');
                    alertTop(mediaId + 'を更新しました');
                } else {
                    console.log('update media fail');
                    alertTop(mediaId + 'の更新に失敗しました', 'danger');
                }

            },
            error: function(XMLHttpRequest, textStatus, errorThrown) {
                console.log("XMLHttpRequest : " + XMLHttpRequest.status);
                console.log("textStatus : " + textStatus);
                console.log("errorThrown : " + errorThrown.message);
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
});