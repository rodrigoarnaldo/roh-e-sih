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
  $('#form-contato').addEventListener('submit', aoSalvarContato);
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
  alvo.innerHTML = '<div class="view-topo"><h2>Follow-up de hoje</h2></div><p class="vazio">Carregando…</p>';
  const r = await API.get('contatos.php?acao=followup');
  if (!r.success) { alvo.innerHTML = '<p class="vazio">Erro ao carregar.</p>'; return; }
  if (!r.data.length) {
    alvo.innerHTML = '<div class="view-topo"><h2>Follow-up de hoje</h2></div><p class="vazio">Nenhum contato pendente. 🎉</p>';
    return;
  }
  const linhas = r.data.map(c => `
    <tr>
      <td>${esc(c.nome)} ${esc(c.sobrenome || '')}</td>
      <td>${esc(c.whatsapp)}</td>
      <td><span class="badge tp-${c.tipo_contato}">${rotulo('tipo_contato', c.tipo_contato)}</span></td>
      <td class="venc">${c.data_proximo_contato}</td>
      <td><button class="btn" data-editar="${c.id}">Abrir</button></td>
    </tr>`).join('');
  alvo.innerHTML = `
    <div class="view-topo"><h2>Follow-up de hoje</h2></div>
    <div class="tabela-wrap">
      <table><thead><tr><th>Nome</th><th>WhatsApp</th><th>Tipo</th><th>Previsto</th><th></th></tr></thead>
      <tbody>${linhas}</tbody></table>
    </div>`;
  $$('[data-editar]', alvo).forEach(b => b.addEventListener('click', () => abrirModalContato(+b.dataset.editar)));
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
