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
})(jQuery);
