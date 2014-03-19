$(function() {
    $('input[name="start_at"], input[name="end_at"]').datetimepicker({
        format: 'YYYY-MM-DD hh:mm:00',
        pickDate: true,
        pickTime: true,
        useMinutes: true,
        useSeconds: false,
        useCurrent: true,
        minuteStepping: 1,
//        minDate: 1/1/1900,
//        maxDate: (today +100 years),
        showToday: true,
        language:'ja', 
    });

    $('.update-media').on('click', function(e){
        var ladda = Ladda.create(this);
        ladda.start();

        var thisBtn = this;
        var rootRow = $(thisBtn).parent().next('.row');
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
                    alertTop('メディアを更新しました');
                } else {
                    console.log('update media fail');
                    alertTop('メディアの更新に失敗しました');
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

    $('.delete-media').on('click', function(e){
        if (!window.confirm('本当に削除しますか？')) {
            return false;
        }

        var ladda = Ladda.create(this);
        ladda.start();

        var thisBtn = this;
        var mediaId = $('span.media-id', $(thisBtn).parent().next('.row')).text();
        console.log('mediaId: ' + mediaId);

        $.ajax({
            type: 'post',
            url: '/media/' + mediaId + '/delete',
            data: {},
            dataType: 'json',
            complete: function(response) {
                ladda.stop();
            },
            success: function(response) {
                console.log(JSON.stringify(response));

                if (response.success) {
                    console.log('delete media success');

                    // メディア要素を削除
                    $(thisBtn).parent().prev('h3').remove();
                    $(thisBtn).parent().next('.row').remove();
                    $(thisBtn).parent().remove();

                    alertTop('メディアを削除しました');
                } else {
                    console.log('delete media fail');
                    alertTop('メディアの削除に失敗しました');
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
});