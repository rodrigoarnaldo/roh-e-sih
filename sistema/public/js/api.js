// ===== Cliente Fetch — envelope JSON oficial =====
const API = (function () {
  const BASE = '../api';

  async function req(caminho, { metodo = 'GET', corpo = null } = {}) {
    const opts = {
      method: metodo,
      headers: { 'Accept': 'application/json' },
      credentials: 'same-origin',
    };
    if (corpo !== null) {
      opts.headers['Content-Type'] = 'application/json';
      opts.body = JSON.stringify(corpo);
    }
    let resp, json;
    try {
      resp = await fetch(`${BASE}/${caminho}`, opts);
      json = await resp.json();
    } catch (e) {
      return { success: false, message: 'Falha de comunicação com o servidor.', data: null, errors: [], _http: 0 };
    }
    json._http = resp.status;
    return json;
  }

  return {
    get:  (c) => req(c),
    post: (c, corpo) => req(c, { metodo: 'POST', corpo }),
  };
})();
