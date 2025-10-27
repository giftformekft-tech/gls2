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
})(jQuery);
// Client-side guard: require PSD if GLS Csomagpont/Automata selected
jQuery(function($){
    $('form.checkout').on('checkout_place_order', function(){
        try {
            var need = false;
            // Read selected shipping labels/ids
            $('[name^="shipping_method"]').each(function(){
                var v = ($(this).val()||'').toLowerCase();
                if (v.indexOf('csomagpont')>=0 || v.indexOf('automata')>=0 || v.indexOf('parcel')>=0) {
                    need = true;
                }
            });
            if (need && !$('#gls_psd_id').val()){
                alert('Kérjük, válassz GLS Csomagpontot a térképen!');
                return false;
            }
        } catch(e){}
        return true;
    });
});
