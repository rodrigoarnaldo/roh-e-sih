// ===== Roh & Sih — lógica do painel =====
let REFS = null;          // listas de referência (enums/turmas)
let USUARIO = null;
let paginaContatos = 1;
let filtroContatos = { q: '', tipo_contato: '' };

const $  = (s, ctx = document) => ctx.querySelector(s);
const $$ = (s, ctx = document) => Array.from(ctx.querySelectorAll(s));
const esc = (s) => (s == null ? '' : String(s).replace(/[&<>"]/g, c => ({ '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;' }[c])));

// ---------- Toast ----------
function toast(msg, tipo = 'ok') {
  const t = $('#toast');
  t.textContent = msg;
  t.className = 'toast ' + tipo;
  setTimeout(() => t.classList.add('oculto'), 3200);
}

// ---------- Inicialização ----------
document.addEventListener('DOMContentLoaded', iniciar);

async function iniciar() {
  const st = await API.get('auth.php?acao=status');
  if (st.success && st.data.instalado && st.data.logado) {
    await entrarNoApp();
  } else if (st.success && !st.data.instalado) {
    mostrarAuth('instalar');
  } else {
    mostrarAuth('login');
  }
  ligarEventosGlobais();
}

function mostrarAuth(modo) {
  $('#app').classList.add('oculto');
  $('#tela-auth').classList.remove('oculto');
  $('#form-login').classList.toggle('oculto', modo !== 'login');
  $('#form-instalar').classList.toggle('oculto', modo !== 'instalar');
}

function ligarEventosGlobais() {
  $('#form-login').addEventListener('submit', aoLogar);
  $('#form-instalar').addEventListener('submit', aoInstalar);
  $('#btn-sair').addEventListener('click', aoSair);
  $$('.nav-item').forEach(a => a.addEventListener('click', (e) => {
    e.preventDefault();
    trocarView(a.dataset.view);
  }));
  $$('[data-fechar-modal]').forEach(b => b.addEventListener('click', fecharModal));
  $$('[data-fechar-turma]').forEach(b => b.addEventListener('click', () => $('#modal-turma').classList.add('oculto')));
  $$('[data-fechar-evento]').forEach(b => b.addEventListener('click', () => $('#modal-evento').classList.add('oculto')));
  $$('[data-fechar-insc]').forEach(b => b.addEventListener('click', () => $('#modal-inscricoes').classList.add('oculto')));
  $('#form-contato').addEventListener('submit', aoSalvarContato);
  $('#form-evento').addEventListener('submit', aoSalvarEvento);
}

// ---------- Auth ----------
async function aoLogar(e) {
  e.preventDefault();
  const f = e.target;
  const r = await API.post('auth.php?acao=login', { email: f.email.value, senha: f.senha.value });
  const msg = $('#auth-msg');
  if (r.success) { await entrarNoApp(); }
  else { msg.textContent = r.message; msg.className = 'msg erro'; }
}

async function aoInstalar(e) {
  e.preventDefault();
  const f = e.target;
  const r = await API.post('instalar.php', { nome: f.nome.value, email: f.email.value, senha: f.senha.value });
  const msg = $('#auth-msg');
  if (r.success) { msg.textContent = 'Administrador criado! Faça login.'; msg.className = 'msg ok'; mostrarAuth('login'); }
  else { msg.textContent = (r.errors[0] && r.errors[0].message) || r.message; msg.className = 'msg erro'; }
}

async function aoSair() {
  await API.post('auth.php?acao=logout', {});
  location.reload();
}

async function entrarNoApp() {
  const me = await API.get('auth.php?acao=me');
  USUARIO = me.data && me.data.usuario;
  if (!USUARIO) { return mostrarAuth('login'); }
  const refs = await API.get('referencias.php');
  REFS = refs.data;
  $('#tela-auth').classList.add('oculto');
  $('#app').classList.remove('oculto');
  $('#usuario-nome').textContent = USUARIO.nome;
  trocarView('dashboard');
}

// ---------- Navegação ----------
function trocarView(view) {
  $$('.nav-item').forEach(a => a.classList.toggle('ativo', a.dataset.view === view));
  $$('.view').forEach(v => v.classList.add('oculto'));
  $('#view-' + view).classList.remove('oculto');
  if (view === 'dashboard') renderDashboard();
  if (view === 'contatos')  renderContatos();
  if (view === 'importar')  renderImportar();
  if (view === 'turmas')    renderTurmas();
  if (view === 'presenca')  renderPresenca();
  if (view === 'eventos')   renderEventos();
  if (view === 'followup')  renderFollowup();
}

// ---------- Dashboard ----------
async function renderDashboard() {
  const alvo = $('#view-dashboard');
  alvo.innerHTML = '<div class="view-topo"><h2>Painel</h2></div><p class="vazio">Carregando…</p>';
  const r = await API.get('contatos.php?acao=resumo');
  if (!r.success) { alvo.innerHTML = '<p class="vazio">Erro ao carregar.</p>'; return; }
  const d = r.data;
  alvo.innerHTML = `
    <div class="view-topo"><h2>Painel</h2></div>
    <div class="grid-cards">
      <div class="card-stat"><div class="num">${d.total}</div><div class="rot">Contatos totais</div></div>
      <div class="card-stat"><div class="num">${d.por_tipo.aluno}</div><div class="rot">Alunos</div></div>
      <div class="card-stat"><div class="num">${d.por_tipo.nao_aluno}</div><div class="rot">Não alunos</div></div>
      <div class="card-stat"><div class="num">${d.por_tipo.ex_aluno}</div><div class="rot">Ex-alunos</div></div>
      <div class="card-stat destaque"><div class="num">${d.followup}</div><div class="rot">Follow-up pendente</div></div>
    </div>
    <p class="ajuda">Bem-vindo(a), ${esc(USUARIO.nome)}. Use o menu para gerenciar contatos e acompanhar quem precisa de retorno.</p>`;
}

// ---------- Contatos: lista ----------
async function renderContatos() {
  const alvo = $('#view-contatos');
  alvo.innerHTML = `
    <div class="view-topo">
      <h2>Contatos</h2>
      <button class="btn btn-primario" id="btn-novo-contato">+ Novo contato</button>
    </div>
    <div class="barra-filtros">
      <input type="search" id="filtro-q" placeholder="Buscar nome, WhatsApp, cidade…" value="${esc(filtroContatos.q)}">
      <select id="filtro-tipo">
        <option value="">Todos os tipos</option>
        ${opcoes('tipo_contato', filtroContatos.tipo_contato)}
      </select>
    </div>
    <div class="tabela-wrap" id="tabela-contatos"></div>`;
  $('#btn-novo-contato').addEventListener('click', () => abrirModalContato(null));
  $('#filtro-q').addEventListener('input', debounce(() => { filtroContatos.q = $('#filtro-q').value; paginaContatos = 1; carregarTabelaContatos(); }, 350));
  $('#filtro-tipo').addEventListener('change', () => { filtroContatos.tipo_contato = $('#filtro-tipo').value; paginaContatos = 1; carregarTabelaContatos(); });
  carregarTabelaContatos();
}

async function carregarTabelaContatos() {
  const wrap = $('#tabela-contatos');
  wrap.innerHTML = '<p class="vazio">Carregando…</p>';
  const params = new URLSearchParams({ acao: 'listar', pagina: paginaContatos });
  if (filtroContatos.q) params.set('q', filtroContatos.q);
  if (filtroContatos.tipo_contato) params.set('tipo_contato', filtroContatos.tipo_contato);
  const r = await API.get('contatos.php?' + params.toString());
  if (!r.success) { wrap.innerHTML = '<p class="vazio">Erro ao carregar.</p>'; return; }
  if (!r.data.length) { wrap.innerHTML = '<p class="vazio">Nenhum contato encontrado.</p>'; return; }

  const hoje = new Date().toISOString().slice(0, 10);
  const linhas = r.data.map(c => `
    <tr>
      <td>${esc(c.nome)} ${esc(c.sobrenome || '')}</td>
      <td>${esc(c.whatsapp)}</td>
      <td>${esc([c.cidade, c.uf].filter(Boolean).join(' / '))}</td>
      <td><span class="badge tp-${c.tipo_contato}">${rotulo('tipo_contato', c.tipo_contato)}</span></td>
      <td class="${c.data_proximo_contato && c.data_proximo_contato <= hoje ? 'venc' : ''}">${c.data_proximo_contato || '—'}</td>
      <td class="acoes-linha">
        <button class="btn" data-editar="${c.id}">Editar</button>
        <button class="btn btn-perigo" data-excluir="${c.id}" data-nome="${esc(c.nome)}">Excluir</button>
      </td>
    </tr>`).join('');

  const m = r.meta;
  wrap.innerHTML = `
    <table>
      <thead><tr><th>Nome</th><th>WhatsApp</th><th>Cidade</th><th>Tipo</th><th>Próx. contato</th><th></th></tr></thead>
      <tbody>${linhas}</tbody>
    </table>
    <div class="paginacao">
      <span>${m.total} contato(s) — página ${m.pagina} de ${m.total_paginas || 1}</span>
      <span>
        <button class="btn" ${m.pagina <= 1 ? 'disabled' : ''} data-pag="ant">Anterior</button>
        <button class="btn" ${m.pagina >= m.total_paginas ? 'disabled' : ''} data-pag="prox">Próxima</button>
      </span>
    </div>`;

  $$('[data-editar]', wrap).forEach(b => b.addEventListener('click', () => abrirModalContato(+b.dataset.editar)));
  $$('[data-excluir]', wrap).forEach(b => b.addEventListener('click', () => excluirContato(+b.dataset.excluir, b.dataset.nome)));
  $$('[data-pag]', wrap).forEach(b => b.addEventListener('click', () => {
    paginaContatos += (b.dataset.pag === 'prox' ? 1 : -1);
    carregarTabelaContatos();
  }));
}

async function excluirContato(id, nome) {
  if (!confirm(`Excluir o contato "${nome}"? Esta ação não pode ser desfeita.`)) return;
  const r = await API.post('contatos.php?acao=excluir&id=' + id, {});
  if (r.success) { toast('Contato excluído.'); carregarTabelaContatos(); }
  else { toast(r.message || 'Erro ao excluir.', 'erro'); }
}

// ---------- Contatos: formulário ----------
async function abrirModalContato(id) {
  const form = $('#form-contato');
  $('#form-contato-msg').textContent = '';
  $('#modal-titulo').textContent = id ? 'Editar contato' : 'Novo contato';
  form.dataset.id = id || '';
  form.innerHTML = montarFormContato();
  ligarCamposDependentes();

  if (id) {
    const r = await API.get('contatos.php?acao=obter&id=' + id);
    if (r.success) preencherForm(r.data);
  }
  atualizarCamposPorTipo();
  $('#modal-contato').classList.remove('oculto');
}

function montarFormContato() {
  return `
    <fieldset><legend>Identificação</legend>
      <label class="full">Nome*<input name="nome" required maxlength="80"></label>
      <label>Sobrenome<input name="sobrenome" maxlength="120"></label>
      <label>WhatsApp*<input name="whatsapp" required placeholder="DDD + número"></label>
      <label>CPF<input name="cpf" placeholder="somente números"></label>
      <label>Data de nascimento<input name="data_nascimento" type="date"></label>
      <label>Cidade<input name="cidade"></label>
      <label>UF<select name="uf"><option value="">—</option>${(REFS.uf||[]).map(u=>`<option>${u}</option>`).join('')}</select></label>
    </fieldset>

    <fieldset><legend>Perfil de dança</legend>
      <label>Par<select name="par_situacao"><option value="">—</option>${opcoes('par_situacao')}</select></label>
      <label>Papel<select name="papel"><option value="">—</option>${opcoes('papel')}</select></label>
      <label class="full">Origem do contato<input name="origem" placeholder="indicação, Instagram, evento…"></label>
      <div class="full"><small>Estilos de interesse</small>
        <div class="checks">${(REFS.estilos||[]).map(o=>`<label><input type="checkbox" name="estilos" value="${o.valor}">${esc(o.rotulo)}</label>`).join('')}</div>
      </div>
      <div class="full"><small>Disponibilidade</small>
        <div class="checks">${(REFS.dias||[]).map(o=>`<label><input type="checkbox" name="disponibilidade" value="${o.valor}">${esc(o.rotulo)}</label>`).join('')}</div>
      </div>
    </fieldset>

    <fieldset><legend>Classificação</legend>
      <label>Tipo de contato*<select name="tipo_contato" required>${opcoes('tipo_contato')}</select></label>
      <label data-cond="aluno">Plano<select name="plano"><option value="">—</option>${opcoes('plano')}</select></label>
      <label data-cond="aluno">Status do aluno<select name="status_aluno"><option value="">—</option>${opcoes('status_aluno')}</select></label>
      <label data-cond="aluno">Data de matrícula<input name="data_matricula" type="date"></label>
      <label data-cond="nao_aluno">Status (não aluno)<select name="status_nao_aluno"><option value="">—</option>${opcoes('status_nao_aluno')}</select></label>
      <label data-cond="ex_aluno">Status (ex-aluno)<select name="status_ex_aluno"><option value="">—</option>${opcoes('status_ex_aluno')}</select></label>
      <label data-cond="nao_contatar">Motivo (não contatar)<select name="status_nao_contatar"><option value="">—</option>${opcoes('status_nao_contatar')}</select></label>
    </fieldset>

    <fieldset><legend>Relacionamento / Follow-up</legend>
      <label>Primeiro contato<input name="data_primeiro_contato" type="date"></label>
      <label>Último contato<input name="data_ultimo_contato" type="date"></label>
      <label>Próximo contato<input name="data_proximo_contato" type="date"></label>
      <label>Data de pausa<input name="data_pausa" type="date"></label>
      <label class="full">Observações<textarea name="observacoes" rows="2"></textarea></label>
    </fieldset>`;
}

function ligarCamposDependentes() {
  $('#form-contato [name=tipo_contato]').addEventListener('change', atualizarCamposPorTipo);
}

function atualizarCamposPorTipo() {
  const tipo = $('#form-contato [name=tipo_contato]').value;
  $$('#form-contato [data-cond]').forEach(el => {
    el.style.display = el.dataset.cond === tipo ? '' : 'none';
  });
}

function preencherForm(d) {
  const form = $('#form-contato');
  form.querySelectorAll('input,select,textarea').forEach(el => {
    if (el.type === 'checkbox') return;
    if (d[el.name] != null) el.value = d[el.name];
  });
  (d.estilos || []).forEach(v => { const c = form.querySelector(`[name=estilos][value="${v}"]`); if (c) c.checked = true; });
  (d.disponibilidade || []).forEach(v => { const c = form.querySelector(`[name=disponibilidade][value="${v}"]`); if (c) c.checked = true; });
}

async function aoSalvarContato(e) {
  e.preventDefault();
  const form = e.target;
  const id = form.dataset.id;
  const dados = {};
  form.querySelectorAll('input,select,textarea').forEach(el => {
    if (el.type === 'checkbox') return;
    dados[el.name] = el.value;
  });
  dados.estilos = $$('[name=estilos]:checked', form).map(c => c.value);
  dados.disponibilidade = $$('[name=disponibilidade]:checked', form).map(c => c.value);

  const url = id ? ('contatos.php?acao=atualizar&id=' + id) : 'contatos.php?acao=criar';
  const r = await API.post(url, dados);
  const msg = $('#form-contato-msg');
  if (r.success) {
    fecharModal(); toast(r.message);
    carregarTabelaContatos();
  } else {
    msg.className = 'msg erro';
    msg.textContent = (r.errors[0] && r.errors[0].message) || r.message;
  }
}

function fecharModal() { $('#modal-contato').classList.add('oculto'); }

// ---------- Follow-up ----------
async function renderFollowup() {
  const alvo = $('#view-followup');
  alvo.innerHTML = '<div class="view-topo"><h2>Follow-up</h2></div><p class="vazio">Carregando…</p>';
  const [rc, re] = await Promise.all([
    API.get('contatos.php?acao=followup'),
    API.get('evento_inscricoes.php?acao=followup'),
  ]);

  // Seção 1: contatos com próximo contato vencido/hoje
  let secContatos;
  if (rc.success && rc.data.length) {
    secContatos = `
      <h3 style="margin:6px 0 10px">Contatos para retornar (${rc.data.length})</h3>
      <div class="tabela-wrap"><table>
        <thead><tr><th>Nome</th><th>WhatsApp</th><th>Tipo</th><th>Previsto</th><th></th></tr></thead>
        <tbody>${rc.data.map(c => `
          <tr>
            <td>${esc(c.nome)} ${esc(c.sobrenome || '')}</td>
            <td>${esc(c.whatsapp)}</td>
            <td><span class="badge tp-${c.tipo_contato}">${rotulo('tipo_contato', c.tipo_contato)}</span></td>
            <td class="venc">${c.data_proximo_contato}</td>
            <td><button class="btn" data-editar="${c.id}">Abrir</button></td>
          </tr>`).join('')}</tbody></table></div>`;
  } else {
    secContatos = '<h3 style="margin:6px 0 10px">Contatos para retornar</h3><p class="vazio">Nenhum contato pendente. 🎉</p>';
  }

  // Seção 2: negociações de eventos (negociando/reservado)
  const hoje = new Date().toISOString().slice(0, 10);
  let secEventos;
  if (re.success && re.data.length) {
    secEventos = `
      <h3 style="margin:22px 0 10px">Negociações de eventos (${re.data.length})</h3>
      <div class="tabela-wrap"><table>
        <thead><tr><th>Contato</th><th>Evento</th><th>Situação</th><th>Valor</th><th>Follow-up</th><th></th></tr></thead>
        <tbody>${re.data.map(i => `
          <tr>
            <td>${esc(i.nome)} ${esc(i.sobrenome || '')}<br><small style="color:var(--cinza)">${esc(i.whatsapp)}</small></td>
            <td>${esc(i.evento_nome)}<br><small style="color:var(--cinza)">${rotulo('evento_tipo', i.evento_tipo)}</small></td>
            <td><span class="badge tp-${i.status === 'reservado' ? 'aluno' : 'nao_aluno'}">${rotulo('inscricao_status', i.status)}</span></td>
            <td>${fmtMoeda(i.valor)}</td>
            <td class="${i.data_followup && i.data_followup <= hoje ? 'venc' : ''}">${i.data_followup || '—'}</td>
            <td><button class="btn" data-abrir-ev="${i.evento_id}" data-nome="${esc(i.evento_nome)}">Abrir</button></td>
          </tr>`).join('')}</tbody></table></div>`;
  } else {
    secEventos = '<h3 style="margin:22px 0 10px">Negociações de eventos</h3><p class="vazio">Nenhuma negociação aberta.</p>';
  }

  alvo.innerHTML = `<div class="view-topo"><h2>Follow-up</h2></div>${secContatos}${secEventos}`;
  $$('[data-editar]', alvo).forEach(b => b.addEventListener('click', () => abrirModalContato(+b.dataset.editar)));
  $$('[data-abrir-ev]', alvo).forEach(b => b.addEventListener('click', () => abrirInscricoes(+b.dataset.abrirEv, b.dataset.nome)));
}

// ---------- Helpers de referência ----------
function opcoes(chave, selecionado = '') {
  return (REFS[chave] || []).map(o =>
    `<option value="${o.valor}" ${o.valor === selecionado ? 'selected' : ''}>${esc(o.rotulo)}</option>`
  ).join('');
}
function rotulo(chave, valor) {
  const o = (REFS[chave] || []).find(x => x.valor === valor);
  return o ? o.rotulo : valor;
}
function debounce(fn, ms) {
  let t; return (...a) => { clearTimeout(t); t = setTimeout(() => fn(...a), ms); };
}

// ================= EVENTOS + INSCRIÇÕES =================
let inscEventoAtual = null;

function fmtMoeda(v) {
  if (v == null || v === '') return '—';
  return 'R$ ' + Number(v).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
}
function fmtDataHora(s) {
  if (!s) return '—';
  return String(s).slice(0, 16).replace('T', ' ').replace(/-/g, '/').replace(/(\d{4})\/(\d{2})\/(\d{2})/, '$3/$2/$1');
}

async function renderEventos() {
  const alvo = $('#view-eventos');
  alvo.innerHTML = `
    <div class="view-topo">
      <h2>Eventos</h2>
      <button class="btn btn-primario" id="btn-novo-evento">+ Novo evento</button>
    </div>
    <div id="lista-eventos"><p class="vazio">Carregando…</p></div>`;
  $('#btn-novo-evento').addEventListener('click', () => abrirModalEvento(null));
  carregarEventos();
}

async function carregarEventos() {
  const alvo = $('#lista-eventos');
  const r = await API.get('eventos.php?acao=listar');
  if (!r.success) { alvo.innerHTML = '<p class="vazio">Erro ao carregar.</p>'; return; }
  if (!r.data.length) { alvo.innerHTML = '<p class="vazio">Nenhum evento ainda. Crie o primeiro.</p>'; return; }
  alvo.innerHTML = `<div class="tabela-wrap"><table>
    <thead><tr><th>Evento</th><th>Tipo</th><th>Quando</th><th>Valor</th><th>Interessados</th><th></th></tr></thead>
    <tbody>${r.data.map(e => `
      <tr>
        <td>${esc(e.nome)}${e.ativo == 0 ? ' <span class="badge tp-nao_contatar">inativo</span>' : ''}</td>
        <td>${rotulo('evento_tipo', e.tipo)}</td>
        <td>${fmtDataHora(e.data_evento)}</td>
        <td>${fmtMoeda(e.valor)}</td>
        <td>${e.pagos} pago(s) · ${e.em_negociacao} negoc.</td>
        <td class="acoes-linha">
          <button class="btn btn-primario" data-insc="${e.id}" data-nome="${esc(e.nome)}">Interessados</button>
          <button class="btn" data-edit-ev="${e.id}">Editar</button>
          <button class="btn btn-perigo" data-del-ev="${e.id}" data-nome="${esc(e.nome)}">Excluir</button>
        </td>
      </tr>`).join('')}</tbody></table></div>`;
  $$('[data-insc]', alvo).forEach(b => b.addEventListener('click', () => abrirInscricoes(+b.dataset.insc, b.dataset.nome)));
  $$('[data-edit-ev]', alvo).forEach(b => b.addEventListener('click', () => abrirModalEvento(+b.dataset.editEv)));
  $$('[data-del-ev]', alvo).forEach(b => b.addEventListener('click', () => excluirEvento(+b.dataset.delEv, b.dataset.nome)));
}

async function abrirModalEvento(id) {
  const form = $('#form-evento');
  $('#form-evento-msg').textContent = '';
  $('#modal-evento-titulo').textContent = id ? 'Editar evento' : 'Novo evento';
  form.dataset.id = id || '';
  form.innerHTML = `
    <label class="full">Nome*<input name="nome" required maxlength="160"></label>
    <label>Tipo*<select name="tipo" required>${opcoes('evento_tipo')}</select></label>
    <label>Data e hora<input name="data_evento" type="datetime-local"></label>
    <label>Valor (R$)<input name="valor" type="number" step="0.01" min="0"></label>
    <label>Vagas<input name="vagas" type="number" min="0"></label>
    <label class="full">Local<input name="local" maxlength="200"></label>
    <label class="full">Descrição<textarea name="descricao" rows="2"></textarea></label>
    <label class="checks" style="align-items:center"><input type="checkbox" name="ativo" checked> Evento ativo</label>`;
  if (id) {
    const r = await API.get('eventos.php?acao=obter&id=' + id);
    if (r.success) {
      const d = r.data;
      form.querySelectorAll('input,select,textarea').forEach(el => {
        if (el.type === 'checkbox') { el.checked = d.ativo == 1; return; }
        if (el.name === 'data_evento' && d.data_evento) { el.value = String(d.data_evento).slice(0, 16).replace(' ', 'T'); return; }
        if (d[el.name] != null) el.value = d[el.name];
      });
    }
  }
  $('#modal-evento').classList.remove('oculto');
}

async function aoSalvarEvento(e) {
  e.preventDefault();
  const form = e.target;
  const dados = {};
  form.querySelectorAll('input,select,textarea').forEach(el => {
    dados[el.name] = el.type === 'checkbox' ? (el.checked ? 1 : 0) : el.value;
  });
  const id = form.dataset.id;
  const url = id ? ('eventos.php?acao=atualizar&id=' + id) : 'eventos.php?acao=criar';
  const r = await API.post(url, dados);
  if (r.success) { $('#modal-evento').classList.add('oculto'); toast(r.message); carregarEventos(); }
  else {
    $('#form-evento-msg').className = 'msg erro';
    $('#form-evento-msg').textContent = (r.errors[0] && r.errors[0].message) || r.message;
  }
}

async function excluirEvento(id, nome) {
  if (!confirm(`Excluir o evento "${nome}" e todas as inscrições dele?`)) return;
  const r = await API.post('eventos.php?acao=excluir&id=' + id, {});
  if (r.success) { toast('Evento excluído.'); carregarEventos(); } else { toast(r.message, 'erro'); }
}

// ----- Inscrições / interessados -----
function abrirInscricoes(eventoId, nome) {
  inscEventoAtual = eventoId;
  $('#modal-insc-titulo').textContent = 'Interessados — ' + nome;
  $('#busca-insc').value = '';
  $('#busca-insc-res').innerHTML = '';
  $('#modal-inscricoes').classList.remove('oculto');
  const busca = $('#busca-insc');
  busca.oninput = debounce(async () => {
    const q = busca.value.trim();
    if (q.length < 2) { $('#busca-insc-res').innerHTML = ''; return; }
    const r = await API.get('evento_inscricoes.php?acao=buscar_contatos&q=' + encodeURIComponent(q));
    if (!r.success) return;
    $('#busca-insc-res').innerHTML = r.data.length
      ? r.data.map(c => `
          <div class="linha-busca">
            <span>${esc(c.nome)} ${esc(c.sobrenome || '')} · ${esc(c.whatsapp)}</span>
            <button class="btn btn-primario" data-add-insc="${c.id}">+ Adicionar</button>
          </div>`).join('')
      : '<p class="ajuda">Nenhum contato encontrado.</p>';
    $$('#busca-insc-res [data-add-insc]').forEach(b => b.addEventListener('click', () => adicionarInscrito(+b.dataset.addInsc)));
  }, 350);
  carregarInscricoes();
}

async function adicionarInscrito(contatoId) {
  const r = await API.post('evento_inscricoes.php?acao=criar', { evento_id: inscEventoAtual, contato_id: contatoId });
  if (r.success) { toast('Interessado adicionado.'); $('#busca-insc').value = ''; $('#busca-insc-res').innerHTML = ''; carregarInscricoes(); }
  else { toast(r.message || 'Erro.', 'erro'); }
}

async function carregarInscricoes() {
  const alvo = $('#lista-inscricoes');
  alvo.innerHTML = '<p class="ajuda">Carregando…</p>';
  const r = await API.get('evento_inscricoes.php?acao=por_evento&evento_id=' + inscEventoAtual);
  if (!r.success) { alvo.innerHTML = '<p class="ajuda">Erro ao carregar.</p>'; return; }
  if (!r.data.length) { alvo.innerHTML = '<p class="vazio">Nenhum interessado ainda.</p>'; return; }
  alvo.innerHTML = `<div class="tabela-wrap"><table>
    <thead><tr><th>Contato</th><th>Situação</th><th>Valor</th><th>Follow-up</th><th></th></tr></thead>
    <tbody>${r.data.map(i => `
      <tr>
        <td>${esc(i.nome)} ${esc(i.sobrenome || '')}<br><small style="color:var(--cinza)">${esc(i.whatsapp)}</small></td>
        <td><select data-insc-status="${i.id}">${opcoes('inscricao_status', i.status)}</select></td>
        <td><input data-insc-valor="${i.id}" type="number" step="0.01" min="0" value="${i.valor ?? ''}" style="width:90px"></td>
        <td><input data-insc-fup="${i.id}" type="date" value="${i.data_followup ?? ''}"></td>
        <td><button class="btn btn-perigo" data-insc-del="${i.id}">Remover</button></td>
      </tr>`).join('')}</tbody></table></div>
    <p class="ajuda">Alterou situação, valor ou data? Clique fora do campo para salvar.</p>`;

  const salvar = (id, campo, valor) =>
    API.post('evento_inscricoes.php?acao=atualizar&id=' + id, { [campo]: valor })
      .then(r2 => toast(r2.success ? 'Salvo.' : (r2.message || 'Erro.'), r2.success ? 'ok' : 'erro'));

  $$('[data-insc-status]', alvo).forEach(s => s.addEventListener('change', () => salvar(s.dataset.inscStatus, 'status', s.value)));
  $$('[data-insc-valor]', alvo).forEach(inp => inp.addEventListener('change', () => salvar(inp.dataset.inscValor, 'valor', inp.value)));
  $$('[data-insc-fup]', alvo).forEach(inp => inp.addEventListener('change', () => salvar(inp.dataset.inscFup, 'data_followup', inp.value)));
  $$('[data-insc-del]', alvo).forEach(b => b.addEventListener('click', async () => {
    if (!confirm('Remover este interessado?')) return;
    const r2 = await API.post('evento_inscricoes.php?acao=excluir&id=' + b.dataset.inscDel, {});
    if (r2.success) { toast('Removido.'); carregarInscricoes(); } else { toast(r2.message, 'erro'); }
  }));
}

// ================= TURMAS + MATRÍCULAS =================
let turmaAtual = null;

async function renderTurmas() {
  const alvo = $('#view-turmas');
  alvo.innerHTML = '<div class="view-topo"><h2>Turmas</h2></div><p class="vazio">Carregando…</p>';
  const r = await API.get('matriculas.php?acao=turmas_resumo');
  if (!r.success) { alvo.innerHTML = '<p class="vazio">Erro ao carregar.</p>'; return; }
  const cards = r.data.map(t => `
    <div class="card-stat card-turma" data-turma="${t.id}" data-nome="${esc(t.nome)}" style="cursor:pointer">
      <div class="num">${t.ativos}</div>
      <div class="rot">${esc(t.nome)} — ${t.ativos} ativo(s)</div>
    </div>`).join('');
  alvo.innerHTML = `
    <div class="view-topo"><h2>Turmas</h2></div>
    <p class="ajuda">Clique numa turma para ver e gerenciar os alunos matriculados.</p>
    <div class="grid-cards">${cards}</div>`;
  $$('.card-turma', alvo).forEach(c => c.addEventListener('click', () => abrirTurma(+c.dataset.turma, c.dataset.nome)));
}

function abrirTurma(turmaId, nome) {
  turmaAtual = turmaId;
  $('#modal-turma-titulo').textContent = 'Turma ' + nome;
  $('#busca-aluno').value = '';
  $('#busca-aluno-res').innerHTML = '';
  $('#modal-turma').classList.remove('oculto');
  const busca = $('#busca-aluno');
  busca.oninput = debounce(async () => {
    const q = busca.value.trim();
    if (q.length < 2) { $('#busca-aluno-res').innerHTML = ''; return; }
    const r = await API.get('matriculas.php?acao=buscar_contatos&q=' + encodeURIComponent(q));
    if (!r.success) return;
    $('#busca-aluno-res').innerHTML = r.data.length
      ? r.data.map(c => `
          <div class="linha-busca">
            <span>${esc(c.nome)} ${esc(c.sobrenome || '')} · ${esc(c.whatsapp)} <span class="badge tp-${c.tipo_contato}">${rotulo('tipo_contato', c.tipo_contato)}</span></span>
            <button class="btn btn-primario" data-add="${c.id}">+ Matricular</button>
          </div>`).join('')
      : '<p class="ajuda">Nenhum contato encontrado.</p>';
    $$('#busca-aluno-res [data-add]').forEach(b => b.addEventListener('click', () => matricular(+b.dataset.add)));
  }, 350);
  carregarMatriculados();
}

async function matricular(contatoId) {
  const r = await API.post('matriculas.php?acao=criar', {
    contato_id: contatoId, turma_id: turmaAtual, data_matricula: new Date().toISOString().slice(0, 10),
  });
  if (r.success) { toast('Aluno matriculado.'); $('#busca-aluno').value = ''; $('#busca-aluno-res').innerHTML = ''; carregarMatriculados(); }
  else { toast(r.message || 'Erro ao matricular.', 'erro'); }
}

async function carregarMatriculados() {
  const alvo = $('#lista-matriculados');
  alvo.innerHTML = '<p class="ajuda">Carregando…</p>';
  const r = await API.get('matriculas.php?acao=por_turma&turma_id=' + turmaAtual);
  if (!r.success) { alvo.innerHTML = '<p class="ajuda">Erro ao carregar.</p>'; return; }
  if (!r.data.length) { alvo.innerHTML = '<p class="vazio">Nenhum aluno matriculado ainda.</p>'; return; }
  const opStatus = (sel) => ['ativa', 'pausada', 'cancelada']
    .map(s => `<option value="${s}" ${s === sel ? 'selected' : ''}>${s}</option>`).join('');
  alvo.innerHTML = `<div class="tabela-wrap"><table>
    <thead><tr><th>Aluno</th><th>WhatsApp</th><th>Status</th><th></th></tr></thead>
    <tbody>${r.data.map(m => `
      <tr>
        <td>${esc(m.nome)} ${esc(m.sobrenome || '')}</td>
        <td>${esc(m.whatsapp)}</td>
        <td><select data-status="${m.matricula_id}">${opStatus(m.status)}</select></td>
        <td><button class="btn btn-perigo" data-rem="${m.matricula_id}">Remover</button></td>
      </tr>`).join('')}</tbody></table></div>`;
  $$('[data-status]', alvo).forEach(s => s.addEventListener('change', async () => {
    const r2 = await API.post('matriculas.php?acao=atualizar_status&id=' + s.dataset.status, { status: s.value });
    toast(r2.success ? 'Status atualizado.' : (r2.message || 'Erro.'), r2.success ? 'ok' : 'erro');
  }));
  $$('[data-rem]', alvo).forEach(b => b.addEventListener('click', async () => {
    if (!confirm('Remover esta matrícula?')) return;
    const r2 = await API.post('matriculas.php?acao=excluir&id=' + b.dataset.rem, {});
    if (r2.success) { toast('Matrícula removida.'); carregarMatriculados(); } else { toast(r2.message, 'erro'); }
  }));
}

// ================= PRESENÇA / CHAMADA =================
async function renderPresenca() {
  const alvo = $('#view-presenca');
  alvo.innerHTML = `
    <div class="view-topo"><h2>Presença (chamada)</h2></div>
    <div class="barra-filtros">
      <select id="pres-turma"><option value="">Selecione a turma…</option>${
        (REFS.turmas || []).map(t => `<option value="${t.id}">${esc(t.nome)}</option>`).join('')}</select>
      <input type="date" id="pres-data" value="${new Date().toISOString().slice(0, 10)}">
      <button class="btn btn-primario" id="btn-chamada">Carregar chamada</button>
    </div>
    <div id="pres-lista"></div>`;
  $('#btn-chamada').addEventListener('click', carregarChamada);
}

async function carregarChamada() {
  const turmaId = $('#pres-turma').value;
  const data = $('#pres-data').value;
  const alvo = $('#pres-lista');
  if (!turmaId) { toast('Selecione a turma.', 'erro'); return; }
  alvo.innerHTML = '<p class="vazio">Carregando…</p>';
  const r = await API.get(`presencas.php?acao=chamada&turma_id=${turmaId}&data=${data}`);
  if (!r.success) { alvo.innerHTML = '<p class="vazio">Erro ao carregar.</p>'; return; }
  if (!r.data.alunos.length) { alvo.innerHTML = '<p class="vazio">Nenhum aluno ativo nesta turma. Matricule alunos em “Turmas”.</p>'; return; }

  const botoes = (contatoId, atual) => ['presente', 'falta', 'justificado'].map(s => `
    <label class="btn-pres pres-${s} ${atual === s ? 'sel' : ''}">
      <input type="radio" name="p${contatoId}" value="${s}" ${atual === s ? 'checked' : ''} hidden>
      ${s === 'presente' ? 'Presente' : s === 'falta' ? 'Falta' : 'Justif.'}
    </label>`).join('');

  alvo.innerHTML = `
    <div class="tabela-wrap"><table>
      <thead><tr><th>Aluno</th><th>Presença</th></tr></thead>
      <tbody>${r.data.alunos.map(a => `
        <tr data-contato="${a.contato_id}">
          <td>${esc(a.nome)} ${esc(a.sobrenome || '')}</td>
          <td class="cel-pres">${botoes(a.contato_id, a.status)}</td>
        </tr>`).join('')}</tbody>
    </table></div>
    <div style="margin-top:14px;display:flex;gap:10px;align-items:center">
      <button class="btn" id="btn-todos-presentes">Marcar todos presentes</button>
      <button class="btn btn-primario" id="btn-salvar-chamada">Salvar chamada</button>
      <span id="pres-msg" class="msg"></span>
    </div>`;

  $$('.btn-pres', alvo).forEach(l => l.addEventListener('click', () => {
    setTimeout(() => {
      l.parentElement.querySelectorAll('.btn-pres').forEach(x => x.classList.remove('sel'));
      l.classList.add('sel');
    }, 0);
  }));
  $('#btn-todos-presentes').addEventListener('click', () => {
    $$('#pres-lista tbody tr').forEach(tr => {
      const rb = tr.querySelector('input[value=presente]');
      rb.checked = true;
      tr.querySelectorAll('.btn-pres').forEach(x => x.classList.remove('sel'));
      tr.querySelector('.pres-presente').classList.add('sel');
    });
  });
  $('#btn-salvar-chamada').addEventListener('click', () => salvarChamada(turmaId, data));
}

async function salvarChamada(turmaId, data) {
  const presencas = [];
  $$('#pres-lista tbody tr').forEach(tr => {
    const sel = tr.querySelector('input[type=radio]:checked');
    if (sel) presencas.push({ contato_id: +tr.dataset.contato, status: sel.value });
  });
  if (!presencas.length) { toast('Marque ao menos um aluno.', 'erro'); return; }
  const r = await API.post('presencas.php?acao=salvar', { turma_id: +turmaId, data, presencas });
  const msg = $('#pres-msg');
  if (r.success) { msg.className = 'msg ok'; msg.textContent = r.message; toast(r.message); }
  else { msg.className = 'msg erro'; msg.textContent = r.message; }
}

// ================= IMPORTAÇÃO DE CSV (não alunos) =================
const CAMPOS_IMPORT = [
  { chave: 'nome',      rotulo: 'Nome',      obrig: true,  dicas: ['nome', 'name', 'primeiro'] },
  { chave: 'sobrenome', rotulo: 'Sobrenome', obrig: false, dicas: ['sobrenome', 'last', 'apelido'] },
  { chave: 'whatsapp',  rotulo: 'WhatsApp',  obrig: true,  dicas: ['whats', 'telefone', 'celular', 'fone', 'phone', 'tel', 'contato'] },
  { chave: 'tipo',      rotulo: 'Tipo',      obrig: false, dicas: ['tipo', 'situacao', 'categoria', 'classific', 'perfil'] },
  { chave: 'cidade',    rotulo: 'Cidade',    obrig: false, dicas: ['cidade', 'city', 'municip'] },
  { chave: 'uf',        rotulo: 'UF',        obrig: false, dicas: ['uf', 'estado', 'state'] },
  { chave: 'cpf',       rotulo: 'CPF',       obrig: false, dicas: ['cpf', 'documento'] },
  { chave: 'origem',    rotulo: 'Origem',    obrig: false, dicas: ['origem', 'fonte', 'source'] },
];
let CSV = { cabecalho: [], linhas: [] };

// Normalizador do tipo (espelha o backend) — usado só na prévia.
function normalizarTipoJS(v) {
  if (!v) return null;
  let s = String(v).toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '').replace(/[^a-z]+/g, ' ').trim();
  if (!s) return null;
  const direto = s.replace(/ /g, '_');
  if (['nao_aluno', 'aluno', 'ex_aluno', 'nao_contatar'].includes(direto)) return direto;
  if (s.startsWith('ex') || s.includes('ex aluno')) return 'ex_aluno';
  if (s.includes('nao contatar') || s.includes('bloq') || s.includes('nao contat')) return 'nao_contatar';
  if (s.includes('nao aluno') || s.includes('lead') || s.includes('prospect')) return 'nao_aluno';
  if (s.includes('aluno') || s.includes('ativo') || s.includes('matricul')) return 'aluno';
  return null;
}

function renderImportar() {
  CSV = { cabecalho: [], linhas: [] };
  $('#view-importar').innerHTML = `
    <div class="view-topo"><h2>Importar contatos (CSV)</h2></div>
    <div class="tabela-wrap" style="padding:20px">
      <p class="ajuda">Selecione um arquivo <strong>.csv</strong> com uma linha de cabeçalho. O <strong>tipo</strong> de cada contato pode vir de uma coluna do arquivo; quando faltar, usa o tipo padrão que você escolher. Duplicados (mesmo WhatsApp) são ignorados.</p>
      <p style="margin:0 0 12px"><a href="modelo-importacao-contatos.csv" download>⬇️ Baixar modelo CSV</a> — preencha com seus dados e envie aqui.</p>
      <input type="file" id="arquivo-csv" accept=".csv,text/csv">
      <div id="importar-passo2"></div>
    </div>`;
  $('#arquivo-csv').addEventListener('change', aoEscolherCsv);
}

function aoEscolherCsv(e) {
  const arq = e.target.files[0];
  if (!arq) return;
  const leitor = new FileReader();
  leitor.onload = () => {
    const linhas = parseCSV(leitor.result);
    if (linhas.length < 2) { toast('Arquivo vazio ou sem dados.', 'erro'); return; }
    CSV.cabecalho = linhas[0].map(h => (h || '').trim());
    CSV.linhas = linhas.slice(1).filter(l => l.some(c => (c || '').trim() !== ''));
    montarMapeamento();
  };
  leitor.readAsText(arq, 'UTF-8');
}

// Parser CSV com suporte a aspas e auto-detecção de delimitador (, ou ;)
function parseCSV(texto) {
  texto = texto.replace(/^﻿/, ''); // remove BOM
  const fim = texto.indexOf('\n');
  const primeira = fim === -1 ? texto : texto.slice(0, fim);
  const delim = (primeira.split(';').length > primeira.split(',').length) ? ';' : ',';
  const linhas = [];
  let campo = '', linha = [], aspas = false;
  for (let i = 0; i < texto.length; i++) {
    const c = texto[i];
    if (aspas) {
      if (c === '"') { if (texto[i + 1] === '"') { campo += '"'; i++; } else aspas = false; }
      else campo += c;
    } else if (c === '"') { aspas = true; }
    else if (c === delim) { linha.push(campo); campo = ''; }
    else if (c === '\n') { linha.push(campo); linhas.push(linha); linha = []; campo = ''; }
    else if (c !== '\r') { campo += c; }
  }
  if (campo !== '' || linha.length) { linha.push(campo); linhas.push(linha); }
  return linhas;
}

function montarMapeamento() {
  const norm = s => (s || '').toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '');
  const adivinhar = (campo) => {
    for (let idx = 0; idx < CSV.cabecalho.length; idx++) {
      const h = norm(CSV.cabecalho[idx]);
      if (campo.dicas.some(d => h.includes(d))) return idx;
    }
    return -1;
  };
  const opcoesColunas = (sel) =>
    `<option value="-1">— ignorar —</option>` +
    CSV.cabecalho.map((h, i) => `<option value="${i}" ${i === sel ? 'selected' : ''}>${esc(h || ('coluna ' + (i + 1)))}</option>`).join('');

  const mapLinhas = CAMPOS_IMPORT.map(c => `
    <label>${c.rotulo}${c.obrig ? '*' : ''}
      <select data-campo="${c.chave}">${opcoesColunas(adivinhar(c))}</select>
    </label>`).join('');

  $('#importar-passo2').innerHTML = `
    <hr style="margin:18px 0;border:none;border-top:1px solid var(--linha)">
    <h3 style="margin:0 0 4px">Mapeie as colunas (${CSV.linhas.length} linhas)</h3>
    <div class="form-contato" style="padding:0;grid-template-columns:1fr 1fr 1fr">${mapLinhas}</div>
    <div class="barra-filtros" style="margin-top:12px">
      <label style="font-size:13px;color:var(--cinza)">Tipo padrão (quando a coluna não indicar)
        <select id="imp-tipo">${opcoes('tipo_contato', 'nao_aluno')}</select>
      </label>
      <label style="font-size:13px;color:var(--cinza)">Origem padrão (opcional)
        <input id="imp-origem" placeholder="ex.: planilha antiga, lista evento">
      </label>
      <label style="font-size:13px;color:var(--cinza)">Status — só p/ não aluno
        <select id="imp-status"><option value="">— nenhum —</option>${opcoes('status_nao_aluno')}</select>
      </label>
    </div>
    <div id="imp-previa"></div>
    <div style="margin-top:14px;display:flex;gap:10px;align-items:center">
      <button class="btn btn-primario" id="btn-importar">Importar ${CSV.linhas.length} contato(s)</button>
      <span id="imp-msg" class="msg"></span>
    </div>
    <div id="imp-resultado"></div>`;

  $$('#importar-passo2 [data-campo]').forEach(s => s.addEventListener('change', renderPrevia));
  $('#imp-tipo').addEventListener('change', renderPrevia);
  $('#btn-importar').addEventListener('click', executarImportacao);
  renderPrevia();
}

function lerMapeamento() {
  const map = {};
  $$('#importar-passo2 [data-campo]').forEach(s => { map[s.dataset.campo] = parseInt(s.value, 10); });
  return map;
}

function linhaParaObjeto(linha, map) {
  const o = {};
  CAMPOS_IMPORT.forEach(c => {
    const idx = map[c.chave];
    o[c.chave] = (idx >= 0 && linha[idx] != null) ? String(linha[idx]).trim() : '';
  });
  return o;
}

function renderPrevia() {
  const map = lerMapeamento();
  const tipoPadrao = ($('#imp-tipo') && $('#imp-tipo').value) || 'nao_aluno';
  const amostra = CSV.linhas.slice(0, 5).map(l => linhaParaObjeto(l, map));
  const linhas = amostra.map(o => {
    const t = normalizarTipoJS(o.tipo) || tipoPadrao;
    return `<tr><td>${esc(o.nome)}</td><td>${esc(o.sobrenome)}</td><td>${esc(o.whatsapp)}</td>
      <td><span class="badge tp-${t}">${rotulo('tipo_contato', t)}</span></td>
      <td>${esc(o.cidade)}</td><td>${esc(o.uf)}</td><td>${esc(o.origem)}</td></tr>`;
  }).join('');
  $('#imp-previa').innerHTML = `
    <p class="ajuda" style="margin-top:14px">Prévia das 5 primeiras linhas (confira o Tipo interpretado):</p>
    <div class="tabela-wrap"><table>
      <thead><tr><th>Nome</th><th>Sobrenome</th><th>WhatsApp</th><th>Tipo</th><th>Cidade</th><th>UF</th><th>Origem</th></tr></thead>
      <tbody>${linhas}</tbody></table></div>`;
}

async function executarImportacao() {
  const map = lerMapeamento();
  if (map.nome < 0 || map.whatsapp < 0) {
    $('#imp-msg').className = 'msg erro';
    $('#imp-msg').textContent = 'Mapeie ao menos Nome e WhatsApp.';
    return;
  }
  const registros = CSV.linhas.map(l => linhaParaObjeto(l, map))
    .filter(o => o.nome !== '' || o.whatsapp !== '');

  const btn = $('#btn-importar');
  btn.disabled = true; btn.textContent = 'Importando…';
  $('#imp-msg').className = 'msg'; $('#imp-msg').textContent = '';

  const r = await API.post('contatos_importar.php', {
    registros,
    tipo_padrao: $('#imp-tipo').value || 'nao_aluno',
    origem_padrao: $('#imp-origem').value.trim() || null,
    status_nao_aluno_padrao: $('#imp-status').value || null,
  });

  btn.disabled = false; btn.textContent = 'Importar novamente';
  if (!r.success) {
    $('#imp-msg').className = 'msg erro';
    $('#imp-msg').textContent = (r.errors[0] && r.errors[0].message) || r.message;
    return;
  }
  const d = r.data;
  const errosHtml = d.erros.length
    ? `<details style="margin-top:8px"><summary>${d.erros.length} linha(s) com erro</summary>
       <ul>${d.erros.slice(0, 50).map(e => `<li>Linha ${e.linha}: ${esc(e.motivo)}</li>`).join('')}</ul></details>`
    : '';
  const pt = d.por_tipo || {};
  const tiposHtml = Object.keys(pt).filter(k => pt[k] > 0)
    .map(k => `<span class="badge tp-${k}" style="margin-right:6px">${rotulo('tipo_contato', k)}: ${pt[k]}</span>`).join('');
  $('#imp-resultado').innerHTML = `
    <div class="grid-cards" style="margin-top:16px">
      <div class="card-stat"><div class="num">${d.inseridos}</div><div class="rot">Inseridos</div></div>
      <div class="card-stat"><div class="num">${d.ignorados_duplicados}</div><div class="rot">Ignorados (duplicados)</div></div>
      <div class="card-stat destaque"><div class="num">${d.erros.length}</div><div class="rot">Com erro</div></div>
    </div>
    ${tiposHtml ? `<p class="ajuda" style="margin-top:10px">Por tipo: ${tiposHtml}</p>` : ''}
    ${errosHtml}`;
  toast(r.message);
}
