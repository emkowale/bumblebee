(function($){
  'use strict';
  var allowed = ['svg','pdf','ai','eps'];

  function extOf(url){
    var s = String(url || '').split('?')[0];
    var parts = s.split('.');
    return (parts.length > 1 ? parts.pop() : '').toLowerCase();
  }
  function humanize(url){
    try{ var name = url.split('/').pop().split('?')[0]; return name.replace(/\.[^.]+$/,'').replace(/[-_]+/g,' ').replace(/\b\w/g, function(c){return c.toUpperCase();}); }
    catch(e){ return url || ''; }
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
  function openPicker(slug){
    var frame = wp.media({ title: 'Select Original Art', button: { text: 'Use this file' }, multiple: false });
    frame.on('select', function(){
      var file = frame.state().get('selection').first().toJSON();
      var url  = file.url || '';
      var ext  = extOf(file.filename || file.name || url);
      var $label = $('#bb_loc_' + slug).closest('label.bb-location-check');
      if (allowed.indexOf(ext) === -1) { $label.find('.bb-file-pill').text('').removeAttr('data-url').hide(); $label.find('.bb-upload-original').removeData('bb-url').removeData('bb-id'); showError($label, 'Only SVG, PDF, AI, or EPS allowed.'); return; }
      clearInline($label);
      $label.find('.bb-file-pill').text(humanize(url)).attr('data-url', url).show();
      $label.find('.bb-upload-original').data('bb-url', url).data('bb-id', file.id || '');
      $('#bb-print-locations .bb-validation').remove(); $label.removeClass('bb-missing');
      storeHidden(slug, url, file.id || '');
      document.dispatchEvent(new CustomEvent('bb:vector:selected', { bubbles: true, detail: { id: file.id, url: url } }));
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
  function onToggle($cb){
    var $label = $cb.closest('label.bb-location-check'); ensureRow($label);
    var $btn  = $label.find('.bb-upload-original'); var $pill = $label.find('.bb-file-pill');
    if ($cb.is(':checked')) {
      $btn.show(); $pill.toggle(hasFileFor($label));
    } else {
      $btn.hide().data('bb-url','').data('bb-id',''); $pill.hide().attr('data-url','').text(''); $label.removeClass('bb-missing'); clearInline($label);
      var id = ($cb.attr('id') || '').replace('bb_loc_',''); var wrap = $('#bb-print-locations'); wrap.find('input[type="hidden"][name="art_'+id+'_url"]').val(''); wrap.find('input[type="hidden"][name="art_'+id+'_id"]').val('');
    }
  }
  $(document).on('change', '.bb-location-checkbox', function(){ onToggle($(this)); });
  $(document).on('click', '.bb-upload-original', function(e){ e.preventDefault(); var slug = String($(this).data('slug') || '').replace('bb_loc_',''); if(!slug){ var id = $(this).closest('label.bb-location-check').find('input.bb-location-checkbox').attr('id') || ''; slug = id.replace('bb_loc_',''); } if(slug){ openPicker(slug); } });
  $(function(){ $('.bb-location-check').each(function(){ var $label=$(this); ensureRow($label); var $cb=$label.find('.bb-location-checkbox'); if ($cb.is(':checked')) { onToggle($cb); } }); });

  function getChecked(){ return Array.prototype.slice.call(document.querySelectorAll('#bb-print-locations .bb-location-check input.bb-location-checkbox:checked')); }
  function clearErrors(){ $('#bb-print-locations .bb-validation').remove(); $('.bb-location-check').removeClass('bb-missing'); }
  function showValidation(msg){ var host = document.querySelector('#bb-print-locations .bb-section.bb-phase1') || document.getElementById('bb-print-locations'); if(!host) return; var div = document.createElement('div'); div.className='bb-validation'; div.style.cssText='color:#a40000;margin-top:6px;'; div.textContent=msg; host.appendChild(div); }

  window.BumblebeeLocations = {
    validate: function(){
      clearErrors();
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
