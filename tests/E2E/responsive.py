"""Responsive audit against a disposable database and real Laravel HTTP pages."""
import argparse, base64, json, os, pathlib, socket, subprocess, sys, time, traceback, uuid
from playwright.sync_api import sync_playwright

ROOT = pathlib.Path(__file__).resolve().parents[2]
parser = argparse.ArgumentParser()
parser.add_argument('--browser', default='C:/Program Files/Google/Chrome/Application/chrome.exe')
parser.add_argument('--widths', default='320,360,390,768,844,1024,1440', help='844px also tests phone landscape at 390px height')
parser.add_argument('--only', default='')
parser.add_argument('--fixture', help='Reuse an existing isolated run for a follow-up audit')
args = parser.parse_args()
sys.stdout.reconfigure(encoding='utf-8')
OUT = pathlib.Path(args.fixture).resolve() if args.fixture else ROOT/'storage/browser-e2e'/('responsive-'+uuid.uuid4().hex)
assert OUT.parent == (ROOT/'storage/browser-e2e').resolve()
OUT.mkdir(parents=True, exist_ok=True)
for directory in ['framework/cache/data', 'framework/sessions', 'framework/views', 'logs', 'app/private', 'app/public']:
    (OUT/directory).mkdir(parents=True, exist_ok=True)
(OUT/'database.sqlite').touch(exist_ok=True)
with socket.socket() as sock:
    sock.bind(('127.0.0.1', 0)); port = sock.getsockname()[1]
BASE = f'http://127.0.0.1:{port}'
env = os.environ | {'APP_ENV':'e2e', 'APP_DEBUG':'false', 'APP_URL':BASE, 'APP_KEY':'base64:'+base64.b64encode(os.urandom(32)).decode(), 'DB_CONNECTION':'sqlite', 'DB_DATABASE':str(OUT/'database.sqlite'), 'DB_URL':'', 'LARAVEL_STORAGE_PATH':str(OUT), 'SESSION_DRIVER':'file', 'SESSION_SECURE_COOKIE':'false', 'CACHE_STORE':'file', 'QUEUE_CONNECTION':'sync', 'MAIL_MAILER':'log', 'BCRYPT_ROUNDS':'4', 'LOG_CHANNEL':'single'}
if not args.fixture:
    # Legacy-to-compact SQLite migrations drop referenced tables while rebuilding.
    # Disable FK checks only for this disposable bootstrap; HTTP requests retain them.
    subprocess.run(['php', str(ROOT/'tests/E2E/responsive-fixtures.php')], cwd=ROOT, env=env | {'DB_FOREIGN_KEYS':'false'}, check=True)
fixture = json.loads((OUT/'fixture.json').read_text())
assert fixture.get('subjective'), 'Responsive fixture bootstrap did not complete.'
log = (OUT/'responsive-server.log').open('wb')
server = subprocess.Popen(['php', '-d', 'variables_order=EGPCS', '-S', f'127.0.0.1:{port}', '-t', str(ROOT/'public'), str(ROOT/'tests/E2E/router.php')], cwd=ROOT/'public', env=env, stdout=log, stderr=subprocess.STDOUT, creationflags=getattr(subprocess, 'CREATE_NO_WINDOW', 0))
results, errors = [], []

def visit(page, path):
    response = page.goto(BASE+path, wait_until='networkidle')
    if response.status >= 400: raise RuntimeError(f'{path}: HTTP {response.status}')

def login(browser, actor, admin=False):
    context = browser.new_context(viewport={'width':1440,'height':1000}, has_touch=True, accept_downloads=True)
    page = context.new_page()
    page.on('pageerror', lambda error: errors.append({'path':page.url,'error':str(error)}))
    visit(page, '/admin/login' if admin else '/login')
    page.locator('[name=email]').fill(actor+'@e2e.test')
    page.locator('[name=password]').fill('VistiqE2e123!')
    page.locator('form button[type=submit]').first.click()
    page.wait_for_load_state('networkidle')
    assert '/login' not in page.url
    return context, page

