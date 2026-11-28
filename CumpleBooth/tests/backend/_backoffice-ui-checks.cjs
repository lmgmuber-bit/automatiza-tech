const fs=require('node:fs'),path=require('node:path');
module.exports=async({out,base,pass,browser})=>{
 const page=await browser.newPage(),errors=[],results=[];page.on('pageerror',e=>errors.push(e.message));await page.setRequestInterception(true);
 page.on('request',r=>(r.url().startsWith(base)||r.url().startsWith('data:')||r.url().startsWith('blob:'))?r.continue():r.abort());
 await page.goto(base+'/admin/maestro.php',{waitUntil:'networkidle0'});await page.type('[name=password]',pass);
 await Promise.all([page.waitForNavigation({waitUntil:'networkidle0'}),page.click('button[type=submit]')]);
 if(/\/(?:login|maestro)\.php/.test(page.url()))throw Error('No se inició sesión de prueba');
 const routes=[['fiesta-editar','/admin/index.php?action=editar&slug=qa-cumple'],['invitaciones','/admin/invitations.php?party=qa-cumple'],['comprobante','/admin/comprobante.php?p=qa-cumple'],['perfil','/admin/event-profile.php?party=qa-cumple'],['finanzas','/admin/finanzas.php'],['album','/admin/album.php?party=qa-cumple']];
 for(const [name,url] of routes)for(const width of [320,360,390,768,1024,1440]){
  await page.setViewport({width,height:900});const response=await page.goto(base+url,{waitUntil:'networkidle0'});
  // Incluir formularios plegados; deben seguir siendo usables cuando se abren.
  await page.evaluate(()=>document.querySelectorAll('details').forEach(d=>d.open=true));
  if(name==='perfil'){const add=await page.$('#add-person');if(add)await add.click();}
  const dims=await page.evaluate(()=>({viewport:innerWidth,document:document.documentElement.scrollWidth,
   overflow:[...document.querySelectorAll('body *')].filter(e=>e.getBoundingClientRect().right>innerWidth+2).map(e=>({tag:e.tagName,id:e.id,class:e.className,width:e.getBoundingClientRect().width,right:e.getBoundingClientRect().right})),
   unlabeled:[...document.querySelectorAll('input:not([type=hidden]),select,textarea')].filter(e=>e.getBoundingClientRect().width>0&&!e.labels?.length&&!e.getAttribute('aria-label')&&!e.getAttribute('aria-labelledby')).map(e=>e.name),
   duplicateIds:[...document.querySelectorAll('[id]')].map(e=>e.id).filter((id,i,a)=>a.indexOf(id)!==i)}));
  const client=await page.createCDPSession(),tree=await client.send('Accessibility.getFullAXTree');await client.detach();
  const unnamed=tree.nodes.filter(n=>!n.ignored&&['textbox','combobox','checkbox','button','spinbutton','Date','InputTime'].includes(n.role?.value)&&!n.name?.value).map(n=>n.role.value);
  const row={name,width,status:response.status(),...dims,unnamed,ok:response.status()===200 && !/\/(?:login|maestro)\.php/.test(page.url()) && dims.document<=width+2&&unnamed.length===0&&dims.unlabeled.length===0&&dims.duplicateIds.length===0};results.push(row);
  if(width===390){await page.evaluate(()=>{for(const el of document.querySelectorAll('input,textarea'))if(/[a-f0-9]{24,}/i.test(el.value))el.style.webkitTextSecurity='disc';});await page.screenshot({path:path.join(out,name+'-390.png'),fullPage:true});}
  console.log((row.ok?'PASS ':'FAIL ')+name+' '+width+' doc='+dims.document+' sin-etiqueta='+(unnamed.length+dims.unlabeled.length)+' ids-duplicados='+dims.duplicateIds.length);
 }
 const failures=results.filter(r=>!r.ok).length+errors.length;
 fs.writeFileSync(path.join(out,'ui.json'),JSON.stringify({results,errors,failures},null,2));console.log('backoffice-ui: '+results.length+' vistas, '+failures+' fallos');return failures;
};