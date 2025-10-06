jQuery(function($){
  function pick(cb){const f=wp.media({title:'Select',multiple:false,library:{type:'image'}});f.on('select',()=>cb(f.state().get('selection').first().toJSON()));f.open();}
  function printsCSV(){const v=[];$('#bee_print_checks input:checked').each(function(){v.push($(this).val());});return v.join(',');}
  function allRequired(){const ids=['#bee_price','#bee_image_url','#bee_art_url','#bee_colors','#bee_sizes','#bee_quality'];for(const s of ids){if(!$(s).val().trim()) return false;}return printsCSV().length>0;}
  function gateCheck(){
    if(!BEE.aiReady){ $('#bee_status').html('Add an OpenAI key (loaded from <code>includes/secret_key.php</code>). <a href="'+BEE.settingsUrl+'">Settings</a>').css('color','#b91c1c'); return false; }
    if(!BEE.stylesCount || BEE.stylesCount<1){ $('#bee_status').html('Select at least one <strong>Copy Style</strong> in <a href="'+BEE.settingsUrl+'">Settings</a>.').css('color','#b91c1c'); return false; }
    return true;
  }
  $('#bee_pick_art').on('click',()=>pick(a=>$('#bee_art_url').val(a.url)));
  $('.bee-pick[data-target="image"]').on('click',()=>pick(a=>{$('#bee_image_url').val(a.url);$('#bee_image_id').val(a.id);}));

  $('#bee_create').on('click',function(){
    if(!gateCheck()) return;
    if(!allRequired()){ $('#bee_status').text('All fields are required (incl. ≥1 Print Location).').css('color','#b91c1c'); return; }
    $('#bee_status').text('Creating…').css('color','inherit');
    $.post(BEE.ajax,{
      action:'bee_create_product',nonce:BEE.nonce,
      price:$('#bee_price').val().trim(),taxable:$('#bee_taxable').val(),
      image_url:$('#bee_image_url').val().trim(),image_id:$('#bee_image_id').val().trim(),
      art_url:$('#bee_art_url').val().trim(),colors:$('#bee_colors').val().trim(),
      sizes:$('#bee_sizes').val().trim(),prints:printsCSV(),quality:$('#bee_quality').val().trim(),
      vendor_url:$('#bee_vendor_url').val().trim()
    }).done(function(r){
      if(r&&r.success){
        const d=r.data||{};$('#bee_status').html('Created product #'+d.product_id+' with '+d.variation_count+' variations. <a href="'+d.edit_url+'">Open editor</a>').css('color','#065f46');
      }else{
        const d=r&&r.data;
        if(d && d.code==='vendor-page-unavailable'){
          let msg='Could not fetch vendor page.';
          if(d.guess_url){ msg+=' We think this is it: <a href="'+d.guess_url+'" target="_blank" rel="noopener">open link</a>.'; }
          msg+=' Paste the exact URL in “Vendor Product URL” and try again.';
          $('#bee_status').html(msg).css('color','#b91c1c');
        }else{
          $('#bee_status').html('Error: '+(d? (d.message||d) : 'unknown')).css('color','#b91c1c');
        }
      }
    }).fail(()=>$('#bee_status').text('AJAX failed').css('color','#b91c1c'));
  });

  // Settings: Test OpenAI
  $('#bee_test_ai').on('click',function(){
    $('#bee_ai_status').text('Testing…').css('color','inherit');
    $.post(BEE.ajax,{action:'bee_test_ai',nonce:BEE.nonce}).done(r=>{
      if(r&&r.success) $('#bee_ai_status').text('✅ OK').css('color','#065f46');
      else $('#bee_ai_status').text('❌ '+(r&&r.data?r.data:'failed')).css('color','#b91c1c');
    }).fail(()=>$('#bee_ai_status').text('❌ HTTP error').css('color','#b91c1c'));
  });
});
