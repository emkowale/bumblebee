(function($){
  'use strict';

  function decodeEntities(value){
    var txt = document.createElement('textarea');
    txt.innerHTML = String(value || '');
    return txt.value;
  }

  function normalizeVendorText(value){
    return decodeEntities(value).trim();
  }

  function stopSpinner(){
    var btn = document.getElementById('bb_create_btn');
    if (!btn) return;
    btn.disabled = false;
    var sp = btn.parentNode ? btn.parentNode.querySelector('.spinner') : null;
    if (sp) sp.classList.remove('is-active');
  }

  function showAiFailure(reason, editUrl, productId){
    var box = document.getElementById('bb-ai-fail');
    var reasonEl = document.getElementById('bb-ai-reason');
    if (reasonEl) reasonEl.textContent = reason || 'Unknown error.';
    if (box){
      box.style.display = 'block';
      box.dataset.editUrl = editUrl || '';
      box.dataset.productId = String(productId || '');
    }
    stopSpinner();
    var btn = document.getElementById('bb_create_btn');
    if (btn) btn.disabled = true;
    if (box && typeof box.scrollIntoView === 'function') {
      box.scrollIntoView({behavior:'smooth', block:'start'});
    } else {
      window.scrollTo({top:0, behavior:'smooth'});
    }
  }

  function hideAiFailure(){
    var box = document.getElementById('bb-ai-fail');
    if (box) box.style.display = 'none';
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

  function isFulfillProduction(){
    return ($('#bb_production').val() || '').trim().toLowerCase() === 'fulfill';
  }

  function scrubsEnabled(){
    return ($('input[name="bb_scrubs"]:checked').val() || 'no') === 'yes';
  }

  function syncScrubCard(){
    const enabled = scrubsEnabled();
    const $wrap = $('[data-bb-scrubs]');
    const $card = $('[data-bb-scrubs-card]');
    const $selects = $('[data-bb-scrub-select]');

    $wrap.toggleClass('is-active', enabled);
    $card.attr('aria-hidden', enabled ? 'false' : 'true');
    $selects.prop('disabled', !enabled);

    if (!enabled) {
      $selects.each(function(){
        clearInline($(this));
      });
    }
  }

  function validateScrubs(){
    if (!scrubsEnabled()) return true;

    let ok = true;
    $('[data-bb-scrub-select]').each(function(){
      const $select = $(this);
      const value = ($select.val() || '').trim();
      if (value !== '') {
        clearInline($select);
        return;
      }

      const $field = $select.closest('.bb-scrubs__field');
      const label = ($field.find('label').first().text() || 'This field').trim();
      show($select, BumblebeeCreate.required.scrubfield.replace('%s', label));
      ok = false;
      return false;
    });

    return ok;
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
        '    <label for="bb_color_name_'+i+'">Color Name <span class="bb-tooltip dashicons dashicons-editor-help" aria-label="Use the exact color name from the vendor&#39;s product webpage." title="Use the exact color name from the vendor&#39;s product webpage."></span></label>',
        '    <input type="text" class="regular-text bb-color-name" id="bb_color_name_'+i+'" data-color-index="'+i+'" />',
        '  </div>',
        '  <div class="bb-inline-error" data-for="bb_color_name_'+i+'"></div>',
        '  <div class="bb-color-row__field">',
        '    <label>Mockup Image <span class="bb-tooltip dashicons dashicons-editor-help" aria-label="Choose a mockup image that has a garment in this color. Use WebP when possible, or upload a PNG/JPG and Bumblebee will convert it to WebP. Also, be sure that the Mockup contains all Print Locations of the product." title="Choose a mockup image that has a garment in this color. Use WebP when possible, or upload a PNG/JPG and Bumblebee will convert it to WebP. Also, be sure that the Mockup contains all Print Locations of the product."></span></label>',
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
      const $img = $('#bb_color_image_'+i);
      const imgId = parseInt($img.val(), 10) || 0;
      if(!imgId){
        show($img, BumblebeeCreate.required.colormockup.replace('%d', i+1));
        ok=false; break;
      } else {
        clearInline($img);
      }
    }
    return ok;
  }

  var hubVendors = [];

  function vendorOptionsHtml(selected){
    var selectedNorm = normalizeVendorText(selected || '');
    var html = '<option value="">— Select —</option>';
    hubVendors.forEach(function(v){
      var label = normalizeVendorText(v.name || v.code || '');
      if (!label && v.id) label = 'Vendor ' + v.id;
      var val = normalizeVendorText(v.name || '');
      var code = normalizeVendorText(v.code || '');
      var sel = selectedNorm && selectedNorm === val ? ' selected' : '';
      html += '<option value="'+ $('<div>').text(val).html() +'" data-code="'+ $('<div>').text(code).html() +'"'+sel+'>'+ $('<div>').text(label).html() +'</option>';
    });
    if (hubVendors.length === 0) {
      html += '<option value="" disabled>No hub vendors found</option>';
    }
    return html;
  }
  function applyVendorsToSelects(){
    $('#bb-vendor-rows select.bb-vendor-name').each(function(){
      var current = $(this).val() || '';
      $(this).html(vendorOptionsHtml(current));
      if (current) $(this).val(current);
    });
  }
  function fetchHubVendors(){
    $.post(BumblebeeCreate.ajaxurl, {
      action: 'bb_hub_vendors',
      nonce: BumblebeeCreate.hubNonce
    }).done(function(resp){
      if(resp && resp.success && resp.data && Array.isArray(resp.data.vendors)){
        hubVendors = resp.data.vendors;
        applyVendorsToSelects();
      }
    });
  }

  function renderVendorRows(){
    const $wrap = $('#bb-vendor-rows');
    $wrap.empty();
    const i = 0;
    const row = [
      '<div class="bb-vendor-row" data-vendor-index="'+i+'">',
      '  <div class="bb-vendor-row__field">',
      '    <label for="bb_vendor_name_'+i+'">Vendor Name</label>',
      '    <select class="regular-text bb-vendor-name" id="bb_vendor_name_'+i+'" data-vendor-index="'+i+'">' + vendorOptionsHtml('') + '</select>',
      '  </div>',
      '  <div class="bb-inline-error" data-for="bb_vendor_name_'+i+'"></div>',
      '  <div class="bb-vendor-row__field">',
      '    <label for="bb_vendor_item_'+i+'">Vendor Item Number <span class="bb-tooltip dashicons dashicons-editor-help" aria-label="i.e. DT6000, NL6210, PC43, etc..." title="i.e. DT6000, NL6210, PC43, etc..."></span></label>',
      '    <input type="text" class="regular-text bb-vendor-item" id="bb_vendor_item_'+i+'" data-vendor-index="'+i+'" />',
      '  </div>',
      '  <div class="bb-inline-error" data-for="bb_vendor_item_'+i+'"></div>',
      '</div>'
    ].join('');
    $wrap.append(row);
  }

  function collectVendorData(){
    const vendors=[];
    const name = normalizeVendorText($('#bb_vendor_name_0').val() || '');
    const item = ($('#bb_vendor_item_0').val() || '').trim();
    vendors.push({name, item});
    return {count: 1, vendors, selected: true};
  }

  function validateVendors(){
    let ok=true;
    const $name = $('#bb_vendor_name_0');
    const $item = $('#bb_vendor_item_0');
    const name = ($name.val() || '').trim();
    const item = ($item.val() || '').trim();
    if(!name){
      show($name, BumblebeeCreate.required.vendorname.replace('%d', 1));
      ok=false;
    } else {
      clearInline($name);
    }
    if(!item){
      show($item, BumblebeeCreate.required.vendoritem.replace('%d', 1));
      ok=false;
    } else {
      clearInline($item);
    }
    return ok;
  }

  $('#bb_price,#bb_title,#bb_color_count,#bb_sizes,#bb_production,#bb_special_instructions').on('input change', function(){
    clearInline($(this));
    if(this.id==='bb_price') requirePrice();
  });
  $(document).on('change', 'input[name="bb_scrubs"]', function(){
    syncScrubCard();
  });
  $(document).on('change', '[data-bb-scrub-select]', function(){
    clearInline($(this));
  });
  $(document).on('input change', '.bb-color-name', function(){ clearInline($(this)); });
  $(document).on('input change', '.bb-vendor-name, .bb-vendor-item', function(){ clearInline($(this)); });
  $(document).on('bb:color:image:chosen', function(e, idx){ clearInline($('#bb_color_image_'+idx)); });
  $(document).on('change', '#bb_color_count', function(){
    const count = parseInt($(this).val(),10);
    renderColorRows(isNaN(count) ? 0 : count);
  });

  $(function(){
    const existing = parseInt($('#bb_color_count').val(),10);
    if(!isNaN(existing) && existing>0) renderColorRows(existing);
    renderVendorRows();
    fetchHubVendors();
    syncScrubCard();
  });

  $('#bb_create_btn').on('click', function(e){
    e.preventDefault();
    const $btn = $(this), $spin = $('.spinner');

    if(!requirePrice() ||
       !requireVal('#bb_title',BumblebeeCreate.required.title) ||
       !validateScrubs() ||
       !validateColors() ||
       !requireVal('#bb_sizes',BumblebeeCreate.required.sizes) ||
       !validateVendors() ||
       !requireVal('#bb_production',BumblebeeCreate.required.production)
      ){
        $btn.prop('disabled',false); $spin.removeClass('is-active'); return;
      }

    var locResult = (window.BumblebeeLocations && window.BumblebeeLocations.validate) ? window.BumblebeeLocations.validate() : {ok:true,names:[]};
    var fulfill = isFulfillProduction();
    if(!locResult.ok){ stopSpinner(); return; }
    var locs = fulfill ? [] : (locResult.names || []);
    if (!fulfill && locs.length === 0) { show($('#bb-print-locations'), BumblebeeCreate.required.printloc); return; }

    $btn.prop('disabled',true); $spin.addClass('is-active');

    const fd = new FormData();
    fd.append('action','bumblebee_create_product');
    fd.append('nonce',BumblebeeCreate.nonce);
    fd.append('title',$('#bb_title').val().trim());
    fd.append('price',$('#bb_price').val() || '');
    fd.append('scrubs', $('input[name="bb_scrubs"]:checked').val() || 'no');
    $('[data-bb-scrub-select]').each(function(){
      const id = $(this).attr('id') || '';
      if (!id) return;
      fd.append(id.replace(/^bb_/, ''), $(this).val() || '');
    });
    fd.append('tax_status',$('input[name="bb_taxable"]:checked').val() || 'taxable');
    fd.append('color_data', JSON.stringify(collectColorData()));
    fd.append('sizes',$('#bb_sizes').val() || '');
    fd.append('vendor_data', JSON.stringify(collectVendorData()));
    fd.append('production',$('#bb_production').val());
    fd.append('print_location', fulfill ? '' : locs.join(', '));
    fd.append('special_instructions', ($('#bb_special_instructions').val() || '').trim());

    if (!fulfill) {
      document.querySelectorAll('#bb-print-locations input[type="hidden"]').forEach(function(inp){
        if (!inp || !inp.name) return;
        if (!/(art|vector)/i.test(inp.name)) return;
        if (inp.value == null || String(inp.value).trim() === '') return;
        fd.append(inp.name, inp.value);
      });
    }

    fetch(BumblebeeCreate.ajaxurl,{method:'POST',body:fd,credentials:'same-origin'})
      .then(r => r.json())
      .then(d => {
        if(d && d.success && d.edit_url){
          if (d.ai_failed){
            showAiFailure(d.ai_error, d.edit_url, d.product_id);
            return;
          }
          window.location.href = d.edit_url;
        } else {
          alert((d && d.message) ? d.message : 'Creation failed.');
          $btn.prop('disabled',false); $spin.removeClass('is-active');
        }
      })
      .catch(err => { alert('Error: ' + err); $btn.prop('disabled',false); $spin.removeClass('is-active'); });
  });

  $(document).on('click', '#bb-ai-continue', function(){
    var box = document.getElementById('bb-ai-fail');
    var url = box ? (box.dataset.editUrl || '') : '';
    if (url) window.location.href = url;
  });

  $(document).on('click', '#bb-ai-cancel', function(){
    var box = document.getElementById('bb-ai-fail');
    var productId = box ? (box.dataset.productId || '') : '';
    if (!productId) { hideAiFailure(); return; }
    var fd = new FormData();
    fd.append('action','bumblebee_delete_product');
    fd.append('nonce', BumblebeeCreate.deleteNonce);
    fd.append('product_id', productId);
    fetch(BumblebeeCreate.ajaxurl,{method:'POST',body:fd,credentials:'same-origin'})
      .then(r => r.json())
      .then(d => {
        if (d && d.success){
          hideAiFailure();
          $('#bb_create_btn').prop('disabled',false);
        } else {
          alert((d && d.message) ? d.message : 'Unable to discard product.');
        }
      })
      .catch(err => { alert('Error: ' + err); });
  });
})(jQuery);
