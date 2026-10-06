'use strict';
const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const { JSDOM } = require('jsdom');
const { convertText, init } = require('../assets/converter.js');
const script = fs.readFileSync(path.join(__dirname, '../assets/converter.js'), 'utf8');
const css = fs.readFileSync(path.join(__dirname, '../assets/converter.css'), 'utf8');
const toggle = '<button class="puc-control" type="button" data-puc-toggle aria-pressed="false"><span data-puc-label>Units: in</span></button>';
function fixture(t, html = '', config = {}, prepare) {
  const dom = new JSDOM('<!doctype html><html><head></head><body>' + html + '</body></html>', { url: 'https://portare.example/product/', runScripts: 'outside-only' });
  if (prepare) prepare(dom.window);
  const instance = init(dom.window, { remember: false, ...config });
  t.after(() => { instance.destroy(); dom.window.close(); });
  return { dom, win: dom.window, doc: dom.window.document, instance };
}
async function settle(win) { await new Promise(resolve => win.setTimeout(resolve, 15)); }

const conversions = [
  ['24 inches', '61 cm'], ['1 inch', '2.5 cm'], ['12 INCHES', '30.5 cm'],
  ['24in', '61 cm'], ['24 in', '61 cm'], ['24 in.', '61 cm'], ['24 in. wide', '61 cm wide'],
  ['0.5 inch', '1.3 cm'], ['.5 inches', '1.3 cm'], ['0 inch', '0 cm'],
  ['1 1/2 inch', '3.8 cm'], ['1-1/2 inch', '3.8 cm'], ['1/2 inch', '1.3 cm'],
  ['½ inch', '1.3 cm'], ['Width: ½ inch', 'Width: 1.3 cm'], ['1½ inches', '3.8 cm'], ['1 ¼ inch', '3.2 cm'], ['⅛ inch', '0.3 cm'],
  ['24–36 inches', '61–91.4 cm'], ['24-36 inches', '61-91.4 cm'], ['24 — 36 inches', '61 — 91.4 cm'],
  ['24 to 36 inches', '61 to 91.4 cm'], ['72 x 36 x 30 inches', '182.9 x 91.4 x 76.2 cm'],
  ['72 × 36 × 30 in', '182.9 × 91.4 × 76.2 cm'], ['72" x 36"', '182.9 cm x 91.4 cm'],
  ['24″', '61 cm'], ['24”', '61 cm'], ['24, 30 and 36 inches', '61, 76.2 and 91.4 cm'],
  ['24, 30, and 36 inches', '61, 76.2, and 91.4 cm'], ['24 and 36 inches', '61 and 91.4 cm'],
  ['Width: 24 inches wide; height: 36 inches.', 'Width: 61 cm wide; height: 91.4 cm.'],
  ['(24 in), [36 inches]', '(61 cm), [91.4 cm]'], ['24\tinches', '61 cm'],
  ['One size fits all 72" L x 30" W', 'One size fits all 182.9 cm L x 76.2 cm W'],
  ['72" L x 30" W', '182.9 cm L x 76.2 cm W'],
  ['3/8″ wide space', '1 cm wide space'], ['30" h', '76.2 cm h']
];
for (const [source, expected] of conversions) test('convert: ' + source, () => assert.equal(convertText(source), expected));
const unchanged = [
  '2 in stock', 'We arrive 2 in the afternoon', 'Made in 24 countries', '24 cm', '24 centimeters',
  '6\' 2"', '6′ 2″', '6 feet 2 inches', '6 ft 2 in', '5\' 1 1/2"', '6 ft. 2"', '6\' 1½"', '6\' ⅜″',
  '24 inches²', '24 inches³', '24 inches^2', '24 inches square', '24 inches cubed', '24 square inches',
  'square 24 inches', '-24 inches', '+24 inches', '1/0 inch', '1 1/0 inch', '1,200 inches',
  'SKU24in', 'Model-24in', 'v1.24in', 'https://example.com/24in', 'https://example.com/24inches',
  'www.example.com/24in', '/products/24in', '“24”', '"24"', 'He said 24" hello', '2 inside', '24inchwide'
];
for (const source of unchanged) test('leave ambiguous/unsupported: ' + source, () => assert.equal(convertText(source), source));
test('configurable precision, finite limits and trimmed zeros', () => {
  assert.equal(convertText('10 inches', 2), '25.4 cm');
  assert.equal(convertText('1 inch', 2), '2.54 cm');
  assert.equal(convertText('1 inch', 0), '3 cm');
  assert.equal(convertText('1 inch', 100), '2.5 cm');
  assert.equal(convertText('1000001 inches'), '1000001 inches');
  assert.equal(convertText(null), null);
});

