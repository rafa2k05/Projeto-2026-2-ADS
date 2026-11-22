<?php require __DIR__.'/_auth.php'; ?><!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Serviços · Larissa Beauty</title><link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@500;600&family=Montserrat:wght@400;500;600&display=swap" rel="stylesheet"><link rel="stylesheet" href="assets/admin.css"></head><body><div class="app"><nav class="menu"><a class="marca" href="dashboard.php">Larissa Beauty</a><a class="link" href="dashboard.php">Início</a><a class="link" href="agenda.php">Agenda</a><a class="link on" href="servicos.php">Serviços</a><a class="link" href="horarios.php">Horários</a><a class="link sair" href="#" id="sair">Sair</a></nav><main class="conteudo"><div class="cabeca"><div><h1>Serviços</h1><p>O que aparece para a cliente escolher no agendamento.</p></div></div><form class="form-linha" id="novo"><label>Nome<input name="nome" required></label><label>Preço (R$)<input name="preco" type="number" min="0" step="0.01" required></label><label>Duração (minutos)<input name="duracao_minutos" type="number" min="1" step="1" required></label><button class="btn">Adicionar</button></form><p id="msg" class="aviso" hidden></p><div class="lista" id="lista"><div class="vazio">Carregando serviços do banco...</div></div></main></div>
<dialog class="dlg" id="dlg"><form id="form-editar"><h2>Editar serviço</h2><div class="erro" id="erro" hidden></div><label>Nome<input name="nome" required maxlength="100"></label><div class="duas"><label>Preço (R$)<input name="preco" type="number" min="0" step="0.01" required></label><label>Duração (minutos)<input name="duracao_minutos" type="number" min="1" max="1440" step="1" required></label></div><small>Agendamentos que já foram feitos continuam com o preço e a duração de quando foram marcados.</small><div class="acoes"><button type="button" class="btn sec" id="fechar">Voltar</button><button class="btn">Salvar alterações</button></div></form></dialog>
<script>
async function api(m,d){const o={method:m,credentials:'same-origin',cache:'no-store',headers:{}};if(d){const me=await fetch('../backend/api/me.php',{credentials:'same-origin',cache:'no-store'}).then(r=>r.json());if(!me.csrf)throw Error('Sessão expirada. Entre novamente.');o.headers['Content-Type']='application/json';o.headers['X-CSRF-Token']=me.csrf;o.body=JSON.stringify(d);}const r=await fetch('../backend/api/admin_servicos.php',o);const x=await r.json().catch(()=>({}));if(!r.ok)throw Error(x.erro||'Falha ('+r.status+')');return x;}
const esc=s=>String(s??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
const reais=n=>Number(n).toLocaleString('pt-BR',{style:'currency',currency:'BRL'});
let rows=[],editandoId=null;
const lista=document.getElementById('lista'),msg=document.getElementById('msg'),dlg=document.getElementById('dlg'),formEditar=document.getElementById('form-editar'),erro=document.getElementById('erro');

// Mostra a mensagem de sucesso (verde) ou de falha (vermelha) no topo da lista.
function avisar(texto,ok=true){msg.textContent=texto;msg.className='aviso'+(ok?'':' falha');msg.hidden=false;}

async function load(){try{rows=(await api('GET')).servicos;lista.innerHTML=rows.map(s=>`<div class="item simples ${Number(s.ativo)?'':'inativo'}"><div><span class="nome">${esc(s.nome)}</span><small>${reais(s.preco)} · ${s.duracao_minutos} min</small></div><div class="acoes"><label style="display:flex;gap:8px;align-items:center"><input type="checkbox" class="chave" data-id="${s.id}" ${Number(s.ativo)?'checked':''}> ${Number(s.ativo)?'Ativo':'Inativo'}</label><button class="btn sec" data-editar="${s.id}">Editar</button><button class="btn perigo" data-rm="${s.id}">Desativar</button></div></div>`).join('')||'<div class="vazio">Nenhum serviço cadastrado.</div>';}catch(e){lista.innerHTML='<div class="vazio">'+esc(e.message)+'</div>';}}

// Ativar/desativar pela chave.
lista.addEventListener('change',async e=>{if(!e.target.matches('[data-id]'))return;try{const r=await api('PATCH',{id:Number(e.target.dataset.id),ativo:e.target.checked?1:0});avisar(r.mensagem);await load();}catch(x){alert(x.message);await load();}});

lista.addEventListener('click',async e=>{
  // EDITAR: abre o pop-up já preenchido com os dados atuais do serviço.
  const ed=e.target.closest('[data-editar]');
  if(ed){const s=rows.find(r=>r.id===Number(ed.dataset.editar));if(!s)return;editandoId=s.id;erro.hidden=true;formEditar.elements.nome.value=s.nome;formEditar.elements.preco.value=s.preco;formEditar.elements.duracao_minutos.value=s.duracao_minutos;dlg.showModal();return;}
  // EXCLUIR (desativar), com confirmação.
  const b=e.target.closest('[data-rm]');if(!b)return;
  if(!confirm('Desativar este serviço? O histórico de agendamentos será preservado.'))return;
  try{const r=await api('DELETE',{id:Number(b.dataset.rm)});avisar(r.mensagem);await load();}catch(x){alert(x.message);}
});

// SALVAR a edição.
formEditar.addEventListener('submit',async e=>{e.preventDefault();erro.hidden=true;const d=Object.fromEntries(new FormData(formEditar));d.id=editandoId;d.preco=Number(d.preco);d.duracao_minutos=Number(d.duracao_minutos);
  try{const r=await api('PUT',d);dlg.close();avisar(r.mensagem+' "'+d.nome+'" agora está '+reais(d.preco)+' · '+d.duracao_minutos+' min.');await load();}catch(x){erro.textContent=x.message;erro.hidden=false;}});
document.getElementById('fechar').onclick=()=>dlg.close();

// INCLUIR novo serviço.
document.getElementById('novo').addEventListener('submit',async e=>{e.preventDefault();const d=Object.fromEntries(new FormData(e.target));d.preco=Number(d.preco);d.duracao_minutos=Number(d.duracao_minutos);try{const r=await api('POST',d);e.target.reset();avisar(r.mensagem+' "'+d.nome+'" já aparece para as clientes.');await load();}catch(x){avisar(x.message,false);}});

document.getElementById('sair').onclick=async e=>{e.preventDefault();const me=await fetch('../backend/api/me.php').then(r=>r.json());await fetch('../backend/api/logout.php',{method:'POST',headers:{'Content-Type':'application/json','X-CSRF-Token':me.csrf},body:'{}'});location.href='login.php';};
load();
</script></body></html>
