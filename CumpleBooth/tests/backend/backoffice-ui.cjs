// Ejecutar con CC_QA_PHP, CC_QA_CHROME y CC_QA_OUT (solo rutas, nunca credenciales).
const fs=require('node:fs'),path=require('node:path'),os=require('node:os'),cp=require('node:child_process'),crypto=require('node:crypto'),net=require('node:net');
const puppeteer=require('puppeteer-core');
const root=path.resolve(__dirname,'../..'),php=process.env.CC_QA_PHP||'php',chrome=process.env.CC_QA_CHROME;
const tmp=fs.mkdtempSync(path.join(os.tmpdir(),'cc-backoffice-ui-'));
const out=process.env.CC_QA_OUT||path.join(tmp,'evidencia');fs.mkdirSync(out,{recursive:true});
for(const d of ['state','photos','sessions','acceptances','profiles','invitations'])fs.mkdirSync(path.join(tmp,d));
let browser,server;
(async()=>{
 const probe=net.createServer();await new Promise(r=>probe.listen(0,'127.0.0.1',r));const port=probe.address().port;await new Promise(r=>probe.close(r));
 const base='http://127.0.0.1:'+port,pass=crypto.randomBytes(24).toString('hex');
 const env={...process.env,CC_AUDIT_ROOT:root,CC_QA_PASSWORD:pass,CUMPLECLICK_CONFIG_FILE:path.join(tmp,'no-config.php'),CC_STORAGE_MODE:'db',
 CC_PDO_DSN:'sqlite:'+path.join(tmp,'test.sqlite'),CC_PDO_USER:'',CC_PDO_PASSWORD:'',CC_APP_HMAC_KEY:crypto.randomBytes(32).toString('hex'),CC_PUBLIC_BASE_URL:base,
 CC_PHOTO_DIR:path.join(tmp,'photos'),CC_STATE_DIR:path.join(tmp,'state'),CC_ACCEPTANCE_DIR:path.join(tmp,'acceptances'),CC_EVENT_PROFILE_DIR:path.join(tmp,'profiles'),CC_EVENT_PROFILE_ENABLED:'1',
 CC_INVITATION_DIR:path.join(tmp,'invitations'),CC_AJUSTES_PATH:path.join(tmp,'ajustes.json'),CC_SMTP_HOST:'',CC_SMTP_USER:'',CC_SMTP_PASSWORD:'',CC_NOTIFY_EMAIL:'',CC_LEADS_NOTIFY_EMAIL:''};
 env.CC_ADMIN_PASSWORD_HASH=cp.execFileSync(php,['-r',"echo password_hash(getenv('CC_QA_PASSWORD'),PASSWORD_DEFAULT);"],{env,encoding:'utf8',windowsHide:true}).trim();
 cp.execFileSync(php,[path.join(__dirname,'seed-backoffice-ui.php')],{env,stdio:'pipe',windowsHide:true});
 server=cp.spawn(php,['-d','session.save_path='+path.join(tmp,'sessions'),'-S','127.0.0.1:'+port,'-t',path.join(root,'public')],{env,stdio:'ignore',windowsHide:true});
 let ready=false;for(let i=0;i<60;i++){try{await fetch(base+'/admin/login.php');ready=true;break}catch{}await new Promise(r=>setTimeout(r,100));}
 if(!ready)throw Error('Servidor de prueba no disponible');
 browser=await puppeteer.launch({executablePath:chrome,headless:true,args:['--disable-background-networking']});
 const failures=await require('./_backoffice-ui-checks.cjs')({root,out,base,pass,browser});
 process.exitCode=failures?1:0;
})().catch(e=>{console.error('FAIL UI: '+e.message.replace(/[a-f0-9]{32,}/gi,'[oculto]'));process.exitCode=1;}).finally(async()=>{
 if(browser)await browser.close();if(server){if(server.exitCode===null){const exited=new Promise(r=>server.once('exit',r));server.kill();await exited;}}
 fs.rmSync(tmp,{recursive:true,force:true,maxRetries:10,retryDelay:100});
});
