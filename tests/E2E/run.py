"""Real Chrome journeys with CSRF, isolated SQLite/storage, screenshots and responsive checks.
Usage: python tests/E2E/run.py [--browser C:/path/to/chrome.exe]
"""
import argparse, base64, json, os, pathlib, socket, struct, subprocess, sys, time, uuid, zlib
from playwright.sync_api import sync_playwright, expect

sys.stdout.reconfigure(encoding='utf-8')
ROOT = pathlib.Path(__file__).resolve().parents[2]
OUT = ROOT/'storage'/'browser-e2e'/uuid.uuid4().hex
OUT.mkdir(parents=True)
for directory in ['framework/cache/data','framework/sessions','framework/views','logs','app/private','app/public']:
    (OUT/directory).mkdir(parents=True,exist_ok=True)
(OUT/'database.sqlite').touch()
with socket.socket() as sock:
    sock.bind(('127.0.0.1',0)); port=sock.getsockname()[1]
BASE=f'http://127.0.0.1:{port}'
env=os.environ|{'APP_ENV':'e2e','APP_DEBUG':'false','APP_URL':BASE,'APP_KEY':'base64:'+base64.b64encode(os.urandom(32)).decode(),'DB_CONNECTION':'sqlite','DB_DATABASE':str(OUT/'database.sqlite'),'DB_URL':'','LARAVEL_STORAGE_PATH':str(OUT),'SESSION_DRIVER':'file','SESSION_SECURE_COOKIE':'false','CACHE_STORE':'file','QUEUE_CONNECTION':'sync','MAIL_MAILER':'log','BCRYPT_ROUNDS':'4','LOG_CHANNEL':'single'}
parser=argparse.ArgumentParser(); parser.add_argument('--browser',default=os.environ.get('CHROME_PATH','C:/Program Files/Google/Chrome/Application/chrome.exe')); args=parser.parse_args()
subprocess.run(['php',str(ROOT/'tests/E2E/bootstrap.php')],cwd=ROOT,env=env,check=True)
fixture=json.loads((OUT/'fixture.json').read_text())
log=(OUT/'server.log').open('wb')
server=subprocess.Popen(['php','-d','variables_order=EGPCS','-S',f'127.0.0.1:{port}','-t',str(ROOT/'public'),str(ROOT/'tests/E2E/router.php')],cwd=ROOT/'public',env=env,stdout=log,stderr=subprocess.STDOUT,creationflags=getattr(subprocess,'CREATE_NO_WINDOW',0))
checks=[]; errors=[]; pages=[]

def avatar_png():
    # Small deterministic PNG fixture, including visible content for screenshot review.
    rows=[]
    for y in range(96):
        row=bytearray([0])
        for x in range(96):
            letter=(29<=x<36 and 25<=y<72) or (29<=x<65 and (25<=y<32 or 45<=y<52 or 65<=y<72))
            row.extend((255,255,255) if letter else (int(116-y*.4),int(159-y*.35),239))
        rows.append(bytes(row))
    def chunk(kind,data): return struct.pack('>I',len(data))+kind+data+struct.pack('>I',zlib.crc32(kind+data)&0xffffffff)
    return b'\x89PNG\r\n\x1a\n'+chunk(b'IHDR',struct.pack('>IIBBBBB',96,96,8,2,0,0,0))+chunk(b'IDAT',zlib.compress(b''.join(rows)))+chunk(b'IEND',b'')
def check(name): checks.append(name); print('PASS: '+name,flush=True)
def goto(page,path):
    response=page.goto(BASE+path,wait_until='domcontentloaded')
    (OUT/'last-page.html').write_text(page.content(),encoding='utf-8')
    assert response.status<400,(path,response.status)
def submit(page,button):
    with page.expect_navigation(wait_until='domcontentloaded') as navigation: button.click()
    (OUT/'last-submitted-page.html').write_text(page.content(),encoding='utf-8')
    assert navigation.value.status < 400,(page.url,navigation.value.status,page.locator('body').inner_text()[:500])
