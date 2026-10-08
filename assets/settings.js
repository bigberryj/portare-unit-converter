/* Settings-only live preview. No network, localStorage, or website-side writes. */
(function (root, factory) {
  'use strict';
  var api = factory();
  if (typeof module === 'object' && module.exports) module.exports = api;
  else {
    root.PUCSettingsPreview = api;
    var boot = function () { api.init(root, root.jQuery); };
    if (root.document.readyState === 'loading') root.document.addEventListener('DOMContentLoaded', boot, { once: true });
    else boot();
  }
})(typeof window !== 'undefined' ? window : this, function () {
  'use strict';
  function init(win, $) {
    var doc = win.document, form = doc.getElementById('puc-settings-form'), preview = doc.getElementById('puc-preview');
    if (!form || !preview || preview.dataset.pucPreviewReady === '1') return null;
    preview.dataset.pucPreviewReady = '1';
    var properties = { '--puc-bg': 'color', '--puc-text': 'color', '--puc-radius': 'number', '--puc-offset': 'number' };
    var unit = 'in', notice = preview.querySelector('.puc-preview-message');
    var fields = Array.from(form.querySelectorAll('[data-css-var]'));
    function field(key) { return form.querySelector('[name="puc_settings[' + key + ']"]'); }
    function apply(input, value) {
      var property = input.dataset.cssVar, type = properties[property];
      if (!type) return;
      if (type === 'color') {
        if (!/^#[a-fA-F0-9]{6}$/.test(value)) return;
        preview.style.setProperty(property, value.toLowerCase());
      } else if (/^\d{1,6}$/.test(String(value))) {
        var n = Math.max(Number(input.min || 0), Math.min(Number(input.max || 200), Number(value)));
        preview.style.setProperty(property, n + 'px');
      }
    }
    function label(mode) {
      var input = field('label_' + mode);
      var text = input ? input.value.replace(/<[^>]*>/g, '').replace(/\s+/g, ' ').trim() : '';
      return text ? Array.from(text).slice(0, 60).join('') : 'Units: ' + mode;
    }
    function render() {
      fields.forEach(function (input) { apply(input, input.value); });
      var invalid = fields.some(function (input) { return properties[input.dataset.cssVar] === 'color' && !/^#[a-fA-F0-9]{6}$/.test(input.value); });
      var enabled = field('enabled').checked;
      preview.querySelectorAll('[data-puc-preview-placement]').forEach(function (el) {
        var name = el.dataset.pucPreviewPlacement;
        var control = field(name);
        el.hidden = !enabled || !control || !control.checked;
      });
      var position = field('position').value;
      if (['top-left', 'top-right', 'bottom-left', 'bottom-right'].indexOf(position) !== -1) {
        preview.querySelector('.puc-floating').setAttribute('data-position', position);
      }
      preview.querySelectorAll('[data-puc-preview-toggle]').forEach(function (button) {
        var mode = button.dataset.pucPreviewUnit || unit;
        button.textContent = label(mode);
        button.setAttribute('aria-pressed', String(mode === 'cm'));
        button.setAttribute('aria-label', label(mode) + '. Preview ' + (mode === 'cm' ? 'centimeters' : 'inches') + ' button.');
      });
      var decimals = Number(field('decimals').value);
      if (!Number.isInteger(decimals) || decimals < 0 || decimals > 3) decimals = 1;
      preview.querySelector('[data-puc-preview-measurement]').textContent = unit === 'cm' ? String(Number((72 * 2.54).toFixed(decimals))) + ' cm' : '72 inches';
      if (notice) notice.textContent = invalid ? 'Enter a six-digit hex colour, such as #173942. The preview keeps the last valid colour.' : (!enabled ? 'Converter is disabled. Sample buttons still show your chosen appearance.' : 'Preview only — Save Changes applies these settings to the website.');
    }
    unit = field('default_unit').value === 'cm' ? 'cm' : 'in';
    fields.forEach(function (input) { apply(input, input.dataset.default || input.value); });
    function change(event) {
      if (event.target === field('default_unit')) unit = event.target.value === 'cm' ? 'cm' : 'in';
      render();
    }
    function click(event) {
      var button = event.target.closest && event.target.closest('[data-puc-preview-toggle]');
      if (!button || !preview.contains(button)) return;
      event.preventDefault();
      unit = button.dataset.pucPreviewUnit || (unit === 'in' ? 'cm' : 'in');
      render();
    }
    form.addEventListener('input', change); form.addEventListener('change', change); preview.addEventListener('click', click);
    if ($ && $.fn && typeof $.fn.wpColorPicker === 'function') {
      fields.filter(function (input) { return input.classList.contains('puc-color-picker'); }).forEach(function (input) {
        $(input).wpColorPicker({
          change: function (event, ui) {
            // Iris calls change before the input value is written. Use its colour
            // for an immediate swatch/preview update, then refresh all fields.
            if (ui && ui.color) apply(input, ui.color.toString());
            win.setTimeout(render, 0);
          },
          clear: function () { win.setTimeout(render, 0); }
        });
      });
    }
    render();
    return { refresh: render, getUnit: function () { return unit; } };
  }
  return { init: init };
});
