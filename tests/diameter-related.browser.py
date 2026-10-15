"""Read-only real-page regression for diameter text in Brizy related products."""
import os,json
from pathlib import Path
from playwright.sync_api import sync_playwright
ORIGIN=os.environ.get('PUC_ORIGIN','https://portare.up.railway.app')
CHROME=os.environ.get('PUC_CHROME','/home/byron/.cache/ms-playwright/chromium-1243/chrome-linux64/chrome')
SOURCE='Round 60″ Dia';METRIC='Round 152.4 cm Dia'
RECTANGLE='Rectangle 30″ x 72″ (seats 6)';RECTANGLE_CM='Rectangle 76.2 cm x 182.9 cm (seats 6)'
OUT=Path(__file__).resolve().parents[1]/'evidence/diameter-0.1.4';OUT.mkdir(parents=True,exist_ok=True)
checks=[];errors=[]
def check(ok,name):
 if not ok:raise AssertionError(name)
 checks.append(name);print('PASS:',name,flush=True)
with sync_playwright() as p:
 browser=p.chromium.launch(executable_path=CHROME,headless=True,args=['--no-sandbox'])
 try:
  for width,height,name in [(1440,1000,'desktop'),(390,844,'phone')]:
   ctx=browser.new_context(viewport={'width':width,'height':height});page=ctx.new_page();page.on('pageerror',lambda e:errors.append(str(e)))
   res=page.goto(ORIGIN+'/product/the-portable-folding-table/',wait_until='domcontentloaded',timeout=90000);page.wait_for_function('!!window.__pucInstance');page.get_by_text(SOURCE,exact=True).first.wait_for()
   check(res.status==200,name+': exact reported page responds successfully')
   check(page.locator('script[src*="assets/converter.js?ver=0.1.4"]').count()==1,name+': fixed 0.1.4 asset loads from normal URL')
   title=page.locator('.brz-wp-title-content').filter(has_text=SOURCE)
   check(title.count()>0,name+': reported related-product title is actual Brizy content')
   links=page.locator('a[href]').evaluate_all('(els)=>els.map(e=>e.getAttribute("href"))')
   page.locator('[data-puc-toggle]:visible').first.click();page.wait_for_timeout(150)
   check(page.get_by_text(METRIC,exact=True).count()>0 and page.get_by_text(SOURCE,exact=True).count()==0,name+': related card converts 60 inches to 152.4 cm')
   check(RECTANGLE_CM in page.inner_text('body'),name+': previous two-dimension/seats fix still works')
   check(page.locator('a[href]').evaluate_all('(els)=>els.map(e=>e.getAttribute("href"))')==links,name+': link targets remain unchanged')
   page.get_by_text(METRIC,exact=True).first.scroll_into_view_if_needed();page.screenshot(path=str(OUT/(name+'.png')))
   page.locator('[data-puc-toggle]:visible').first.click()
   check(page.get_by_text(SOURCE,exact=True).count()>0 and RECTANGLE in page.inner_text('body'),name+': related and main titles restore original inches exactly')
   for _ in range(5):page.locator('[data-puc-toggle]:visible').first.click();page.locator('[data-puc-toggle]:visible').first.click()
   check(page.get_by_text(SOURCE,exact=True).count()>0 and page.get_by_text(METRIC,exact=True).count()==0,name+': repeated toggles introduce no drift')
   page.locator('[data-puc-toggle]:visible').first.click();page.reload(wait_until='domcontentloaded');page.wait_for_function('!!window.__pucInstance')
   check(page.get_by_text(METRIC,exact=True).count()>0 and RECTANGLE_CM in page.inner_text('body'),name+': remembered metric mode converts both titles after reload')
   if name=='phone':check(page.evaluate('document.documentElement.scrollWidth<=innerWidth+1'),'phone: page has no horizontal overflow')
   ctx.close()
  check(not errors,'no frontend JavaScript exceptions')
 finally:browser.close()
(OUT/'results.json').write_text(json.dumps({'checks':checks,'count':len(checks),'errors':errors,'source':SOURCE,'converted':METRIC},indent=2))
print('PASS:',len(checks),'real diameter/related-product checks')
