"""Run MySQL contracts only in a new disposable prefixed database (never app DB)."""
import json, os, pathlib, subprocess, sys, time, uuid

ROOT = pathlib.Path(__file__).resolve().parents[2]
OUT = ROOT / 'storage' / 'browser-mysql' / uuid.uuid4().hex
OUT.mkdir(parents=True)
for directory in ['framework/cache/data','framework/sessions','framework/views','logs','app/private','app/public']:
    (OUT/directory).mkdir(parents=True, exist_ok=True)
env = os.environ | {'APP_ENV':'testing','LARAVEL_STORAGE_PATH':str(OUT),'PHAT_MYSQL_DATABASE':'vistiq_phat_test_'+uuid.uuid4().hex,'PHAT_MYSQL_PORT':os.environ.get('PHAT_MYSQL_PORT','3306')}
def run(script, *args):
    result = subprocess.run(['php',str(ROOT/script),*map(str,args)],cwd=ROOT,env=env,stdout=subprocess.PIPE,stderr=subprocess.STDOUT)
    text = result.stdout.decode('utf-8',errors='replace')
    (OUT/(pathlib.Path(script).stem+'-'+str(args[0])+'.log')).write_text(text,encoding='utf-8')
    if result.returncode: raise RuntimeError(text[-4000:])
    print(text.strip(),flush=True)
run('tests/E2E/mysql-system.php','create')
try:
    for script in ['tests/Admin/Feature/ContentApproval/mysql-review.php','tests/Admin/Feature/ExamAttempts/mysql-attempts.php']:
        fixture = OUT/(pathlib.Path(script).stem+'.json')
        run(script,'prepare',fixture)
        workers = [subprocess.Popen(['php',str(ROOT/script),'worker',str(fixture),str(i)],cwd=ROOT,env=env,stdout=subprocess.PIPE,stderr=subprocess.STDOUT) for i in range(2)]
        deadline = time.monotonic()+30
        while not all(pathlib.Path(str(fixture)+'.ready'+str(i)).exists() for i in range(2)):
            if time.monotonic()>deadline: raise RuntimeError('Worker barrier timeout')
            time.sleep(.1)
        pathlib.Path(str(fixture)+'.go').write_text('go')
        for worker in workers:
            stdout,_=worker.communicate(timeout=40)
            if worker.returncode: raise RuntimeError(stdout.decode('utf-8',errors='replace'))
        run(script,'verify',fixture)
    run('tests/E2E/mysql-system.php','verify')
    print(json.dumps({'mysql':'passed','artifacts':str(OUT)}))
finally:
    run('tests/E2E/mysql-system.php','drop')