METRICS = """() => {
 const width = innerWidth;
 const visible = el => {
   const r=el.getBoundingClientRect(); const s=getComputedStyle(el);
   if(r.width<=1||r.height<=1||s.visibility==='hidden'||s.display==='none')return false;
   for(let p=el;p;p=p.parentElement)if(p.tagName==='DETAILS'&&!p.open&&!p.querySelector(':scope > summary')?.contains(el))return false;
   return !el.checkVisibility || el.checkVisibility({checkOpacity:true,checkVisibilityCSS:true});
 };
 const inScroller = el => {for(let p=el.parentElement;p&&p!==document.body;p=p.parentElement){const s=getComputedStyle(p);if(['auto','scroll'].includes(s.overflowX)&&p.scrollWidth>p.clientWidth+2)return true;}return false;};
 const overflow = [...document.querySelectorAll('main,section,form,header,footer,nav,aside,table,dialog[open],input,select,textarea,button,summary')].filter(visible).filter(el=>{const r=el.getBoundingClientRect();return(r.right>width+2||r.left < -2)&&!inScroller(el);}).slice(0,12).map(el=>({tag:el.tagName,cls:el.className,text:(el.innerText||el.getAttribute('aria-label')||'').slice(0,75),width:Math.round(el.getBoundingClientRect().width)}));
 const small = [...document.querySelectorAll('button,a.button,summary,input:not([type=hidden]):not([type=checkbox]):not([type=radio]),select,textarea')].filter(visible).filter(el=>!el.disabled&&el.getBoundingClientRect().height<40).slice(0,12).map(el=>({tag:el.tagName,cls:el.className,text:(el.innerText||el.getAttribute('aria-label')||el.getAttribute('name')||'').slice(0,60),height:Math.round(el.getBoundingClientRect().height)}));
 return {documentWidth:document.documentElement.scrollWidth,viewport:width,overflow,small};
}"""

def audit(page, name, path, state=None):
    if args.only and not any(token in name for token in args.only.split(',')): return
    try:
        visit(page, path)
        for width in [int(value) for value in args.widths.split(',')]:
            height = {320:568,360:800,390:844,768:1024,844:390,1024:768}.get(width,900)
            page.set_viewport_size({'width':width,'height':height})
            if state: state(page)
            page.evaluate('() => new Promise(resolve => requestAnimationFrame(() => requestAnimationFrame(resolve)))')
            metrics = page.evaluate(METRICS)
            broken = metrics['documentWidth'] > width+1 or bool(metrics['overflow'])
            if metrics['documentWidth'] > width+1:
                metrics['overflowSources'] = page.evaluate("""() => [...document.querySelectorAll('*')].filter(el=>{const r=el.getBoundingClientRect();return r.right>innerWidth+1&&r.width>0}).map(el=>{const r=el.getBoundingClientRect(),s=getComputedStyle(el);return {tag:el.tagName,cls:el.className,left:Math.round(r.left),right:Math.round(r.right),position:s.position,overflow:s.overflow,minWidth:s.minWidth,text:el.textContent.trim().slice(0,60)}}).slice(0,70)""")
            result = {'page':name, 'path':path, 'width':width, 'height':height, 'broken':broken, **metrics}
            results.append(result)
            if broken or (name in ['writing','speaking','writing-editor','speaking-editor','writing-import'] and width in [390,768,1440]):
                page.screenshot(path=str(OUT/f'{name}-{width}.png'), full_page=True)
            if name in ['writing','speaking'] and width == 390:
                page.locator('.subjective-table tbody tr').first.scroll_into_view_if_needed()
                page.screenshot(path=str(OUT/f'{name}-cards-{width}.png'), full_page=True)
                page.locator('.admin-page').evaluate('(el)=>el.scrollTop=0')
        print(json.dumps({'page':name,'broken':sum(row['broken'] for row in results if row['page']==name),'smallControls':sum(bool(row.get('small')) for row in results if row['page']==name and row.get('width',2000)<=1024)}), flush=True)
    except Exception as error:
        results.append({'page':name,'path':path,'error':traceback.format_exc(),'broken':True})
        page.screenshot(path=str(OUT/f'{name}-error.png'), full_page=True)
        print(json.dumps({'page':name,'error':traceback.format_exc()}), flush=True)

def subjective_controls(page):
    boxes = page.locator('[data-subjective-select]')
    select_all = page.locator('[data-subjective-select-all]')
    select_all.check()
    assert boxes.count() > 1
    assert page.locator('[data-subjective-select]:checked').count() == boxes.count()
    assert page.locator('[data-subjective-review-submit]').is_enabled()
    boxes.first.uncheck()
    assert select_all.evaluate('(el)=>el.indeterminate')
    select_all.check()
    select_all.uncheck()
    assert page.locator('[data-subjective-review-submit]').is_disabled()
    filters = page.locator('[data-subjective-filters]')
    if not filters.evaluate('(el)=>el.open'):
        filters.locator('summary').click()
    page.evaluate("() => { window.responsiveAjaxDone=false; document.addEventListener('vistiq:ajax-results-updated',()=>window.responsiveAjaxDone=true,{once:true}); }")
    part = filters.locator('[name=part]')
    part.select_option(part.locator('option').nth(1).get_attribute('value'))
    page.wait_for_function('window.responsiveAjaxDone')
    assert page.locator('[data-subjective-select]').count() == 1
    assert page.locator('[data-subjective-review-submit]').is_disabled()
    page.evaluate("() => { window.responsiveAjaxDone=false; document.addEventListener('vistiq:ajax-results-updated',()=>window.responsiveAjaxDone=true,{once:true}); }")
    page.locator('#subjective-filters [data-ajax-filter-reset]').click()
    page.wait_for_function('window.responsiveAjaxDone')
    assert page.locator('[data-subjective-select]').count() > 1
    trigger = page.locator('.question-delete-action button').first
    trigger.click()
    panel = page.locator('[data-admin-confirm-dialog] [role=dialog]')
    panel.wait_for(state='visible')
    page.wait_for_function("document.activeElement.matches('[data-admin-confirm-cancel]')")
    bounds = panel.bounding_box()
    assert bounds['x'] >= 0 and bounds['x']+bounds['width'] <= page.viewport_size['width']+1
    assert bounds['y'] >= 0 and bounds['y']+bounds['height'] <= page.viewport_size['height']+1
    page.keyboard.press('Escape')
    assert panel.is_hidden()
    assert trigger.evaluate('(el)=>el===document.activeElement'), page.evaluate('({active:document.activeElement.outerHTML,disabled:document.querySelector(".question-delete-action button")?.disabled})')

