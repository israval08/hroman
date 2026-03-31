// www/js/auth.js
const AUTH = (function () {
  const OFFLINE_GRACE_DAYS = 7; // configurable

  function nowISO() { return new Date().toISOString(); }
  function plusDaysISO(d) { const dt = new Date(); dt.setDate(dt.getDate() + d); return dt.toISOString(); }

  async function loginOnline(rut, clave) {
    const res = await API.post('/crm/api/mobile_login.php', { rut, clave }); // ajusta endpoint/body
    if (!res.ok) throw new Error('Credenciales inválidas');
    const data = await res.json();

    // data: { id, rut, nombre, tipo, token? ... }
    await DB.tx(tx => {
      tx.executeSql(`INSERT OR REPLACE INTO usuarios (id, rut, nombre, apellido, email, tipo, estado_binario, updated_at, _dirty, _deleted)
        VALUES (?,?,?,?,?,?,?, ?,0,0)`,
        [data.id, data.rut, data.nombre || '', data.apellido || '', data.email || '', data.tipo || '', 1, nowISO()]);
      tx.executeSql(`INSERT OR REPLACE INTO session_cache (rut, user_id, nombre, tipo, token, last_login, valid_until)
        VALUES (?,?,?,?,?,?,?)`,
        [data.rut, data.id, data.nombre || '', data.tipo || '', data.token || '', nowISO(), plusDaysISO(OFFLINE_GRACE_DAYS)]);
    });

    return data;
  }

  async function login(rut, clave) {
    if (navigator.connection && navigator.connection.type === 'none') {
      // Offline => intentar con cache
      const ok = await DB.tx(tx => new Promise(resolve => {
        tx.executeSql(`SELECT * FROM session_cache WHERE rut=?`, [rut], (_, rs) => {
          if (rs.rows.length === 0) return resolve(null);
          const row = rs.rows.item(0);
          resolve(row);
        });
      }));
      if (!ok) throw new Error('Sin conexión y sin sesión previa');
      if (new Date(ok.valid_until) < new Date()) throw new Error('Sesión offline expirada');

      // Sesión offline aceptada
      return { id: ok.user_id, rut: rut, nombre: ok.nombre, tipo: ok.tipo, offline: true };
    }

    // Online
    return await loginOnline(rut, clave);
  }

  async function logout(rut) {
    await DB.tx(tx => {
      tx.executeSql(`DELETE FROM session_cache WHERE rut=?`, [rut]);
    });
  }

  return { login, logout };
})();
