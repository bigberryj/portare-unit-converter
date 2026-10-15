"""Public, read-only staging regression for dimensions before seating annotations.
Requires Python Playwright; does not save settings, submit quotes, or edit content.
"""
import json,os
from pathlib import Path
from playwright.sync_api import sync_playwright
ORIGIN=os.environ.get('PUC_ORIGIN','https://portare.up.railway.app')
CHROME=os.environ.get('PUC_CHROME','/home/byron/.cache/ms-playwright/chromium-1243/chrome-linux64/chrome')
PATH='/product/the-portable-folding-table/'
SOURCE='Rectangle 30″ x 72″ (seats 6)'
EXPECTED='Rectangle 76.2 cm x 182.9 cm (seats 6)'
out=Path(__file__).resolve().parents[1]/'evidence/parenthesis-0.1.3';out.mkdir(parents=True,exist_ok=True)
checks=[];errors=[]
def check(ok,label):
 if not ok:raise AssertionError(label)
 checks.append(label);print('PASS:',label,flush=True)
with sync_playwright() as p:
 browser=p.chromium.launch(executable_path=CHROME,headless=True,args=['--no-sandbox'])
 try:
  for width,height,name in [(1440,1000,'desktop'),(390,844,'phone')]:
   context=browser.new_context(viewport={'width':width,'height':height});page=context.new_page();page.on('pageerror',lambda e:errors.append(str(e)))
   response=page.goto(ORIGIN+PATH,wait_until='domcontentloaded',timeout=90000);page.wait_for_function('!!window.__pucInstance');page.get_by_text(SOURCE,exact=True).first.wait_for()
   check(response.status==200,name+': reported product page responds 200')
   check(page.locator('script[src*="assets/converter.js?ver=0.1.3"]').count()==1,name+': plain page URL loads cache-busted fixed asset')
   count=page.inner_text('body').count(SOURCE);check(count>0,name+': original reported title visible')
   page.evaluate('''source=>{const w=document.createTreeWalker(document.body,NodeFilter.SHOW_TEXT);window.__pucTitleNodes=[];let n;while(n=w.nextNode())if(n.data.includes(source))window.__pucTitleNodes.push([n,n.data]);}''',SOURCE)
   page.locator('[data-puc-toggle]:visible').first.click();page.wait_for_timeout(150)
   check(page.inner_text('body').count(EXPECTED)==count,name+': every authored title converts both 30 and 72, retaining seats 6')
   check(SOURCE not in page.inner_text('body') and '76.2 cm x 72″' not in page.inner_text('body'),name+': no partially converted title remains')
   page.get_by_text(EXPECTED,exact=True).first.scroll_into_view_if_needed();page.screenshot(path=str(out/(name+'.png')))
   page.locator('[data-puc-toggle]:visible').first.click();check(page.evaluate('window.__pucTitleNodes.every(([n,s])=>n.data===s)'),name+': original text restored verbatim')
   for _ in range(5):
    page.locator('[data-puc-toggle]:visible').first.click();page.locator('[data-puc-toggle]:visible').first.click()
   check(page.evaluate('window.__pucTitleNodes.every(([n,s])=>n.data===s)'),name+': repeated toggles do not introduce rounding drift')
   if name=='phone':check(page.evaluate('document.documentElement.scrollWidth<=innerWidth+1'),'phone: no horizontal page overflow')
   page.locator('[data-puc-toggle]:visible').first.click();page.reload(wait_until='domcontentloaded');page.wait_for_function('!!window.__pucInstance');check(page.inner_text('body').count(EXPECTED)==count,name+': remembered metric mode converts both dimensions on reload')
   context.close()
  check(not errors,'no frontend JavaScript exceptions')
 finally:browser.close()
(out/'results.json').write_text(json.dumps({'checks':checks,'count':len(checks),'errors':errors,'source':SOURCE,'metric':EXPECTED},indent=2))
print('PASS:',len(checks),'real product-page regression checks')
