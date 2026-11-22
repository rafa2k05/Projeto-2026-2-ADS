<?php require __DIR__.'/_auth.php'; ?><!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Agenda · Larissa Beauty</title><link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@500;600&family=Montserrat:wght@400;500;600&display=swap" rel="stylesheet"><link rel="stylesheet" href="assets/admin.css"></head><body><div class="app"><nav class="menu"><a class="marca" href="dashboard.php">Larissa Beauty</a><a class="link " href="dashboard.php">Início</a><a class="link on" href="agenda.php">Agenda</a><a class="link " href="servicos.php">Serviços</a><a class="link " href="horarios.php">Horários</a><a class="link sair" href="#" id="sair">Sair</a></nav><main class="conteudo"><div class="cabeca"><div><h1>Agenda</h1><p>Todos os agendamentos registrados no banco.</p></div><button class="btn" id="novo">Novo agendamento</button></div><div class="filtros"><input type="search" id="busca" placeholder="Buscar por nome ou telefone"><input type="date" id="data"><select id="status"><option value="">Todos os status</option><option>pendente</option><option>confirmado</option><option>cancelado</option></select><button class="btn sec" id="limpar">Limpar filtros</button></div><p class="aviso" id="aviso" hidden></p><p class="resumo" id="resumo"></p><div class="lista" id="lista"><div class="vazio">Carregando...</div></div></main></div><dialog class="dlg" id="dlg"><form id="form"><h2 id="dlg-titulo">Novo agendamento</h2><div class="erro" id="erro" hidden></div><label>Cliente<input name="cliente_nome" required></label><label>Telefone<input name="cliente_telefone" required></label><label>Serviço<select name="servico_id" id="servicos" required></select></label><div class="duas"><label>Data<input name="data" type="date" required></label><label>Hora<select name="hora" required><option value="">Selecione</option><option>10:00</option><option>12:00</option><option>14:00</option><option>16:00</option><option>18:00</option></select></label></div><div class="acoes"><button type="button" class="btn sec" id="fechar">Voltar</button><button class="btn">Salvar</button></div></form></dialog><script>
async function api(url,method='GET',data=null){
  const opt={method,credentials:'same-origin',cache:'no-store',headers:{}};
  if(data!==null){
    const m=await fetch('../backend/api/me.php',{credentials:'same-origin',cache:'no-store'}).then(r=>r.json());
    if(!m.csrf) throw Error('Sessão expirada. Entre novamente no painel.');
    opt.headers['Content-Type']='application/json';
    opt.headers['X-CSRF-Token']=m.csrf;
    opt.body=JSON.stringify(data);
  }
  const r=await fetch('../backend/api/'+url,opt);
  const d=await r.json().catch(()=>({}));
  if(!r.ok)throw Error(d.erro||'Erro na operação ('+r.status+').');
  return d;
}
document.getElementById('sair')?.addEventListener('click',async e=>{e.preventDefault();try{await api('logout.php','POST',{});}finally{location.href='login.php';}});
const br=d=>new Date(d+'T12:00:00').toLocaleDateString('pt-BR'), esc=s=>String(s??'').replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
</script><script>
let rows=[],services=[],editandoId=null;
const fData=document.getElementById('data'),fBusca=document.getElementById('busca'),fStatus=document.getElementById('status');
const lista=document.getElementById('lista'),dlg=document.getElementById('dlg'),err=document.getElementById('erro'),form=document.getElementById('form'),aviso=document.getElementById('aviso'),titulo=document.getElementById('dlg-titulo');

// Mostra a mensagem de sucesso no topo da lista (incluído / alterado / cancelado...).
function avisar(texto){aviso.textContent=texto;aviso.hidden=false;}

async function load(){try{const q=new URLSearchParams();if(fData.value)q.set('data',fData.value);const d=await api('listar_agendamentos.php?'+q);rows=d.agendamentos;draw();}catch(e){lista.innerHTML='<div class="vazio">'+esc(e.message)+'</div>';}}

