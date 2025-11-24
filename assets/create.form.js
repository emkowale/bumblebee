(function($){
  'use strict';

  function stopSpinner(){
    var btn = document.getElementById('bb_create_btn');
    if (!btn) return;
    btn.disabled = false;
    var sp = btn.parentNode ? btn.parentNode.querySelector('.spinner') : null;
    if (sp) sp.classList.remove('is-active');
  }

  function show($f,msg){
    const id = $f.attr('id');
    const $e = $('.bb-inline-error[data-for="'+id+'"]');
    $f.addClass('bb-error');
    if($e.length){
      $e.text(msg).addClass('show');
      stopSpinner();
    }
    if ($f[0] && typeof $f[0].scrollIntoView === 'function') $f[0].scrollIntoView({behavior:'smooth', block:'center'});
    $f.focus();
  }
  function clearInline($f){
    const id = $f.attr('id');
    const $e = $('.bb-inline-error[data-for="'+id+'"]');
    $f.removeClass('bb-error');
    if($e.length){ $e.removeClass('show').text(''); }
  }
  function requireVal(sel,msg){
    const $el = $(sel);
    const v = $el.val ? ($el.val() || '').trim() : '';
    if(!v){ show($el,msg); return false; }
    return true;
  }
  function requirePrice(){
    const $el = $('#bb_price');
    const v = parseFloat($el.val());
    if(isNaN(v) || v <= 0){ show($el, BumblebeeCreate.required.price); return false; }
    clearInline($el);
    return true;
  }

  function renderColorRows(count){
    const $wrap = $('#bb-color-rows');
    $wrap.empty();
    if(!count || count < 1) return;
    for(let i=0;i<count;i++){
      const idx=i+1;
      const row = [
        '<div class="bb-color-row" data-color-index="'+i+'">',
        '  <div class="bb-color-row__header">Color '+idx+'</div>',
        '  <div class="bb-color-row__field">',
        '    <label for="bb_color_name_'+i+'">Name</label>',
        '    <input type="text" class="regular-text bb-color-name" id="bb_color_name_'+i+'" data-color-index="'+i+'" />',
        '  </div>',
        '  <div class="bb-inline-error" data-for="bb_color_name_'+i+'"></div>',
        '  <div class="bb-color-row__field">',
        '    <label>Color image</label>',
        '    <button class="button bb-color-pick" data-color-index="'+i+'">Upload/Choose</button>',
        '    <span class="bb-color-preview" data-color-index="'+i+'"></span>',
        '    <input type="hidden" class="bb-color-image" id="bb_color_image_'+i+'" data-color-index="'+i+'" />',
        '  </div>',
        '  <div class="bb-inline-error" data-for="bb_color_image_'+i+'"></div>',
        '</div>'
      ].join('');
      $wrap.append(row);
    }
  }

  function collectColorData(){
    const raw = $('#bb_color_count').val();
    const count = parseInt(raw, 10);
    const colors=[];
    if(!isNaN(count) && count>0){
      for(let i=0;i<count;i++){
        const name = ($('#bb_color_name_'+i).val() || '').trim();
        const imageId = parseInt($('#bb_color_image_'+i).val(), 10) || 0;
        colors.push({name, image_id:imageId});
      }
    }
    return {count: isNaN(count) ? 0 : count, colors, selected: raw !== ''};
  }

  function validateColors(){
    const countRaw = $('#bb_color_count').val();
    const count = parseInt(countRaw,10);
    if(!countRaw){
      show($('#bb_color_count'), BumblebeeCreate.required.colorcount);
      return false;
    }
    clearInline($('#bb_color_count'));
    if(isNaN(count) || count < 0){ show($('#bb_color_count'), BumblebeeCreate.required.colorcount); return false; }
    if(count === 0) return true;
    let ok=true;
    for(let i=0;i<count;i++){
      const $name = $('#bb_color_name_'+i);
      const name = ($name.val() || '').trim();
      if(!name){
        show($name, BumblebeeCreate.required.colorname.replace('%d', i+1));
        ok=false; break;
      } else {
        clearInline($name);
      }
    }
    return ok;
  }

  function renderVendorRows(count){
    const $wrap = $('#bb-vendor-rows');
    $wrap.empty();
    if(!count || count < 1) return;
    for(let i=0;i<count;i++){
      const idx = i+1;
      const row = [
        '<div class="bb-vendor-row" data-vendor-index="'+i+'">',
        '  <div class="bb-vendor-row__header">Vendor '+idx+'</div>',
        '  <div class="bb-vendor-row__field">',
        '    <label for="bb_vendor_name_'+i+'">Name</label>',
        '    <input type="text" class="regular-text bb-vendor-name" id="bb_vendor_name_'+i+'" data-vendor-index="'+i+'" />',
        '  </div>',
        '  <div class="bb-inline-error" data-for="bb_vendor_name_'+i+'"></div>',
        '  <div class="bb-vendor-row__field">',
        '    <label for="bb_vendor_item_'+i+'">Item Number</label>',
        '    <input type="text" class="regular-text bb-vendor-item" id="bb_vendor_item_'+i+'" data-vendor-index="'+i+'" />',
        '  </div>',
        '  <div class="bb-inline-error" data-for="bb_vendor_item_'+i+'"></div>',
        '</div>'
      ].join('');
      $wrap.append(row);
    }
  }

  function collectVendorData(){
    const raw = $('#bb_vendor_count').val();
    const count = parseInt(raw, 10);
    const vendors=[];
    if(!isNaN(count) && count>0){
      for(let i=0;i<count;i++){
        const name = ($('#bb_vendor_name_'+i).val() || '').trim();
        const item = ($('#bb_vendor_item_'+i).val() || '').trim();
        vendors.push({name, item});
      }
    }
    return {count: isNaN(count) ? 0 : count, vendors, selected: raw !== ''};
  }

  function validateVendors(){
    const countRaw = $('#bb_vendor_count').val();
    const count = parseInt(countRaw,10);
    if(!countRaw){
      show($('#bb_vendor_count'), BumblebeeCreate.required.vendorcount);
      return false;
    }
    clearInline($('#bb_vendor_count'));
    if(isNaN(count) || count < 1){ show($('#bb_vendor_count'), BumblebeeCreate.required.vendorcount); return false; }
    let ok=true;
    for(let i=0;i<count;i++){
      const $name = $('#bb_vendor_name_'+i);
      const $item = $('#bb_vendor_item_'+i);
      const name = ($name.val() || '').trim();
      const item = ($item.val() || '').trim();
      if(!name){
        show($name, BumblebeeCreate.required.vendorname.replace('%d', i+1));
        ok=false; break;
      } else {
        clearInline($name);
      }
      if(!item){
        show($item, BumblebeeCreate.required.vendoritem.replace('%d', i+1));
        ok=false; break;
      } else {
        clearInline($item);
      }
    }
    return ok;
  }

  $('#bb_price,#bb_title,#bb_color_count,#bb_sizes,#bb_vendor_count,#bb_production,#bb_special_instructions').on('input change', function(){
    clearInline($(this));
    if(this.id==='bb_price') requirePrice();
  });
  $(document).on('input change', '.bb-color-name', function(){ clearInline($(this)); });
  $(document).on('input change', '.bb-vendor-name, .bb-vendor-item', function(){ clearInline($(this)); });
  $(document).on('bb:image:chosen', function(){ clearInline($('#bb_image_id')); });
  $(document).on('bb:color:image:chosen', function(e, idx){ clearInline($('#bb_color_image_'+idx)); });
  $(document).on('change', '#bb_color_count', function(){
    const count = parseInt($(this).val(),10);
    renderColorRows(isNaN(count) ? 0 : count);
  });
  $(document).on('change', '#bb_vendor_count', function(){
    const count = parseInt($(this).val(),10);
    renderVendorRows(isNaN(count) ? 0 : count);
  });

  $(function(){
    const existing = parseInt($('#bb_color_count').val(),10);
    if(!isNaN(existing) && existing>0) renderColorRows(existing);
    const vCount = parseInt($('#bb_vendor_count').val(),10);
    if(!isNaN(vCount) && vCount>0) renderVendorRows(vCount);
  });

  $('#bb_create_btn').on('click', function(e){
    e.preventDefault();
    const $btn = $(this), $spin = $('.spinner');

    if(!requirePrice() ||
       !requireVal('#bb_title',BumblebeeCreate.required.title) ||
       (!$('#bb_image_id').val() && (show($('#bb_image_id'), BumblebeeCreate.required.image), false)) ||
       !validateColors() ||
       !requireVal('#bb_sizes',BumblebeeCreate.required.sizes) ||
       !validateVendors() ||
       !requireVal('#bb_production',BumblebeeCreate.required.production)
      ){
        $btn.prop('disabled',false); $spin.removeClass('is-active'); return;
      }

    var locResult = (window.BumblebeeLocations && window.BumblebeeLocations.validate) ? window.BumblebeeLocations.validate() : {ok:true,names:[]};
    if(!locResult.ok){ stopSpinner(); return; }
    var locs = locResult.names || [];
    if (locs.length === 0) { show($('#bb-print-locations'), BumblebeeCreate.required.printloc); return; }

    $btn.prop('disabled',true); $spin.addClass('is-active');

    const fd = new FormData();
    fd.append('action','bumblebee_create_product');
    fd.append('nonce',BumblebeeCreate.nonce);
    fd.append('title',$('#bb_title').val().trim());
    fd.append('price',$('#bb_price').val() || '');
    fd.append('tax_status',$('input[name="bb_taxable"]:checked').val() || 'taxable');
    fd.append('image_id',$('#bb_image_id').val() || '');
    fd.append('color_data', JSON.stringify(collectColorData()));
    fd.append('sizes',$('#bb_sizes').val() || '');
    fd.append('vendor_data', JSON.stringify(collectVendorData()));
    fd.append('production',$('#bb_production').val());
    fd.append('print_location', locs.join(', '));
    fd.append('image_url', ($('#bb_image_preview img').attr('src') || '').trim());
    fd.append('special_instructions', ($('#bb_special_instructions').val() || '').trim());

    document.querySelectorAll('#bb-print-locations input[type="hidden"]').forEach(function(inp){
      if (!inp || !inp.name) return;
      if (!/(art|vector)/i.test(inp.name)) return;
      if (inp.value == null || String(inp.value).trim() === '') return;
      fd.append(inp.name, inp.value);
    });

    fetch(BumblebeeCreate.ajaxurl,{method:'POST',body:fd,credentials:'same-origin'})
      .then(r => r.json())
      .then(d => { if(d && d.success && d.edit_url){ window.location.href = d.edit_url; } else { alert((d && d.message) ? d.message : 'Creation failed.'); $btn.prop('disabled',false); $spin.removeClass('is-active'); } })
      .catch(err => { alert('Error: ' + err); $btn.prop('disabled',false); $spin.removeClass('is-active'); });
  });
})(jQuery);
