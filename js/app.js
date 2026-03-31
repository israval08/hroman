
/* Minimal Cordova front-end SDK for your CRM API */
const App = {
  state: { API_BASE:"", csrf:null, camion:null, lookups:null, userId:null, seleccionado:null, lastMedMin:0 },
  init(cfg){
    this.state.API_BASE = cfg.API_BASE;
    document.addEventListener('deviceready', () => cfg.onReady && cfg.onReady());
    // When running in browser (for quick tests)
    if(!window.cordova){ cfg.onReady && cfg.onReady(); }
    // SW optional for web fallback
    if('serviceWorker' in navigator){
      navigator.serviceWorker.register('sw.js').catch(()=>{});
    }
    this.offline.init();
  },
  ui:{
    loading(btn, on){ if(!btn) return; btn.disabled=!!on; },
    alert(msg, type="info"){
      const el = document.getElementById('alerts'); if(!el) return;
      el.innerHTML = `<div class="card" style="border-color:${type==='error'?'#f66':'#ffd700'}"><b>${type==='error'?'⚠️ ':''}${msg}</b></div>`;
      setTimeout(()=>{ el.innerHTML=''; }, 4000);
    },
    progress(saldo, capacidad){
      const pct = capacidad>0 ? Math.max(0, Math.min(100, (saldo/capacidad)*100)) : 0;
      const bar = document.getElementById('fuelProgress'); if(bar) bar.style.width = pct.toFixed(2) + "%";
    },
    fillSelect(sel, arr, v='id', t='nombre', withEmpty=false){
      if(!sel) return; sel.innerHTML = withEmpty? `<option value="">Selecciona…</option>`:"";
      (arr||[]).forEach(o => {
        const op = document.createElement('option'); op.value = o[v]; op.textContent = o[t]; sel.appendChild(op);
      });
    },
    toggleByTipo(tipo){
      const colSur = document.getElementById('colSurtidor');
      const colDesc = document.getElementById('colDescargaTipo');
      [colSur,colDesc].forEach(e=>e.classList.add('hidden'));
      if(tipo==='carga'){ colSur.classList.remove('hidden'); }
      if(tipo==='descarga'){ colDesc.classList.remove('hidden'); }
    },
    toggleDescarga(kind){
      const colAct = document.getElementById('colBuscarActivo');
      const colMed = document.getElementById('colMedicion');
      const colFae = document.getElementById('colFaena');
      [colAct,colMed,colFae].forEach(e=>e.classList.add('hidden'));
      if(kind==='activo'){ colAct.classList.remove('hidden'); colMed.classList.remove('hidden'); }
      if(kind==='faena'){ colFae.classList.remove('hidden'); }
    },
    clearResultados(){
      const res = document.getElementById('resultados'); if(!res) return;
      res.classList.add('hidden'); res.innerHTML='';
      document.getElementById('seleccionActivo').textContent='';
      App.state.seleccionado = null; App.state.lastMedMin=0;
    },
    resultados(items){
      const res = document.getElementById('resultados'); if(!res) return;
      res.classList.remove('hidden'); res.innerHTML = (items||[]).map(it=>{
        const est = it.estado_binario==1 ? '✅' : '⛔';
        return `<div class="list-item"><div><b>${it.identificacion||('ID '+it.id_activo)}</b><div class="small muted">${it.tipo||''} ${it.marca||''} ${it.modelo||''}</div></div><div>${est}</div></div>`;
      }).join('');
      Array.from(res.children).forEach((el, i)=> el.addEventListener('click', async ()=>{
        const a = items[i]; App.state.seleccionado = a;
        document.getElementById('seleccionActivo').textContent = `Seleccionado: ${a.identificacion||('ID '+a.id_activo)} (${a.tipo||''})`;
        res.classList.add('hidden');
        // Fetch last medición
        try{
          const m = await App.api.getLastMedicion(a.id_activo);
          const min = (m && m.last_value!=null) ? Number(m.last_value) : 0;
          App.state.lastMedMin = min;
          const medEl = document.getElementById('medicionValor');
          if(medEl){ medEl.min = min; document.getElementById('medicionHint').textContent = min>0?`Mínimo permitido: ${min}`:''; }
        }catch(_){ /* ignore */ }
      }));
    }
  },
  offline:{
    db:null,
    init(){
      if(!('indexedDB' in window)) return;
      const req = indexedDB.open('FuelAppDB', 1);
      req.onupgradeneeded = e => {
        const db = e.target.result;
        db.createObjectStore('pending', { autoIncrement:true });
        db.createObjectStore('lookups', { keyPath:'name' });
      };
      req.onsuccess = e => { this.db = e.target.result; this.syncPending(); window.addEventListener('online', ()=>this.syncPending()); };
    },
    async mirrorLookups(lookups){
      if(!this.db) return;
      const tx = this.db.transaction('lookups','readwrite');
      const st = tx.objectStore('lookups');
      await Promise.all([
        st.put({name:'surtidores', data: lookups.surtidores||[]}),
        st.put({name:'destinos', data: lookups.destinos||[]})
      ]);
    },
    async getLookup(name){
      if(!this.db) return null;
      return new Promise(res=>{
        const tx = this.db.transaction('lookups','readonly');
        tx.objectStore('lookups').get(name).onsuccess = ev => res(ev.target.result?.data||null);
      });
    },
    async addPending(payload){
      if(!this.db) return;
      return new Promise(res=>{
        const tx = this.db.transaction('pending','readwrite');
        tx.objectStore('pending').add(payload).onsuccess = ()=>res(true);
      });
    },
    async syncPending(){
      if(!navigator.onLine || !this.db) return;
      const tx = this.db.transaction('pending','readwrite');
      const st = tx.objectStore('pending');
      st.openCursor().onsuccess = async ev => {
        const cursor = ev.target.result; if(!cursor) return;
        try{
          await App.api.syncMovimiento(cursor.value);
          cursor.delete();
        }catch(e){ /* keep item */ }
        cursor.continue();
      };
    }
  },
  api:{
    async _fetch(path, {method='GET', body, headers={}}={}){
      const url = `${App.state.API_BASE}${path}`;
      const opts = {
        method,
        headers: {
          "Content-Type":"application/json",
          ...headers
        },
        credentials: "include", // critical for PHPSESSID cookies
        mode: "cors"
      };
      if(body) opts.body = JSON.stringify(body);
      const r = await fetch(url, opts);
      if(!r.ok) throw new Error(`HTTP ${r.status}`);
      return r.json();
    },
    login(rut, clave){ 
      return this._fetch("/login.php", { method:"POST", body:{ rut, clave } })
        .then(j=>!!j.success);
    },
    logout(){ return this._fetch("/logout.php", { method:"POST" }); },
    bootstrap(){ return this._fetch("/bootstrap.php"); },
    searchActivos(q){ return this._fetch(`/search_activos.php?query=${encodeURIComponent(q)}`); },
    getLastMedicion(id){ return this._fetch(`/get_last_medicion.php?id_activo=${encodeURIComponent(id)}`); },
    async syncMovimiento(payload){
      // payload must include: action='create', fields as in your sync.php and CSRF header
      return this._fetch("/sync.php", { method:"POST", body:payload, headers:{"X-CSRF-Token": App.state.csrf} });
    }
  },
  handlers:{
    async buscarActivo(q){
      try{
        if(!navigator.onLine){
          const local = await App.offline.getLookup('activos'); // optional future cache
          App.ui.resultados((local||[]).filter(a => (a.identificacion||'').toLowerCase().includes(q.toLowerCase())));
          return;
        }
        const data = await App.api.searchActivos(q);
        if(Array.isArray(data)) App.ui.resultados(data);
        else App.ui.resultados([]);
      }catch(e){
        App.ui.alert("Error buscando activos", "error");
      }
    },
    async onRegistrar(){
      const tipo = document.getElementById('tipoMovimiento').value;
      const cantidad = Number(document.getElementById('cantidad').value);
      const destKind = document.getElementById('descargaTipo').value;
      const obs = document.getElementById('observaciones').value || null;
      const surt = document.getElementById('surtidores').value || null;
      const dest = document.getElementById('destino').value || null;
      const sel = App.state.seleccionado;
      const medValRaw = document.getElementById('medicionValor').value;
      const medVal = medValRaw!=="" ? Number(medValRaw) : null;

      // Validaciones mínimas UI
      if(!tipo) return App.ui.alert("Selecciona tipo de movimiento","error");
      if(!(cantidad>0)) return App.ui.alert("Cantidad inválida","error");
      if(tipo==='carga' && !surt) return App.ui.alert("Selecciona surtidor","error");
      if(tipo==='descarga' && !destKind) return App.ui.alert("Selecciona tipo de descarga","error");
      if(tipo==='descarga' && destKind==='activo' && !sel) return App.ui.alert("Selecciona un activo","error");
      if(tipo==='descarga' && destKind==='faena' && !dest) return App.ui.alert("Selecciona faena","error");
      if(tipo==='descarga' && destKind==='activo' && (medVal==null || medVal<0)) return App.ui.alert("Ingresa medición válida","error");
      if(tipo==='descarga' && destKind==='activo' && (App.state.lastMedMin!=null) && (medVal<App.state.lastMedMin)) return App.ui.alert(`La medición no puede ser menor a ${App.state.lastMedMin}`,"error");

      const btn = document.getElementById('btnRegistrar'); const spin = document.getElementById('spin');
      btn.disabled=true; spin.classList.remove('hidden');

      const payload = {
        action: "create",
        id_activo: App.state.camion.id_activo,
        tipo_movimiento: tipo,
        cantidad: cantidad,
        tipo_combustible: "petroleo",
        surtidores: tipo==='carga'? Number(surt): null,
        id_activos: (tipo==='descarga' && destKind==='activo' && sel)? Number(sel.id_activo): null,
        destino_combustible: (tipo==='descarga' && destKind==='faena' && dest)? Number(dest): null,
        observaciones: obs,
        id_usuario: App.state.userId,
        medicion_valor: (tipo==='descarga' && destKind==='activo')? medVal : null
      };

      try{
        if(!navigator.onLine){
          await App.offline.addPending(payload);
          App.ui.alert("Offline: movimiento guardado localmente.");
        }else{
          const r = await App.api.syncMovimiento(payload);
          if(r.success){
            const newBal = Number(r.new_balance||0);
            document.getElementById('saldoActual').textContent = newBal.toFixed(2);
            App.ui.progress(newBal, Number(App.state.camion.capacidad_aljibe||0));
            App.ui.alert("Movimiento registrado.");
          }else{
            throw new Error(r.error||"Servidor rechazó el movimiento");
          }
        }
      }catch(e){
        App.ui.alert(e.message||String(e), "error");
      }finally{
        btn.disabled=false; spin.classList.add('hidden');
      }
    }
  }
};

// Simple SW (no-op) for web tests