def login(browser,actor,admin=False):
    ctx=browser.new_context(base_url=BASE,viewport={'width':1440,'height':1000},accept_downloads=True)
    page=ctx.new_page(); pages.append(page)
    page.on('pageerror',lambda error:errors.append(str(error)))
    goto(page,'/admin/login' if admin else '/login')
    page.locator('input[name="email"]').fill(actor+'@e2e.test')
    page.locator('input[name="password"]').fill('VistiqE2e123!')
    submit(page,page.locator('form button[type="submit"]').first)
    assert '/login' not in page.url,page.url
    return ctx,page
def dialog(page,accept=True):
    panel=page.locator('[data-admin-confirm-dialog]')
    expect(panel).to_be_visible()
    expect(panel.locator('button[data-admin-confirm-cancel]')).to_be_focused()
    page.keyboard.press('Shift+Tab'); expect(panel.locator('[data-admin-confirm-submit]')).to_be_focused()
    page.keyboard.press('Tab'); expect(panel.locator('button[data-admin-confirm-cancel]')).to_be_focused()
    if accept: submit(page,panel.locator('[data-admin-confirm-submit]'))
    else: page.keyboard.press('Escape'); expect(panel).to_be_hidden()
def fit(page,name):
    for width in [375,768,1024,1440]:
        page.set_viewport_size({'width':width,'height':1000})
        if width==375 and page.locator('[data-sidebar-nav-group]').count():
            expect(page.locator('[data-sidebar-nav-group][open]')).to_have_count(0)
        page.screenshot(path=str(OUT/f'{name}-{width}.png'),full_page=True)
        size=page.evaluate('({width:document.documentElement.clientWidth,scroll:document.documentElement.scrollWidth})')
        assert size['scroll']<=size['width']+1,(name,width,size)
    page.set_viewport_size({'width':1440,'height':1000})

