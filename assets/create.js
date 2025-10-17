(function($){
  function pick(cb,type){ const frame=wp.media({title:'Select File',multiple:false,library:type?{type}:undefined}); frame.on('select',function(){ const a=frame.state().get('selection').first().toJSON(); cb(a); }); frame.open(); }
  $('#bb_pick_image').on('click',function(e){ e.preventDefault(); pick(function(a){ $('#bb_image_id').val(a.id); $('#bb_image_preview').html(a.url?'<img src="'+a.url+'" style="max-height:40px;vertical-align:middle;" />':''); clear($('#bb_image_id')); },'image'); });
  $('#bb_pick_vector').on('click',function(e){ e.preventDefault(); pick(function(a){ $('#bb_vector_id').val(a.id||''); $('#bb_vector_url').val(a.url||''); $('#bb_vector_preview').text(a.filename||a.url||''); clear($('#bb_vector_id')); }); });
  function show($f,msg){ const id=$f.attr('id'); const $e=$('.bb-inline-error[data-for="'+id+'"]'); $f.addClass('bb-error'); if($e.length){$e.text(msg).addClass('show');} $f[0].scrollIntoView({behavior:'smooth',block:'center'}); $f.focus(); }
  function clear($f){ const id=$f.attr('id'); const $e=$('.bb-inline-error[data-for="'+id+'"]'); $f.removeClass('bb-error'); if($e.length){$e.removeClass('show').text('');} }
  function requireVal(sel,msg){ const $el=$(sel); const v=$el.val?($el.val()||'').trim():''; if(!v){ show($el,msg); return false; } return true; }
  function requirePrice(){ const $el=$('#bb_price'); const v=parseFloat($el.val()); if(isNaN(v)||v<=0){ show($el, BumblebeeCreate.required.price); return false; } clear($el); return true; }
  $('#bb_price,#bb_title,#bb_colors,#bb_sizes,#bb_vendor_code,#bb_production,#bb_print_location').on('input change', function(){ clear($(this)); if(this.id==='bb_price'){ requirePrice(); } });
  $('#bb_create_btn').on('click', function(e){
    e.preventDefault();
    const $btn=$(this), $spin=$('.spinner'); $btn.prop('disabled',true); $spin.addClass('is-active');
    if(!requirePrice() ||
       !requireVal('#bb_title',BumblebeeCreate.required.title) ||
       !$('#bb_image_id').val() && (show($('#bb_image_id'), BumblebeeCreate.required.image), false) ||
       !$('#bb_vector_id').val() && (show($('#bb_vector_id'), BumblebeeCreate.required.vector), false) ||
       !requireVal('#bb_colors',BumblebeeCreate.required.colors) ||
       !requireVal('#bb_sizes',BumblebeeCreate.required.sizes) ||
       !requireVal('#bb_vendor_code',BumblebeeCreate.required.vendor) ||
       !requireVal('#bb_production',BumblebeeCreate.required.production) ||
       !requireVal('#bb_print_location',BumblebeeCreate.required.printloc)
      ){
        $btn.prop('disabled',false); $spin.removeClass('is-active'); return;
      }
    const fd=new FormData(); fd.append('action','bumblebee_create_product'); fd.append('nonce',BumblebeeCreate.nonce);
    fd.append('title',$('#bb_title').val().trim()); fd.append('price',$('#bb_price').val()||''); fd.append('tax_status',$('input[name=\"bb_taxable\"]:checked').val()||'taxable');
    fd.append('image_id',$('#bb_image_id').val()||''); fd.append('vector_id',$('#bb_vector_id').val()||''); fd.append('vector_url',$('#bb_vector_url').val()||'');
    fd.append('colors',$('#bb_colors').val()||''); fd.append('sizes',$('#bb_sizes').val()||''); fd.append('vendor_code',$('#bb_vendor_code').val()||'');
    fd.append('production',$('#bb_production').val()); fd.append('print_location',$('#bb_print_location').val());
    fetch(BumblebeeCreate.ajaxurl,{method:'POST',body:fd,credentials:'same-origin'}).then(r=>r.json()).then(d=>{
      if(d&&d.success&&d.edit_url){ window.location.href=d.edit_url; } else { alert((d&&d.message)?d.message:'Creation failed.'); $btn.prop('disabled',false); $spin.removeClass('is-active'); }
    }).catch(err=>{ alert('Error: '+err); $btn.prop('disabled',false); $spin.removeClass('is-active'); });
  });
})(jQuery);