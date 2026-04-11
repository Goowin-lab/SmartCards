/* global jQuery, SCAdminCredits */
jQuery(function($){

  const $select = $('#sc-user-select');
  const $uid    = $('#sc-user-id');
  const $box    = $('#sc-user-preview');

  // Render helper
  function paintPreview(d){
    $('#sc-prev-credits').text(d.credits);
    $('#sc-prev-used').text(d.used);
    $('#sc-prev-vcf').text(d.vcf);
    $('#sc-prev-upd').text(d.last_update || '—');
  }

  // Inicializa Select2 AJAX
  $select.select2({
    width: 'resolve',
    placeholder: SCAdminCredits.i18n.ph,
    language: {
      inputTooShort: () => 'Escribe al menos 2 caracteres…',
      searching:     () => SCAdminCredits.i18n.loading,
      noResults:     () => SCAdminCredits.i18n.noresults
    },
    ajax: {
      url: SCAdminCredits.ajax,
      dataType: 'json',
      delay: 250,
      cache: true,
      data: params => ({
        action: 'sc_buscar_usuario',
        nonce:  SCAdminCredits.nonce,
        term:   params.term || ''
      }),
      processResults: data => ({ results: data })
    },
    minimumInputLength: 2,
    allowClear: true
  });

  // Al seleccionar un usuario → mostrar resumen
  $select.on('select2:select', function(e){
    const userId = e.params.data.id;
    $uid.val(userId);

    $.getJSON(SCAdminCredits.ajax, {
      action: 'sc_user_summary',
      nonce:  SCAdminCredits.nonce,
      user_id: userId
    }).done(function(resp){
      if (resp && resp.success){
        paintPreview(resp.data);
        $box.prop('hidden', false);
        // 👇 IMPORTANTE: muestra controles y precarga el input
      enableActionsFromSummary(resp.data);
      } else {
        $box.prop('hidden', true);
        alert((resp && resp.data && resp.data.message) || 'No fue posible obtener el resumen.');
      }
    }).fail(function(){
      $box.prop('hidden', true);
      alert('Error de red. Intenta nuevamente.');
    });
  });

  // Al limpiar selección
  $select.on('select2:clear', function(){
    $uid.val('');
    $box.prop('hidden', true);
  });

  // ——— Integración con tus formularios existentes ———
  // Si tu botón "Actualizar Créditos" o "Reiniciar Créditos" envía un form,
  // asegúrate de que incluya <input name="user_id" value="...">.
  // Aquí clonamos el valor a un input común si tu form lo necesita:
  $('form').on('submit', function(){
    const userId = $uid.val();
    // Ejemplo: si tu form espera "sc_user_id" o "user_id", ajusta el selector:
    const $dest = $(this).find('input[name="user_id"], input[name="sc_user_id"]').first();
    if ($dest.length) $dest.val(userId);
  });

});

/* global jQuery, SCAdminCredits */
jQuery(function($){

  const $uid   = $('#sc-user-id');
  const $box   = $('#sc-user-preview');
  const $act   = $('.sc-user-actions');
  const $in    = $('#sc-edit-credits');
  const $save  = $('#sc-btn-save-credits');
  const $reset = $('#sc-btn-reset-used');
  const $msg   = $('.sc-user-actions__msg');

  // Mostrar acciones cuando haya usuario seleccionado y pintar el valor actual:
  function enableActionsFromSummary(d){
    if (!d) return;
    $in.val( (typeof d.credits !== 'undefined') ? d.credits : 0 );
    $act.prop('hidden', false);
  }

  // Hook: cuando el resumen se pinta tras select2 (engánchate a tu flujo)
  // Si tu código actual llama paintPreview(resp.data), añade después:
  // enableActionsFromSummary(resp.data);

  // Si no puedes tocar esa función, observable simple:
  const obs = new MutationObserver(() => {
    if (!$box.prop('hidden') && $uid.val()) $act.prop('hidden', false);
  });
  if ($box[0]) obs.observe($box[0], {childList:true, subtree:true});

  function lock(btn, on){
    btn.prop('disabled', !!on);
    if (on) btn.addClass('is-busy'); else btn.removeClass('is-busy');
  }
  function toast(t){ $msg.text(t); setTimeout(()=> $msg.text(''), 2500); }

  // Guardar créditos
  $save.on('click', function(){
    const id  = $uid.val();
    const val = parseInt($in.val(), 10);
    if (!id){ alert('Selecciona un usuario.'); return; }
    if (isNaN(val) || val < 0){ alert('Créditos inválidos'); return; }

    lock($save, true);
    $.post(SCAdminCredits.ajax, {
      action: 'sc_update_credits',
      nonce : SCAdminCredits.nonce,
      user_id: id,
      credits: val
    }, null, 'json')
    .done(function(r){
      if (r && r.success){
        $('#sc-prev-credits').text(r.data.credits);
        $('#sc-prev-used').text(r.data.used);
        $('#sc-prev-vcf').text(r.data.vcf);
        $('#sc-prev-upd').text(r.data.last_update);
        toast('✔ ' + (r.data.msg || 'Actualizado'));
      } else {
        alert((r && r.data && r.data.message) || 'No se pudo actualizar.');
      }
    })
    .fail(function(){ alert('Error de red.'); })
    .always(function(){ lock($save, false); });
  });

  // Reiniciar usados
  $reset.on('click', function(){
    const id = $uid.val();
    if (!id){ alert('Selecciona un usuario.'); return; }
    if (!confirm('¿Reiniciar créditos usados a 0?')) return;

    lock($reset, true);
    $.post(SCAdminCredits.ajax, {
      action: 'sc_reset_credits',
      nonce : SCAdminCredits.nonce,
      user_id: id
    }, null, 'json')
    .done(function(r){
      if (r && r.success){
        $('#sc-prev-credits').text(r.data.credits);
        $('#sc-prev-used').text(0);
        $('#sc-prev-vcf').text(r.data.vcf);
        $('#sc-prev-upd').text(r.data.last_update);
        toast('✔ ' + (r.data.msg || 'Reiniciado'));
      } else {
        alert((r && r.data && r.data.message) || 'No se pudo reiniciar.');
      }
    })
    .fail(function(){ alert('Error de red.'); })
    .always(function(){ lock($reset, false); });
  });

  // Cuando seleccionas usuario por Select2 cargamos el resumen y habilitamos acciones
  // (si ya tienes ese GET hecho, solo añade la llamada a enableActionsFromSummary)
  $('#sc-user-select').on('select2:select', function(){
    $act.prop('hidden', false);
  }).on('select2:clear', function(){
    $act.prop('hidden', true); $msg.text('');
  });

});
