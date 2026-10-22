(function($){
  'use strict';

  function pick(cb, type){
    const frame = wp.media({
      title: 'Select File',
      multiple: false,
      library: type ? { type } : undefined,
      button: { text: 'Select' }
    });

    frame.on('select', function(){
      const a = frame.state().get('selection').first().toJSON();
      cb(a);
    });

    frame.open();
  }

  function showInlineError($input, msg){
    if(!$input || !$input.length) return;
    var id = $input.attr('id');
    var $e = $('.bb-inline-error[data-for="'+id+'"]');
    $input.addClass('bb-error');
    if($e.length){ $e.text(msg || '').addClass('show'); }
  }
  function clearInlineError($input){
    if(!$input || !$input.length) return;
    var id = $input.attr('id');
    var $e = $('.bb-inline-error[data-for="'+id+'"]');
    $input.removeClass('bb-error');
    if($e.length){ $e.removeClass('show').text(''); }
  }
  function fileNameFromUrl(url){
    try {
      var clean = String(url || '').split('?')[0];
      return clean.split('/').pop() || '';
    } catch(e){
      return '';
    }
  }
  function renderMockupPreview($preview, url, name){
    if(!$preview || !$preview.length) return;
    $preview.empty();

    var safeUrl = String(url || '').trim();
    if (!safeUrl) return;
    var label = String(name || '').trim() || fileNameFromUrl(safeUrl) || safeUrl;

    $('<a>', {
      class: 'bb-mockup-preview',
      href: safeUrl,
      target: '_blank',
      rel: 'noopener'
    })
      .append($('<img>', { class: 'bb-mockup-thumb', src: safeUrl, alt: '' }))
      .appendTo($preview);

    $('<a>', {
      class: 'bb-mockup-filename',
      href: safeUrl,
      target: '_blank',
      rel: 'noopener',
      text: label
    }).appendTo($preview);
  }
  function mockupExt(a){
    if(!a) return '';
    if (a.mime) {
      var mime = String(a.mime).toLowerCase();
      if (mime === 'image/webp') return 'webp';
      if (mime === 'image/png') return 'png';
      if (mime === 'image/jpeg' || mime === 'image/jpg') return 'jpg';
    }
    var url = a.url || '';
    var ext = (url || '').split('?')[0].split('.').pop() || '';
    return String(ext).toLowerCase();
  }
  function isAllowedMockupAttachment(a){
    var ext = mockupExt(a);
    return ext === 'webp' || ext === 'png' || ext === 'jpg' || ext === 'jpeg';
  }
  function setPickerBusy($btn, busy){
    if(!$btn || !$btn.length) return;
    var defaultText = $btn.data('bb-default-text');
    if(!defaultText){
      defaultText = $btn.text() || 'Upload/Choose';
      $btn.data('bb-default-text', defaultText);
    }
    if (busy) {
      $btn.prop('disabled', true).text('Processing...');
    } else {
      $btn.prop('disabled', false).text($btn.data('bb-default-text') || 'Upload/Choose');
    }
  }
  function responseMessage(resp, fallback){
    var msg = '';
    if (resp && resp.data && resp.data.message) msg = String(resp.data.message);
    if (!msg && resp && resp.message) msg = String(resp.message);
    return msg || fallback;
  }
  function currentOriginalArtIds(){
    var out = [];
    document.querySelectorAll('#bb-print-locations input[type="hidden"][name^="art_"][name$="_id"]').forEach(function(inp){
      var id = parseInt((inp && inp.value) ? inp.value : '', 10) || 0;
      if (id > 0) out.push(id);
    });
    return out;
  }
  function prepareMockup(index, a, $btn){
    const $input = $('#bb_color_image_'+index);
    const $preview = $('.bb-color-preview[data-color-index="'+index+'"]');
    const ajaxurl = (typeof BumblebeeCreate !== 'undefined' && BumblebeeCreate.ajaxurl) ? BumblebeeCreate.ajaxurl : '';
    const nonce = (typeof BumblebeeCreate !== 'undefined' && BumblebeeCreate.prepareMockupNonce) ? BumblebeeCreate.prepareMockupNonce : '';

    if (!ajaxurl || !nonce) {
      $input.val('');
      if ($preview.length) $preview.html('');
      showInlineError($input, 'Mockup processing is unavailable. Please refresh and try again.');
      return;
    }

    var sourceId = a && a.id ? parseInt(a.id, 10) || 0 : 0;
    var preserveOriginal = sourceId > 0 && currentOriginalArtIds().indexOf(sourceId) !== -1;

    setPickerBusy($btn, true);
    clearInlineError($input);
    $.post(ajaxurl, {
      action: 'bb_prepare_mockup',
      nonce: nonce,
      attachment_id: sourceId,
      preserve_original: preserveOriginal ? 1 : 0,
      product_title: ($('#bb_title').val() || '').trim()
    })
      .done(function(resp){
        if (!resp || !resp.success || !resp.data) {
          $input.val('');
          if ($preview.length) $preview.html('');
          showInlineError($input, responseMessage(resp, 'Mockup Image could not be prepared.'));
          return;
        }

        var imageId = parseInt(resp.data.image_id, 10) || 0;
        var url = resp.data.url || (a && a.url ? a.url : '');
        if (!imageId) {
          $input.val('');
          if ($preview.length) $preview.html('');
          showInlineError($input, 'Mockup Image could not be prepared.');
          return;
        }

        $input.val(imageId);
        renderMockupPreview($preview, url, fileNameFromUrl(url));
        clearInlineError($input);
        $(document).trigger('bb:color:image:chosen', [index]);
      })
      .fail(function(xhr){
        var resp = xhr && xhr.responseJSON ? xhr.responseJSON : null;
        $input.val('');
        if ($preview.length) $preview.html('');
        showInlineError($input, responseMessage(resp, 'Mockup Image could not be prepared.'));
      })
      .always(function(){
        setPickerBusy($btn, false);
      });
  }
  function chooseColorImage(index, $btn){
    pick(function(a){
      const $input = $('#bb_color_image_'+index);
      const $preview = $('.bb-color-preview[data-color-index="'+index+'"]');
      if (!isAllowedMockupAttachment(a)) {
        $input.val('');
        if ($preview.length) $preview.html('');
        showInlineError($input, 'Mockup Image must be a .webp, .png, or .jpg file.');
        return;
      }
      prepareMockup(index, a, $btn);
    }, 'image');
  }

  $(document).on('click', '.bb-color-pick', function(e){
    e.preventDefault();
    const idx = $(this).data('color-index');
    if (idx == null) return;
    chooseColorImage(idx, $(this));
  });

})(jQuery);