function draw(){
  const q=fBusca.value.toLowerCase(),st=fStatus.value;
  const arr=rows.filter(a=>(!st||a.status===st)&&(!q||(a.cliente_nome||'').toLowerCase().includes(q)||(a.cliente_telefone||'').includes(q)));
  resumo.textContent=arr.length+' agendamento(s) encontrados';
  lista.innerHTML=arr.length?arr.map(a=>`<div class="item"><div><span class="hora">${a.hora}</span><small>${br(a.data)}</small></div><div><span class="nome">${esc(a.cliente_nome)}</span><span class="tag ${a.status}">${a.status}</span><small>${esc(a.servico)} · ${esc(a.cliente_telefone)} · R$ ${Number(a.preco_aplicado).toFixed(2).replace('.',',')}</small></div><div class="acoes">${['pendente','confirmado'].includes(a.status)?`<button class="btn sec" data-editar="${a.id}">Editar</button>`:''}${a.status==='pendente'?`<button class="btn" data-status="confirmado" data-id="${a.id}">Confirmar</button>`:''}${['pendente','confirmado'].includes(a.status)?`<button class="btn perigo" data-status="cancelado" data-id="${a.id}">Cancelar</button>`:''}</div></div>`).join(''):'<div class="vazio">Nenhum agendamento encontrado.</div>';
}

lista.addEventListener('click',async e=>{
  // EDITAR: abre o pop-up já preenchido.
  const ed=e.target.closest('[data-editar]');
  if(ed){const a=rows.find(r=>Number(r.id)===Number(ed.dataset.editar));if(a)abrirEdicao(a);return;}
  // CONFIRMAR / CANCELAR, com confirmação.
  const b=e.target.closest('[data-status]');if(!b)return;
  if(!confirm('Confirma a alteração?'))return;
  try{const r=await api('atualizar_status.php','POST',{agendamento_id:Number(b.dataset.id),status:b.dataset.status});avisar(r.mensagem);await load();}catch(x){alert(x.message);}
});

// Carrega os serviços e monta o <select> do pop-up.
// Na edição, o serviço atual do agendamento aparece mesmo se já estiver inativo.
async function carregarServicos(servicoAtual=null){
  const d=await api('admin_servicos.php');
  services=d.servicos.filter(s=>Number(s.ativo)===1||s.id===servicoAtual);
  if(!services.length)throw Error('Cadastre/ative um serviço antes.');
  servicos.innerHTML=services.map(s=>`<option value="${s.id}">${esc(s.nome)} — R$ ${Number(s.preco).toFixed(2)}${Number(s.ativo)?'':' (inativo)'}</option>`).join('');
}

async function abrirNovo(){
  try{await carregarServicos();editandoId=null;titulo.textContent='Novo agendamento';form.reset();err.hidden=true;dlg.showModal();}catch(e){alert(e.message);}
}
async function abrirEdicao(a){
  try{
    await carregarServicos(Number(a.servico_id));
    editandoId=Number(a.id);titulo.textContent='Editar agendamento';err.hidden=true;
    form.elements.cliente_nome.value=a.cliente_nome;
    form.elements.cliente_telefone.value=a.cliente_telefone;
    form.elements.servico_id.value=String(a.servico_id);
    form.elements.data.value=a.data;
    form.elements.hora.value=a.hora;
    dlg.showModal();
  }catch(e){alert(e.message);}
}

document.getElementById('novo').onclick=abrirNovo;
document.getElementById('fechar').onclick=()=>dlg.close();

// SALVAR: se estiver editando, chama a API de alterar; senão, a de incluir.
form.addEventListener('submit',async e=>{
  e.preventDefault();err.hidden=true;
  try{
    const d=Object.fromEntries(new FormData(form));d.servico_id=Number(d.servico_id);
    let r;
    if(editandoId!==null){d.agendamento_id=editandoId;r=await api('admin_agendamento_editar.php','POST',d);}
    else{r=await api('admin_agendamento_manual.php','POST',d);}
    const quem=d.cliente_nome,quando=br(d.data)+' às '+d.hora;
    dlg.close();form.reset();
    avisar(r.mensagem+' '+quem+' · '+quando+'.');
    editandoId=null;
    await load();
  }catch(x){err.textContent=x.message;err.hidden=false;}
});

[fData,fBusca,fStatus].forEach(x=>x.addEventListener(x===fData?'change':'input',load));
document.getElementById('limpar').onclick=()=>{fBusca.value='';fData.value='';fStatus.value='';load();};
load();
</script></body></html>