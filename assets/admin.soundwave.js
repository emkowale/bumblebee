(function($){
  'use strict';

  function escHtml(str){
    return String(str || '')
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#39;');
  }

  function render(data){
    var $box = $('#bb-soundwave-box');
    if(!$box.length) return;
    var $status = $box.find('.bb-sw-status');
    var $list = $box.find('.bb-sw-list');
    if(!data || !data.checks){
      $status.text('Unable to load status.');
      $list.html('<li class="bb-sw-item"><span class="dashicons dashicons-no-alt"></span>Error</li>');
      return;
    }
    $status.text(data.ready ? 'Ready' : 'Not Ready');
    var html = '';
    data.checks.forEach(function(c){
      var icon = c.ok ? 'dashicons-yes' : 'dashicons-no-alt';
      html += '<li class="bb-sw-item ' + (c.ok ? 'ok' : 'fail') + '">'
        + '<span class="dashicons ' + icon + '"></span>'
        + '<span class="bb-sw-label">' + escHtml(c.label) + '</span>'
        + '</li>';
    });
    $list.html(html);
    $box.toggleClass('bb-sw-ready', !!data.ready);
  }

  function fetchStatus(){
    if(!window.BumblebeeSoundwave) return;
    $.post(BumblebeeSoundwave.ajaxurl, {
      action: 'bumblebee_soundwave_status',
      nonce: BumblebeeSoundwave.nonce,
      post_id: BumblebeeSoundwave.post_id
    }).done(function(resp){
      if(resp && resp.success){
        render(resp.data);
      }
    });
  }

  $(document).ready(function(){
    fetchStatus();
    var deb;
    $('#post').on('change input', function(){
      clearTimeout(deb);
      deb = setTimeout(fetchStatus, 1500);
    });
    setInterval(fetchStatus, 10000);
  });
})(jQuery);
