<?php
declare(strict_types=1);
require_once __DIR__ . '/../backend/helpers/funcoes.php';
iniciar_sessao();
?>
<!doctype html>
<html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Entrar · Larissa Beauty</title><link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@500;600&family=Montserrat:wght@400;500;600&display=swap" rel="stylesheet"><link rel="stylesheet" href="assets/admin.css"></head>
<body class="login"><form id="login-form"><span class="marca">Larissa Beauty</span><p>Entre para gerenciar sua agenda.</p>
<div id="erro" class="erro" hidden></div><label>E-mail<input type="email" name="email" required autofocus></label>
<label>Senha<input type="password" name="senha" required></label><button type="submit" class="btn largo">Entrar</button></form>
<script>
const form=document.getElementById('login-form'),erro=document.getElementById('erro');
const API='../backend/api';
async function req(file,options={}) {
  const r=await fetch(API+'/'+file,{credentials:'same-origin',cache:'no-store',...options});
  const d=await r.json().catch(()=>({}));
  if(!r.ok) throw Error(d.erro||'Falha na comunicação com o servidor ('+r.status+').');
  return d;
}
form.addEventListener('submit',async e=>{
  e.preventDefault();erro.hidden=true;
  const btn=form.querySelector('button');btn.disabled=true;
  try {
    const inicio=await req('me.php');
    if(!inicio.csrf) throw Error('Não foi possível iniciar a sessão. Atualize a página.');
    await req('login.php',{method:'POST',headers:{'Content-Type':'application/json','X-CSRF-Token':inicio.csrf},
      body:JSON.stringify({email:form.elements.email.value.trim(),senha:form.elements.senha.value})});
    const atual=await req('me.php');
    if(!atual.logado||!atual.usuario) throw Error('A sessão não foi mantida pelo navegador. Verifique os cookies.');
    if(atual.usuario.tipo!=='admin') {
      await req('logout.php',{method:'POST',headers:{'Content-Type':'application/json','X-CSRF-Token':atual.csrf},body:'{}'});
      throw Error('Esta conta não é administradora. Use o e-mail da conta admin.');
    }
    location.href='dashboard.php';
  } catch(err) { erro.textContent=err.message;erro.hidden=false;btn.disabled=false; }
});
</script></body></html>
