/**
 * APP.JS - SDK para App Android de Control de Combustible
 * ACTUALIZADO para compatibilidad con bootstrap.php nuevo
 */

const App = {
  config: { API_BASE: "" },
  state: {
    API_BASE: "",
    csrf: null,
    vehiculo: null,        // ← CAMBIADO: ahora es "vehiculo" (puede ser camión o estanque)
    lookups: null,
    userId: null,
    userName: null,
    userRole: null,
    rut: null,
    seleccionado: null,
    lastMedMin: 0,
    usuarioDestino: null,
    saldo_actual: 0        // ← AÑADIDO: para compatibilidad con bootstrap
  },

  // ==========================================
  // UTILIDADES (se mantienen igual)
  // ==========================================
  utils: {
    nf(decimals=2){
      return new Intl.NumberFormat('es-CL', { 
        minimumFractionDigits: decimals, 
        maximumFractionDigits: decimals 
      });
    },
    
    debounce(fn, ms){
      let t=null; 
      return (...args)=>{ 
        clearTimeout(t); 
        t=setTimeout(()=>fn(...args), ms); 
      };
    },
    
    formatRut(rut) {
      let valor = rut.replace(/\./g, '').replace(/-/g, '');
      if (!valor) return '';
      let cuerpo = valor.slice(0, -1);
      let dv = valor.slice(-1).toUpperCase();
      cuerpo = cuerpo.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
      return cuerpo + '-' + dv;
    }
  },

  // ==========================================
  // MÁSCARA NUMÉRICA (se mantiene igual)
  // ==========================================
  numMask: {
    // ... (mismo código anterior)
  },

  // ==========================================
  // ALMACENAMIENTO SEGURO (se mantiene igual)
  // ==========================================
  _store: {
    // ... (mismo código anterior)
  },

  // ==========================================
  // CACHÉ DE MEDICIONES (se mantiene igual)
  // ==========================================
  cache: {
    // ... (mismo código anterior)
  },

  // ==========================================
  // INICIALIZACIÓN - ACTUALIZADA
  // ==========================================
  init(cfg) {
    this.config.API_BASE = cfg.API_BASE;
    this.state.API_BASE = cfg.API_BASE;

    // Cargar sesión previa
    const sess = this.api.getSavedSession();
    if (sess) {
      this.state.csrf = sess.csrf || null;
      this.state.userId = sess.userId || null;
      this.state.userName = sess.nombre || null;
      this.state.userRole = sess.role || null;
      this.state.rut = sess.rut || null;
    }

    // Detectar Cordova
    document.addEventListener('deviceready', () => {
      console.log('Cordova ready');
      cfg.onReady && cfg.onReady();
      this.bindUI();
      this.loadInitialData(); // ← NUEVO: cargar datos iniciales
    });
    
    // Fallback para web
    if (!window.cordova) { 
      cfg.onReady && cfg.onReady(); 
      this.bindUI();
      this.loadInitialData(); // ← NUEVO: cargar datos iniciales
    }

    // Service Worker
    if ('serviceWorker' in navigator) {
      navigator.serviceWorker.register('sw.js').catch(() => {});
    }

    this.offline.init();
  },

  // ==========================================
  // NUEVO: Cargar datos iniciales
  // ==========================================
  async loadInitialData() {
    try {
      const bootstrapData = await this.api.bootstrap();
      if (bootstrapData && bootstrapData.session_ok) {
        // Actualizar estado con datos del bootstrap
        this.state.vehiculo = bootstrapData.camion; // ← Compatibilidad con nombre antiguo
        this.state.saldo_actual = bootstrapData.saldo_actual;
        this.state.lookups = bootstrapData.lookups;
        this.state.csrf = bootstrapData.csrf_token;
        
        // Actualizar UI
        this.ui.updateHeader(bootstrapData);
        
        console.log('Datos iniciales cargados correctamente');
      } else {
        console.warn('Sesión inválida, redirigiendo a login');
        // Redirigir a login si la sesión no es válida
        window.location.href = 'index.html';
      }
    } catch (error) {
      console.error('Error cargando datos iniciales:', error);
      this.ui.alert("Error cargando datos iniciales", "error");
    }
  },

  // ==========================================
  // BIND UI EVENTS - ACTUALIZADA
  // ==========================================
  bindUI(){
    // Máscaras numéricas
    this.numMask.bind(document.getElementById('cantidad'), {decimals: 2});
    this.numMask.bind(document.getElementById('medicionValor'), {decimals: 2});

    // Selects
    const tipoSel = document.getElementById('tipoMovimiento');
    if (tipoSel) tipoSel.addEventListener('change', e => this.ui.toggleByTipo(e.target.value));

    // Fuente de carga
    const fuenteRadios = document.querySelectorAll('input[name="fuenteRadio"]');
    fuenteRadios.forEach(radio => {
      radio.addEventListener('change', (e) => {
        this.ui.updateFuenteUI(e.target.value);
      });
    });

    // Búsqueda con debounce - Activos
    const buscar = document.getElementById('buscarActivo');
    if (buscar) {
      const doSearch = this.utils.debounce(q => {
        q = (q||'').trim();
        if (q.length < 2) { 
          this.ui.clearResultadosActivos(); 
          return; 
        }
        this.handlers.buscarActivo(q);
      }, 250);
      buscar.addEventListener('input', () => doSearch(buscar.value));
    }

    // Búsqueda con debounce - Usuarios
    const buscarUsuario = document.getElementById('buscarUsuario');
    if (buscarUsuario) {
      const doSearchUsuario = this.utils.debounce(q => {
        q = (q||'').trim();
        if (q.length < 2) { 
          this.ui.clearResultadosUsuarios(); 
          return; 
        }
        this.handlers.buscarUsuario(q);
      }, 250);
      buscarUsuario.addEventListener('input', () => doSearchUsuario(buscarUsuario.value));
    }

    // Botones
    const btnReg = document.getElementById('btnRegistrar');
    if (btnReg) btnReg.addEventListener('click', () => this.handlers.onRegistrar());
    
    const btnReset = document.getElementById('btnReset');
    if (btnReset) btnReset.addEventListener('click', () => this.ui.resetForm());

    const btnCambiarVehiculo = document.getElementById('btnCambiarCamion');
    if (btnCambiarVehiculo) {
      btnCambiarVehiculo.addEventListener('click', () => this.handlers.abrirSelectorVehiculo());
    }

    const btnLogout = document.getElementById('btnLogout');
    if (btnLogout) {
      btnLogout.addEventListener('click', () => this.api.logout());
    }
  },

  // ==========================================
  // UI HELPERS - ACTUALIZADA
  // ==========================================
  ui: {
    loading(btn, on) { 
      if (!btn) return; 
      btn.disabled = !!on; 
    },
    
    alert(msg, type = "info") {
      const el = document.getElementById('alerts'); 
      if (!el) return;
      el.innerHTML = `<div class="card" style="border-color:${type === 'error' ? '#f66' : '#ffd700'}"><b>${type === 'error' ? '⚠️ ' : ''}${msg}</b></div>`;
      setTimeout(() => { el.innerHTML = ''; }, 4000);
    },
    
    progress(saldo, capacidad) {
      const pct = capacidad > 0 ? Math.max(0, Math.min(100, (saldo / capacidad) * 100)) : 0;
      const bar = document.getElementById('fuelProgress'); 
      if (bar) {
        bar.style.width = pct.toFixed(2) + "%";
        // Color basado en nivel
        if (pct > 80) bar.style.backgroundColor = '#dc3545';
        else if (pct > 50) bar.style.backgroundColor = '#ffc107';
        else bar.style.backgroundColor = '#28a745';
      }
    },
    
    fillSelect(sel, arr, v = 'id', t = 'nombre', withEmpty = false) {
      if (!sel) return; 
      sel.innerHTML = withEmpty ? `<option value="">Selecciona…</option>` : "";
      (arr || []).forEach(o => {
        const op = document.createElement('option'); 
        op.value = o[v]; 
        op.textContent = o[t]; 
        sel.appendChild(op);
      });
    },
    
    // NUEVO: Actualizar cabecera con datos del bootstrap
    updateHeader(bootstrapData) {
      const vehiculo = bootstrapData.camion;
      const saldo = bootstrapData.saldo_actual || 0;
      const capacidad = vehiculo ? vehiculo.capacidad_aljibe : 0;

      // Actualizar información del vehículo
      const vehiculoNombre = document.getElementById('vehiculoNombre') || document.getElementById('camionNombre');
      const saldoActual = document.getElementById('saldoActual');
      const capacidadTotal = document.getElementById('capacidadTotal');
      const capacidadTotalLabel = document.getElementById('capacidadTotalLabel');

      if (vehiculoNombre) {
        vehiculoNombre.textContent = vehiculo ? vehiculo.identificacion : '—';
      }

      if (saldoActual) {
        saldoActual.textContent = App.utils.nf(2).format(saldo);
      }

      if (capacidadTotal) {
        capacidadTotal.textContent = App.utils.nf(2).format(capacidad);
      }

      if (capacidadTotalLabel) {
        capacidadTotalLabel.textContent = `${App.utils.nf(2).format(capacidad)} L`;
      }

      // Actualizar barra de progreso
      this.progress(saldo, capacidad);

      // Actualizar lookups si están disponibles
      if (bootstrapData.lookups) {
        this.fillSelect(document.getElementById('marca'), bootstrapData.lookups.surtidores, 'id', 'nombre');
        this.fillSelect(document.getElementById('area'), bootstrapData.lookups.destinos, 'id', 'nombre_destino');
      }
    },

    toggleByTipo(tipo) {
      const seccionFuente = document.getElementById('seccionFuente');
      const colMarca = document.getElementById('colMarca');
      const colCamionSurtidor = document.getElementById('colCamionSurtidor');
      const colBoleta = document.getElementById('colBoleta');
      const colBuscarActivo = document.getElementById('colBuscarActivo');
      const colArea = document.getElementById('colArea');
      const colMedicion = document.getElementById('colMedicion');
      const colUsuarioDestino = document.getElementById('colUsuarioDestino');
      
      [seccionFuente, colMarca, colCamionSurtidor, colBoleta, colBuscarActivo, colArea, colMedicion, colUsuarioDestino]
        .forEach(e => e && e.classList.add('hidden'));
      
      if (tipo === 'carga') {
        seccionFuente && seccionFuente.classList.remove('hidden');
        // Activar fuente por defecto
        const radioEstacion = document.querySelector('input[name="fuenteRadio"][value="estacion"]');
        if (radioEstacion) {
          radioEstacion.checked = true;
          this.updateFuenteUI('estacion');
        }
      }
      
      if (tipo === 'descarga') {
        colBuscarActivo && colBuscarActivo.classList.remove('hidden');
        colArea && colArea.classList.remove('hidden');
        colMedicion && colMedicion.classList.remove('hidden');
        colUsuarioDestino && colUsuarioDestino.classList.remove('hidden');
      }
    },
    
    updateFuenteUI(fuente) {
      const colMarca = document.getElementById('colMarca');
      const colCamionSurtidor = document.getElementById('colCamionSurtidor');
      const colBoleta = document.getElementById('colBoleta');
      
      [colMarca, colCamionSurtidor, colBoleta].forEach(e => e && e.classList.add('hidden'));
      
      if (fuente === 'estacion') {
        colMarca && colMarca.classList.remove('hidden');
        colBoleta && colBoleta.classList.remove('hidden');
      } else if (fuente === 'camion_surtidor') {
        colCamionSurtidor && colCamionSurtidor.classList.remove('hidden');
        colBoleta && colBoleta.classList.remove('hidden');
      }
    },
    
    clearResultadosActivos() {
      const res = document.getElementById('searchResults'); 
      if (!res) return;
      res.classList.add('hidden'); 
      res.innerHTML = '';
      const sel = document.getElementById('seleccionActivo'); 
      if (sel) sel.textContent = '';
      App.state.seleccionado = null; 
      App.state.lastMedMin = 0;
      const hint = document.getElementById('medicionHint'); 
      if (hint) hint.textContent = '';
      const med = document.getElementById('medicionValor'); 
      if (med) med.value = '';
    },
    
    clearResultadosUsuarios() {
      const res = document.getElementById('searchResultsUsuario'); 
      if (!res) return;
      res.classList.add('hidden'); 
      res.innerHTML = '';
      const sel = document.getElementById('seleccionUsuario'); 
      if (sel) sel.textContent = '';
      App.state.usuarioDestino = null;
    },
    
    resultadosActivos(items) {
      const res = document.getElementById('searchResults'); 
      if (!res) return;
      if (!items || items.length === 0) { 
        this.clearResultadosActivos(); 
        return; 
      }

      res.classList.remove('hidden');
      res.innerHTML = items.map(it => {
        const est = it.estado_binario == 1 ? '✅' : '⛔';
        const icon = it.tipo?.includes('camion') ? '🚛' : 
                     it.tipo?.includes('automovil') || it.tipo?.includes('camioneta') ? '🚗' : 
                     it.tipo?.includes('maquinaria') ? '⚙️' : '🔧';
        return `<div class="list-item" data-id="${it.id_activo}">
                  <div>
                    <b>${icon} ${it.identificacion || ('ID ' + it.id_activo)}</b>
                    <div class="small muted">${[it.tipo||'', it.marca||'', it.modelo||''].filter(Boolean).join(' ')}</div>
                  </div>
                  <div>${est}</div>
                </div>`;
      }).join('');

      // Binding clicks
      Array.from(res.children).forEach((el, i) => {
        el.addEventListener('click', async () => {
          const a = items[i]; 
          if (!a) return;
          App.state.seleccionado = a;
          
          const selTxt = document.getElementById('seleccionActivo');
          if (selTxt) selTxt.textContent = `Seleccionado: ${a.identificacion || ('ID ' + a.id_activo)} (${a.tipo || ''})`;
          
          res.classList.add('hidden');

          // Consulta última medición
          try {
            const m = await App.api.getLastMedicion(a.id_activo);
            const min = (m && m.success && m.last_value != null) ? Number(m.last_value) : 0;
            App.state.lastMedMin = min;
            App.cache.saveMin(a.id_activo, min);
          } catch(_) {
            App.state.lastMedMin = App.cache.getMin(a.id_activo) || 0;
          }

          const hint = document.getElementById('medicionHint');
          if (hint) hint.textContent = App.state.lastMedMin > 0 ? 
            `Mínimo permitido: ${App.utils.nf(0).format(App.state.lastMedMin)}` : '';
        });
      });
    },
    
    resultadosUsuarios(items) {
      const res = document.getElementById('searchResultsUsuario'); 
      if (!res) return;
      if (!items || items.length === 0) { 
        this.clearResultadosUsuarios(); 
        return; 
      }

      res.classList.remove('hidden');
      res.innerHTML = items.map(it => {
        return `<div class="list-item" data-id="${it.id}">
                  <div>
                    <b>👤 ${it.nombre} ${it.apellido}</b>
                    <div class="small muted">${it.rut || ''} ${it.tipo ? '· ' + it.tipo : ''}</div>
                  </div>
                </div>`;
      }).join('');

      // Binding clicks
      Array.from(res.children).forEach((el, i) => {
        el.addEventListener('click', () => {
          const u = items[i]; 
          if (!u) return;
          App.state.usuarioDestino = u;
          
          const selTxt = document.getElementById('seleccionUsuario');
          if (selTxt) selTxt.textContent = `Seleccionado: ${u.nombre} ${u.apellido} (${u.rut || ''})`;
          
          res.classList.add('hidden');
        });
      });
    },
    
    resetForm(){
      const ids = ['tipoMovimiento','cantidad','marca','camionSurtidor','numeroBoleta','buscarActivo','area','medicionValor','buscarUsuario','observaciones'];
      ids.forEach(id => { 
        const el = document.getElementById(id); 
        if (el) el.value = ''; 
      });
      
      this.clearResultadosActivos();
      this.clearResultadosUsuarios();
      
      const colIds = ['seccionFuente','colMarca','colCamionSurtidor','colBoleta','colBuscarActivo','colArea','colMedicion','colUsuarioDestino'];
      colIds.forEach(id => { 
        const el = document.getElementById(id); 
        if (el) el.classList.add('hidden'); 
      });
      
      const tipo = document.getElementById('tipoMovimiento'); 
      if (tipo) tipo.focus();
    }
  },

  // ==========================================
  // OFFLINE (IndexedDB) - se mantiene igual
  // ==========================================
  offline: {
    // ... (mismo código anterior)
  },

  // ==========================================
  // API - ACTUALIZADA para compatibilidad
  // ==========================================
  api: {
    _allowedRole(tipo) {
      const t = (tipo || "").toLowerCase().replace(/_/g, " ").replace(/\s+/g, " ").trim();
      return ["surtidor", "gerencia", "control gps", "control combustible"].includes(t);
    },

    async _fetch(path, { method = 'GET', body, headers = {} } = {}) {
      const url = `${App.state.API_BASE}${path}`;
      const opts = {
        method,
        headers: { "Content-Type": "application/json", ...headers },
        credentials: "include",
        mode: "cors"
      };
      if (body) opts.body = JSON.stringify(body);

      const r = await fetch(url, opts);
      let data = null;
      try { data = await r.json(); } catch (_) {}
      
      if (!r.ok) {
        if (data) return data;
        throw new Error(`HTTP ${r.status}`);
      }
      return data;
    },

    _saveSession(payload) { 
      App._store.set("userSession", payload); 
    },
    
    getSavedSession() { 
      return App._store.get("userSession"); 
    },
    
    _clearSession() { 
      App._store.remove("userSession"); 
    },

    async login(rut, clave) {
      const data = await this._fetch("/login.php", { 
        method: "POST", 
        body: { rut, clave } 
      });
      
      if (!data || !data.success || !data.user) return false;
      if (!this._allowedRole(data.user.tipo)) return false;

      App.state.csrf = data.csrf_token || null;
      App.state.userId = data.user.id || null;
      App.state.userName = data.user.nombre || null;
      App.state.userRole = data.user.tipo || null;
      App.state.rut = rut;

      this._saveSession({
        rut,
        userId: App.state.userId,
        nombre: App.state.userName,
        role: App.state.userRole,
        csrf: App.state.csrf,
        lastLoginAt: Date.now()
      });

      return true;
    },

    async logout() {
      try { 
        await this._fetch("/logout.php", { method: "POST" }); 
      } catch (_) {}
      
      this._clearSession();
      App.state.csrf = null;
      App.state.userId = null;
      App.state.userName = null;
      App.state.userRole = null;
      App.state.rut = null;
      App.state.vehiculo = null;
      App.state.saldo_actual = 0;
      
      // Redirigir a login
      window.location.href = 'index.html';
    },

    bootstrap() { 
      return this._fetch("/bootstrap.php"); 
    },
    
    searchActivos(q) { 
      return this._fetch(`/search_activos.php?query=${encodeURIComponent(q)}`); 
    },
    
    searchUsuarios(q) { 
      return this._fetch(`/search_usuarios.php?query=${encodeURIComponent(q)}`); 
    },
    
    getLastMedicion(id) { 
      return this._fetch(`/get_last_medicion.php?id_activo=${encodeURIComponent(id)}`); 
    },

    async syncMovimiento(payload) {
      const headers = {};
      if (App.state.csrf) headers["X-CSRF-Token"] = App.state.csrf;
      return this._fetch("/sync.php", { 
        method: "POST", 
        body: payload, 
        headers 
      });
    },

    listCamiones() { 
      return this._fetch("/list_camiones.php"); 
    },
    
    selectCamion(id_activo) { 
      return this._fetch("/select_camion.php", { 
        method: "POST", 
        body: { id_activo } 
      }); 
    }
  },

  // ==========================================
  // HANDLERS - ACTUALIZADA
  // ==========================================
  handlers: {
    async buscarActivo(q) {
      try {
        if (!navigator.onLine) {
          const local = await App.offline.getLookup('activos');
          const items = (local || []).filter(a => {
            const hay = `${a.identificacion||''} ${a.serial||''} ${a.interno||''} ${a.marca||''} ${a.modelo||''}`.toLowerCase();
            return hay.includes((q||'').toLowerCase());
          }).slice(0,50);
          App.ui.resultadosActivos(items);
          return;
        }
        
        const data = await App.api.searchActivos(q);
        App.ui.resultadosActivos(Array.isArray(data) ? data.slice(0,50) : []);
      } catch (e) {
        App.ui.alert("Error buscando activos", "error");
      }
    },

    async buscarUsuario(q) {
      try {
        if (!navigator.onLine) {
          const local = await App.offline.getLookup('usuarios');
          const items = (local || []).filter(u => {
            const hay = `${u.rut||''} ${u.nombre||''} ${u.apellido||''}`.toLowerCase();
            return hay.includes((q||'').toLowerCase());
          }).slice(0,50);
          App.ui.resultadosUsuarios(items);
          return;
        }
        
        const data = await App.api.searchUsuarios(q);
        App.ui.resultadosUsuarios(Array.isArray(data) ? data.slice(0,50) : []);
      } catch (e) {
        App.ui.alert("Error buscando usuarios", "error");
      }
    },

    async abrirSelectorVehiculo() {
      try {
        const vehiculos = await App.api.listCamiones();
        // Aquí deberías implementar el modal para seleccionar vehículo
        // Similar a como lo hace tu formulario.html
        console.log('Vehículos disponibles:', vehiculos);
        App.ui.alert("Función de selección de vehículo en desarrollo", "info");
      } catch (error) {
        App.ui.alert("Error cargando vehículos", "error");
      }
    },

    async onRegistrar() {
      // Validar que hay un vehículo seleccionado
      if (!App.state.vehiculo) {
        App.ui.alert("Primero debes seleccionar un vehículo", "error");
        return;
      }

      const tipo = document.getElementById('tipoMovimiento').value;
      const cantidad = App.numMask.parseMasked(document.getElementById('cantidad').value);
      const obs = (document.getElementById('observaciones').value || '').trim() || null;
      
      // Campos CARGA
      const fuenteTipo = tipo === 'carga' ? 
        (document.querySelector('input[name="fuenteRadio"]:checked')?.value || null) : null;
      const marca = document.getElementById('marca').value || null;
      const camionSurtidor = document.getElementById('camionSurtidor').value || null;
      const numeroBoleta = document.getElementById('numeroBoleta').value || null;
      
      // Campos DESCARGA
      const area = document.getElementById('area').value || null;
      const medValRaw = document.getElementById('medicionValor').value || '';
      const medVal = medValRaw.trim() !== '' ? App.numMask.parseMasked(medValRaw) : null;
      const sel = App.state.seleccionado;
      const usuarioDest = App.state.usuarioDestino;

      // Validaciones UI
      if (!tipo) return App.ui.alert("Selecciona tipo de movimiento", "error");
      if (!Number.isFinite(cantidad) || !(cantidad > 0)) return App.ui.alert("Cantidad inválida", "error");
      
      if (tipo === 'carga') {
        if (!fuenteTipo) return App.ui.alert("Selecciona fuente de carga", "error");
        if (fuenteTipo === 'estacion' && !marca) return App.ui.alert("Selecciona surtidor", "error");
        if (fuenteTipo === 'camion_surtidor' && !camionSurtidor) return App.ui.alert("Selecciona camión surtidor", "error");
      }
      
      if (tipo === 'descarga') {
        if (!sel) return App.ui.alert("Selecciona un activo", "error");
        if (!area) return App.ui.alert("Selecciona destino", "error");
        if (medVal == null || !Number.isFinite(medVal) || medVal < 0) return App.ui.alert("Ingresa medición válida", "error");
        if (App.state.lastMedMin != null && Number.isFinite(App.state.lastMedMin) && medVal < App.state.lastMedMin)
          return App.ui.alert(`La medición no puede ser menor a ${App.utils.nf(0).format(App.state.lastMedMin)}`, "error");
        if (!usuarioDest) return App.ui.alert("Selecciona usuario destinatario", "error");
      }

      const btn = document.getElementById('btnRegistrar'); 
      const spin = document.getElementById('spin');
      if (btn) btn.disabled = true; 
      if (spin) spin.classList.remove('hidden');

      const payload = {
        action: "create",
        id_activo: App.state.vehiculo.id_activo, // ← Usa vehiculo en lugar de camion
        tipo_movimiento: tipo,
        cantidad: cantidad,
        tipo_combustible: "petroleo",
        // CARGA
        fuente_tipo: fuenteTipo,
        marca: (tipo === 'carga' && fuenteTipo === 'estacion') ? Number(marca) : null,
        camion_surtidor: (tipo === 'carga' && fuenteTipo === 'camion_surtidor') ? Number(camionSurtidor) : null,
        numero_boleta: (tipo === 'carga') ? numeroBoleta : null,
        // DESCARGA
        id_activos: (tipo === 'descarga' && sel) ? Number(sel.id_activo) : null,
        id_area: (tipo === 'descarga') ? Number(area) : null,
        medicion_valor: (tipo === 'descarga') ? (medVal != null ? medVal : null) : null,
        id_usuario_destino: (tipo === 'descarga' && usuarioDest) ? Number(usuarioDest.id) : null,
        // COMUNES
        observaciones: obs,
        id_usuario: App.state.userId,
        es_carga_directa: 0,
        client_ts: Date.now()
      };

      try {
        if (!navigator.onLine) {
          await App.offline.addPending(payload);
          App.ui.alert("Offline: movimiento guardado localmente.");
        } else {
          const r = await App.api.syncMovimiento(payload);
          if (r && r.success) {
            // Actualizar saldo desde la respuesta
            App.state.saldo_actual = Number(r.new_balance || 0);
            
            // Actualizar UI
            const saldoEl = document.getElementById('saldoActual');
            if (saldoEl) saldoEl.textContent = App.utils.nf(2).format(App.state.saldo_actual);
            App.ui.progress(App.state.saldo_actual, Number(App.state.vehiculo && App.state.vehiculo.capacidad_aljibe || 0));
            
            App.ui.alert("Movimiento registrado.");
            App.ui.resetForm();
          } else {
            throw new Error(r && r.error || "Servidor rechazó el movimiento");
          }
        }
      } catch (e) {
        App.ui.alert(e.message || String(e), "error");
      } finally {
        if (btn) btn.disabled = false; 
        if (spin) spin.classList.add('hidden');
      }
    }
  }
};

// (El resto del código permanece igual...)

// Funcionalidad legado/externa (si existe)
document.addEventListener('deviceready', async () => {
  if (typeof DB !== "undefined" && DB.init) {
    try { await DB.init(); } catch (_) {}
  }

  if (!navigator.connection || navigator.connection.type !== 'none') {
    try { 
      if (typeof SYNC !== "undefined" && SYNC.syncAll) await SYNC.syncAll(); 
    } catch (_) {}
  }

  setInterval(async () => {
    if (!navigator.connection || navigator.connection.type !== 'none') {
      try { 
        if (typeof SYNC !== "undefined" && SYNC.syncAll) await SYNC.syncAll(); 
      } catch (_) {}
    }
  }, 5 * 60 * 1000);

  document.addEventListener('online', async () => {
    try { 
      if (typeof SYNC !== "undefined" && SYNC.syncAll) await SYNC.syncAll(); 
    } catch (_) {}
  });
});