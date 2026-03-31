(function(){
  const API_BASE = "https://www.constructorahroman.cl/crm/api";

  // ---------- helpers ----------
  const $ = s => document.querySelector(s);
  const sleep = ms => new Promise(r=>setTimeout(r,ms));

  const UI = {
    alert(msg, type="info"){
      const a=$("#alerts"); a.classList.remove("hidden");
      a.className="alert"+(type==="error"?" error":type==="success"?" success":"");
      a.textContent = msg;
      setTimeout(()=>{ a.classList.add("hidden"); a.textContent=""; },3000);
    },
    fillSelect(el, list, v="id", t="nombre", withEmpty=true){
      if(!el) return; el.innerHTML = withEmpty? `<option value="">Selecciona…</option>` : "";
      (list||[]).forEach(x=>{
        const o=document.createElement("option"); o.value=x[v]; o.textContent=x[t];
        el.appendChild(o);
      });
    },
    progress(saldo, cap){ 
      const p=(cap>0)?Math.max(0,Math.min(100,(saldo/cap)*100)):0; 
      $("#fuelProgress").style.width=p.toFixed(2)+"%"; 
    },
    setConn(){ 
      const el=$("#statusConn"); 
      if(navigator.onLine){ el.textContent="Modo online"; el.classList.remove("bad"); } 
      else { el.textContent="Modo offline"; el.classList.add("bad"); }
    },
    setSyncStatus(status, spinning=false){
      const el=$("#syncStatus");
      el.textContent=status;
      if(spinning) el.classList.add("syncing"); else el.classList.remove("syncing");
    }
  };

  // ---------- API ----------
  async function callApi(path, {method="GET", body=null, headers={}}={}){
    const controller = new AbortController();
    const to = setTimeout(()=>controller.abort(), 20000);
    try{
      const opts={method, credentials:"include", headers:{...headers}, signal: controller.signal};
      if(body!==null){ opts.headers["Content-Type"]="application/json"; opts.body=JSON.stringify(body); }
      const r=await fetch(API_BASE+path, opts);
      const j = await r.json().catch(()=>null);
      if(!r.ok) throw new Error(j?.error || ("HTTP "+r.status));
      return j;
    }finally{ clearTimeout(to); }
  }

  // ---------- Cache en localStorage ----------
  const LS_CAMION  ="surtidor.camion";
  const cache = {
    get(k, d=null){ try{ const v=localStorage.getItem(k); return v? JSON.parse(v): d; }catch{ return d; } },
    set(k, v){ localStorage.setItem(k, JSON.stringify(v)); },
    del(k){ localStorage.removeItem(k); }
  };

  // ---------- SQLite ----------
  let db=null;
  function openDb(){
    return new Promise((resolve, reject)=>{
      try{
        db = window.sqlitePlugin.openDatabase({name:'fuelapp.db', location:'default'});
        db.transaction(function(tx){
          tx.executeSql('CREATE TABLE IF NOT EXISTS pending_movements (id INTEGER PRIMARY KEY AUTOINCREMENT, data TEXT, created_at INTEGER)');
          tx.executeSql('CREATE TABLE IF NOT EXISTS cached_mediciones (id_activo INTEGER PRIMARY KEY, valor REAL, updated_at INTEGER)');
        }, err=>reject(err), ()=>resolve());
      }catch(e){ reject(e); }
    });
  }
  function txPromise(fn){ return new Promise((res,rej)=>{ db.transaction(fn, rej, res); }); }

  async function readPendingMovements(){
    const rows = [];
    await txPromise(tx=>{
      tx.executeSql('SELECT * FROM pending_movements ORDER BY created_at ASC',[],(_,rs)=>{
        for(let i=0;i<rs.rows.length;i++){ 
          rows.push({ id:rs.rows.item(i).id, data:JSON.parse(rs.rows.item(i).data), created_at:rs.rows.item(i).created_at }); 
        }
      });
    });
    return rows;
  }
  function insertPendingMovement(movement){
    return txPromise(tx=>{
      tx.executeSql('INSERT INTO pending_movements (data, created_at) VALUES (?,?)',[JSON.stringify(movement), Date.now()]);
    });
  }
  function removePendingMovement(id){
    return txPromise(tx=>{
      tx.executeSql('DELETE FROM pending_movements WHERE id=?',[id]);
    });
  }
  async function getCachedMedicion(id_activo){
    let val=null;
    await txPromise(tx=>{
      tx.executeSql('SELECT valor FROM cached_mediciones WHERE id_activo=?',[id_activo],(_,rs)=>{
        if(rs.rows.length>0) val = rs.rows.item(0).valor;
      });
    });
    return val;
  }
  async function setCachedMedicion(id_activo, valor){
    await txPromise(tx=>{
      tx.executeSql('INSERT OR REPLACE INTO cached_mediciones (id_activo, valor, updated_at) VALUES (?,?,?)',[id_activo, valor, Date.now()]);
    });
  }

  // ---------- Estado ----------
  let CSRF=null, USER_ID=null, CAMION=null, ACTIVO_SEL=null, LAST_MED_MIN=0;

  // ---------- UI ----------
  function updateLocalBalance(newBalance){
    $("#saldoActual").textContent = Number(newBalance).toFixed(2);
    UI.progress(newBalance, Number(CAMION?.capacidad_aljibe||0));
    if(CAMION?.id_activo) cache.set(`saldo_${CAMION.id_activo}`, newBalance);
  }
  function pintarCabecera(boot){
    $("#camionNombre").textContent = boot.camion.identificacion;
    $("#capacidadTotal").textContent = Number(boot.camion.capacidad_aljibe||0).toFixed(2);
    $("#capacidadTotalLabel").textContent = Number(boot.camion.capacidad_aljibe||0).toFixed(2)+" L";
    $("#saldoActual").textContent = Number(boot.saldo_actual||0).toFixed(2);
    UI.progress(Number(boot.saldo_actual||0), Number(boot.camion.capacidad_aljibe||0));
  }

  // ---------- Selección activo y medición ----------
  async function selectActivo(a){
    ACTIVO_SEL=a;
    $("#seleccionActivo").textContent = `Seleccionado: ${a.identificacion||('ID '+a.id_activo)}`;
    try{
      const m = await callApi(`/get_last_medicion.php?id_activo=${encodeURIComponent(a.id_activo)}`);
      const min = (m && m.last_value!=null) ? Number(m.last_value) : 0;
      LAST_MED_MIN = min; $("#medicionValor").min=min; $("#medicionHint").textContent = min>0?`Mínimo permitido: ${min}`:'';
      await setCachedMedicion(a.id_activo, min);
    }catch(_){
      const min = await getCachedMedicion(a.id_activo);
      if(min!=null){ 
        LAST_MED_MIN = Number(min); $("#medicionValor").min=min;
        $("#medicionHint").textContent = min>0?`Mínimo permitido: ${min} (offline)`:'';
      }
    }
  }

  // ---------- Registrar movimiento ----------
  async function registrar(){
    if(!CAMION){ UI.alert("Primero selecciona un camión","error"); return; }
    const tipo=$("#tipoMovimiento").value;
    const cantidad= Number($("#cantidad").value);
    const medValRaw=$("#medicionValor").value;
    const medVal = medValRaw!=="" ? Number(medValRaw) : null;

    if(!tipo) return UI.alert("Selecciona tipo de movimiento","error");
    if(!(cantidad>0)) return UI.alert("Cantidad inválida","error");
    if(tipo==='descarga' && (medVal==null || medVal<LAST_MED_MIN)) 
      return UI.alert(`La medición no puede ser menor a ${LAST_MED_MIN}`,"error");

    const payload = {
      action:"create",
      id_activo: CAMION.id_activo,
      tipo_movimiento: tipo,
      cantidad: cantidad,
      id_usuario: USER_ID,
      medicion_valor: (tipo==='descarga')? medVal : null,
      client_ts: Date.now()
    };

    $("#btnRegistrar").disabled=true; $("#spin").classList.remove("hidden");
    try{
      if(!navigator.onLine){
        await insertPendingMovement(payload);
        UI.alert("Offline: movimiento guardado","success");
        const bal = Number($("#saldoActual").textContent);
        updateLocalBalance(bal + (tipo==="carga"?cantidad:-cantidad));
      } else {
        const r = await callApi("/sync.php",{method:"POST", body:payload, headers:{"X-CSRF-Token":CSRF}});
        if(r.success){
          updateLocalBalance(Number(r.new_balance||0));
          UI.alert("Movimiento registrado","success");
        } else throw new Error(r.error||"Error en servidor");
      }
    }catch(e){ UI.alert(e.message||String(e),"error"); }
    finally{ $("#btnRegistrar").disabled=false; $("#spin").classList.add("hidden"); }
  }

  // ---------- Boot ----------
  async function boot(){
    UI.setConn();
    try{
      await openDb();

      const boot = await callApi("/bootstrap.php");
      if(!boot || !boot.session_ok){ location.href="index.html"; return; }
      CSRF=boot.csrf_token; USER_ID=boot.user?.id||null;

      if(boot.camion){
        CAMION=boot.camion;
        pintarCabecera({camion: boot.camion, saldo_actual: boot.saldo_actual||0});
        cache.set(LS_CAMION, boot.camion);
      }
    }catch(e){
      const cached = cache.get(LS_CAMION);
      if(cached){ CAMION=cached; pintarCabecera({camion: cached, saldo_actual: cache.get(`saldo_${cached.id_activo}`)||0}); }
      UI.alert("Modo offline","info");
    }
  }

  // ---------- Listeners ----------
  document.addEventListener('deviceready', function(){
    if(window.StatusBar){ StatusBar.backgroundColorByHexString('#000'); StatusBar.styleLightContent(); }
    boot();
  }, false);
  if(!window.cordova){ boot(); }

  $("#btnRegistrar").addEventListener("click", registrar);
  $("#btnLogout").addEventListener("click", async ()=>{ try{ await callApi("/logout.php"); }catch(_){}
    cache.del(LS_CAMION); location.href="index.html";
  });

  window.addEventListener("online", ()=>{ UI.setConn(); UI.alert("Conexión restablecida","success"); });
  window.addEventListener("offline", ()=>{ UI.setConn(); UI.alert("Modo offline activado","info"); });

})();
