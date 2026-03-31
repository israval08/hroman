# Cambios Implementados - Sistema de Permisos y Checklist

## 📋 Resumen General

Se ha implementado un sistema de permisos diferenciado y un nuevo módulo de checklist para la aplicación de control de combustible H Roman.

---

## 🔐 Sistema de Permisos

### Roles con Acceso Completo (Carga + Descarga)
- ✅ **surtidor**
- ✅ **control combustible**

### Roles con Acceso Limitado (Solo Carga)
- ✅ **Todos los demás roles** que puedan autenticarse con RUT y contraseña

### Acceso al Checklist
- ✅ **Todos los usuarios autenticados** pueden acceder y usar el checklist

---

## 📝 Archivos Modificados

### 1. **www/index.html** - Pantalla Principal
**Cambios realizados**:

#### a) Variable de estado para permisos
```javascript
let state = {
    // ... otros campos
    canDescarga: false // Control de permiso para descarga
};
```

#### b) Detección de permisos en login
```javascript
async function handleLogin(e) {
    // ...
    if (data.success) {
        state.user = data.user;
        state.csrf = data.csrf_token;

        // Determinar permisos de descarga
        const userType = (data.user.tipo || '').toLowerCase().trim();
        state.canDescarga = (userType === 'surtidor' || userType === 'control combustible');

        await saveSession(data.user, data.csrf_token);
        // ...
    }
}
```

#### c) Detección de permisos al cargar sesión guardada
```javascript
async function loadSession() {
    // ...
    const userType = (data.user.tipo || '').toLowerCase().trim();
    state.canDescarga = (userType === 'surtidor' || userType === 'control combustible');
    // ...
}
```

#### d) Nueva función para actualizar UI según permisos
```javascript
function updatePermisosUI() {
    const opcionDescarga = document.getElementById('opcionDescarga');
    const tipoMovimiento = document.getElementById('tipoMovimiento');

    if (!state.canDescarga) {
        // Ocultar opción de descarga para usuarios sin permiso
        if (opcionDescarga) opcionDescarga.style.display = 'none';

        // Si estaba seleccionada descarga, resetear a vacío
        if (tipoMovimiento && tipoMovimiento.value === 'descarga') {
            tipoMovimiento.value = '';
            toggleCampos();
        }
    } else {
        // Mostrar opción de descarga para surtidor y control combustible
        if (opcionDescarga) opcionDescarga.style.display = '';
    }
}
```

#### e) ID agregado a la opción descarga
```html
<option value="descarga" id="opcionDescarga">Descarga</option>
```

#### f) Navegación actualizada
```html
<!-- Agregado botón de Checklist en navegación inferior -->
<div class="nav-item" onclick="goToChecklist()"><span>✅</span>Checklist</div>

<!-- Agregado botón en pantalla de selección de camiones -->
<button class="btn btn-primary" onclick="goToChecklist()" style="margin-top: 10px;">✅ Ir a Checklist</button>
```

#### g) Nueva función de navegación
```javascript
function goToChecklist() { window.location.href = 'checklist.html'; }
```

---

### 2. **www/registros.html** - Pantalla de Historial
**Cambios realizados**:

#### a) Navegación actualizada
```html
<div class="bottom-nav">
    <div class="nav-item" onclick="goToIndex()">
        <span>⛽</span>Registro
    </div>
    <div class="nav-item active">
        <span>📋</span>Historial
    </div>
    <div class="nav-item" onclick="goToChecklist()">
        <span>✅</span>Checklist
    </div>
    <div class="nav-item" onclick="goToIndex()">
        <span>🚛</span>Camiones
    </div>
    <div class="nav-item" onclick="logout()">
        <span>🚪</span>Salir
    </div>
</div>
```

#### b) Nueva función de navegación
```javascript
function goToChecklist() { window.location.href = 'checklist.html'; }
```

---

### 3. **www/checklist.html** - NUEVO ARCHIVO
**Archivo creado**: Pantalla completa de checklist de activos

**Características**:
- ✅ Búsqueda de activos desde IndexedDB local
- ✅ 10 items de verificación predefinidos:
  1. 📄 Documentos al día
  2. 🛞 Estado de neumáticos
  3. 💡 Sistema de luces
  4. 🛑 Sistema de frenos
  5. 🛢️ Nivel de aceite motor
  6. ❄️ Nivel de refrigerante
  7. 🔋 Estado de batería
  8. 🚗 Estado general de carrocería
  9. 🧹 Limpieza interior y exterior
  10. 🔧 Kit de herramientas completo
- ✅ Campo para kilometraje/horómetro actual
- ✅ Campo de observaciones
- ✅ Cálculo automático de porcentaje de aprobación
- ✅ Guardado local en IndexedDB
- ✅ Sincronización con servidor (cuando hay conexión)
- ✅ Navegación integrada con el resto de la app
- ✅ Mismo diseño visual que el resto de la aplicación

