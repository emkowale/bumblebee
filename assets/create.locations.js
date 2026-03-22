(function($){
  'use strict';
  var allowedOriginals = ['png'];
  var imageExts = ['png','jpg','jpeg','gif','webp'];

  function isFulfillProduction(){
    return String($('#bb_production').val() || '').trim().toLowerCase() === 'fulfill';
  }

  function setOriginalArtUploadFlag(enabled){
    if (typeof wp === 'undefined' || !wp.Uploader || !wp.Uploader.defaults) return;
    var defaults = wp.Uploader.defaults;
    if (!defaults.multipart_params) defaults.multipart_params = {};
    if (enabled) defaults.multipart_params.bumblebee_original_art_upload = '1';
    else delete defaults.multipart_params.bumblebee_original_art_upload;
  }

  function extOf(url){
    var s = String(url || '').split('?')[0];
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
  function allowedMessage(){
    return 'Only PNG allowed for original art.';
  }
  function humanize(url){
    try{ var name = url.split('/').pop().split('?')[0]; return name.replace(/\.[^.]+$/,'').replace(/[-_]+/g,' ').replace(/\b\w/g, function(c){return c.toUpperCase();}); }
    catch(e){ return url || ''; }
  }
  function fileNameFromUrl(url){
    try {
      var clean = String(url || '').split('?')[0];
      return clean.split('/').pop() || '';
    } catch(e){
      return '';
    }
  }
  function isPreviewable(url, name){
    var fromName = extOf(name || '');
    var fromUrl = extOf(url || '');
    return imageExts.indexOf(fromName) !== -1 || imageExts.indexOf(fromUrl) !== -1;
  }
  function renderStoredFile($label, url, name, ext){
    var safeUrl = String(url || '').trim();
    var labelText = String(name || '').trim();
    if (!labelText) labelText = humanize(safeUrl) || fileNameFromUrl(safeUrl);

    var $pill = $label.find('.bb-file-pill');
    $pill.empty().attr('data-url', safeUrl).attr('data-ext', String(ext || extOf(labelText || safeUrl)).toLowerCase());

    if (safeUrl && isPreviewable(safeUrl, labelText)) {
      $('<a>', {
        class: 'bb-art-preview',
        href: safeUrl,
        target: '_blank',
        rel: 'noopener'
      })
        .append($('<img>', { class: 'bb-art-thumb', src: safeUrl, alt: '' }))
        .appendTo($pill);
    }

    if (safeUrl) {
      $('<a>', {
        class: 'bb-art-filename',
        href: safeUrl,
        target: '_blank',
        rel: 'noopener',
        text: labelText || safeUrl
      }).appendTo($pill);
    } else {
      $pill.text(labelText || '');
    }

    $pill.show();
  }
  function ensureRow($label){
    if($label.data('bb-initialized')) return;
    var slug = $label.find('input.bb-location-checkbox').attr('id') || '';
    var $btn = $('<button>', {type:'button',class:'button bb-upload-original',text:'Upload Original Art','data-slug':String(slug).replace('bb_loc_','')}).css({ marginLeft: '8px', display: 'none' });
    $label.append($btn).append($('<span>', { class: 'bb-file-pill' }).css({ marginLeft: '8px', display: 'none' }));
    $label.data('bb-initialized', true);
  }
  function showError($label,msg){
    $label.find('.bb-inline-mime-error').remove();
    $('<div class="bb-inline-mime-error" style="color:#a40000;margin-left:8px;"></div>').text(msg).appendTo($label);
  }
  function clearInline($label){
    $label.find('.bb-inline-mime-error').remove();
  }
  function clearStoredFile($label){
    $label.find('.bb-file-pill').empty().removeAttr('data-url').removeAttr('data-ext').hide();
    $label.find('.bb-upload-original').removeData('bb-url').removeData('bb-id').removeData('bb-ext');
    var $cb = $label.find('.bb-location-checkbox');
    if ($cb.length) {
      var id = ($cb.attr('id') || '').replace('bb_loc_','');
      var wrap = $('#bb-print-locations');
      wrap.find('input[type="hidden"][name="art_'+id+'_url"]').val('');
      wrap.find('input[type="hidden"][name="art_'+id+'_id"]').val('');
    }
  }
  function storeHidden(slug, url, id){
    var wrap  = document.getElementById('bb-print-locations');
    if(!wrap) return;
    var nameU = 'art_' + slug + '_url';
    var nameI = 'art_' + slug + '_id';
    var inU = wrap.querySelector('input[type="hidden"][name="'+nameU+'"]');
    if(!inU){ inU = document.createElement('input'); inU.type='hidden'; inU.name=nameU; wrap.appendChild(inU); }
    inU.value = url;
    if(id){
      var inI = wrap.querySelector('input[type="hidden"][name="'+nameI+'"]');
      if(!inI){ inI = document.createElement('input'); inI.type='hidden'; inI.name=nameI; wrap.appendChild(inI); }
      inI.value = id;
    }
  }
  function resolveOriginalAttachmentUrl(file, done){
    var fallbackUrl = String((file && file.url) || '').trim();
    var attachmentId = parseInt(file && file.id, 10) || 0;
    var ajaxurl = (typeof BumblebeeCreate !== 'undefined' && BumblebeeCreate.ajaxurl) ? BumblebeeCreate.ajaxurl : '';
    var nonce = (typeof BumblebeeCreate !== 'undefined' && BumblebeeCreate.originalAttachmentNonce) ? BumblebeeCreate.originalAttachmentNonce : '';

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
  function openPicker(slug){
    setOriginalArtUploadFlag(true);
    var frame = wp.media({ title: 'Select Original Art', button: { text: 'Use this file' }, multiple: false });
    frame.on('select', function(){
      var file = frame.state().get('selection').first().toJSON();
      var url  = file.url || '';
      var ext  = originalArtExtFromAttachment(file);
      var allowed = allowedOriginals;
      var $label = $('#bb_loc_' + slug).closest('label.bb-location-check');
      if (ext && allowed.indexOf(ext) === -1) { clearStoredFile($label); showError($label, allowedMessage()); return; }
      resolveOriginalAttachmentUrl(file, function(resolvedUrl){
        var finalUrl = resolvedUrl || url;
        clearInline($label);
        renderStoredFile($label, finalUrl, file.filename || file.name || humanize(finalUrl || url), ext);
        $label.find('.bb-upload-original').data('bb-url', finalUrl).data('bb-id', file.id || '').data('bb-ext', ext);
        $('#bb-print-locations .bb-validation').remove(); $label.removeClass('bb-missing');
        storeHidden(slug, finalUrl, file.id || '');
        document.dispatchEvent(new CustomEvent('bb:vector:selected', { bubbles: true, detail: { id: file.id, url: finalUrl } }));
      });
    });
    frame.on('close', function(){
      setOriginalArtUploadFlag(false);
    });
    frame.open();
  }
  function hasFileFor($label){
    var btn  = $label.find('.bb-upload-original');
    var pill = $label.find('.bb-file-pill');
    var urlBtn  = (btn.data('bb-url') || '');
    var urlPill = pill.attr('data-url') || '';
    return !!(urlBtn || (urlPill && urlPill.trim()));
  }
  function currentFileUrl($label){
    var btn  = $label.find('.bb-upload-original');
    var pill = $label.find('.bb-file-pill');
    var urlBtn  = (btn.data('bb-url') || '');
    var urlPill = pill.attr('data-url') || '';
    return (urlBtn || urlPill || '').trim();
  }
  function currentFileExt($label){
    var btn  = $label.find('.bb-upload-original');
    var pill = $label.find('.bb-file-pill');
    var extBtn = String(btn.data('bb-ext') || '').toLowerCase().trim();
    var extPill = String(pill.attr('data-ext') || '').toLowerCase().trim();
    if (extBtn) return extBtn;
    if (extPill) return extPill;
    return extOf(currentFileUrl($label));
  }
  function enforceProductionForLabel($label){
    var url = currentFileUrl($label);
    if(!url) { clearInline($label); return; }
    var ext = currentFileExt($label);
    var allowed = allowedOriginals;
    if (ext && allowed.indexOf(ext) === -1) {
      clearStoredFile($label);
      showError($label, allowedMessage());
    } else {
      clearInline($label);
    }
  }
  function enforceProductionForAll(){
    $('#bb-print-locations .bb-location-check').each(function(){
      enforceProductionForLabel($(this));
    });
  }
  function syncPrintLocationVisibility(){
    var fulfill = isFulfillProduction();
    var $wrap = $('#bb-print-locations');
    var $row = $wrap.closest('tr');
    var $host = $row.length ? $row : $wrap;

    if (!$wrap.length) return;

    $host.toggle(!fulfill);
    $wrap.attr('aria-hidden', fulfill ? 'true' : 'false');
    $wrap.find('.bb-location-checkbox, .bb-upload-original, input[type="hidden"]').prop('disabled', fulfill);

    if (fulfill) {
      clearErrors();
      $wrap.find('.bb-inline-mime-error').remove();
      return;
    }

    $wrap.find('.bb-location-check').each(function(){
      var $label = $(this);
      var $cb = $label.find('.bb-location-checkbox');
      onToggle($cb);
      enforceProductionForLabel($label);
    });
  }
  function onToggle($cb){
    var $label = $cb.closest('label.bb-location-check'); ensureRow($label);
    var $btn  = $label.find('.bb-upload-original'); var $pill = $label.find('.bb-file-pill');
    if ($cb.is(':checked')) {
      $btn.show(); $pill.toggle(hasFileFor($label));
    } else {
      $btn.hide().data('bb-url','').data('bb-id','').data('bb-ext',''); $pill.hide().attr('data-url','').attr('data-ext','').empty(); $label.removeClass('bb-missing'); clearInline($label);
      var id = ($cb.attr('id') || '').replace('bb_loc_',''); var wrap = $('#bb-print-locations'); wrap.find('input[type="hidden"][name="art_'+id+'_url"]').val(''); wrap.find('input[type="hidden"][name="art_'+id+'_id"]').val('');
    }
  }
  $(document).on('change', '.bb-location-checkbox', function(){ onToggle($(this)); });
  $(document).on('click', '.bb-upload-original', function(e){ e.preventDefault(); var slug = String($(this).data('slug') || '').replace('bb_loc_',''); if(!slug){ var id = $(this).closest('label.bb-location-check').find('input.bb-location-checkbox').attr('id') || ''; slug = id.replace('bb_loc_',''); } if(slug){ openPicker(slug); } });
  $(function(){
    $('.bb-location-check').each(function(){
      var $label = $(this);
      ensureRow($label);
      var $cb = $label.find('.bb-location-checkbox');
      if ($cb.is(':checked')) {
        onToggle($cb);
      }
    });
    syncPrintLocationVisibility();
  });

  function getChecked(){ return Array.prototype.slice.call(document.querySelectorAll('#bb-print-locations .bb-location-check input.bb-location-checkbox:checked')); }
  function clearErrors(){ $('#bb-print-locations .bb-validation').remove(); $('.bb-location-check').removeClass('bb-missing'); }
  function showValidation(msg){ var host = document.querySelector('#bb-print-locations .bb-section.bb-phase1') || document.getElementById('bb-print-locations'); if(!host) return; var div = document.createElement('div'); div.className='bb-validation'; div.style.cssText='color:#a40000;margin-top:6px;'; div.textContent=msg; host.appendChild(div); }
  $(document).on('change', '#bb_production', function(){
    enforceProductionForAll();
    syncPrintLocationVisibility();
  });

  window.BumblebeeLocations = {
    validate: function(){
      clearErrors();
      if (isFulfillProduction()) return { ok:true, names:[], error:null };
      var checked = getChecked();
      if(checked.length === 0){ showValidation('Select at least one Print Location.'); return { ok:false, names:[], error:'printloc' }; }
      var missing = 0, names=[];
      checked.forEach(function(cb){
        var label = cb.closest('label.bb-location-check'); if(!label) return;
        var name = (label.querySelector('span') || {}).textContent || cb.getAttribute('data-name') || cb.value || '';
        if(name) names.push(name.trim());
        if(!hasFileFor($(label))){ missing++; $(label).addClass('bb-missing'); }
      });
      if(missing>0){ showValidation('Upload original art for every selected Print Location.'); return { ok:false, names:names, error:'art' }; }
      return { ok:true, names:names, error:null };
    }
  };
})(jQuery);
