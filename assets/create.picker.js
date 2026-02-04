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

    frame.on('open', function(){
      frame.$el.off('click.bb-one').on('click.bb-one', '.attachments .attachment', function(){
        setTimeout(function(){
          const $select = frame.$el.find('.media-button-select');
          if ($select.length) $select.trigger('click');
        }, 0);
      });
    });

    frame.on('close', function(){
      frame.$el.off('click.bb-one');
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
  function isWebpAttachment(a){
    if(!a) return false;
    if (a.mime && String(a.mime).toLowerCase() === 'image/webp') return true;
    var url = a.url || '';
    var ext = (url || '').split('?')[0].split('.').pop() || '';
    return String(ext).toLowerCase() === 'webp';
  }
  function chooseColorImage(index){
    pick(function(a){
      const $input = $('#bb_color_image_'+index);
      const $preview = $('.bb-color-preview[data-color-index="'+index+'"]');
      if (!isWebpAttachment(a)) {
        $input.val('');
        if ($preview.length) $preview.html('');
        showInlineError($input, 'Mockup Image must be a .webp file.');
        return;
      }
      $input.val(a.id);
      if ($preview.length) $preview.html(a.url ? '<img src="'+a.url+'" alt="" />' : '');
      clearInlineError($input);
      $(document).trigger('bb:color:image:chosen', [index]);
    }, 'image/webp');
  }

  $(document).on('click', '.bb-color-pick', function(e){
    e.preventDefault();
    const idx = $(this).data('color-index');
    if (idx == null) return;
    chooseColorImage(idx);
  });

})(jQuery);
