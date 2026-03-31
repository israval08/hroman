// www/js/sync.js
const SYNC = (function () {

  async function getLast(table) {
    return await DB.tx(tx => new Promise(resolve => {
      tx.executeSql(`SELECT last_pull FROM sync_state WHERE table_name=?`, [table], (_, rs) => {
        resolve(rs.rows.length ? rs.rows.item(0).last_pull : null);
      });
    }));
  }
  async function setLast(table, iso) {
    await DB.tx(tx => {
      tx.executeSql(`INSERT OR REPLACE INTO sync_state (table_name, last_pull) VALUES (?,?)`, [table, iso]);
    });
  }

  // ---- PULL ----
  async function pullTable({ table, path, upsert, sinceField = 'updated_at' }) {
    const since = await getLast(table);
    const qp = since ? `?since=${encodeURIComponent(since)}` : '';
    const res = await API.get(`${path}${qp}`);
    if (!res.ok) throw new Error(`Pull ${table} fallo`);
    const rows = await res.json();
    if (!rows.length) return;

    await DB.tx(tx => {
      rows.forEach(r => upsert(tx, r));
    });

    const maxUpdated = rows
      .map(r => r[sinceField] || r.fecha_registro)
      .filter(Boolean)
      .sort()
      .pop();
    if (maxUpdated) await setLast(table, maxUpdated);
  }

  // ---- PUSH registros_combustible ----
  async function pushRegistros() {
    const pend = await DB.tx(tx => new Promise(resolve => {
      tx.executeSql(`SELECT * FROM registros_combustible WHERE _dirty=1 AND _deleted=0 ORDER BY fecha_hora ASC`, [], (_, rs) => {
        const arr = []; for (let i=0;i<rs.rows.length;i++) arr.push(rs.rows.item(i));
        resolve(arr);
      });
    }));
    if (!pend.length) return;

    for (const r of pend) {
      const payload = {
        id_registro: r.id_registro || null,
        id_activo: r.id_activo,
        fecha_hora: r.fecha_hora,
        tipo_movimiento: r.tipo_movimiento,
        cantidad: r.cantidad,
        tipo_combustible: r.tipo_combustible,
        surtidores: r.surtidores,
        observaciones: r.observaciones,
        id_usuario: r.id_usuario,
        id_activos: r.id_activos,
        destino_combustible: r.destino_combustible,
        id_medicion: r.id_medicion
      };
      const res = await API.post('/crm/api/sync/registros_combustible_push.php', payload); // ajusta endpoint
      if (res.ok) {
        const data = await res.json(); // { id_registro }
        await DB.tx(tx => {
          tx.executeSql(`UPDATE registros_combustible SET id_registro=?, _dirty=0 WHERE local_id=?`,
            [data.id_registro, r.local_id]);
        });
      } else {
        // si falla, dejamos _dirty=1 para reintentar
        break;
      }
    }
  }

  // ---- PULL medicion (siempre) ----
  async function pullMedicion() {
    await pullTable({
      table: 'medicion',
      path: '/crm/api/sync/medicion_pull.php',
      sinceField: 'updated_at', // o 'fecha_registro' si tu API no tiene updated_at
      upsert: (tx, m) => {
        tx.executeSql(`INSERT OR REPLACE INTO medicion
          (id_medicion, id_activo, valor, fecha_registro, updated_at, _dirty, _deleted)
          VALUES (?,?,?,?,?, 0,0)`,
          [m.id_medicion, m.id_activo, m.valor, m.fecha_registro, m.updated_at || m.fecha_registro]);
      }
    });
  }

  async function pullCatalogos() {
    // Activos
    await pullTable({
      table: 'activos',
      path: '/crm/api/sync/activos_pull.php',
      upsert: (tx, a) => {
        tx.executeSql(`INSERT OR REPLACE INTO activos
          (id_activo, identificacion, tipo, capacidad_tanque, tipo_medicion, capacidad_aljibe, tipo_combustible, estado_binario, updated_at, _dirty, _deleted)
          VALUES (?,?,?,?,?,?,?,?,?, 0,0)`,
          [a.id_activo, a.identificacion, a.tipo, a.capacidad_tanque, a.tipo_medicion, a.capacidad_aljibe, a.tipo_combustible, a.estado_binario, a.updated_at || new Date().toISOString()]);
      }
    });
    // Surtidores
    await pullTable({
      table: 'surtidores',
      path: '/crm/api/sync/surtidores_pull.php',
      upsert: (tx, s) => {
        tx.executeSql(`INSERT OR REPLACE INTO surtidores
          (id, nombre, ubicacion, estado_binario, updated_at, _dirty, _deleted)
          VALUES (?,?,?,?,?, 0,0)`,
          [s.id, s.nombre, s.ubicacion, s.estado_binario, s.updated_at || new Date().toISOString()]);
      }
    });
    // Destino material
    await pullTable({
      table: 'destino_material',
      path: '/crm/api/sync/destinos_pull.php',
      upsert: (tx, d) => {
        tx.executeSql(`INSERT OR REPLACE INTO destino_material
          (id, nombre_destino, tipo_destino, ubicacion, latitud, longitud, descripcion, estado_binario, updated_at, _dirty, _deleted)
          VALUES (?,?,?,?,?,?,?,?,?, 0,0)`,
          [d.id, d.nombre_destino, d.tipo_destino, d.ubicacion, d.latitud, d.longitud, d.descripcion, d.estado_binario, d.updated_at || new Date().toISOString()]);
      }
    });
  }

  // Ciclo de sincronización
  async function syncAll() {
    // Pull primero (para tener catálogos al día)
    await pullCatalogos();
    await pullMedicion();        // importante: medición SIEMPRE trae cambios
    // Push después (para que el server reciba lo que capturamos offline)
    await pushRegistros();
  }

  return { syncAll, pullCatalogos, pullMedicion, pushRegistros };
})();