test('exact reversible text, identity, markup, attributes and form values', t => {
  const html = '<p id="text">Width: 1-1/2 inches <strong>24 in.</strong> and 72 × 36 inches</p><a href="/24in" title="24 inches" id="link">24 inches</a><form><input value="24 inches"><textarea>24 inches</textarea><button type="button" value="24 inches">24 inches</button></form>' + toggle;
  const { doc, instance } = fixture(t, html);
  const before = doc.querySelector('#text').innerHTML;
  const textNode = doc.querySelector('#text').firstChild;
  const strong = doc.querySelector('strong');
  for (let i = 0; i < 15; i++) {
    instance.setUnit('cm'); assert.match(doc.querySelector('#text').textContent, /3.8 cm/);
    instance.setUnit('in'); assert.equal(doc.querySelector('#text').innerHTML, before);
  }
  assert.equal(doc.querySelector('#text').firstChild, textNode);
  assert.equal(doc.querySelector('strong'), strong);
  assert.equal(doc.querySelector('#link').getAttribute('href'), '/24in');
  assert.equal(doc.querySelector('#link').title, '24 inches');
  instance.setUnit('cm');
  assert.equal(doc.querySelector('input').value, '24 inches');
  assert.equal(doc.querySelector('textarea').value, '24 inches');
  assert.equal(doc.querySelector('form button').value, '24 inches');
});

test('every exclusion is honored including nested editable and ignored content', t => {
  const tags = ['script', 'style', 'textarea', 'code', 'pre', 'noscript', 'svg', 'math'];
  const html = tags.map(tag => '<' + tag + ' data-excluded>' + (tag === 'style' ? '/*24 inches*/' : '24 inches') + '</' + tag + '>').join('') +
    '<div contenteditable="false"><span data-excluded>24 inches</span></div><div data-puc-ignore><span data-excluded>24 inches</span></div>' +
    '<div id="wpadminbar"><span data-excluded>24 inches</span></div><div class="puc-control" data-excluded>24 inches</div>' +
    '<div class="puc-status" data-excluded>24 inches</div><p>24 inches</p>';
  const { doc, instance } = fixture(t, html);
  instance.setUnit('cm');
  doc.querySelectorAll('[data-excluded]').forEach(el => assert.equal(el.textContent, el.tagName === 'STYLE' ? '/*24 inches*/' : '24 inches'));
  assert.equal(doc.querySelector('p').textContent, '61 cm');
});

test('explicit and implicit option values remain identical in submitted FormData', t => {
  const { doc, win, instance } = fixture(t, '<form><select name="size"><option selected>24 inches</option><option value="width-36">36 inches</option></select></form>');
  const select = doc.querySelector('select'), first = select.options[0], second = select.options[1];
  const value = select.value;
  instance.setUnit('cm');
  assert.equal(first.textContent, '61 cm'); assert.equal(first.value, value);
  assert.equal(first.getAttribute('value'), value); // deliberate permanent pin, not a changed submission value
  assert.equal(second.textContent, '91.4 cm'); assert.equal(second.value, 'width-36');
  assert.equal(new win.FormData(doc.querySelector('form')).get('size'), value);
  instance.setUnit('in');
  assert.equal(first.textContent, '24 inches'); assert.equal(select.value, value);
  assert.equal(second.getAttribute('value'), 'width-36');
});

