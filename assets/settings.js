(function($){
  function runTest(which, $btn, $status){
    $status.empty();
    $btn.prop('disabled', true);

    // Put spinner INSIDE the status span (immediately after the button in DOM)
    const $spinner = $('<span class="spinner" style="visibility:visible;display:inline-block;margin-left:6px;vertical-align:middle;"></span>');
    $status.append($spinner);

    $.post(BumblebeeSettings.ajaxurl, {
      action: 'bb_test_openai_key',
      nonce: BumblebeeSettings.nonce,
      which: which
    }).done(function(resp){
      $spinner.remove();
      $btn.prop('disabled', false);
      if (resp && resp.success) {
        $status.html('<span style="color:#198754;font-weight:600;vertical-align:middle;margin-left:6px;">✔ OK</span>');
      } else {
        const msg = resp && resp.data && resp.data.message ? resp.data.message : 'Failed';
        $status.html('<span style="color:#dc3545;font-weight:600;vertical-align:middle;margin-left:6px;">✖ ' + $('<div>').text(msg).html() + '</span>');
      }
    }).fail(function(){
      $spinner.remove();
      $btn.prop('disabled', false);
      $status.html('<span style="color:#dc3545;font-weight:600;vertical-align:middle;margin-left:6px;">✖ Network error</span>');
    });
  }

  $(document).on('click', '#bb-test-primary',  function(){ runTest('primary',  $(this), $('#bb-test-primary-status'));  });
  $(document).on('click', '#bb-test-secondary', function(){ runTest('secondary', $(this), $('#bb-test-secondary-status')); });

  function runHubTest(){
    const $btn = $('#bb-test-hub');
    const $status = $('#bb-test-hub-status');
    $status.empty();
    $btn.prop('disabled', true);
    const $spinner = $('<span class="spinner" style="visibility:visible;display:inline-block;margin-left:6px;vertical-align:middle;"></span>');
    $status.append($spinner);
    $.post(BumblebeeSettings.ajaxurl, {
      action: 'bb_test_hub',
      nonce: BumblebeeSettings.hubNonce
    }).done(function(resp){
      $spinner.remove(); $btn.prop('disabled', false);
      if(resp && resp.success){
        $status.html('<span style="color:#198754;font-weight:600;vertical-align:middle;margin-left:6px;">✔ Hub OK</span>');
      } else {
        const msg = resp && resp.data && resp.data.message ? resp.data.message : 'Failed';
        $status.html('<span style="color:#dc3545;font-weight:600;vertical-align:middle;margin-left:6px;">✖ ' + $('<div>').text(msg).html() + '</span>');
      }
    }).fail(function(xhr){
      $spinner.remove(); $btn.prop('disabled', false);
      const msg = xhr && xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message ? xhr.responseJSON.data.message : 'Network error';
      $status.html('<span style="color:#dc3545;font-weight:600;vertical-align:middle;margin-left:6px;">✖ ' + $('<div>').text(msg).html() + '</span>');
    });
  }

  $(document).on('click', '#bb-test-hub', function(e){
    e.preventDefault();
    runHubTest();
  });

  function addApprovedVendorRow(){
    const $wrap = $('#bb-approved-vendors-rows');
    const tpl = $('#bb-approved-vendor-template').html();
    if(!$wrap.length || !tpl) return;
    let next = parseInt($wrap.data('next-index'), 10);
    if (isNaN(next)) next = $wrap.find('.bb-approved-vendor-row').length;
    const html = tpl.replace(/__i__/g, next);
    $wrap.append(html);
    $wrap.data('next-index', next + 1);
  }

  $(document).on('click', '#bb-add-approved-vendor', function(e){
    e.preventDefault();
    addApprovedVendorRow();
  });

  $(document).on('click', '.bb-remove-approved-vendor', function(e){
    e.preventDefault();
    $(this).closest('.bb-approved-vendor-row').remove();
  });

  function collectVendorRows(){
    const rows = [];
    $('#bb-approved-vendors-rows .bb-approved-vendor-row').each(function(){
      const $row = $(this);
      const row = {
        id: parseInt($row.attr('data-id'), 10) || 0,
        name: $row.find('input[name*="[name]"]').val() || '',
        code: $row.find('input[name*="[code]"]').val() || '',
        description: $row.find('textarea[name*="[description]"]').val() || ''
      };
      if(!row.name && !row.code && !row.description) return;
      row.__idx = rows.length;
      rows.push(row);
    });
    return rows;
  }

  function renderVendorRows(vendors){
    const $wrap = $('#bb-approved-vendors-rows');
    const tpl = $('#bb-approved-vendor-template').html();
    if(!$wrap.length || !tpl) return;
    $wrap.empty();
    let idx = 0;
    (vendors || []).forEach(function(v){
      let html = tpl.replace(/__i__/g, idx);
      const $row = $(html);
      $row.attr('data-id', v.id || '');
      $row.find('input[name*=\"[name]\"]').val(v.name || '');
      $row.find('input[name*=\"[code]\"]').val(v.code || '');
      $row.find('textarea[name*=\"[description]\"]').val(v.description || '');
      $wrap.append($row);
      idx++;
    });
    $wrap.data('next-index', idx);
  }

  function refreshVendorsFromHub(){
    const $status = $('#bb-vendor-save-status');
    const $wrap = $('#bb-approved-vendors-rows');
    let $loadSpin = $('#bb-vendor-load-spinner');
    if(!$loadSpin.length){ $loadSpin = $('<span class="spinner is-active" id="bb-vendor-load-spinner" style="float:none;margin-left:6px;vertical-align:middle;"></span>'); $wrap.before($loadSpin); }
    $.post(BumblebeeSettings.ajaxurl, {
      action: 'bb_hub_get_vendors',
      nonce: BumblebeeSettings.hubNonce
    }).done(function(resp){
      if(resp && resp.success && resp.data && resp.data.vendors){
        renderVendorRows(resp.data.vendors);
      }
    }).fail(function(xhr){
      const msg = xhr && xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message ? xhr.responseJSON.data.message : 'Load failed';
      $status.html('<span style="color:#dc3545;font-weight:600;">'+ msg +'</span>');
    }).always(function(){
      $loadSpin.removeClass('is-active');
    });
  }

  function syncVendorsToHub(){
    const vendors = collectVendorRows();
    const $btn = $('#bb-sync-vendors');
    const $status = $('#bb-vendor-save-status');
    let $spin = $('#bb-vendor-sync-spinner');
    if(!$spin.length){ $spin = $('<span class="spinner" id="bb-vendor-sync-spinner" style="float:none;margin-left:6px;vertical-align:middle;"></span>'); $btn.after($spin); }
    if($btn.length) { $btn.prop('disabled', true); $btn.text('Saving…'); }
    $spin.addClass('is-active');
    $status.text('');
    const queue = vendors.slice();
    const errors = [];
    let successCount = 0;
    function next(){
      if(queue.length === 0){
        if($btn.length){ $btn.prop('disabled', false); $btn.text('Save Vendors to Hub'); }
        $spin.removeClass('is-active');
        const html = errors.length
          ? '<span style="color:#dc3545;font-weight:600;">Save failed: '+errors.join(' | ')+'</span>'
          : '<span style="color:#198754;font-weight:600;">Saved.</span>';
        $status.html(html);
        return;
      }
      const v = queue.shift();
      const label = (v.name || v.code || ('Vendor '+(v.id || '?')));
      $.post(BumblebeeSettings.ajaxurl, {
        action: 'bb_hub_save_vendor',
        nonce: BumblebeeSettings.hubNonce,
        id: v.id || '',
        name: v.name || '',
        code: v.code || '',
        description: v.description || ''
      }).done(function(resp){
        if (window.console && console.debug) console.debug('Vendor save request', v, resp);
        if(!(resp && resp.success)){
          const msg = resp && resp.data && resp.data.message ? resp.data.message : 'Save failed';
          errors.push(label+': '+msg);
          if (window.console && console.warn) console.warn('Vendor save failed', v, resp);
        } else if (resp && resp.data && resp.data.vendor) {
          const saved = resp.data.vendor;
          v.id = saved.id || v.id;
          const returnedName = saved.name || '';
          let metaCode = '';
          if (Array.isArray(saved.meta_data)) {
            const m = saved.meta_data.find(m => m && m.key === 'bb_vendor_code');
            if (m && m.value) metaCode = m.value;
          }
          const returnedCode = metaCode || saved.slug || saved.code || '';
          const returnedDesc = saved.description || '';
          const mismatch = (returnedName && returnedName !== v.name) ||
                           (returnedCode && returnedCode !== v.code) ||
                           (returnedDesc && returnedDesc !== v.description);

          // keep DOM values as user-entered; only update ID
          const $row = $('#bb-approved-vendors-rows .bb-approved-vendor-row').eq(v.__idx);
          $row.attr('data-id', v.id);

          successCount++;
        } else {
          errors.push(label+': Empty hub response');
          if (window.console && console.warn) console.warn('Vendor save empty response', v, resp);
        }
      }).fail(function(xhr){
        const msg = xhr && xhr.responseJSON && xhr.responseJSON.data && xhr.responseJSON.data.message ? xhr.responseJSON.data.message : 'Network error';
        errors.push(label+': '+msg);
        if (window.console && console.error) console.error('Vendor save AJAX error', msg, v, xhr);
      }).always(function(){ next(); });
    }
    next();
  }

  $(document).on('click', '#bb-sync-vendors', function(e){
    e.preventDefault();
    syncVendorsToHub();
  });

  $(function(){ refreshVendorsFromHub(); });
})(jQuery);