try:
    deadline=time.monotonic()+15
    while True:
        try:
            with socket.create_connection(('127.0.0.1',port),timeout=.5): break
        except OSError:
            if time.monotonic()>deadline: raise
            time.sleep(.1)
    pw=sync_playwright().start()
    browser=pw.chromium.launch(executable_path=args.browser,headless=True)
    probe=browser.new_context().request.get(BASE+'/storage/e2e-isolation.txt')
    assert probe.status==200 and probe.text()==fixture['storage_marker'],('HTTP storage isolation failed; refusing E2E mutations',probe.status,probe.text()[:500])
    admin_ctx,admin=login(browser,'admin',True)
    author_ctx,author=login(browser,'author',True)
    review_ctx,review=login(browser,'reviewer',True)
    learner_ctx,learner=login(browser,'learner')
    assert review_ctx.request.get('/admin/settings').status==403
    assert author_ctx.request.get('/admin/roles').status==403
    denied=learner_ctx.request.get('/admin/settings',max_redirects=0)
    assert denied.status==302 and '/admin/login' in denied.headers['location']
    assert admin_ctx.request.post('/admin/settings/exam',form={'_method':'PUT'}).status==419
    check('Authentication, direct URL permissions and real CSRF rejection')

    question=fixture['question']
    goto(author,f'/admin/questions/{question}/edit')
    submit(author,author.get_by_role('button',name='Gửi duyệt',exact=True))
    goto(review,f'/admin/content-approval/question/{question}')
    review.get_by_role('button',name='Duyệt revision 1',exact=True).click(); dialog(review,False)
    review.get_by_role('button',name='Duyệt revision 1',exact=True).click(); dialog(review)
    expect(review.get_by_text('Chờ xuất bản',exact=True)).to_be_visible()
    goto(admin,f'/admin/questions/{question}/edit')
    submit(admin,admin.get_by_role('button',name='Xuất bản',exact=True))
    check('Question revision handoff, reviewer decision, safe dialog focus and publication')

    goto(author,'/admin/knowledge/create?type=idiom'); fit(author,'admin-knowledge-editor')
    author.locator('[name="english"]').fill('E2E shared knowledge')
    author.locator('[name="meaning_vi"]').fill('Kiến thức kiểm thử xuyên module')
    submit(author,author.get_by_role('button',name='Tạo bản nháp',exact=True))
    knowledge_id=author.url.split('/')[-2]
    submit(author,author.get_by_role('button',name='Gửi duyệt',exact=True))
    goto(review,f'/admin/knowledge/{knowledge_id}/preview')
    submit(review,review.get_by_role('button',name='Duyệt',exact=True))
    goto(admin,f'/admin/knowledge/{knowledge_id}/preview')
    submit(admin,admin.get_by_role('button',name='Xuất bản',exact=True))
    goto(learner,'/lookup?q=E2E')
    knowledge_slug=learner.locator('.dictionary-word-header form').get_attribute('action').split('/')[-2]
    with learner.expect_response(lambda response: '/knowledge/' in response.url and response.request.method=='POST' and response.status==200):
        learner.get_by_role('button',name='Lưu vào kho kiến thức',exact=True).click()
    expect(learner.get_by_role('button',name='Bỏ lưu khỏi kho kiến thức',exact=True)).to_be_visible()
    goto(learner,'/knowledge?item='+knowledge_slug)
    learner.locator('.library-collections summary').click()
    learner.locator('#collection-name').fill('E2E private collection')
    submit(learner,learner.locator('.library-collections form button'))
    learner.locator('#library-collection').select_option(label='E2E private collection')
    learner.locator('#library-note').fill('My private E2E note')
    submit(learner,learner.get_by_role('button',name='Lưu thay đổi',exact=True))
    goto(learner,'/profile/saved'); expect(learner.get_by_text('My private E2E note')).to_be_visible()
    check('Knowledge create → review → publish → learner reads/saves → profile saved contract')
    goto(learner,'/knowledge')
    learner.locator('[data-library-select]').filter(has_text='commitment').first.click()
    expect(learner.locator('#knowledge-detail h3')).to_have_text('commitment')
    expect(learner.locator('#knowledge-detail')).to_be_focused()
    assert 'item=e2e-library-0' in learner.url
    check('Personal library detail selection loads without a full page navigation')

    goto(admin,'/admin/knowledge/import?type=idiom')
    admin.locator('input[type="file"]').set_input_files({'name':'invalid.csv','mimeType':'text/csv','buffer':'english,meaning_vi\nInvalid missing meaning,\n'.encode()})
    submit(admin,admin.get_by_role('button',name='Xem trước dữ liệu',exact=True))
    expect(admin.get_by_text('Dòng 2:',exact=False)).to_be_visible()
    assert admin.locator('button[type="submit"]:enabled').count()==0
    check('Import preview rejects row errors before committing')

    goto(learner,'/exams')
    reading=fixture['exams']['reading']
    submit(learner,learner.locator(f'form[action$="/exams/{reading}/start"] button'))
    expect(learner.locator('input[type="radio"][value="B"]')).to_be_visible()
    fit(learner,'learner-runner')
    learner_ctx.set_offline(True)
    learner.locator('input[type="radio"][value="B"]').check()
    learner.wait_for_timeout(1200)
    learner_ctx.set_offline(False)
    expect(learner.locator('[data-save-status]')).to_contain_text('Đã đồng bộ với server',timeout=15000)
    learner.reload(); expect(learner.locator('input[value="B"]')).to_be_checked()
    learner.locator('[data-submit]').click(); dialog(learner)
    expect(learner.get_by_text('Đúng: 1',exact=False)).to_be_visible()
    goto(learner,f'/exams/{reading}/leaderboard'); assert 'learner@e2e.test' not in learner.content()
    goto(learner,'/profile/history'); expect(learner.get_by_role('link',name='Xem kết quả')).to_be_visible()
    check('Exam snapshot, offline answer retry/autosave, submit, score, history and public leaderboard')

    writing=fixture['exams']['writing']; goto(learner,'/exams')
    submit(learner,learner.locator(f'form[action$="/exams/{writing}/start"] button'))
    fit(learner,'learner-writing-runner')
    learner.locator('[data-text]').fill('Learning English is useful. I study with real examples every day.')
    expect(learner.locator('[data-save-status]')).to_contain_text('Đã đồng bộ với server',timeout=15000)
    learner.locator('[data-submit]').click(); dialog(learner)
    goto(admin,'/admin/grading')
    for field in admin.locator('input[name^="criteria["]').all(): field.fill('6')
    admin.locator('textarea[name="feedback"]').first.fill('Clear response. Continue developing examples.')
    submit(admin,admin.get_by_role('button',name='Công bố điểm Estimated',exact=True))
    goto(learner,'/profile/history'); expect(learner.get_by_text('Chưa có điểm')).to_have_count(0)
    check('Writing submission → reviewer rubric grading → profile real results')

    goto(admin,f'/admin/leaderboard?exam_id={reading}'); fit(admin,'admin-leaderboard')
    for fmt in ['csv','xlsx']:
        with admin.expect_download() as info:
            admin.locator(f'a[data-report-export][href*="format={fmt}"]').click()
        download=info.value; download.save_as(OUT/download.suggested_filename)
        assert download.failure() is None
    expect(admin.locator('a[data-report-export]').first).not_to_have_attribute('aria-busy','true')
    check('Permission guarded CSV/XLSX downloads and pending reset')

    goto(learner,'/profile')
    learner.locator('[name="name"]').fill('E2E learner updated')
    learner.locator('[name="country_code"]').fill('vn')
    learner.locator('[name="time_zone"]').select_option('Asia/Ho_Chi_Minh')
    learner.locator('[name="bio"]').fill('Saved through the real profile endpoint.')
    learner.locator('[data-avatar-open]').first.click()
    learner.locator('[name="avatar"]').set_input_files({'name':'avatar.png','mimeType':'image/png','buffer':avatar_png()})
    expect(learner.locator('[data-avatar-preview]')).to_be_visible()
    submit(learner,learner.get_by_role('button',name='Lưu thay đổi',exact=True))
    expect(learner.locator('[name="name"]')).to_have_value('E2E learner updated')
    expect(learner.locator('[data-avatar-preview]')).to_be_visible()
    learner.wait_for_function("document.querySelector('[data-avatar-preview]').complete && document.querySelector('[data-avatar-preview]').naturalWidth>0")
    learner.locator('[name="time_zone"]').evaluate("el=>{el.add(new Option('Invalid/Timezone','Invalid/Timezone'));el.value='Invalid/Timezone'}")
    learner.locator('[data-avatar-open]').first.click()
    learner.locator('[name="avatar"]').set_input_files({'name':'retry-avatar.png','mimeType':'image/png','buffer':avatar_png()})
    with learner.expect_response(lambda response: response.url.endswith('/profile') and response.request.method=='POST' and response.status==422):
        learner.get_by_role('button',name='Lưu thay đổi',exact=True).click()
    assert learner.locator('[name="avatar"]').evaluate('(input)=>input.files.length')==1
    expect(learner.locator('#time_zone-error')).not_to_be_empty()
    expect(learner.locator('[name="bio"]')).to_have_value('Saved through the real profile endpoint.')
    learner.locator('[name="time_zone"]').select_option('Asia/Ho_Chi_Minh')
    submit(learner,learner.get_by_role('button',name='Lưu thay đổi',exact=True)); fit(learner,'learner-profile')
    learner.emulate_media(reduced_motion='reduce')
    assert learner.evaluate("matchMedia('(prefers-reduced-motion: reduce)').matches")
    assert learner.evaluate("getComputedStyle(document.documentElement).scrollBehavior") == "auto"
    check('Profile avatar preview/persistence, field validation/preservation, responsive and reduced motion')

    for page,path,name in [
        (admin,'/admin','admin-overview'),(admin,'/admin/content-approval','admin-review-queue'),
        (admin,'/admin/roles','admin-roles'),(admin,'/admin/users','admin-users'),
        (admin,'/admin/readings','admin-reading-bank'),(admin,f'/admin/exams/{reading}/edit','admin-exam-builder'),
        (admin,f'/admin/content-approval/question/{question}','admin-review'),
        (admin,f'/admin/knowledge/{knowledge_id}/preview','admin-knowledge'),
        (learner,'/profile/history','learner-history'),(learner,'/profile/saved','learner-saved'),
        (learner,'/knowledge','learner-knowledge')]:
        goto(page,path); fit(page,name)
    check('Four viewport layout checks across Identity, Question Bank, Exams, Approval, Knowledge, history and saved items')
    goto(admin,'/admin/settings?group=exam'); fit(admin,'admin-settings')
    admin.locator('[name="value[duration_minutes]"]').fill('45')
    submit(admin,admin.get_by_role('button',name='Lưu làm đề',exact=True))
    expect(admin.locator('[name="value[duration_minutes]"]')).to_have_value('45')
    goto(admin,'/admin/audit?action=settings.update'); fit(admin,'admin-audit')
    expect(admin.get_by_text('settings.update',exact=True).first).to_be_visible()
    goto(admin,'/admin/settings/database'); fit(admin,'admin-database')
    admin.locator('#backup-password').fill('VistiqE2e123!')
    with admin.expect_download() as info: admin.get_by_role('button',name='Tạo và tải bản sao lưu',exact=True).click()
    backup=info.value; backup_path=OUT/'http-backup.sql'; backup.save_as(backup_path)
    assert backup_path.read_text(encoding='utf-8').startswith('-- Vistiq Database Backup v2')
    expect(admin.get_by_role('button',name='Tạo và tải bản sao lưu',exact=True)).to_be_enabled()
    admin.locator('#restore-file').set_input_files(backup_path)
    submit(admin,admin.get_by_role('button',name='Tải lên và kiểm tra',exact=True))
    expect(admin.get_by_text('2. Xác nhận phục hồi',exact=True)).to_be_visible()
    restore=admin.locator('form[action$="/restore"]')
    restore.locator('[name="password"]').fill('VistiqE2e123!'); restore.locator('[name="confirm_restore"]').check()
    restore.get_by_role('button',name='Phục hồi database',exact=True).click(); dialog(admin,False)
    assert '/admin/settings/database' in admin.url
    # Execute only against this run's isolated SQLite/storage. Real maintenance never touches app storage.
    restore.get_by_role('button',name='Phục hồi database',exact=True).click(); dialog(admin)
    assert '/admin/login' in admin.url
    assert not (OUT/'framework/down').exists()
    check('Typed settings → redacted audit → private backup download → preflight → confirmed restore/logout')
    assert not errors,errors
    check('No browser JavaScript exceptions')
    browser.close()
except Exception:
    for index,page in enumerate(pages):
        try: page.screenshot(path=str(OUT/f'failure-{index}.png'),full_page=True); (OUT/f'failure-{index}.html').write_text(page.content(),encoding='utf-8')
        except Exception: pass
    raise
finally:
    if 'pw' in globals(): pw.stop()
    server.terminate(); server.wait(timeout=15); log.close()
    (OUT/'result.json').write_text(json.dumps({'checks':checks,'js_errors':errors,'artifacts':str(OUT)},ensure_ascii=False,indent=2),encoding='utf-8')
    print('Artifacts: '+str(OUT),flush=True)