test('quote-builder attribute matching uses preserved explicit values, not rendered labels', t => {
  const { doc, win, instance } = fixture(t, '<form><select name="attr:width"><option value="24 inches">24 inches</option><option value="36 inches">36 inches</option></select></form>');
  const select = doc.querySelector('select');
  const variations = [{ attributes: { width: '24 inches' }, image: 'first.jpg' }, { attributes: { width: '36 inches' }, image: 'second.jpg' }];
  const matchingVariation = () => variations.find(v => v.attributes.width === doc.querySelector('form').elements.namedItem('attr:width').value);
  instance.setUnit('cm');
  assert.equal(select.options[0].textContent, '61 cm'); assert.equal(matchingVariation().image, 'first.jpg');
  select.value = '36 inches'; select.dispatchEvent(new win.Event('change', { bubbles: true }));
  assert.equal(matchingVariation().image, 'second.jpg');
  instance.setUnit('in'); assert.equal(select.value, '36 inches');
});

test('real dynamic quote modal label preserves 30-h and restores 30" h', async t => {
  const { doc, win, instance } = fixture(t, '', { defaultUnit: 'cm' });
  const form = doc.createElement('form');
  form.innerHTML = '<select name="height"><option value="30-h">30" h</option></select>';
  doc.body.append(form); await settle(win);
  const option = form.querySelector('option');
  assert.equal(option.textContent, '76.2 cm h'); assert.equal(option.value, '30-h');
  assert.equal(new win.FormData(form).get('height'), '30-h');
  instance.setUnit('in'); assert.equal(option.textContent, '30" h'); assert.equal(option.value, '30-h');
});

test('all dimension suffix cases and prose, without ambiguous quoted prose', () => {
  for (const suffix of ['h', 'H', 'l', 'L', 'w', 'W', 'd', 'D', 'wide', 'deep', 'high', 'long']) {
    assert.equal(convertText('30" ' + suffix), '76.2 cm ' + suffix);
    assert.equal(convertText('30″ ' + suffix), '76.2 cm ' + suffix);
  }
  assert.equal(convertText('30" words spoken'), '30" words spoken');
});

test('AJAX insertion, same-node external changes, and replacement become new baselines', async t => {
  const { doc, win, instance } = fixture(t, '<p id="p">24 inches</p>', { defaultUnit: 'cm' });
  const p = doc.querySelector('#p'), node = p.firstChild;
  assert.equal(node.data, '61 cm');
  node.data = '30 inches'; await settle(win);
  assert.equal(node.data, '76.2 cm'); instance.setUnit('in'); assert.equal(node.data, '30 inches');
  instance.setUnit('cm'); p.textContent = '36 inches';
  const fresh = doc.createElement('span'); fresh.textContent = '72 inches'; doc.body.append(fresh);
  await settle(win);
  assert.equal(p.textContent, '91.4 cm'); assert.equal(fresh.textContent, '182.9 cm');
  instance.setUnit('in'); assert.equal(p.textContent, '36 inches'); assert.equal(fresh.textContent, '72 inches');
});

test('external mutations just before toggling are not lost', t => {
  const { doc, instance } = fixture(t, '<p>24 inches</p>', { defaultUnit: 'cm' });
  const node = doc.querySelector('p').firstChild; node.data = '36 inches';
  instance.setUnit('in'); assert.equal(node.data, '36 inches');
  instance.setUnit('cm'); assert.equal(node.data, '91.4 cm');
});

test('detached nodes are restored, pruned, and safe to reinsert without drift', async t => {
  const { doc, win, instance } = fixture(t, '<p>24 inches</p>', { defaultUnit: 'cm' });
  const p = doc.querySelector('p'); assert.equal(instance.diagnostics().trackedNodes, 1);
  p.remove(); await settle(win);
  assert.equal(instance.diagnostics().trackedNodes, 0); assert.equal(p.textContent, '24 inches');
  doc.body.append(p); await settle(win); assert.equal(p.textContent, '61 cm');
  instance.setUnit('in'); assert.equal(p.textContent, '24 inches');
});

test('mutations are batched and converter mutations do not cause a feedback loop', async t => {
  const { doc, win, instance } = fixture(t, '<main></main>', { defaultUnit: 'cm' });
  const before = instance.diagnostics().flushes;
  for (let i = 0; i < 100; i++) { const p = doc.createElement('p'); p.textContent = '24 inches'; doc.querySelector('main').append(p); }
  await settle(win);
  assert.equal(instance.diagnostics().flushes, before + 1);
  assert.equal(doc.querySelector('main').textContent, '61 cm'.repeat(100));
  const after = instance.diagnostics().flushes; await settle(win);
  assert.equal(instance.diagnostics().flushes, after); assert.equal(instance.diagnostics().pending, false);
});

