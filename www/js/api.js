// www/js/api.js
const API = (function () {
  const BASE = 'https://www.constructorahroman.cl'; // ajusta si usas subdominios

  async function request(path, options = {}) {
    const url = `${BASE}${path}`;
    const res = await fetch(url, {
      method: options.method || 'GET',
      headers: { 'Content-Type': 'application/json', ...(options.headers || {}) },
      body: options.body ? JSON.stringify(options.body) : undefined,
      credentials: 'include' // si usas sesión por cookie/PHP
    });
    return res;
  }

  function get(path) { return request(path); }
  function post(path, body) { return request(path, { method: 'POST', body }); }

  return { get, post };
})();
