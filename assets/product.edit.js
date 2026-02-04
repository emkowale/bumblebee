(function($){
  'use strict';
  var allowedOriginals = ['png','psd'];
  var imageExts = ['png','jpg','jpeg','gif','webp'];

  function extOf(value){
    var s = String(value || '').split('?')[0];
    var parts = s.split('.');
    return (parts.length > 1 ? parts.pop() : '').toLowerCase();
  }

  function fileNameFromUrl(url){
    try {
      var clean = String(url || '').split('?')[0];
      return clean.split('/').pop() || '';
    } catch(e){
      return '';
    }
  }

  function showError($row, msg){
    var $err = $row.find('.bb-art-error');
    if ($err.length){
      $err.text(msg || '').show();
    }
  }

  function clearError($row){
    var $err = $row.find('.bb-art-error');
    if ($err.length){
      $err.hide().text('');
    }
  }

  function toggleRow($row, checked){
    $row.toggleClass('is-checked', checked);
    $row.find('.bb-art-tools').toggle(checked);
    $row.find('.bb-art-url, .bb-art-id').prop('disabled', !checked);
    if (!checked) clearError($row);
  }

  function setPreview($row, url, name){
    var $thumb = $row.find('.bb-art-thumb');
    var $file = $row.find('.bb-art-filename');
    var $preview = $row.find('.bb-art-preview');
    var ext = extOf(name || url);
    var showImg = url && imageExts.indexOf(ext) !== -1;
    if ($preview.length){
      $preview.attr('href', url || '#');
    }
    if ($thumb.length){
      if (showImg) {
        $thumb.attr('src', url).show();
      } else {
        $thumb.attr('src', '').hide();
      }
    }
    if ($file.length){
      $file.text(name || fileNameFromUrl(url));
      $file.attr('href', url || '#');
    }
  }

  function openPicker($row){
    var frame = wp.media({ title: 'Select Original Art', button: { text: 'Use this file' }, multiple: false });
    frame.on('select', function(){
      var file = frame.state().get('selection').first().toJSON();
      if (!file) return;
      var url = file.url || '';
      var name = file.filename || file.name || fileNameFromUrl(url);
      var ext = extOf(name || url);
      if (allowedOriginals.indexOf(ext) === -1){
        showError($row, 'Only PNG or PSD allowed for original art.');
        return;
      }
      clearError($row);
      $row.find('.bb-art-url').val(url);
      $row.find('.bb-art-id').val(file.id || '');
      setPreview($row, url, name);
    });
    frame.open();
  }

  function vendorOptionsHtml(vendors, current){
    var html = '<option value="">— Select —</option>';
    (vendors || []).forEach(function(v){
      var label = v.name || v.code || '';
      var val = v.name || '';
      var sel = current && current === val ? ' selected' : '';
      html += '<option value="'+ $('<div>').text(val).html() +'"'+sel+'>'+ $('<div>').text(label).html() +'</option>';
    });
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
    });
  }

  $(function(){
    loadVendors();
    $('.bb-location-check').each(function(){
      var $row = $(this);
      var checked = $row.find('.bb-location-checkbox').is(':checked');
      toggleRow($row, checked);
    });
  });

  $(document).on('change', '.bb-location-checkbox', function(){
    var $row = $(this).closest('.bb-location-check');
    toggleRow($row, $(this).is(':checked'));
  });

  $(document).on('click', '.bb-upload-original', function(e){
    e.preventDefault();
    e.stopPropagation();
    var $row = $(this).closest('.bb-location-check');
    openPicker($row);
  });
})(jQuery);
