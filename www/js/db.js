// www/js/db.js
window.DB = (function () {
  let db;
  function open() {
    return new Promise((resolve, reject) => {
      db = window.sqlitePlugin.openDatabase({ name: 'hroman.db', location: 'default' },
        () => resolve(db), reject);
    });
  }

  function tx(fn) {
    return new Promise((resolve, reject) => {
      db.transaction(fn, reject, resolve);
    });
  }

  async function init() {
    await open();
    await tx(tx => {
      // Meta sync
      tx.executeSql(`CREATE TABLE IF NOT EXISTS sync_state (
        table_name TEXT PRIMARY KEY,
        last_pull TEXT DEFAULT NULL
      )`);

      // Usuarios (para login offline)
      tx.executeSql(`CREATE TABLE IF NOT EXISTS usuarios (
        id INTEGER PRIMARY KEY,
        rut TEXT UNIQUE,
        nombre TEXT, apellido TEXT, email TEXT,
        tipo TEXT,
        estado_binario INTEGER,
        updated_at TEXT,
        _dirty INTEGER DEFAULT 0,
        _deleted INTEGER DEFAULT 0
      )`);

      // Activos (sólo lo necesario para la app móvil)
      tx.executeSql(`CREATE TABLE IF NOT EXISTS activos (
        id_activo INTEGER PRIMARY KEY,
        identificacion TEXT,                 -- patente
        tipo TEXT,
        capacidad_tanque INTEGER,
        tipo_medicion TEXT,
        capacidad_aljibe REAL,
        tipo_combustible TEXT,
        estado_binario INTEGER,
        updated_at TEXT,
        _dirty INTEGER DEFAULT 0,
        _deleted INTEGER DEFAULT 0
      )`);
      tx.executeSql(`CREATE INDEX IF NOT EXISTS idx_activos_ident ON activos(identificacion)`);

      // Surtidores (catálogo)
      tx.executeSql(`CREATE TABLE IF NOT EXISTS surtidores (
        id INTEGER PRIMARY KEY,
        nombre TEXT, ubicacion TEXT,
        estado_binario INTEGER,
        updated_at TEXT,
        _dirty INTEGER DEFAULT 0,
        _deleted INTEGER DEFAULT 0
      )`);

      // Destino material (catálogo)
      tx.executeSql(`CREATE TABLE IF NOT EXISTS destino_material (
        id INTEGER PRIMARY KEY,
        nombre_destino TEXT,
        tipo_destino TEXT,
        ubicacion TEXT,
        latitud REAL, longitud REAL,
        descripcion TEXT,
        estado_binario INTEGER,
        updated_at TEXT,
        _dirty INTEGER DEFAULT 0,
        _deleted INTEGER DEFAULT 0
      )`);

      // Medición (siempre sincroniza cambios)
      tx.executeSql(`CREATE TABLE IF NOT EXISTS medicion (
        id_medicion INTEGER PRIMARY KEY,
        id_activo INTEGER NOT NULL,
        valor REAL NOT NULL,
        fecha_registro TEXT NOT NULL,
        updated_at TEXT,                     -- si el API la entrega; si no, usamos fecha_registro
        _dirty INTEGER DEFAULT 0,            -- 1 = creada/actualizada localmente (pendiente de subir)
        _deleted INTEGER DEFAULT 0
      )`);
      tx.executeSql(`CREATE INDEX IF NOT EXISTS idx_med_activo_fecha ON medicion(id_activo, fecha_registro)`);

      // Registros de combustible (cola offline)
      tx.executeSql(`CREATE TABLE IF NOT EXISTS registros_combustible (
        local_id INTEGER PRIMARY KEY AUTOINCREMENT,  -- id local temporal
        id_registro INTEGER,                         -- id del servidor (nulo mientras no suba)
        id_activo INTEGER NOT NULL,                  -- surtidor o receptor según tu API
        fecha_hora TEXT NOT NULL,
        tipo_movimiento TEXT CHECK(tipo_movimiento IN ('carga','descarga')),
        cantidad REAL NOT NULL,
        tipo_combustible TEXT,
        surtidores INTEGER,
        observaciones TEXT,
        id_usuario INTEGER NOT NULL,
        id_activos INTEGER,                          -- si tu API lo ocupa
        destino_combustible INTEGER,
        id_medicion INTEGER,
        updated_at TEXT,
        _dirty INTEGER DEFAULT 1,                    -- pendientes de enviar por defecto
        _deleted INTEGER DEFAULT 0
      )`);
      tx.executeSql(`CREATE INDEX IF NOT EXISTS idx_reg_dirty ON registros_combustible(_dirty)`);
      tx.executeSql(`CREATE INDEX IF NOT EXISTS idx_reg_tipo_fecha ON registros_combustible(tipo_movimiento, fecha_hora)`);

      // Sesión cacheada para login offline
      tx.executeSql(`CREATE TABLE IF NOT EXISTS session_cache (
        rut TEXT PRIMARY KEY,
        user_id INTEGER,
        nombre TEXT,
        tipo TEXT,
        token TEXT,             -- si tu API entrega token; si no, guarda marcador de "sesión validada"
        last_login TEXT,
        valid_until TEXT        -- fecha límite para permitir reingreso offline (p.ej. +7 días)
      )`);
    });
  }

  return { init, tx };
})();
