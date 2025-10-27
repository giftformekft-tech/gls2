(function($){
    $(function(){
        var $btn = $('#woo-mygls-test-api');
        if (!$btn.length || typeof WOO_MYGLS === 'undefined'){
            return;
        }
        var $result = $('#woo-mygls-test-api-result');
        var texts = (WOO_MYGLS && WOO_MYGLS.i18n) || {};
        $btn.on('click', function(e){
            e.preventDefault();
            $btn.prop('disabled', true);
            if ($result.length){
                $result.removeClass('woo-mygls-result-success woo-mygls-result-warning woo-mygls-result-error');
                $result.text(texts.testing || 'Kapcsolat tesztelése...');
            }
            $.post(WOO_MYGLS.ajax, {
                action: 'woo_mygls_test_connection',
                _wpnonce: WOO_MYGLS.nonce
            }).done(function(resp){
                var message = '';
                var status = 'success';
                if (resp && resp.success){
                    if (resp.data){
                        message = resp.data.message || '';
                        status = resp.data.status || 'success';
                    }
                    if (!message){
                        message = status === 'warning' ? (texts.warning || '') : (texts.success || 'Kapcsolat sikeres.');
                    }
                    if ($result.length){
                        $result.removeClass('woo-mygls-result-success woo-mygls-result-warning woo-mygls-result-error');
                        if (status === 'warning'){
                            $result.addClass('woo-mygls-result-warning');
                        } else {
                            $result.addClass('woo-mygls-result-success');
                        }
                        $result.text(message);
                    }
                } else {
                    if (resp && resp.data && resp.data.message){
                        message = resp.data.message;
                    } else {
                        message = texts.error || 'Sikertelen kapcsolat.';
                    }
                    if ($result.length){
                        $result.removeClass('woo-mygls-result-success woo-mygls-result-warning woo-mygls-result-error');
                        $result.addClass('woo-mygls-result-error');
                        $result.text(message);
                    }
                }
            }).fail(function(){
                if ($result.length){
                    $result.removeClass('woo-mygls-result-success woo-mygls-result-warning woo-mygls-result-error');
                    $result.addClass('woo-mygls-result-error');
                    $result.text(texts.error || 'Sikertelen kapcsolat.');
                }
            }).always(function(){
                $btn.prop('disabled', false);
            });
        });
    });
})(jQuery);
