(function($){
    function initModal(){
        var $m = $('#gls-map-modal');
        if ($m.data('ready')) return;
        $m.data('ready', true).css({position:'fixed', left:'5%', top:'5%', right:'5%', bottom:'5%', background:'#fff', zIndex:99999, borderRadius:'12px', padding:'12px', boxShadow:'0 10px 40px rgba(0,0,0,.25)'});
        $m.append('<button id="gls-close" class="button">Bezár</button>');
        $('#gls-close').on('click', function(){ $m.hide(); });
        $('#gls-map').css({height:'70%', border:'1px solid #e5e7eb', borderRadius:'8px', marginBottom:'8px'});
        $('#gls-list').css({height:'25%', overflow:'auto', border:'1px solid #e5e7eb', borderRadius:'8px', padding:'8px', fontSize:'13px'});
        // Load points
        $.getJSON(WOO_MYGLS.pointsUrl, function(data){
            var map = L.map('gls-map').setView([47.1625,19.5033], 7);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {maxZoom: 19}).addTo(map);
            var group = L.layerGroup().addTo(map);
            function add(point){
                var lat = point.gpsLat || point.location?.lat;
                var lon = point.gpsLong || point.location?.lng;
                if (!lat || !lon) return;
                var m = L.marker([lat,lon]).addTo(group);
                m.bindPopup('<strong>'+point.name+'</strong><br>'+ (point.address||'') +'<br><button class="select-psd" data-id="'+(point.parcelShopId||point.parcelLockerId||point.id)+'" data-label="'+(point.name+' | '+(point.address||''))+'">Ezt választom</button>');
                m.on('click', function(){ m.openPopup(); });
            }
            if (data && data.length){
                data.forEach(add);
            } else if (data && data.deliveryPoints){
                data.deliveryPoints.forEach(add);
            }
            // List
            var html = '<input type="text" id="gls-q" placeholder="Keresés város / név szerint..." style="width:100%;margin-bottom:6px">';
            html += '<div id="gls-ul"></div>';
            $('#gls-list').html(html);
            function renderList(q){
                q = (q||'').toLowerCase();
                var items = (data.deliveryPoints || data).filter(function(p){
                    var s = ((p.name||'')+' '+(p.address||'')+' '+(p.city||'')).toLowerCase();
                    return s.indexOf(q)>=0;
                }).slice(0,200);
                var out = items.map(function(p){
                    var id = p.parcelShopId||p.parcelLockerId||p.id;
                    var label = p.name+' | '+(p.address||p.city||'');
                    return '<div class="psd-row"><strong>'+p.name+'</strong> — '+(p.address||'')+' <button class="button button-small select-psd" data-id="'+id+'" data-label="'+label+'">Választ</button></div>';
                }).join('');
                $('#gls-ul').html(out || '<em>Nincs találat</em>');
            }
            renderList('');
            $('#gls-q').on('input', function(){ renderList(this.value); });
            $(document).on('click','.select-psd', function(){
                var id = $(this).data('id');
                var label = $(this).data('label');
                $('#gls_psd_id').val(id);
                $('input[name="gls_psd"]').val(label);
                $('#gls-map-modal').hide();
            });
        });
    }

    $(document).on('click','#gls-open-map', function(e){
        e.preventDefault();
        initModal();
        $('#gls-map-modal').show();
    });

    function getSelectedShippingMethods(){
        var out = [];
        $('[name^="shipping_method"]').each(function(){
            var $el = $(this);
            var tag = ($el.prop('tagName') || '').toLowerCase();
            if ($el.is(':radio') || $el.is(':checkbox')){
                if ($el.is(':checked')){
                    out.push(String($el.val() || ''));
                }
                return;
            }
            if (tag === 'select'){
                var val = $el.val();
                if (Array.isArray(val)){
                    val.forEach(function(v){ out.push(String(v || '')); });
                } else if (val){
                    out.push(String(val));
                }
                return;
            }
            var value = $el.val();
            if (value){
                out.push(String(value));
            }
        });
        return out;
    }

    function getSelectedShippingLabels(){
        var labels = [];
        $('[name^="shipping_method"]').each(function(){
            var $el = $(this);
            if (($el.is(':radio') || $el.is(':checkbox')) && !$el.is(':checked')){
                return;
            }
            var label = $el.closest('li').find('label').first().text();
            if (label){
                labels.push(label);
            }
        });
        return labels;
    }

    function methodRequiresPsd(){
        var selected = getSelectedShippingMethods();
        var configured = Array.isArray(WOO_MYGLS.shippingMethods) ? WOO_MYGLS.shippingMethods : [];
        var need = false;
        if (selected.length){
            if (configured.length){
                need = selected.some(function(v){ return configured.indexOf(v) !== -1; });
            }
            if (!need){
                need = selected.some(function(v){
                    var val = String(v || '').toLowerCase();
                    return val.indexOf('csomagpont') >= 0 || val.indexOf('automata') >= 0 || val.indexOf('parcel') >= 0;
                });
            }
        }
        if (!need){
            var labels = getSelectedShippingLabels();
            need = labels.some(function(text){
                var lower = text.toLowerCase();
                return lower.indexOf('csomagpont') >= 0 || lower.indexOf('automata') >= 0 || lower.indexOf('parcel') >= 0;
            });
        }
        return need;
    }

    function updatePsdVisibility(){
        var $field = $('#gls-psd-field');
        if (!$field.length) return;
        var need = methodRequiresPsd();
        if (need){
            $field.stop(true, true).slideDown(150);
            if (!$('#gls_psd_id').val() && !updatePsdVisibility._autoOpened){
                updatePsdVisibility._autoOpened = true;
                $('#gls-open-map').trigger('click');
            }
        } else {
            $field.stop(true, true).slideUp(150);
            updatePsdVisibility._autoOpened = false;
            if (!$field.is(':visible')){
                $('#gls_psd_id').val('');
                $('input[name="gls_psd"]').val('');
            }
        }
    }
    updatePsdVisibility._autoOpened = false;

    $(document.body).on('updated_checkout updated_shipping_method', function(){
        setTimeout(updatePsdVisibility, 75);
    });
    $(document).on('change', '[name^="shipping_method"]', function(){
        setTimeout(updatePsdVisibility, 50);
    });
    $(function(){
        setTimeout(updatePsdVisibility, 100);
        $('form.checkout').on('checkout_place_order', function(){
            try {
                var need = methodRequiresPsd();
                if (need && !$('#gls_psd_id').val()){
                    alert('Kérjük, válassz GLS Csomagpontot a térképen!');
                    return false;
                }
            } catch(e){}
            return true;
        });
    });
})(jQuery);
