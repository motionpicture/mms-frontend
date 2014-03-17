$(function() {
    $('.update-media').click(function(e){
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
                    console.log('delete media success');
                } else {
                    console.log('delete media fail');
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
                } else {
                    console.log('delete media fail');
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