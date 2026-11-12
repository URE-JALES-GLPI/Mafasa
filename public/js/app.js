/**
 * Mafasa - Relatórios de Ativos (tela v2).
 * - Tipos: checkbox mestre (#mf-types-all) com estado indeterminado.
 * - Colunas: sincroniza input[name="cols[]"] e esconde th/td via data-col.
 * - Copiar link compartilha filtros + types[] + cols[] (URL do form).
 * - Links de impressão e PDF sincronizados com os mesmos parâmetros.
 * - Select2 nos selects de filtro (quando a lib está disponível).
 */
(function () {
  'use strict';

  function checkedCols() {
    return Array.prototype.slice
      .call(document.querySelectorAll('input[name="cols[]"]:checked'))
      .map(function (el) { return el.getAttribute('data-col'); })
      .filter(Boolean);
  }

  function applyVisibility() {
    var checked = checkedCols();
    document.querySelectorAll('#mf-table [data-col]').forEach(function (cell) {
      var col = cell.getAttribute('data-col');
      cell.style.display = checked.indexOf(col) === -1 ? 'none' : '';
    });
    syncPrintLink();
  }

  function setAll(on, defaults) {
    document.querySelectorAll('input[name="cols[]"]').forEach(function (el) {
      var col = el.getAttribute('data-col');
      if (defaults) {
        el.checked = defaults.indexOf(col) !== -1;
      } else {
        el.checked = !!on;
      }
    });
    applyVisibility();
  }

  function currentShareUrl() {
    var form = document.getElementById('mf-form');
    if (!form) return window.location.href;
    var params = new URLSearchParams(new FormData(form));
    params.set('page', '1');
    return window.location.pathname + '?' + params.toString();
  }

  function syncPrintLink() {
    ['mf-print-link', 'mf-pdf-link'].forEach(function (id) {
      var link = document.getElementById(id);
      if (!link) return;
      try {
        var shared = new URL(currentShareUrl(), window.location.href);
        if (id === 'mf-pdf-link') shared.searchParams.set('format', 'pdf');
        var target = link.getAttribute('data-print') || 'print.php';
        link.setAttribute('href', target + '?' + shared.searchParams.toString());
      } catch (e) {
        /* mantém link original */
      }
    });
  }

  function typesBoxes() {
    return Array.prototype.slice.call(document.querySelectorAll('input[name="types[]"]'));
  }

  function syncTypesMaster() {
    var master = document.getElementById('mf-types-all');
    if (!master) return;
    var boxes = typesBoxes();
    if (!boxes.length) {
      master.checked = false;
      master.indeterminate = false;
      return;
    }
    var checked = boxes.filter(function (b) { return b.checked; }).length;
    master.checked = (checked === boxes.length);
    master.indeterminate = (checked > 0 && checked < boxes.length);
  }

  function initSelect2() {
    if (!window.jQuery || !jQuery.fn || typeof jQuery.fn.select2 !== 'function') return;
    jQuery('#mf-form .mf-field select').each(function () {
      if (!jQuery(this).hasClass('select2-hidden-accessible')) {
        jQuery(this).select2({ width: '100%', allowClear: true });
      }
    });
  }

  document.addEventListener('DOMContentLoaded', function () {
    var form = document.getElementById('mf-form');
    if (form) {
      form.addEventListener('input', syncPrintLink);
      form.addEventListener('change', syncPrintLink);
    }
    document.querySelectorAll('input[name="cols[]"]').forEach(function (el) {
      el.addEventListener('change', applyVisibility);
    });

    var all = document.getElementById('mf-cols-all');
    if (all) all.addEventListener('click', function () { setAll(true); });
    var none = document.getElementById('mf-cols-none');
    if (none) none.addEventListener('click', function () { setAll(false); });
    var def = document.getElementById('mf-cols-default');
    if (def) def.addEventListener('click', function () {
      var raw = def.getAttribute('data-default') || '';
      var list = raw.split(',').map(function (s) { return s.trim(); }).filter(Boolean);
      setAll(false, list);
    });

    var master = document.getElementById('mf-types-all');
    if (master) {
      master.addEventListener('change', function () {
        typesBoxes().forEach(function (el) { el.checked = master.checked; });
        master.indeterminate = false;
      });
    }
    typesBoxes().forEach(function (el) {
      el.addEventListener('change', syncTypesMaster);
    });
    syncTypesMaster();

    var copy = document.getElementById('mf-copy-link');
    if (copy) copy.addEventListener('click', function () {
      var url = new URL(currentShareUrl(), window.location.href).toString();
      function ok() {
        var t = copy.textContent;
        copy.textContent = 'Copiado!';
        setTimeout(function () { copy.textContent = t; }, 1600);
      }
      if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(url).then(ok, function () {
          window.prompt('Copie o link:', url);
        });
      } else {
        window.prompt('Copie o link:', url);
      }
    });

    initSelect2();
    applyVisibility();
    initEntityPicker();
  });

  /* Seletor de entidades (Tralla → Por entidade e mês): filtra pelo nome. */
  function initEntityPicker() {
    document.querySelectorAll('[data-entity-picker]').forEach(function (picker) {
      var search = picker.querySelector('[data-entity-search]');
      var items = Array.prototype.slice.call(picker.querySelectorAll('.mf-entity-item'));
      var count = picker.querySelector('[data-entity-count]');
      function norm(t) { return (t || '').toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, ''); }
      function refresh() {
        var term = norm(search ? search.value.trim() : '');
        var shown = 0, checked = 0;
        items.forEach(function (item) {
          var ok = !term || norm(item.getAttribute('data-entity-name')).indexOf(term) !== -1;
          item.hidden = !ok;
          if (ok) { shown++; }
          if (item.querySelector('input').checked) { checked++; }
        });
        if (count) { count.textContent = checked + ' marcada(s) · ' + shown + ' na lista'; }
      }
      if (search) { search.addEventListener('input', refresh); }
      picker.addEventListener('change', refresh);
      picker.querySelectorAll('[data-entity-mark]').forEach(function (btn) {
        btn.addEventListener('click', function () {
          var on = btn.getAttribute('data-entity-mark') === '1';
          items.forEach(function (item) {
            if (!on || !item.hidden) { item.querySelector('input').checked = on; }
          });
          refresh();
        });
      });
      picker.addEventListener('toggle', function () { if (picker.open && search) { search.focus(); } });
      refresh();
    });
  }
})();
