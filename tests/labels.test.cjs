const test = require('node:test');
const assert = require('node:assert/strict');
const { JSDOM } = require('jsdom');
const { execFileSync } = require('node:child_process');
const path = require('node:path');
const { init } = require('../assets/converter.js');
const preview = require('../assets/settings.js');
const toggle='<button class="puc-control" data-puc-toggle type="button"><span data-puc-label></span></button>';
function fixture(t, labels, prepare) {
 const dom=new JSDOM('<header id="header"><nav><ul></ul></nav></header><nav class="mobile-menu"><ul></ul></nav><p>72 inches</p><footer id="footer"></footer>'+toggle,{url:'https://example.test'});
 if(prepare)prepare(dom.window);
 const instance=init(dom.window,{labels,header:true,footer:true,remember:!!prepare});
 t.after(()=>{instance.destroy();dom.window.close()});return {dom,doc:dom.window.document,instance};
}
test('custom labels synchronize native, fallback, mobile, footer and newly added buttons', async t=>{
 const {dom,doc,instance}=fixture(t,{in:'Imperial inches',cm:'Metric centimetres'});
 assert.equal(doc.querySelectorAll('[data-puc-toggle]').length,4);
 assert.ok([...doc.querySelectorAll('[data-puc-toggle]')].every(e=>e.textContent==='Imperial inches'));
 instance.setUnit('cm');assert.ok([...doc.querySelectorAll('[data-puc-toggle]')].every(e=>e.textContent==='Metric centimetres'));
 assert.match(doc.querySelector('p').textContent,/182.9 cm/);
 const extra=doc.createElement('button');extra.setAttribute('data-puc-toggle','');doc.body.append(extra);
 await new Promise(r=>dom.window.setTimeout(r,15));assert.equal(extra.textContent,'Metric centimetres');
 assert.match(extra.getAttribute('aria-label'),/^Metric centimetres\. Showing centimeters\. Switch to inches/);
 instance.setUnit('in');assert.equal(extra.textContent,'Imperial inches');assert.equal(doc.querySelector('p').textContent,'72 inches');
});
test('labels remain text, are excluded from conversion, and cannot inject markup',t=>{
 const {doc,instance}=fixture(t,{in:'<img src=x> & 30 inches',cm:'<b>30 inches</b>'});
 assert.equal(doc.querySelector('[data-puc-toggle]').textContent,'<img src=x> & 30 inches');
 assert.equal(doc.querySelectorAll('img,b').length,0);
 instance.setUnit('cm');assert.equal(doc.querySelector('[data-puc-toggle]').textContent,'<b>30 inches</b>');
 assert.equal(doc.querySelectorAll('img,b').length,0);
});
test('missing blank or non-string labels retain defaults',t=>{
 const {doc,instance}=fixture(t,{in:[],cm:'  '});assert.equal(doc.querySelector('[data-puc-toggle]').textContent,'Units: in');
 instance.setUnit('cm');assert.equal(doc.querySelector('[data-puc-toggle]').textContent,'Units: cm');
});
test('remembered metric mode starts with the saved metric label and bounded Unicode',t=>{
 const {doc}=fixture(t,{in:'Imperial',cm:'é'.repeat(70)},win=>win.localStorage.setItem('puc_unit','cm'));
 assert.equal(doc.querySelector('[data-puc-toggle]').textContent,'é'.repeat(60));
});
const root=path.resolve(__dirname,'..');
const html=execFileSync('php',['-r',`require '${root}/qa/phpstan-wordpress-stubs.php';require '${root}/portare-unit-converter.php';(new PUC_Settings())->render();`],{encoding:'utf8'});
test('both preview sample labels update on typing without saving or preference changes',()=>{
 const dom=new JSDOM(html,{url:'https://example.test'});const doc=dom.window.document;const api=preview.init(dom.window);
 const change=(key,text)=>{const input=doc.querySelector('[name="puc_settings['+key+']"]');input.value=text;input.dispatchEvent(new dom.window.Event('input',{bubbles:true}));};
 change('label_in','Imperial');change('label_cm','Metric');
 assert.equal(doc.querySelector('[data-puc-preview-unit="in"]').textContent,'Imperial');
 assert.equal(doc.querySelector('[data-puc-preview-unit="cm"]').textContent,'Metric');
 doc.querySelector('[data-puc-preview-unit="cm"]').click();assert.equal(api.getUnit(),'cm');
 assert.ok([...doc.querySelectorAll('[data-puc-preview-placement] button')].every(e=>e.textContent==='Metric'));
 assert.equal(dom.window.localStorage.getItem('puc_unit'),null);
 change('label_cm',' ');assert.equal(doc.querySelector('[data-puc-preview-unit="cm"]').textContent,'Units: cm');
 change('label_in','<b>Imperial & inches</b>');assert.equal(doc.querySelector('[data-puc-preview-unit="in"]').textContent,'Imperial & inches');
 assert.equal(doc.querySelectorAll('#puc-preview b').length,0);dom.window.close();
});
