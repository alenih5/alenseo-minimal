(function($){
    var postId = $('#seo-ai-post-id').val();
    
    function handleAjax(action, data, callback) {
        data.action = action;
        data.nonce  = seoAiMeta.nonce;
        $.post(seoAiMeta.ajaxUrl, data, function(resp) {
            if (resp.success) {
                callback(resp.data);
            } else {
                alert(seoAiMeta.i18n.error + ': ' + resp.data);
            }
        });
    }

    $('#seo-ai-analyze-btn').on('click', function(e){
        e.preventDefault();
        var btn = $(this).prop('disabled', true).text(seoAiMeta.i18n.loading);
        handleAjax('seo_ai_meta_analyze', { post_id: postId }, function(data){
            $('#seo-ai-analyze-result').html('<pre>'+ JSON.stringify(data, null, 2) +'</pre>');
            btn.prop('disabled', false).text(seoAiMeta.i18n.analyze);
        });
    });

    $('#seo-ai-title-btn').on('click', function(e){
        e.preventDefault();
        var btn = $(this).prop('disabled', true).text(seoAiMeta.i18n.loading);
        handleAjax('seo_ai_meta_title', { post_id: postId }, function(data){
            $('#seo-ai-meta-title').val(data.title);
            btn.prop('disabled', false).text(seoAiMeta.i18n.generate);
        });
    });

    $('#seo-ai-desc-btn').on('click', function(e){
        e.preventDefault();
        var btn = $(this).prop('disabled', true).text(seoAiMeta.i18n.loading);
        handleAjax('seo_ai_meta_desc', { post_id: postId }, function(data){
            $('#seo-ai-meta-desc').val(data.description);
            btn.prop('disabled', false).text(seoAiMeta.i18n.generate);
        });
    });

    $('#seo-ai-keywords-btn').on('click', function(e){
        e.preventDefault();
        var btn = $(this).prop('disabled', true).text(seoAiMeta.i18n.loading);
        handleAjax('seo_ai_meta_keywords', { post_id: postId }, function(data){
            $('#seo-ai-meta-keywords').val(data.keywords.join(', '));
            btn.prop('disabled', false).text(seoAiMeta.i18n.keywords);
        });
    });

    $('#seo-ai-optimize-btn').on('click', function(e){
        e.preventDefault();
        var btn = $(this).prop('disabled', true).text(seoAiMeta.i18n.loading);
        handleAjax('seo_ai_meta_optimize', { post_id: postId }, function(data){
            // Insert optimized content into editor
            if (tinymce.get('content')) {
                tinymce.get('content').setContent(data.content);
            } else {
                $('#content').val(data.content);
            }
            btn.prop('disabled', false).text(seoAiMeta.i18n.optimize);
        });
    });

})(jQuery); 