**Estructura de datos guardada**:
```javascript
{
    id_activo: 123,
    activo_identificacion: "AA-BB-12",
    id_usuario: 456,
    usuario_nombre: "Juan Pérez",
    timestamp: 1234567890,
    fecha: "2024-01-15T10:30:00.000Z",
    checks: {
        documentos: true,
        neumaticos: true,
        luces: false,
        // ... etc
    },
    porcentaje_aprobacion: 80,
    medicion_valor: 15000.5,
    observaciones: "Todo ok",
    synced: false
}
```

---

## 🗄️ Cambios en IndexedDB

### Nueva ObjectStore: `checklists`
```javascript
database.createObjectStore('checklists', { keyPath: 'id', autoIncrement: true });
checklistStore.createIndex('timestamp', 'timestamp');
checklistStore.createIndex('id_activo', 'id_activo');
```

**Nota**: La versión de DB se mantiene en 6. Si fuera necesario, incrementar a 7 para forzar upgrade.

---

## 🔧 Validaciones Backend Requeridas

Ver archivo **BACKEND_VALIDATIONS.md** para:
- ✅ Validación de permisos en `sync.php`
- ✅ Actualización de `login.php` para permitir todos los roles
- ✅ Creación de `guardar_checklist.php`
- ✅ Script SQL para tabla `checklists`

---

## 🎨 Experiencia de Usuario

### Para Usuario "Surtidor" o "Control Combustible":
1. Login normal
2. Selecciona camión
3. Ve opción **Carga** Y **Descarga** en el formulario
4. Puede usar el checklist desde el menú inferior
5. Puede ver historial

### Para Cualquier Otro Usuario:
1. Login normal (ahora permitido para todos los roles)
2. Selecciona camión
3. Ve SOLO opción **Carga** en el formulario
4. No ve la opción "Descarga" (está oculta)
5. Puede usar el checklist desde el menú inferior
6. Puede ver historial

### Para Todos los Usuarios:
1. Botón "✅ Checklist" visible en navegación inferior
2. Botón "✅ Ir a Checklist" en pantalla de selección de camiones
3. Acceso completo al módulo de checklist
4. Guardado local y sincronización automática

---

## 🧪 Testing Recomendado

### Pruebas de Frontend:
1. ✅ Login con usuario surtidor → debe ver opción descarga
2. ✅ Login con usuario control combustible → debe ver opción descarga
3. ✅ Login con usuario gerencia/otro → NO debe ver opción descarga
4. ✅ Todos los usuarios deben poder acceder a checklist
5. ✅ Navegación entre pantallas debe funcionar correctamente

### Pruebas de Backend:
1. ✅ Usuario surtidor puede hacer POST descarga a sync.php
2. ✅ Usuario control combustible puede hacer POST descarga a sync.php
3. ❌ Usuario gerencia/otro intenta POST descarga → debe recibir 403 Forbidden
4. ✅ Todos los usuarios pueden hacer POST carga a sync.php
5. ✅ Todos los usuarios pueden guardar checklist

---

## 📱 Navegación Actualizada

```
┌─────────────────────────────────────┐
│         Pantalla Principal          │
│  [⛽ Registro] [📋 Historial]       │
│  [✅ Checklist] [☁️ Sync] [🚪 Salir] │
└─────────────────────────────────────┘
              ↓ ↑
┌─────────────────────────────────────┐
│      Pantalla de Historial          │
│  [⛽ Registro] [📋 Historial]       │
│  [✅ Checklist] [🚛 Camiones]       │
│  [🚪 Salir]                         │
└─────────────────────────────────────┘
              ↓ ↑
┌─────────────────────────────────────┐
│      Pantalla de Checklist          │
│  [⛽ Registro] [📋 Historial]       │
│  [✅ Checklist] [🏠 Inicio]         │
│  [🚪 Salir]                         │
└─────────────────────────────────────┘
```

---

## ⚠️ Notas Importantes

1. **Seguridad**: Las validaciones de frontend son para UX. Las validaciones de backend son OBLIGATORIAS.

2. **Sincronización**: Los checklists se guardan localmente y se sincronizan cuando hay conexión. El campo `synced` indica si ya fue enviado al servidor.

3. **Compatibilidad**: Los cambios son retrocompatibles. Usuarios existentes mantendrán sus datos.

4. **Versión**: Actualizar versión a **v1.2** en todos los archivos HTML.

5. **Backend**: Asegurarse de implementar TODAS las validaciones del archivo BACKEND_VALIDATIONS.md

---

## 🚀 Próximos Pasos

1. ✅ Implementar validaciones backend (ver BACKEND_VALIDATIONS.md)
2. ✅ Crear tabla `checklists` en base de datos
3. ✅ Probar en ambiente de desarrollo
4. ✅ Probar en diferentes roles de usuario
5. ✅ Compilar APK y distribuir

---

**Desarrollado por**: Israel Valenzuela Lavín
**Fecha**: Enero 2026
**Versión**: 1.2