test('changing exclusion attributes restores text, then reenables conversion', async t => {
  const { doc, win, instance } = fixture(t, '<p>24 inches</p>', { defaultUnit: 'cm' });
  const p = doc.querySelector('p'); p.setAttribute('data-puc-ignore', ''); await settle(win);
  assert.equal(p.textContent, '24 inches'); assert.equal(instance.diagnostics().trackedNodes, 0);
  p.removeAttribute('data-puc-ignore'); await settle(win); assert.equal(p.textContent, '61 cm');
  p.setAttribute('contenteditable', 'true'); await settle(win); assert.equal(p.textContent, '24 inches');
});

test('all native controls synchronize, and native controls remain in place', t => {
  const { doc, instance } = fixture(t, '<header id="header"><nav><ul><li class="puc-menu-item">' + toggle + '</li></ul></nav></header><nav class="mobile-menu"><ul></ul></nav><div class="puc-floating">' + toggle + '</div><footer id="footer"></footer><div class="puc-footer">' + toggle + '</div>', { header: true, footer: true });
  const native = doc.querySelector('#header button');
  assert.equal(doc.querySelectorAll('[data-puc-toggle]').length, 4);
  assert.equal(doc.querySelector('.puc-footer').parentNode.id, 'footer');
  doc.querySelector('.puc-floating [data-puc-label]').click();
  assert.equal(instance.getUnit(), 'cm');
  doc.querySelectorAll('[data-puc-toggle]').forEach(el => {
    assert.equal(el.getAttribute('aria-pressed'), 'true'); assert.equal(el.textContent, 'Units: cm');
    assert.match(el.getAttribute('aria-label'), /Switch to inches/);
  });
  assert.equal(doc.querySelector('#header button'), native);
  native.click(); assert.equal(instance.getUnit(), 'in');
  assert.match(doc.querySelector('[role="status"]').textContent, /restored/);
});

test('configured Brizy selectors take precedence and do not duplicate controls', t => {
  const { doc, instance } = fixture(t, '<div class="brizy-head"></div><div class="brizy-footer"></div><header id="header"><nav><ul></ul></nav></header><footer></footer>', { header: true, footer: true, headerSelector: '.brizy-head', footerSelector: '.brizy-footer' });
  assert.ok(doc.querySelector('.brizy-head [data-puc-toggle]'));
  assert.equal(doc.querySelector('#header [data-puc-toggle]'), null);
  assert.ok(doc.querySelector('.brizy-footer .puc-footer'));
  instance.refresh(); instance.refresh(); assert.equal(doc.querySelectorAll('[data-puc-toggle]').length, 2);
});

test('missing/invalid header selectors produce visible floating fallback; invalid footer is safe', t => {
  const { doc, instance } = fixture(t, '<p>24 inches</p>', { header: true, footer: true, headerSelector: '[broken', footerSelector: '[broken', position: 'bottom-left' });
  const fallback = doc.querySelector('.puc-floating');
  assert.ok(fallback); assert.equal(fallback.dataset.position, 'bottom-left');
  assert.equal(fallback.hidden, false); assert.ok(fallback.querySelector('button'));
  fallback.querySelector('button').click(); assert.equal(instance.getUnit(), 'cm');
  assert.equal(doc.querySelector('p').textContent, '61 cm'); assert.ok(doc.querySelector('.puc-footer'));
});

test('desktop ID fallback and dynamically inserted mobile menu controls', async t => {
  const { doc, win, instance } = fixture(t, '<div id="header-menu-1"><ul></ul></div>', { header: true, defaultUnit: 'cm' });
  assert.ok(doc.querySelector('#header-menu-1 > ul > li.puc-menu-item'));
  const mobile = doc.createElement('nav'); mobile.className = 'mobile-menu'; mobile.innerHTML = '<ul><li>24 inches</li></ul>'; doc.body.append(mobile);
  await settle(win);
  assert.equal(mobile.querySelectorAll('[data-puc-toggle]').length, 1);
  assert.equal(mobile.querySelector('button').getAttribute('aria-pressed'), 'true');
  assert.match(mobile.textContent, /61 cm/); instance.refresh(); assert.equal(mobile.querySelectorAll('button').length, 1);
});

