/*
 * Bumblebee admin UI (Variations tab)
 * - Hide Woo generator
 * - Thumbnails for Original Art + Base images (no text boxes)
 * - Quality dropdown
 * - Auto-detect Color/Print attribute keys and send with request
 * v1.3.1
*/
jQuery(function($){
  if (typeof BEE === 'undefined') return;

  /* ---- Nuke Woo's generator controls everywhere ---- */
  function hideWooGen(ctx){
    const $c = ctx ? $(ctx) : $(document);
    $c.find('select.variable_actions option[value="link_all_variations"]').remove();
    $c.find('select.variations-select option[value="link_all_variations"]').remove();
    $c.find('.variations_options .toolbar .button.generate_variations').hide();
    $c.find('.button.link_all_variations, .generate_variations, a.generate_variations, a.link_all_variations').hide();
    $c.find('select.variable_actions, select.variations-select').each(function(){
      if ($(this).val()==='link_all_variations') $(this).val('');
    });
  }

  /* ---- Attribute auto-detection ---- */
  const COLOR_HINTS=['color','colour','shade','hue'];
  const PRINT_HINTS=['print','location','placement','side','front','back','sleeve','chest'];

  function pickAttr(hints){
    if (!Array.isArray(BEE.attrs)) return null;
    const ranked = BEE.attrs.filter(a=>a.is_var).map(a=>{
      const n=(a.name||a.key||'').toLowerCase();
      let score=0; hints.forEach(h=>{ if(n.includes(h)) score+=2; });
      if (hints===PRINT_HINTS && Array.isArray(a.values)){
        const hasF=a.values.some(v=>/front/i.test(v.label||v.slug));
        const hasB=a.values.some(v=>/back/i.test(v.label||v.slug));
        if (hasF) score+=2; if (hasB) score+=2;
      }
      return {a,score};
    }).sort((x,y)=>y.score-x.score);
    return ranked.length? ranked[0].a : null;
  }
  const colorAttr = pickAttr(COLOR_HINTS);
  const printAttr = pickAttr(PRINT_HINTS);
  const qualityAttr = (BEE.attrs||[]).find(a=>/(^|[^a-z])quality([^a-z]|$)/i.test(a.key||a.name||''));

  /* ---- Build UI ---- */
  function ensureUI(){
    if ($('#bee-panel').length) return;
    const $panel = $(`
      <div id="bee-panel" style="margin:12px 0 16px;padding:14px;border:1px solid #dcdcde;border-radius:8px;background:#f8f9fb">
        <h4 style="margin:0 0 10px;font-weight:600">🐝 Bumblebee — Variations & Mockups</h4>

        <div class="bee-row" style="display:flex;align-items:center;gap:12px;margin:8px 0;">
          <div style="width:160px;font-weight:600;">Original Art</div>
          <input type="hidden" id="bee-art-url">
          <button class="button bee-pick" data-target="art">Choose</button>
          <div class="bee-thumb" data-for="art" style="min-height:52px;display:flex;align-items:center;"></div>
        </div>

        <div class="bee-row" style="display:flex;align-items:center;gap:12px;margin:8px 0;">
          <div style="width:160px;font-weight:600;">Base (front/default)</div>
          <input type="hidden" id="bee-base-front">
          <button class="button bee-pick" data-target="base:front">Choose</button>
          <div class="bee-thumb" data-for="front" style="min-height:52px;display:flex;align-items:center;"></div>
        </div>

        <div id="bee-bases" style="margin-left:160px;"></div>

        <div class="bee-row" style="display:flex;align-items:center;gap:12px;margin:12px 0;">
          <div style="width:160px;font-weight:600;">Quality</div>
          <select id="bee-quality" style="min-width:240px;"></select>
        </div>

        <div class="bee-row" style="display:flex;align-items:center;gap:12px;margin-top:12px;">
          <button class="button button-primary" id="bee-run">Generate Variations + Mockups</button>
          <span id="bee-status" style="margin-left:4px;"></span>
        </div>
      </div>
    `);
    // Insert at top of variations section
    $('#variable_product_options').prepend($panel);
  }

  function renderQuality(){
    const $q=$('#bee-quality').empty();
    if (!qualityAttr || !Array.isArray(qualityAttr.values) || !qualityAttr.values.length){
      $q.append('<option value="">(no quality attribute found)</option>');
      return;
    }
    qualityAttr.values.forEach(v=>{
      $q.append($('<option>').val(v.slug).text(v.label));
    });
  }

  function renderPrintLocationRows(){
    const $wrap = $('#bee-bases').empty();
    if (!printAttr || !Array.isArray(printAttr.values)) return;
    const seen=new Set(['front']);
    printAttr.values.forEach(v=>{
      const slug=(v.slug||'').toLowerCase(); if(!slug||seen.has(slug)) return; seen.add(slug);
      const row = $(`
        <div class="bee-row" data-slug="${slug}" style="display:flex;align-items:center;gap:12px;margin:8px 0;">
          <input type="hidden" id="bee-base-${slug}">
          <button class="button bee-pick" data-target="base:${slug}">Choose ${v.label}</button>
          <div class="bee-thumb" data-for="${slug}" style="min-height:52px;display:flex;align-items:center;"></div>
        </div>`);
      $wrap.append(row);
    });
  }

  /* ---- Media pickers ---- */
  function showThumb($container, src){
    const img = $('<img>').attr('src',src).css({maxHeight:'48px',maxWidth:'96px',border:'1px solid #e2e4e7',borderRadius:'4px',background:'#fff',padding:'2px',boxShadow:'0 1px 0 rgba(0,0,0,.03)'});
    $container.empty().append(img);
  }
  function pick($btn){
    const t=$btn.data('target'); const [mode,slug] = String(t).split(':');
    const frame=wp.media({title:'Select image',button:{text:'Use this'},multiple:false});
    frame.on('select',function(){
      const att=frame.state().get('selection').first().toJSON();
      if(mode==='art'){
        $('#bee-art-url').val(att.url);
        showThumb($('.bee-thumb[data-for="art"]'), att.url);
      }else{
        $('#bee-base-'+slug).val(att.id);
        const thumb=(att.sizes && att.sizes.thumbnail && att.sizes.thumbnail.url) ? att.sizes.thumbnail.url : (att.icon||att.url);
        showThumb($('.bee-thumb[data-for="'+slug+'"]'), thumb);
      }
    });
    frame.open();
  }

  /* ---- Collect + Run ---- */
  function collectBases(){
    const map={};
    $('#bee-panel input[id^="bee-base-"]').each(function(){
      const slug = this.id.replace('bee-base-','');
      const id = parseInt($(this).val()||'0',10); if(id) map[slug]=id;
    });
    return map;
  }

  function run(){
    $('#bee-status').text('Working…');
    $.post(BEE.ajax,{
      action:'bee_generate_variations',
      nonce:BEE.nonce,
      product_id:BEE.productId,
      color_key: (colorAttr? colorAttr.key : ''),
      print_key: (printAttr? printAttr.key : ''),
      art_url: $('#bee-art-url').val(),
      bases: JSON.stringify(collectBases()),
      quality_key: (qualityAttr? qualityAttr.key : ''),
      quality_value: $('#bee-quality').val()
    },function(res){
      if(!res || !res.success){
        $('#bee-status').text('❌ '+(res && res.data || 'error'));
        return;
      }
      $('#bee-status').text('✅ Variation '+res.data.variation_id+(res.data.with_image?' with mockup':''));
      // Nudge Woo UI to refresh list
      $('button.cancel-variation-changes,button.save-variation-changes').prop('disabled',false);
      $('button.cancel-variation-changes').trigger('click');
    });
  }

  /* ---- Boot & observers ---- */
  function boot(){
    hideWooGen(document);
    ensureUI();
    renderQuality();
    renderPrintLocationRows();
    $(document)
      .off('click.bee','.bee-pick').on('click.bee','.bee-pick',function(e){e.preventDefault();pick($(this));})
      .off('click.bee','#bee-run').on('click.bee','#bee-run',function(e){e.preventDefault();run();});
  }
  boot();
  $(document).on('woocommerce_variations_loaded', function(){ hideWooGen(document); if(!$('#bee-panel').length) boot(); });

  const container=document.querySelector('#variable_product_options');
  if(container && 'MutationObserver' in window){
    const mo=new MutationObserver(()=>hideWooGen(container));
    mo.observe(container,{childList:true,subtree:true,attributes:true});
  }
});
