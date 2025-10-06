/*
 * File: assets/admin.js
 * Description: Bumblebee Variations-tab UI. Force-hides Woo generator, Original Art picker (with thumbnail incl. SVG), auto-detects Color/Print, base images per location, runs generator.
 * Plugin: Bumblebee
 * Author: Eric Kowalewski
 * Last Updated: 2025-09-02 (EDT)
 */

jQuery(function($){
  /* -------- Helpers to nuke Woo's generator everywhere -------- */
  function hideWooGeneratorStrict(ctx){
    const $ctx = ctx ? $(ctx) : $(document);

    // Remove the "Link all variations" option from any action dropdown
    $ctx.find('select.variable_actions option[value="link_all_variations"]').remove();
    $ctx.find('select.variations-select option[value="link_all_variations"]').remove();

    // Hide any stock generator buttons/links
    $ctx.find('.variations_options .toolbar .button.generate_variations').hide();
    $ctx.find('.button.link_all_variations').hide();
    // Some themes/plugins duplicate the button elsewhere:
    $ctx.find('.generate_variations, .link_all_variations, a.generate_variations, a.link_all_variations').hide();

    // If a dropdown still has link_all_variations selected, reset it
    $ctx.find('select.variable_actions, select.variations-select').each(function(){
      const v = $(this).val();
      if (v === 'link_all_variations') { $(this).val(''); }
    });
  }

  /* -------- Attribute auto-detection -------- */
  const COLOR_HINTS = ['color','colour','shade','hue'];
  const PRINT_HINTS = ['print','location','placement','side','chest','sleeve','front','back'];

  function pickAttr(hints){
    if (!Array.isArray(BEE.attrs)) return null;
    const ranked = BEE.attrs
      .filter(a => a.is_var)
      .map(a => {
        const n = (a.name || a.key || '').toLowerCase();
        let score = 0;
        hints.forEach(h => { if (n.includes(h)) score += 2; });
        if (hints === PRINT_HINTS && Array.isArray(a.values)) {
          const hasFront = a.values.some(v => /front/i.test(v.label||v.slug));
          const hasBack  = a.values.some(v => /back/i.test(v.label||v.slug));
          if (hasFront) score += 2; if (hasBack) score += 2;
        }
        return { a, score };
      })
      .sort((x,y) => y.score - x.score);
    return ranked.length ? ranked[0].a : null;
  }
  const pickColorAttr = () => pickAttr(COLOR_HINTS);
  const pickPrintAttr = () => pickAttr(PRINT_HINTS);

  /* -------- UI creation -------- */
  function ensureUI(){
    if (!$('.variations_options').length || $('#bee-generator').length) return;
    const $b = $(`
      <div id="bee-generator" style="margin:12px 0;padding:14px;border:1px solid #ccd0d4;background:#f8f9fb;border-radius:6px;">
        <h4 style="margin:0 0 8px;">🐝 Bumblebee — Generate Variations + Mockups</h4>
        <p style="margin:6px 0 10px;">Select Original Art and garment base images per print location. Bumblebee will replace Woo’s generator.</p>

        <div class="bee-row">
          <label style="min-width:160px;display:inline-block;">Original Art</label>
          <input type="hidden" id="bee-art-url">
          <button class="button bee-pick" data-target="art">Select</button>
          <span class="bee-art-preview" style="margin-left:8px;vertical-align:middle;"></span>
        </div>

        <div id="bee-bases" style="margin-top:10px;">
          <div class="bee-base-row" data-slug="front">
            <label style="min-width:160px;display:inline-block;">Base (front/default)</label>
            <input type="hidden" id="bee-base-front">
            <button class="button bee-pick" data-target="base:front">Select</button>
            <span class="bee-base-preview" data-for="front" style="margin-left:8px;opacity:.9;"></span>
          </div>
          <!-- dynamic print-location rows go here -->
        </div>

        <p style="margin-top:12px;">
          <button class="button button-primary" id="bee-run">Generate Variations + Mockups</button>
          <span id="bee-status" style="margin-left:10px;"></span>
        </p>
      </div>
    `);
    $('.variations_options').prepend($b);
  }

  /* -------- Rendering helpers -------- */
  function renderArtThumb(url){
    if (!url) { $('.bee-art-preview').empty(); return; }
    // Show thumbnail for raster and SVG
    const $img = $('<img>').attr('src', url).css({ maxHeight:'40px', maxWidth:'80px' });
    $('.bee-art-preview').empty().append($img);
  }

  function renderPrintLocationRows(printAttr){
    const $wrap = $('#bee-bases');
    $wrap.find('.bee-base-row').not('[data-slug="front"]').remove();
    if (!printAttr || !Array.isArray(printAttr.values)) return;
    const seen = new Set(['front']);
    printAttr.values.forEach(v => {
      const slug = (v.slug || '').toLowerCase();
      if (!slug || seen.has(slug)) return;
      seen.add(slug);
      const row = $(`
        <div class="bee-base-row" data-slug="${slug}" style="margin-top:6px;">
          <label style="min-width:160px;display:inline-block;">Base (${v.label})</label>
          <input type="hidden" id="bee-base-${slug}">
          <button class="button bee-pick" data-target="base:${slug}">Select</button>
          <span class="bee-base-preview" data-for="${slug}" style="margin-left:8px;opacity:.9;"></span>
        </div>`);
      $wrap.append(row);
    });
  }

  function pickImage($btn){
    const t = $btn.data('target'); const [mode, slug] = String(t).split(':');
    const frame = wp.media({ title:'Select image', button:{ text:'Use this' }, multiple:false });
    frame.on('select', function(){
      const att = frame.state().get('selection').first().toJSON();
      if (mode === 'art') {
        $('#bee-art-url').val(att.url);
        renderArtThumb(att.url);
      } else if (mode === 'base') {
        $('#bee-base-'+slug).val(att.id);
        const thumb = (att.sizes && att.sizes.thumbnail && att.sizes.thumbnail.url) ? att.sizes.thumbnail.url : (att.icon || att.url);
        $('.bee-base-preview[data-for="'+slug+'"]').html('<img src="'+thumb+'" style="max-height:40px;max-width:80px;" />');
      }
    });
    frame.open();
  }

  function collectBases(){
    const map = {};
    $('.bee-base-row').each(function(){
      const slug = $(this).data('slug');
      const id = parseInt($('#bee-base-'+slug).val() || '0', 10);
      if (id) map[slug] = id;
    });
    return map;
  }

  function run(colorAttr, printAttr){
    $('#bee-status').text('Working...');
    $.post(BEE.ajax, {
      action: 'bee_generate_variations',
      nonce:  BEE.nonce,
      product_id: BEE.productId,
      color_key: colorAttr ? colorAttr.key : '',
      print_key: printAttr ? printAttr.key : '',
      art_url:   $('#bee-art-url').val(),
      bases:     JSON.stringify(collectBases())
    }, function(res){
      if (!res || !res.success) {
        $('#bee-status').text('Error: ' + (res && res.data || 'failed'));
        return;
      }
      $('#bee-status').text('Done. Created ' + res.data.created + ' variations; ' + res.data.with_images + ' with images.');
      $('button.cancel-variation-changes,button.save-variation-changes').prop('disabled', false);
      $('button.cancel-variation-changes').trigger('click');
    });
  }

  /* -------- Boot & persistence -------- */
  function boot(){
    hideWooGeneratorStrict(document);
    ensureUI();

    // Auto-detect attributes
    const colorAttr = pickColorAttr();
    const printAttr = pickPrintAttr();

    renderPrintLocationRows(printAttr);

    // Wire events
    $(document)
      .off('click.bee','.bee-pick').on('click.bee','.bee-pick',function(e){ e.preventDefault(); pickImage($(this)); })
      .off('click.bee','#bee-run').on('click.bee','#bee-run',function(e){ e.preventDefault(); run(colorAttr, printAttr); });
  }

  // Initial boot
  boot();

  // When Woo reloads the panel
  $(document).on('woocommerce_variations_loaded', function(){
    hideWooGeneratorStrict(document);
    if (!$('#bee-generator').length) boot();
  });

  // MutationObserver to catch dynamic changes injected by Woo or other plugins
  const container = document.querySelector('.variations_options');
  if (container && 'MutationObserver' in window){
    const mo = new MutationObserver(() => hideWooGeneratorStrict(container));
    mo.observe(container, { childList:true, subtree:true, attributes:true });
  }

  // Safety interval (first 10s) to ensure removal even on very slow admin screens
  let tries = 0;
  const killTimer = setInterval(function(){
    hideWooGeneratorStrict(document);
    if (++tries > 20) clearInterval(killTimer);
  }, 500);
});
