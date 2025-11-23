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

  function chooseImage(){
    pick(function(a){
      $('#bb_image_id').val(a.id);
      $('#bb_image_preview').html(a.url ? '<img src="'+a.url+'" style="max-height:40px;vertical-align:middle;" />' : '');
      $(document).trigger('bb:image:chosen');
    }, 'image');
  }

  function chooseColorImage(index){
    pick(function(a){
      const $input = $('#bb_color_image_'+index);
      const $preview = $('.bb-color-preview[data-color-index="'+index+'"]');
      $input.val(a.id);
      if ($preview.length) $preview.html(a.url ? '<img src="'+a.url+'" alt="" />' : '');
      $(document).trigger('bb:color:image:chosen', [index]);
    }, 'image');
  }

  $(document).on('click', '#bb_pick_image', function(e){
    e.preventDefault();
    chooseImage();
  });

  $(document).on('click', '.bb-color-pick', function(e){
    e.preventDefault();
    const idx = $(this).data('color-index');
    if (idx == null) return;
    chooseColorImage(idx);
  });

})(jQuery);
