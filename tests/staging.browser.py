"""Authorized real staging UI checks; temporary cookies supplied outside Git.
Run: PUC_COOKIES=/private/path.json PUC_ORIGIN=https://... python tests/staging.browser.py
Requires Python Playwright and a Chromium executable (PUC_CHROME).
Does not create customer requests or edit authored content. Restores plugin defaults.
"""
import json,os,time
from pathlib import Path
from playwright.sync_api import sync_playwright
ORIGIN=os.environ.get('PUC_ORIGIN','https://portare.up.railway.app')
COOKIES=os.environ['PUC_COOKIES']
CHROME=os.environ.get('PUC_CHROME','/home/byron/.cache/ms-playwright/chromium-1243/chrome-linux64/chrome')
OUT=Path(__file__).resolve().parents[1]/'evidence/staging-0.1.0';OUT.mkdir(parents=True,exist_ok=True)
checks=[];errors=[]
def check(value,name):
 if not value:raise AssertionError(name)
 checks.append(name);print('PASS:',name,flush=True)
with sync_playwright() as p:
 browser=p.chromium.launch(executable_path=CHROME,headless=True,args=['--no-sandbox'])
 admin_ctx=browser.new_context(viewport={'width':1440,'height':1000});admin_ctx.add_cookies(json.loads(Path(COOKIES).read_text()))
 admin=admin_ctx.new_page();admin.on('pageerror',lambda e:errors.append(str(e)))
 public=browser.new_context(viewport={'width':1440,'height':1000})
 page=public.new_page();page.on('pageerror',lambda e:errors.append(str(e)))
 def settings():
  admin.goto(ORIGIN+'/wp-admin/options-general.php?page=portare-unit-converter',wait_until='domcontentloaded',timeout=90000)
  admin.locator('h1').filter(has_text='Portare Unit Converter').wait_for()
 def save(**changes):
  settings()
  for key,value in changes.items():
   node=admin.locator('[name="puc_settings['+key+']"]')
   if key in ['enabled','floating','header','footer','remember']:node.set_checked(bool(value))
   elif key in ['position','decimals','default_unit','header_location']:node.select_option(str(value))
   elif key in ['background_color','text_color']:
    picker=node.locator('xpath=ancestor::div[contains(@class,"wp-picker-container")]')
    if not node.is_visible():picker.locator('.wp-color-result').click()
    node.fill(str(value));node.press('Tab')
    if picker.locator('.iris-picker').is_visible():picker.locator('.wp-color-result').click()
   else:node.fill(str(value))
  with admin.expect_navigation(wait_until='domcontentloaded',timeout=90000):admin.locator('#submit').click()
 def go(path):
  sep='&' if '?' in path else '?'
  page.goto(ORIGIN+path+sep+'puc_qa='+str(time.time_ns()),wait_until='domcontentloaded',timeout=90000)
  page.wait_for_function('!!window.__pucInstance',timeout=20000)
 def toggle():
  page.locator('.puc-floating [data-puc-toggle]').click();page.wait_for_timeout(150)
 defaults=dict(enabled=1,floating=1,header=0,footer=0,remember=1,default_unit='in',decimals=1,position='bottom-right',header_location='menu_1',header_selector='',footer_selector='',background_color='#173942',text_color='#ffffff',radius=8,floating_offset=20)
 try:
  settings();check(admin.locator('[name^="puc_settings["]').count()==17,'authenticated settings exposes all 17 controls')
  admin.screenshot(path=str(OUT/'settings.png'),full_page=True)
  go('/portare-buffet-service-tables/')
  check(page.locator('.puc-floating').count()==1 and page.locator('.puc-menu-item').count()==0,'default floating-only placement on real Brizy page')
  check('72" L x 30" W' in page.inner_text('body'),'authored buffet measurements initially in inches')
  originals=page.evaluate('''()=>{const w=document.createTreeWalker(document.body,NodeFilter.SHOW_TEXT);const list=[];let n;while(n=w.nextNode()){if(n.parentElement&&!n.parentElement.closest('[data-puc-ignore],.puc-status,.puc-control,script,style')&&PortareUnitConverter.convertText(n.data,1)!==n.data)list.push([n,n.data]);}window.__pucQA=list;return list.length;}''')
  check(originals>0,'real Brizy measurement text nodes identified')
  toggle();check('182.9 cm L x 76.2 cm W' in page.inner_text('body'),'72-inch and 30-inch dimensions convert on real page')
  check(page.locator('.puc-floating button').get_attribute('aria-pressed')=='true','control exposes active metric state accessibly')
  for _ in range(5):toggle();toggle()
  toggle();check(page.evaluate('window.__pucQA.every(([n,text])=>n.data===text)'),'six metric round trips restore original page text exactly')
  button=page.locator('.puc-floating button');button.focus();page.keyboard.press('Space')
  check(button.get_attribute('aria-pressed')=='true','Space key activates real toggle')
  page.keyboard.press('Enter');check(button.get_attribute('aria-pressed')=='false','Enter key restores original units')
  page.screenshot(path=str(OUT/'floating-desktop.png'))
  toggle();go('/custom-pieces-faqs/')
  check('1 cm wide space' in page.inner_text('body'),'remembered metric preference and fraction conversion on a different page')
  toggle();check('3/8″ wide space' in page.inner_text('body'),'FAQ fraction restores original typography')
  go('/product/the-portable-folding-kings-table/');toggle()
  page.locator('[data-pqb-product]').first.click();page.wait_for_selector('#pqb-fields select',timeout=30000)
  page.wait_for_function('document.querySelector("#pqb-fields").textContent.includes("76.2 cm h")')
  option=page.locator('#pqb-fields option[value="30-h"]')
  check(option.inner_text()=='76.2 cm h' and option.get_attribute('value')=='30-h','dynamic quote height converts while variation key remains 30-h')
  selects=page.locator('#pqb-fields select')
  for index in range(selects.count()):
   node=selects.nth(index);value=node.locator('option').nth(1).get_attribute('value');node.select_option(value)
  check(page.locator('#pqb-image').is_visible(),'quote selection still resolves a product image')
  page.keyboard.press('Escape');toggle()
  page.locator('[data-pqb-product]').first.click();page.wait_for_selector('#pqb-fields select',timeout=30000)
  check(page.locator('#pqb-fields option[value="30-h"]').inner_text()=='30" h','quote label restores/reopens in original inches')
  page.keyboard.press('Escape')
  # Every automatic-placement combination, saved through the real Settings API.
  for floating,header,footer in [(0,0,0),(1,0,0),(0,1,0),(0,0,1),(1,1,0),(1,0,1),(0,1,1),(1,1,1)]:
   save(floating=floating,header=header,footer=footer)
   settings();check(all(admin.locator('[name="puc_settings['+k+']"]').is_checked()==bool(v) for k,v in [('floating',floating),('header',header),('footer',footer)]),f'placement save/reload {floating}/{header}/{footer}')
   go('/')
   check(page.locator('.puc-floating').count()==floating and page.locator('#header-menu-1 .puc-menu-item').count()==header and page.locator('#footer .puc-footer').count()==footer,f'real frontend placement {floating}/{header}/{footer}')
  page.locator('#header-menu-1 [data-puc-toggle]').click()
  check(page.locator('[data-puc-toggle]').evaluate_all('(els)=>els.every(e=>e.getAttribute("aria-pressed")==="true")'),'header toggle synchronizes floating, footer, and mobile controls')
  page.screenshot(path=str(OUT/'all-placements-desktop.png'))
  page.set_viewport_size({'width':390,'height':844});go('/')
  check(page.evaluate('document.documentElement.scrollWidth<=innerWidth+1'),'all placements create no phone horizontal overflow')
  check(page.locator('nav.mobile-menu [data-puc-toggle]').count()==1,'mobile menu has one synchronized toggle')
  check(page.locator('.puc-floating button').bounding_box()['height']>=44,'phone floating control meets 44px touch target')
  page.screenshot(path=str(OUT/'floating-mobile.png'))
  page.set_viewport_size({'width':1440,'height':1000})
  save(header=0,footer=0,background_color='#445566',text_color='#ffeeaa',radius=17,floating_offset=33,decimals=2,remember=0,default_unit='cm')
  go('/portare-buffet-service-tables/')
  style=page.locator('.puc-floating button').evaluate('(e)=>{let s=getComputedStyle(e);return {bg:s.backgroundColor,color:s.color,radius:s.borderRadius}}')
  check(style=={'bg':'rgb(68, 85, 102)','color':'rgb(255, 238, 170)','radius':'17px'},'saved appearance settings actually affect computed button styles')
  check(page.locator('.puc-floating').evaluate('(e)=>getComputedStyle(e).right')=='33px','saved edge offset applied')
  check('182.88 cm L x 76.2 cm W' in page.inner_text('body'),'two-decimal rounding and metric initial setting work')
  toggle();go('/portare-buffet-service-tables/')
  check(page.locator('.puc-floating button').get_attribute('aria-pressed')=='true','remember disabled ignores prior local preference on navigation')
  for corner in ['top-left','top-right','bottom-left','bottom-right']:
   save(position=corner);go('/')
   box=page.locator('.puc-floating').bounding_box()
   check((box['x']<100 if corner.endswith('left') else box['x']>1100) and (box['y']<100 if corner.startswith('top') else box['y']>850),f'floating corner {corner} saved and positioned')
  save(enabled=0);page.goto(ORIGIN+'/?puc_disabled_qa='+str(time.time_ns()),wait_until='domcontentloaded',timeout=90000)
  check(page.locator('[data-puc-toggle]').count()==0 and page.locator('script[src*="portare-unit-converter"]').count()==0,'global disable removes controls and frontend assets')
  save(**defaults)
  page.goto(ORIGIN+'/?brizy-edit=1',wait_until='domcontentloaded',timeout=90000)
  check(page.locator('[data-puc-toggle]').count()==0 and page.locator('script[src*="portare-unit-converter"]').count()==0,'Brizy editor request excludes converter')
  check(not errors,'no JavaScript exceptions across tested staging frontend/admin pages')
 finally:
  save(**defaults)
  browser.close()
(OUT/'results.json').write_text(json.dumps({'origin':ORIGIN,'checks':checks,'page_errors':errors,'count':len(checks)},indent=2))
print('PASS:',len(checks),'real staging browser checks; default settings restored')
