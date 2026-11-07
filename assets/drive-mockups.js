(function($){
  'use strict';
  function message(resp, fallback){
    return resp && resp.data && resp.data.message ? resp.data.message : fallback;
  }
  function request(action, data){
    return $.post(BumblebeeDrive.ajaxurl, $.extend({action:action, nonce:BumblebeeDrive.nonce}, data));
  }
  $(document).on('change', '#bb_mockup_source', function(){
    var $select = $(this), value = $select.val(), $status = $('#bb-mockup-source-status');
    var previous = $select.data('saved-source') || (value === 'drive' ? 'media' : 'drive');
    $select.prop('disabled', true);
    $status.text('Saving…');
    request('bb_mockup_source', {source:value}).done(function(resp){
      if (resp && resp.success) {
        $select.data('saved-source', value);
        $status.text('Preference saved.');
      } else {
        $select.val(previous);
        $status.text(message(resp, 'Could not save your preference. Please try again.'));
      }
    }).fail(function(){
      $select.val(previous);
      $status.text('Could not save your preference. Please try again.');
    }).always(function(){ $select.prop('disabled', false); });
  });
  window.BumblebeeDrivePicker = function(onSelect){
    if (document.querySelector('dialog.bb-drive-dialog')) return;
    var $dialog = $('<dialog>', {class:'bb-drive-dialog', 'aria-labelledby':'bb-drive-title'});
    var $title = $('<h2>', {id:'bb-drive-title', text:'Choose a Google Drive mockup'});
    var $close = $('<button>', {type:'button', class:'button', text:'Cancel'});
    var $input = $('<input>', {type:'text', id:'bb-drive-file-link', placeholder:'https://drive.google.com/file/d/…/view'});
    var $choose = $('<button>', {type:'button', class:'button button-primary', text:'Use image'});
    var $browse = $('<button>', {type:'button', class:'button', text:'Browse configured folder'});
    var $status = $('<p>', {role:'status', 'aria-live':'polite'});
    var $list = $('<ul>');
    var $more = $('<button>', {type:'button', class:'button', text:'Load more'}).hide();
    var pending = false, closed = false, pageToken = '';
    $dialog.append($title, $('<label>', {for:'bb-drive-file-link', text:'Drive image link or file ID'}), $input,
      $('<p>', {text:'Use a PNG, JPG, or WebP image. Without a private Drive connection, share it as Anyone with the link.'}),
      $('<p>').append($choose, ' ', $browse, ' ', $close), $status, $list, $more).appendTo(document.body);
    var opener = document.activeElement;
    function close(){ closed = true; $dialog[0].close(); $dialog.remove(); if(opener) opener.focus(); }
    $close.on('click', close);
    $dialog.on('cancel', function(e){ e.preventDefault(); close(); });
    function busy(value){ pending=value; $dialog.find('button').not($close).prop('disabled', value); }
    function select(file){
      if (pending || !file) return;
      busy(true); $status.text('Loading image…');
      request('bb_drive_mockup_select', {file:file}).done(function(resp){
        if (closed) return;
        if (resp && resp.success && resp.data && resp.data.image_id) { onSelect(resp.data); close(); }
        else $status.text(message(resp, 'Could not load the image.'));
      }).fail(function(xhr){ if (!closed) $status.text(message(xhr.responseJSON, 'Could not load the image. Please try again.')); })
        .always(function(){ if (!closed) busy(false); });
    }
    $choose.on('click', function(){
      var value = $input.val().trim();
      if (!value) { $status.text('Enter a Drive image link or file ID.'); $input.trigger('focus'); return; }
      select(value);
    });
    $input.on('keydown', function(e){ if(e.key === 'Enter') { e.preventDefault(); $choose.trigger('click'); } });
    function browse(more){
      if(pending) return;
      busy(true); $status.text('Loading folder…');
      request('bb_drive_mockups_list', {page_token:more ? pageToken : ''}).done(function(resp){
        if(closed) return;
        if(!resp || !resp.success) { $status.text(message(resp, 'Could not open the folder.')); return; }
        if(!more) $list.empty();
        var files=resp.data.files || [];
        files.forEach(function(file){
          $('<li>').append($('<button>', {type:'button', class:'button bb-drive-file', text:file.name}).on('click', function(){ select(file.id); })).appendTo($list);
        });
        pageToken=resp.data.nextPageToken || '';
        $more.toggle(!!pageToken);
        $status.text($list.children().length ? 'Select an image below.' : 'No PNG, JPG, or WebP images in this folder.');
      }).fail(function(xhr){ if(!closed) $status.text(message(xhr.responseJSON, 'Could not open the folder.')); })
        .always(function(){ if(!closed) busy(false); });
    }
    $browse.on('click', function(){ browse(false); });
    $more.on('click', function(){ browse(true); });
    $dialog[0].showModal();
    $input.trigger('focus');
  };
})(jQuery);
