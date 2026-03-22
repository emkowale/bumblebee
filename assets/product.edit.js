(function($){
  'use strict';
  var allowedOriginals = ['png'];
  var imageExts = ['png','jpg','jpeg','gif','webp'];
  function setOriginalArtUploadFlag(enabled){
    if (typeof wp === 'undefined' || !wp.Uploader || !wp.Uploader.defaults) return;
    var defaults = wp.Uploader.defaults;
    if (!defaults.multipart_params) defaults.multipart_params = {};
    if (enabled) defaults.multipart_params.bumblebee_original_art_upload = '1';
    else delete defaults.multipart_params.bumblebee_original_art_upload;
  }

  function decodeEntities(value){
    var txt = document.createElement('textarea');
    txt.innerHTML = String(value || '');
    return txt.value;
  }

  function normalizeVendorText(value){
    return decodeEntities(value).trim();
  }

  function isFulfillProduction(){
    return String($('#bb_production').val() || '').trim().toLowerCase() === 'fulfill';
  }

  function extOf(value){
    var s = String(value || '').split('?')[0];
    var parts = s.split('.');
    return (parts.length > 1 ? parts.pop() : '').toLowerCase();
  }
  function originalArtExtFromAttachment(file){
    if (file && file.mime) {
      var mime = String(file.mime).toLowerCase();
      if (mime === 'image/png') return 'png';
      if (mime === 'image/webp') return 'webp';
      if (mime === 'image/jpeg' || mime === 'image/jpg') return 'jpg';
    }
    return extOf((file && (file.filename || file.name || file.url)) || '');
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

  function syncPrintLocationField(){
    var fulfill = isFulfillProduction();
    var $field = $('.bb-print-location-field');
    if (!$field.length) $field = $('.bb-print-locations').closest('.form-field');
    if (!$field.length) return;

    $field.toggle(!fulfill);
    $field.find('.bb-location-checkbox, .bb-upload-original, .bb-art-url, .bb-art-id').prop('disabled', fulfill);

    if (fulfill) {
      $field.find('.bb-art-error').hide().text('');
      return;
    }

    $field.find('.bb-location-check').each(function(){
      var $row = $(this);
      toggleRow($row, $row.find('.bb-location-checkbox').is(':checked'));
    });
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

  function resolveOriginalAttachmentUrl(file, done){
    var fallbackUrl = String((file && file.url) || '').trim();
    var attachmentId = parseInt(file && file.id, 10) || 0;
    var ajaxurl = (typeof BumblebeeProductEdit !== 'undefined' && BumblebeeProductEdit.ajaxurl) ? BumblebeeProductEdit.ajaxurl : '';
    var nonce = (typeof BumblebeeProductEdit !== 'undefined' && BumblebeeProductEdit.originalAttachmentNonce) ? BumblebeeProductEdit.originalAttachmentNonce : '';

    if (!attachmentId || !ajaxurl || !nonce) {
      done(fallbackUrl);
      return;
    }

    $.post(ajaxurl, {
      action: 'bb_original_attachment_url',
      nonce: nonce,
      attachment_id: attachmentId
    })
      .done(function(resp){
        var resolved = (resp && resp.success && resp.data && resp.data.url) ? String(resp.data.url).trim() : '';
        done(resolved || fallbackUrl);
      })
      .fail(function(){
        done(fallbackUrl);
      });
  }

  function openPicker($row){
    setOriginalArtUploadFlag(true);
    var frame = wp.media({ title: 'Select Original Art', button: { text: 'Use this file' }, multiple: false });
    frame.on('select', function(){
      var file = frame.state().get('selection').first().toJSON();
      if (!file) return;
      var url = file.url || '';
      var name = file.filename || file.name || fileNameFromUrl(url);
      var ext = originalArtExtFromAttachment(file);
      if (ext && allowedOriginals.indexOf(ext) === -1){
        showError($row, 'Only PNG allowed for original art.');
        return;
      }
      resolveOriginalAttachmentUrl(file, function(resolvedUrl){
        var finalUrl = resolvedUrl || url;
        clearError($row);
        $row.find('.bb-art-url').val(finalUrl);
        $row.find('.bb-art-id').val(file.id || '');
        setPreview($row, finalUrl, name);
      });
    });
    frame.on('close', function(){
      setOriginalArtUploadFlag(false);
    });
    frame.open();
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

  $(function(){
    loadVendors();
    $('.bb-location-check').each(function(){
      var $row = $(this);
      var checked = $row.find('.bb-location-checkbox').is(':checked');
      toggleRow($row, checked);
    });
    syncPrintLocationField();
  });

  $(document).on('change', '#bb_production', syncPrintLocationField);

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
