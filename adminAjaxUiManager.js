(function($){
    function escapeHtml(str){
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function showNotice(type, message){
        var $notice = $('<div class="meta-ai-notice notice notice-' + type + ' is-dismissible"><p></p></div>');
        $notice.find('p').text(message);
        $('#scan-filters-form').before($notice);
    }

    function clearNotices(){
        $('.meta-ai-notice').remove();
    }

    var uiManager = {
        init: function(){
            $('#scan-posts-button').on('click', this.scanPosts.bind(this));
            $('#posts-table').on('click', '.preview-button', this.handlePreviewClick.bind(this));
            $('#posts-table').on('click', '.apply-button', this.handleApplyClick.bind(this));
        },
        scanPosts: function(e){
            e.preventDefault();
            clearNotices();
            var $btn = $(e.currentTarget), filters = {};
            $.each($('#scan-filters-form').serializeArray(), function(){
                filters[this.name] = this.value;
            });
            $btn.prop('disabled', true).text('Scanning...');
            $('#posts-table tbody').empty();
            $.ajax({
                url: metaAi.ajaxUrl,
                method: 'POST',
                dataType: 'json',
                data: {
                    action: metaAi.actions.scan_posts,
                    filters: filters,
                    security: metaAi.nonce
                }
            }).done(function(resp){
                if(resp.success && resp.data.posts.length){
                    uiManager.populateTable(resp.data.posts);
                } else {
                    var msg = (resp.data && resp.data.message) ? resp.data.message : 'No posts found.';
                    showNotice('warning', msg);
                }
            }).fail(function(){
                showNotice('error', 'Error scanning posts.');
            }).always(function(){
                $btn.prop('disabled', false).text('Scan Posts');
            });
        },
        populateTable: function(posts){
            var $tbody = $('#posts-table tbody');
            posts.forEach(function(post){
                var $row = $('<tr>').attr('data-post-id', post.id);
                var $checkboxTd = $('<td>').append(
                    $('<input>').attr({ type: 'checkbox', class: 'post-checkbox' })
                );
                var $titleTd = $('<td>').addClass('post-title').text(post.title);
                var $actionsTd = $('<td>').addClass('actions').append(
                    $('<button>').addClass('button preview-button').text('Preview')
                ).append(' ').append(
                    $('<button>').addClass('button apply-button').prop('disabled', true).text('Apply')
                );
                var $statusTd = $('<td>').addClass('status-cell');
                $row.append($checkboxTd, $titleTd, $actionsTd, $statusTd);

                var $sRow = $('<tr>').addClass('suggestion-row').attr('data-post-id', post.id).hide();
                var $sCell = $('<td>').attr('colspan', 4).addClass('suggestion-cell');
                $sRow.append($sCell);

                $tbody.append($row, $sRow);
            });
        },
        handlePreviewClick: function(e){
            e.preventDefault();
            clearNotices();
            var $row = $(e.currentTarget).closest('tr'),
                postId = $row.data('postId'),
                $previewBtn = $row.find('.preview-button'),
                $sRow = $('#posts-table').find('tr.suggestion-row[data-post-id="'+postId+'"]'),
                $sCell = $sRow.find('.suggestion-cell');
            $previewBtn.prop('disabled', true);
            $sCell.text('Loading...');
            $sRow.show();
            $.ajax({
                url: metaAi.ajaxUrl,
                method: 'POST',
                dataType: 'json',
                data: {
                    action: metaAi.actions.get_suggestion,
                    post_id: postId,
                    security: metaAi.nonce
                }
            }).done(function(resp){
                if(resp.success && resp.data){
                    var d = resp.data;
                    var origTitle = escapeHtml(d.original.title);
                    var origDesc = escapeHtml(d.original.meta_desc);
                    var sugTitle = escapeHtml(d.suggestion.title);
                    var sugDesc = escapeHtml(d.suggestion.meta_desc);
                    var html = '<div class="suggestion-sidebyside">'
                             + '<div class="original-block"><h4>Original Title</h4><p>' + origTitle + '</p><h4>Original Meta Description</h4><p>' + origDesc + '</p></div>'
                             + '<div class="suggested-block"><h4>Suggested Title</h4><p>' + sugTitle + '</p><h4>Suggested Meta Description</h4><p>' + sugDesc + '</p></div>'
                             + '</div>';
                    $sCell.html(html);
                    $row.find('.apply-button').prop('disabled', false);
                } else {
                    $sCell.text('No suggestion available.');
                }
            }).fail(function(){
                $sCell.text('Error loading suggestion.');
            });
        },
        handleApplyClick: function(e){
            e.preventDefault();
            clearNotices();
            var $row = $(e.currentTarget).closest('tr'),
                postId = $row.data('postId'),
                $applyBtn = $row.find('.apply-button'),
                $status = $row.find('.status-cell'),
                $previewBtn = $row.find('.preview-button'),
                $checkbox = $row.find('.post-checkbox');
            $applyBtn.prop('disabled', true);
            $previewBtn.prop('disabled', true);
            $checkbox.prop('disabled', true);
            $status.text('Applying...');
            $.ajax({
                url: metaAi.ajaxUrl,
                method: 'POST',
                dataType: 'json',
                data: {
                    action: metaAi.actions.apply_suggestion,
                    post_id: postId,
                    security: metaAi.nonce
                }
            }).done(function(resp){
                if(resp.success){
                    $status.text('Applied');
                    $('#posts-table').find('tr.suggestion-row[data-post-id="'+postId+'"]').hide();
                } else {
                    var msg = (resp.data && resp.data.message) ? resp.data.message : 'Failed to apply suggestion.';
                    showNotice('error', msg);
                    $status.text('Error');
                    $previewBtn.prop('disabled', false);
                    $checkbox.prop('disabled', false);
                }
            }).fail(function(){
                showNotice('error', 'Error applying suggestion.');
                $status.text('Error');
                $previewBtn.prop('disabled', false);
                $checkbox.prop('disabled', false);
            });
        }
    };

    $(document).ready(uiManager.init);
})(jQuery);