(function($){
  $('#bb_orphan_preview').on('click', function(e){
    e.preventDefault(); $('#bb_orphan_result').text('Working...');
    $.post(BumblebeeSettings.ajaxurl, {action:'bumblebee_orphan_sweep_preview', nonce:BumblebeeSettings.nonce}, function(res){
      if(res && res.success){ $('#bb_orphan_result').text(res.data.count+' orphan(s) found').data('ids', res.data.ids||[]); $('#bb_orphan_delete').prop('disabled',(res.data.count||0)===0); }
      else { $('#bb_orphan_result').text('Error'); }
    });
  });
  $('#bb_orphan_delete').on('click', function(e){
    e.preventDefault(); const ids = $('#bb_orphan_result').data('ids')||[]; $('#bb_orphan_result').text('Deleting...');
    $.post(BumblebeeSettings.ajaxurl, {action:'bumblebee_orphan_sweep_delete', nonce:BumblebeeSettings.nonce, ids:ids}, function(res){
      if(res && res.success){ $('#bb_orphan_result').text('Deleted '+(res.data.deleted||0)+' item(s)'); $('#bb_orphan_delete').prop('disabled',true); }
      else { $('#bb_orphan_result').text('Error'); }
    });
  });
})(jQuery);