test('remember preference is read on a new page and cross-tab changes synchronize', t => {
  const { doc, win, instance } = fixture(t, '<p>24 inches</p>' + toggle, { remember: true }, win => win.localStorage.setItem('puc_unit', 'cm'));
  assert.equal(doc.querySelector('p').textContent, '61 cm');
  instance.setUnit('in'); assert.equal(win.localStorage.getItem('puc_unit'), 'in');
  win.dispatchEvent(new win.StorageEvent('storage', { key: 'puc_unit', newValue: 'cm' }));
  assert.equal(instance.getUnit(), 'cm'); assert.equal(doc.querySelector('p').textContent, '61 cm');
  win.dispatchEvent(new win.StorageEvent('storage', { key: 'puc_unit', newValue: 'nonsense' }));
  assert.equal(instance.getUnit(), 'cm');
});

test('remember=false ignores existing storage and does not write preference', t => {
  const { win, instance } = fixture(t, '<p>24 inches</p>', {}, win => win.localStorage.setItem('puc_unit', 'cm'));
  assert.equal(instance.getUnit(), 'in'); instance.setUnit('cm'); instance.setUnit('in');
  assert.equal(win.localStorage.getItem('puc_unit'), 'cm');
});

test('blocked storage still converts and shows visible polite warning', t => {
  const { doc, instance } = fixture(t, '<p>24 inches</p>', { remember: true }, win => {
    Object.defineProperty(win, 'localStorage', { get() { throw new win.DOMException('Blocked', 'SecurityError'); } });
  });
  instance.setUnit('cm'); assert.equal(doc.querySelector('p').textContent, '61 cm');
  const status = doc.querySelector('.puc-status');
  assert.ok(status.classList.contains('puc-status--visible')); assert.match(status.textContent, /cannot be saved/);
  assert.equal(status.getAttribute('aria-live'), 'polite');
});

test('destroy restores original text, respects external edits and stops observing', async t => {
  const { doc, win, instance } = fixture(t, '<p>24 inches</p><p>36 inches</p>', { defaultUnit: 'cm' });
  const [first, second] = doc.querySelectorAll('p'); second.firstChild.data = 'External 72 inches';
  instance.destroy(); assert.equal(first.textContent, '24 inches'); assert.equal(second.textContent, 'External 72 inches');
  first.textContent = '48 inches'; await settle(win); assert.equal(first.textContent, '48 inches');
  assert.equal(doc.querySelector('[role="status"]'), null);
});

test('browser script auto-starts once, consumes PUCConfig and exposes pure API', async t => {
  const dom = new JSDOM('<!doctype html><p>24 inches</p>' + toggle, { url: 'https://portare.example', runScripts: 'outside-only' });
  t.after(() => { dom.window.__pucInstance?.destroy(); dom.window.close(); });
  dom.window.PUCConfig = { defaultUnit: 'cm', remember: false };
  dom.window.eval(script); await settle(dom.window);
  assert.equal(dom.window.document.querySelector('p').textContent, '61 cm');
  assert.equal(dom.window.PortareUnitConverter.convertText('10 inches'), '25.4 cm');
  dom.window.eval(script); await settle(dom.window);
  assert.equal(dom.window.document.querySelectorAll('[role="status"]').length, 1);
});

test('stylesheet parses and only plugin-owned selectors are used', t => {
  const { doc } = fixture(t);
  const style = doc.createElement('style'); style.textContent = css; doc.head.append(style);
  assert.ok(style.sheet.cssRules.length > 10);
  function check(rules) {
    for (const rule of rules) {
      if (rule.selectorText) for (const selector of rule.selectorText.split(',')) assert.match(selector.trim(), /^\.puc-/);
      if (rule.cssRules) check(rule.cssRules);
    }
  }
  check(style.sheet.cssRules);
});
