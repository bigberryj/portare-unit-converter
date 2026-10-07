const { test } = require('node:test');
const assert = require('node:assert/strict');
const { execFileSync } = require('node:child_process');
const { JSDOM } = require('jsdom');
const path = require('node:path');
const { init } = require('../assets/settings.js');
const root = path.resolve(__dirname, '..');
const html = execFileSync('php', ['-r', `require '${root}/qa/phpstan-wordpress-stubs.php';require '${root}/portare-unit-converter.php';(new PUC_Settings())->render();`], { encoding: 'utf8' });
function setup($) {
  const dom = new JSDOM(html, { url: 'https://example.test/wp-admin/options-general.php' });
  const win = dom.window, doc = win.document;
  const api = init(win, $);
  const field = key => doc.querySelector(`[name="puc_settings[${key}]"]`);
  const change = (key, value) => { const el = field(key); if (el.type === 'checkbox') el.checked = value; else el.value = value; el.dispatchEvent(new win.Event('input', { bubbles: true })); };
  return { dom, win, doc, api, field, change, preview: doc.getElementById('puc-preview') };
}
test('real PHP settings HTML initializes live preview and saved defaults without picker JS', () => {
  const s = setup();
  assert.equal(s.preview.style.getPropertyValue('--puc-bg'), '#173942');
  assert.equal(s.preview.style.getPropertyValue('--puc-text'), '#ffffff');
  assert.equal(s.preview.style.getPropertyValue('--puc-radius'), '8px');
  assert.equal(s.preview.style.getPropertyValue('--puc-offset'), '20px');
  assert.equal(s.preview.querySelector('[data-puc-preview-placement="floating"]').hidden, false);
  assert.equal(s.preview.querySelector('[data-puc-preview-placement="header"]').hidden, true);
  assert.equal(s.doc.querySelectorAll('.puc-color-picker').length, 2);
  assert.equal(s.doc.querySelectorAll('[name^="puc_settings["]').length, 15);
  s.win.close();
});
test('colour and dimensions update immediately and remain scoped to preview', () => {
  const s = setup();
  s.change('background_color', '#ABCDEF'); s.change('text_color', '#102030');
  s.change('radius', '23'); s.change('floating_offset', '47');
  assert.equal(s.preview.style.getPropertyValue('--puc-bg'), '#abcdef');
  assert.equal(s.preview.style.getPropertyValue('--puc-text'), '#102030');
  assert.equal(s.preview.style.getPropertyValue('--puc-radius'), '23px');
  assert.equal(s.preview.style.getPropertyValue('--puc-offset'), '47px');
  assert.equal(s.doc.documentElement.style.length, 0);
  assert.equal(s.doc.body.style.length, 0);
  s.win.close();
});
test('invalid colours preserve last valid preview and report visible validation help', () => {
  const s = setup();s.change('background_color', '#445566');
  for (const v of ['red', '#fff', '#445566;}', '']) {
    s.change('background_color', v);
    assert.equal(s.preview.style.getPropertyValue('--puc-bg'), '#445566');
    assert.match(s.preview.querySelector('[role="status"]').textContent, /six-digit hex/);
  }
  s.change('background_color', '#112233');
  assert.match(s.preview.querySelector('[role="status"]').textContent, /Preview only/);
  s.win.close();
});
test('numeric preview values mirror server clamping and reject stylesheet injection', () => {
  const s = setup();s.change('radius', '999');s.change('floating_offset', '999');
  assert.equal(s.preview.style.getPropertyValue('--puc-radius'), '100px');
  assert.equal(s.preview.style.getPropertyValue('--puc-offset'), '200px');
  s.change('radius', '-1');assert.equal(s.preview.style.getPropertyValue('--puc-radius'), '100px');
  s.win.close();
});
test('all placement choices, four corners and master enable update in preview only', () => {
  const s = setup();
  for (const key of ['floating', 'header', 'footer']) {
    s.change(key, true);assert.equal(s.preview.querySelector(`[data-puc-preview-placement="${key}"]`).hidden, false);
    s.change(key, false);assert.equal(s.preview.querySelector(`[data-puc-preview-placement="${key}"]`).hidden, true);
    s.change(key, true);
  }
  for (const corner of ['top-left','top-right','bottom-left','bottom-right']) { s.change('position', corner);assert.equal(s.preview.querySelector('.puc-floating').dataset.position, corner); }
  s.change('enabled', false);assert.ok([...s.preview.querySelectorAll('[data-puc-preview-placement]')].every(e=>e.hidden));
  assert.match(s.preview.querySelector('[role="status"]').textContent, /disabled/);
  s.win.close();
});
test('preview unit buttons work without submit, preference writes, or changing initial-unit setting', () => {
  const s = setup();let submitted = 0;s.doc.querySelector('form').addEventListener('submit', ()=>submitted++);
  s.win.localStorage.setItem('puc_unit', 'in');
  s.preview.querySelector('[data-puc-preview-unit="cm"]').click();
  assert.equal(s.preview.querySelector('[data-puc-preview-measurement]').textContent, '182.9 cm');
  assert.equal(s.api.getUnit(), 'cm');assert.equal(s.field('default_unit').value, 'in');
  assert.equal(s.win.localStorage.getItem('puc_unit'), 'in');assert.equal(submitted, 0);
  s.change('decimals', '2');assert.equal(s.preview.querySelector('[data-puc-preview-measurement]').textContent, '182.88 cm');
  s.preview.querySelector('.puc-floating button').click();assert.equal(s.api.getUnit(), 'in');
  s.change('default_unit', 'cm');assert.equal(s.api.getUnit(), 'cm');
  s.win.close();
});
test('native picker callbacks apply colour immediately before Iris writes its input value', async () => {
  const callbacks = new Map();const $ = input=>({wpColorPicker: options=>callbacks.set(input,options)});$.fn={wpColorPicker(){}};
  const s = setup($);assert.equal(callbacks.size, 2);
  const input=s.field('background_color');
  callbacks.get(input).change({}, {color:{toString:()=> '#556677'}});
  assert.equal(s.preview.style.getPropertyValue('--puc-bg'), '#556677');
  input.value='#556677';await new Promise(r=>s.win.setTimeout(r,5));
  assert.equal(s.preview.style.getPropertyValue('--puc-bg'), '#556677');
  callbacks.get(input).clear();input.value='';await new Promise(r=>s.win.setTimeout(r,5));
  assert.match(s.preview.querySelector('[role="status"]').textContent,/six-digit hex/);
  s.win.close();
});
test('initialization is idempotent and unrelated admin pages are untouched', () => {
  const s=setup();assert.equal(init(s.win),null);s.win.close();
  const dom=new JSDOM('<p>Other settings</p>');assert.equal(init(dom.window),null);assert.equal(dom.window.document.body.innerHTML,'<p>Other settings</p>');dom.window.close();
});
