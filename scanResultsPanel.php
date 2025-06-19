<script>
(function($){
    var ajaxUrl = miaoVars.ajaxUrl;
    var nonce   = miaoVars.nonce;
    var i18n    = {
        preview:             '<?php echo esc_js( esc_html__( 'Preview', 'meta-ai-optimizer' ) ); ?>',
        preview_for_post:    '<?php echo esc_js( esc_html__( 'Preview for Post', 'meta-ai-optimizer' ) ); ?>',
        current_title:       '<?php echo esc_js( esc_html__( 'Current Title:', 'meta-ai-optimizer' ) ); ?>',
        ai_title:            '<?php echo esc_js( esc_html__( 'AI Title:', 'meta-ai-optimizer' ) ); ?>',
        current_desc:        '<?php echo esc_js( esc_html__( 'Current Description:', 'meta-ai-optimizer' ) ); ?>',
        ai_desc:             '<?php echo esc_js( esc_html__( 'AI Description:', 'meta-ai-optimizer' ) ); ?>',
        no_items_selected:   '<?php echo esc_js( esc_html__( 'No items selected.', 'meta-ai-optimizer' ) ); ?>',
        changes_applied:     '<?php echo esc_js( esc_html__( 'Changes applied successfully.', 'meta-ai-optimizer' ) ); ?>'
    };

    $('#miao-scan-button').on('click', function(){
        var data = {
            action:      'miao_scan_posts',
            security:    nonce,
            post_type:   $('#miao_post_type').val(),
            keyword:     $('#miao_keyword').val()
        };
        $('#miao-scan-results').hide();
        $.post(ajaxUrl, data)
        .done(function(response){
            if(response.success){
                var $tbody = $('#miao-results-table tbody').empty();
                $.each(response.data, function(index, item){
                    var $tr = $('<tr>').attr('data-id', item.id);
                    $('<td>').text(item.id).appendTo($tr);
                    $('<td>').text(item.title).appendTo($tr);
                    $('<td>').text(item.ai_title).appendTo($tr);
                    $('<td>').text(item.meta_description).appendTo($tr);
                    $('<td>').text(item.ai_description).appendTo($tr);
                    var $actions = $('<td>');
                    $('<button>')
                        .addClass('button miao-preview')
                        .attr('data-id', item.id)
                        .text(i18n.preview)
                        .appendTo($actions);
                    $('<label>')
                        .append( $('<input>').attr({ type: 'checkbox', class: 'miao-select', value: item.id }) )
                        .appendTo($actions);
                    $actions.appendTo($tr);
                    $tbody.append($tr);
                });
                $('#miao-scan-results').slideDown();
            } else {
                alert(response.data);
            }
        })
        .fail(function(jqXHR, textStatus){
            alert('AJAX Error: ' + textStatus);
        });
    });

    $(document).on('click', '.miao-preview', function(){
        var postId = $(this).data('id');
        var data = {
            action:   'miao_get_preview',
            security: nonce,
            post_id:  postId
        };
        $.post(ajaxUrl, data)
        .done(function(resp){
            if(resp.success){
                var $panel = $('#miao-preview-panel').empty();
                $('<h3>').text(i18n.preview_for_post + ' ' + postId).appendTo($panel);
                $('<div>').addClass('miao-preview-section')
                    .html('<strong>' + i18n.current_title + '</strong> ' + $('<div>').text(resp.data.current.title).html())
                    .appendTo($panel);
                $('<div>').addClass('miao-preview-section')
                    .html('<strong>' + i18n.ai_title + '</strong> ' + $('<div>').text(resp.data.ai.title).html())
                    .appendTo($panel);
                $('<div>').addClass('miao-preview-section')
                    .html('<strong>' + i18n.current_desc + '</strong> ' + $('<div>').text(resp.data.current.description).html())
                    .appendTo($panel);
                $('<div>').addClass('miao-preview-section')
                    .html('<strong>' + i18n.ai_desc + '</strong> ' + $('<div>').text(resp.data.ai.description).html())
                    .appendTo($panel);
            } else {
                alert(resp.data);
            }
        })
        .fail(function(jqXHR, textStatus){
            alert('AJAX Error: ' + textStatus);
        });
    });

    $('#miao-apply-bulk').on('click', function(){
        var ids = $('.miao-select:checked').map(function(){ return $(this).val(); }).get();
        if(ids.length === 0){
            alert(i18n.no_items_selected);
            return;
        }
        var data = {
            action:   'miao_apply_bulk',
            security: nonce,
            ids:      ids
        };
        $.post(ajaxUrl, data)
        .done(function(resp){
            if(resp.success){
                alert(i18n.changes_applied);
            } else {
                alert(resp.data);
            }
        })
        .fail(function(jqXHR, textStatus){
            alert('AJAX Error: ' + textStatus);
        });
    });
})(jQuery);
</script>