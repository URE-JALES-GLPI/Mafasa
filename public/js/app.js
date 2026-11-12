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

/**
 * Mafasa - gráficos opcionais (ECharts do GLPI). Cinco modelos: barras, barras horizontais,
 * linha, pizza e rosca. Começa sempre desligado; a escolha fica lembrada por tela neste navegador.
 */
(function () {
  'use strict';

  var TYPES = [
    ['', 'Sem gráfico'],
    ['bar', 'Barras'],
    ['hbar', 'Barras horizontais'],
    ['line', 'Linha'],
    ['pie', 'Pizza'],
    ['donut', 'Rosca']
  ];
  var PALETTE = ['#0079bf', '#2bb673', '#f2a000', '#e45959', '#8b5cf6', '#14b8c4', '#e0559a', '#7c8a9a', '#a3c93a', '#d97706'];
  var MAX_ITEMS = 15;
  var echartsPromise = null;

  function store(key, value) {
    try {
      if (value === undefined) { return window.localStorage.getItem(key); }
      window.localStorage.setItem(key, value);
    } catch (e) { /* armazenamento bloqueado: não lembra a escolha */ }
    return null;
  }

  function loadEcharts(src) {
    if (window.echarts) { return Promise.resolve(window.echarts); }
    if (!echartsPromise) {
      echartsPromise = new Promise(function (resolve, reject) {
        var el = document.createElement('script');
        el.src = src;
        el.onload = function () { resolve(window.echarts); };
        el.onerror = function () { echartsPromise = null; reject(new Error('Não foi possível carregar a biblioteca de gráficos.')); };
        document.head.appendChild(el);
      });
    }
    return echartsPromise;
  }

  function sum(list) { return list.reduce(function (a, b) { return a + b; }, 0); }

  /* Muitos itens poluem o gráfico: fica com os maiores e agrupa o resto em "Outros". */
  function limited(categories, series) {
    var totals = categories.map(function (_, i) {
      return sum(series.map(function (s) { return s.data[i] || 0; }));
    });
    var order = totals.map(function (t, i) { return i; }).sort(function (a, b) { return totals[b] - totals[a]; });
    if (categories.length <= MAX_ITEMS) { return { categories: categories, series: series, grouped: 0 }; }
    var keep = order.slice(0, MAX_ITEMS - 1).sort(function (a, b) { return a - b; });
    var rest = order.slice(MAX_ITEMS - 1);
    var cats = keep.map(function (i) { return categories[i]; }).concat(['Outros (' + rest.length + ')']);
    var out = series.map(function (s) {
      var data = keep.map(function (i) { return s.data[i] || 0; });
      data.push(sum(rest.map(function (i) { return s.data[i] || 0; })));
      return { name: s.name, data: data };
    });
    return { categories: cats, series: out, grouped: rest.length };
  }

  function build(echarts, cfg, kind) {
    var text = getComputedStyle(document.body).color || '#334155';
    var grid = 'rgba(120,130,150,.25)';
    var data = { categories: cfg.categories, series: cfg.series };

    // Linha com linhas=entidades e colunas=meses: o tempo vai no eixo X.
    if (cfg.transpose && kind === 'line' && cfg.series.length > 1) {
      var top = limited(cfg.categories, cfg.series);
      data = {
        categories: cfg.series.map(function (s) { return s.name; }),
        series: top.categories.map(function (name, i) {
          return { name: name, data: top.series.map(function (s) { return s.data[i]; }) };
        })
      };
    } else {
      var lim = limited(cfg.categories, cfg.series);
      data = { categories: lim.categories, series: lim.series };
    }

    var common = { color: PALETTE, animation: false, textStyle: { color: text },
      tooltip: { trigger: kind === 'pie' || kind === 'donut' ? 'item' : 'axis', confine: true } };
    var multi = data.series.length > 1;
    var legend = { type: 'scroll', bottom: 0, textStyle: { color: text } };

    if (kind === 'pie' || kind === 'donut') {
      // Pizza e rosca usam um valor por item (com vários meses, a soma).
      var slices = data.categories.map(function (name, i) {
        return { name: name, value: sum(data.series.map(function (s) { return s.data[i] || 0; })) };
      }).filter(function (d) { return d.value > 0; });
      return Object.assign(common, {
        legend: legend,
        series: [{
          type: 'pie', radius: kind === 'donut' ? ['45%', '70%'] : '68%', center: ['50%', '45%'], data: slices,
          label: { color: text, formatter: '{b}: {c} ({d}%)' }, avoidLabelOverlap: true
        }]
      });
    }

    var horizontal = kind === 'hbar';
    var catAxis = { type: 'category', data: data.categories, axisLabel: { color: text, interval: 0, width: horizontal ? 220 : 90, overflow: 'truncate' },
      inverse: horizontal, axisLine: { lineStyle: { color: grid } } };
    var valAxis = { type: 'value', minInterval: 1, axisLabel: { color: text }, splitLine: { lineStyle: { color: grid, type: 'dashed' } } };
    return Object.assign(common, {
      legend: multi ? legend : undefined,
      grid: { left: 12, right: 24, top: 18, bottom: multi ? 46 : 22, containLabel: true },
      xAxis: horizontal ? valAxis : catAxis,
      yAxis: horizontal ? catAxis : valAxis,
      series: data.series.map(function (s) {
        var item = { name: s.name, data: s.data, type: kind === 'line' ? 'line' : 'bar', emphasis: { focus: 'series' } };
        if (kind === 'line') { item.smooth = true; item.symbolSize = 6; item.areaStyle = { opacity: .08 }; }
        else { item.barMaxWidth = 38; item.label = { show: !multi, position: horizontal ? 'right' : 'top', color: text }; }
        return item;
      })
    });
  }

  function mount(section) {
    var cfg;
    try { cfg = JSON.parse(section.getAttribute('data-mf-chart')); } catch (e) { return; }
    var id = section.getAttribute('data-mf-chart-id') || 'chart';
    var key = 'mafasa.chart.' + id;

    section.innerHTML = '<div class="mf-chart-bar"><label>Gráfico <select class="mf-chart-kind" aria-label="Tipo de gráfico"></select></label>'
      + '<span class="mf-chart-title"></span></div><div class="mf-chart-canvas" hidden></div><p class="mf-chart-msg" hidden></p>';
    var select = section.querySelector('select');
    TYPES.forEach(function (t) {
      var o = document.createElement('option');
      o.value = t[0]; o.textContent = t[1];
      select.appendChild(o);
    });
    section.querySelector('.mf-chart-title').textContent = cfg.title || '';
    var canvas = section.querySelector('.mf-chart-canvas');
    var msg = section.querySelector('.mf-chart-msg');
    var instance = null;

    async function draw() {
      var kind = select.value;
      store(key, kind);
      if (!kind) {
        canvas.hidden = true;
        msg.hidden = true;
        if (instance) { instance.dispose(); instance = null; }
        return;
      }
      try {
        var echarts = await loadEcharts(section.getAttribute('data-echarts'));
        canvas.hidden = false;
        msg.hidden = true;
        if (!instance) { instance = echarts.init(canvas); }
        instance.setOption(build(echarts, cfg, kind), true);
        instance.resize();
      } catch (error) {
        msg.textContent = error.message;
        msg.hidden = false;
      }
    }

    select.value = store(key) || '';
    if (select.value !== (store(key) || '')) { select.value = ''; }
    select.addEventListener('change', draw);
    window.addEventListener('resize', function () { if (instance) { instance.resize(); } });
    draw();
  }

  document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('[data-mf-chart]').forEach(mount);
  });
})();