def builder_bank(page):
    if not page.locator('[data-wizard-panel="2"]').is_visible():
        page.locator('[data-step-link="0"]').click()
        page.locator('[data-template-category="toeic"]').click()
        page.locator('[data-template-card="toeic-part5"]').click()
        page.locator('[data-step-link="1"]').click()
        page.locator('[data-step-link="2"]').click()
    assert page.locator('[data-wizard-panel="2"]').is_visible()

try:
    deadline = time.monotonic()+20
    while True:
        try:
            with socket.create_connection(('127.0.0.1', port), timeout=.5): break
        except OSError:
            if time.monotonic()>deadline: raise
            time.sleep(.1)
    with sync_playwright() as playwright:
        browser = playwright.chromium.launch(headless=True, executable_path=args.browser)
        probe = browser.new_context().request.get(BASE+'/storage/e2e-isolation.txt')
        assert probe.status == 200 and probe.text() == fixture['storage_marker']
        admin_context, admin = login(browser, 'admin', True)
        learner_context, learner = login(browser, 'learner')
        guest = browser.new_page(viewport={'width':1440,'height':900},has_touch=True)
        # Newly added functions first, including their expanded import/editor states.
        for skill in ['writing','speaking']:
            audit(admin, skill, '/admin/questions/'+skill)
            audit(admin, skill+'-detail', '/admin/questions/'+skill+'?question='+str(fixture['subjective'][skill][0]))
            audit(admin, skill+'-editor', '/admin/questions/create?skill='+skill)
            audit(admin, skill+'-import', '/admin/questions/'+skill, lambda page: page.locator('[data-workbook-import]').evaluate('(el)=>el.open=true'))
        audit(admin, 'account-menu', '/admin/questions/writing', lambda page: page.locator('.admin-account-menu').evaluate('(el)=>el.open=true'))
        audit(admin, 'admin-navigation', '/admin/questions/writing', lambda page: page.locator('[data-sidebar-nav-group]').first.evaluate('(el)=>{el.open=true; const child=el.querySelector("details"); if(child)child.open=true;}'))
        audit(admin, 'question-picker', '/admin/questions/create?skill=writing', lambda page: page.locator('[data-qe-dialog]').evaluate('(el)=>{if(!el.open)el.showModal()}'))
        for skill in ['writing','speaking']:
            audit(admin, skill+'-controls', '/admin/questions/'+skill, subjective_controls)
        def workbook_preview(page):
            container = page.locator('[data-workbook-import]')
            container.evaluate('(el)=>el.open=true')
            if page.locator('[data-workbook-upload]').is_visible():
                skill = container.get_attribute('data-kind')
                content = page.context.request.get(BASE+'/admin/questions/import-template?template='+skill+'-xlsx').body()
                page.locator('[data-workbook-upload] input[type=file]').set_input_files({'name':skill+'.xlsx','mimeType':'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet','buffer':content})
                page.locator('[data-workbook-upload] button').click()
                page.locator('[data-workbook-confirm]').wait_for(state='visible')
        audit(admin, 'writing-import-preview', '/admin/questions/writing', workbook_preview)
        audit(admin, 'speaking-import-preview', '/admin/questions/speaking', workbook_preview)
        if not args.only or any(token in args.only for token in ['runner','result','attempt-detail','grading']):
            for kind in ['reading','writing','speaking']:
                if kind not in fixture['exams']: continue
                visit(learner,'/exams')
                learner.locator(f'form[action$="/exams/{fixture["exams"][kind]}/start"] button').click()
                learner.wait_for_load_state('networkidle')
                attempt_path = learner.url.removeprefix(BASE)
                audit(learner,kind+'-runner',attempt_path)
                if kind in ['reading','writing']:
                    if kind=='reading': learner.locator('input[type=radio][value=B]').check()
                    else: learner.locator('[data-text]').fill('Learning English is useful. I study with real examples every day.')
                    learner.locator('[data-submit]').click()
                    learner.locator('[data-admin-confirm-submit]').click()
                    learner.wait_for_url('**/result')
                    audit(learner,kind+'-result',learner.url.removeprefix(BASE))
                    audit(admin,kind+'-attempt-detail','/admin'+attempt_path)
        for name,path in [
            ('admin-home','/admin'),('listening','/admin/questions/listening'),('reading','/admin/readings'),('thpt','/admin/questions/thpt'),
            ('reading-create','/admin/readings?create=1'),('listening-create','/admin/questions/listening?create=1'),
            ('question-bank','/admin/questions'),('questions-deleted','/admin/questions/deleted'),('media','/admin/media'),('audio','/admin/audio'),('groups','/admin/groups'),
            ('question-editor','/admin/questions/'+str(fixture['question'])+'/edit'),('resources','/admin/resources'),
            ('content-approval','/admin/content-approval'),('exams-admin','/admin/exams'),('exam-editor','/admin/exams/'+str(fixture['draft_exam'])+'/edit'),
            ('exam-create','/admin/exams/create'),('exams-deleted','/admin/exams/deleted'),('approval-detail','/admin/content-approval/question/'+str(fixture['question'])),
            ('attempts-admin','/admin/attempts'),('grading','/admin/grading'),('leaderboard-admin','/admin/leaderboard'),('analytics','/admin/analytics'),
            ('users','/admin/users'),('user-detail','/admin/users/'+str(fixture['actors']['learner'])),('roles','/admin/roles'),
            ('knowledge-admin','/admin/knowledge'),('knowledge-editor','/admin/knowledge/create?type=vocabulary'),
            ('knowledge-import','/admin/knowledge/import'),
            ('settings','/admin/settings'),('database','/admin/settings/database'),('jobs','/admin/settings/jobs'),('audit','/admin/audit'),('admin-search','/admin/search?q=English'),
        ]: audit(admin,name,path)
        if fixture.get('knowledge'):
            audit(admin,'knowledge-preview','/admin/knowledge/'+str(fixture['knowledge'])+'/preview')
            audit(admin,'knowledge-edit','/admin/knowledge/'+str(fixture['knowledge'])+'/edit')
        if fixture.get('resource'):
            audit(admin,'resource-edit','/admin/resources/'+str(fixture['resource'])+'/edit')
        audit(admin, 'exam-editor-templates', '/admin/exams/'+str(fixture['draft_exam'])+'/edit', lambda page: page.locator('[data-step-link="0"]').click())
        audit(admin, 'exam-editor-bank', '/admin/exams/'+str(fixture['draft_exam'])+'/edit', builder_bank)
        audit(admin, 'role-forms', '/admin/roles', lambda page: page.locator('.admin-page details').evaluate_all('(items)=>items.forEach(el=>el.open=true)'))
        for name,path in [('home','/'),('lookup','/lookup?q=commitment'),('grammar-lookup','/lookup?type=grammar&q=likely'),('grammar-library','/grammar'),('grammar-detail','/grammar/e2e-library-1'),('library','/knowledge'),('library-detail','/knowledge/e2e-library-0'),('library-saved','/knowledge/saved'),('profile','/profile'),('profile-password','/account/password'),('profile-saved','/profile/saved'),('profile-history','/profile/history'),('exams','/exams'),('attempt-history','/attempts/history'),('leaderboard','/exams/'+str(fixture['exams']['reading'])+'/leaderboard')]: audit(learner,name,path)
        for name,path in [('login','/login'),('register','/register'),('admin-login','/admin/login'),('forgot-password','/forgot-password'),('forgot-password-sent','/forgot-password/sent'),('admin-forgot-password','/admin/forgot-password'),('reset-password','/reset-password/responsive-test-token?email=learner%40e2e.test')]: audit(guest,name,path)
        audit(learner, 'learner-navigation', '/lookup?q=commitment', lambda page: page.locator('#user-main-navigation').evaluate('(el)=>el.classList.add("is-open")'))
        browser.close()
finally:
    server.terminate()
    server.wait(timeout=10)
    log.close()
    (OUT/'responsive-results.json').write_text(json.dumps({'results':results,'errors':errors},ensure_ascii=False,indent=2),encoding='utf-8')
    print(json.dumps({'out':str(OUT),'checks':len(results),'broken':sum(row['broken'] for row in results),'jsErrors':len(errors)}),flush=True)
if errors or any(row['broken'] for row in results):
    raise SystemExit(1)
