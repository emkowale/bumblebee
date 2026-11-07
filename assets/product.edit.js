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

  function vendorOptionsHtml(vendors, current){
    var currentNorm = normalizeVendorText(current || '');
    var hasCurrent = false;
    var html = '<option value="">— Select —</option>';
    (vendors || []).forEach(function(v){
      var label = normalizeVendorText(v.name || v.code || '');
      var val = normalizeVendorText(v.name || '');
      var sel = currentNorm && currentNorm === val ? ' selected' : '';
      if (sel) hasCurrent = true;
      html += '<option value="'+ $('<div>').text(val).html() +'"'+sel+'>'+ $('<div>').text(label).html() +'</option>';
    });
    // Keep the existing product vendor selectable even if Hub does not return it.
    if (currentNorm && !hasCurrent) {
      var safeCurrent = $('<div>').text(currentNorm).html();
      html += '<option value="' + safeCurrent + '" selected>' + safeCurrent + '</option>';
    }
    if (!vendors || vendors.length === 0) {
      html += '<option value="" disabled>No hub vendors found</option>';
    }
    return html;
  }

  function loadVendors(){
    var $sel = $('#bb_vendor_name');
    if (!$sel.length || typeof BumblebeeProductEdit === 'undefined') return;
    var current = $sel.data('current') || '';
    $.post(BumblebeeProductEdit.ajaxurl, {
      action: 'bb_hub_vendors',
      nonce: BumblebeeProductEdit.hubNonce
    }).done(function(resp){
      if(resp && resp.success && resp.data && Array.isArray(resp.data.vendors)){
        $sel.html(vendorOptionsHtml(resp.data.vendors, current));
        if (current) $sel.val(current);
      }
    }).fail(function(){
      // If Hub lookup fails, keep existing vendor value so save does not wipe it.
      var currentNorm = normalizeVendorText(current || '');
      if (!currentNorm) return;
      var safeCurrent = $('<div>').text(currentNorm).html();
      $sel.html('<option value="">— Select —</option><option value="' + safeCurrent + '" selected>' + safeCurrent + '</option>');
    });
  }

  $(function(){ loadVendors(); });
})(jQuery);
