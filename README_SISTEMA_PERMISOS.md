# 🎯 Sistema de Permisos y Checklist - Guía de Uso

## ✨ Cambios Implementados

Tu aplicación ahora tiene:

### 1. **Sistema de Permisos Diferenciado** 🔐
- **Surtidor** y **Control Combustible**: Pueden hacer CARGA y DESCARGA
- **Todos los demás usuarios**: Solo pueden hacer CARGA

### 2. **Módulo de Checklist de Activos** ✅
- Todos los usuarios pueden acceder
- Inspección completa de vehículos/activos
- 10 puntos de verificación estándar
- Guardado local + sincronización con servidor

---

## 📱 Cómo Usar la Aplicación

### Para Usuario SURTIDOR o CONTROL COMBUSTIBLE:
```
1. Login con RUT y contraseña
2. Selecciona camión surtidor
3. En "Tipo de movimiento" verás:
   ✅ Carga
   ✅ Descarga (visible para ti)
4. Acceso completo a todas las funciones
5. Acceso al checklist desde el menú inferior
```

### Para CUALQUIER OTRO USUARIO:
```
1. Login con RUT y contraseña (ahora permitido)
2. Selecciona camión surtidor
3. En "Tipo de movimiento" solo verás:
   ✅ Carga
   ❌ Descarga (oculta automáticamente)
4. Puedes registrar cargas en estaciones
5. Acceso al checklist desde el menú inferior
```

### Módulo de Checklist (TODOS):
```
1. Desde cualquier pantalla, toca "✅ Checklist"
2. Busca el activo (vehículo/máquina)
3. Marca los items verificados
4. Ingresa kilometraje/horómetro
5. Agrega observaciones (opcional)
6. Guarda → Se almacena local y se sincroniza
```

---

## 🗂️ Archivos Modificados

### Frontend (App Cordova):
- ✅ `www/index.html` - Lógica de permisos + navegación
- ✅ `www/registros.html` - Navegación actualizada
- ✅ `www/checklist.html` - **NUEVO** módulo completo

### Backend (Por Implementar):
- 📋 `api/sync.php` - Validar permisos de descarga
- 📋 `api/login.php` - Permitir todos los roles
- 📋 `api/guardar_checklist.php` - **NUEVO** endpoint
- 📋 Base de datos - Crear tabla `checklists`

---

## 🔧 Pasos Siguientes (Backend)

### 1. Actualizar `sync.php`
Agregar al inicio de la función que procesa movimientos:

```php
// Verificar permisos para descarga
if ($payload['tipo_movimiento'] === 'descarga') {
    $user_tipo = strtolower(trim($_SESSION['user']['tipo']));
    $allowed_roles = ['surtidor', 'control combustible'];

    if (!in_array($user_tipo, $allowed_roles)) {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'error' => 'No tienes permisos para realizar descargas.'
        ]);
        exit;
    }
}
```

### 2. Actualizar `login.php`
Permitir login a TODOS los usuarios (eliminar restricción de roles):

```php
// ANTES: Solo ciertos roles
// AHORA: Cualquier usuario autenticado puede logearse
// Los permisos se controlan a nivel de operación, no de login
```

### 3. Crear `guardar_checklist.php`
Ver archivo **BACKEND_VALIDATIONS.md** para código completo del endpoint.

### 4. Crear tabla en base de datos
```sql
CREATE TABLE IF NOT EXISTS `checklists` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `id_activo` INT(11) NOT NULL,
  `id_usuario` INT(11) NOT NULL,
  `fecha` DATETIME NOT NULL,
  `checks` JSON NOT NULL,
  `porcentaje_aprobacion` DECIMAL(5,2) DEFAULT 0,
  `medicion_valor` DECIMAL(10,2) DEFAULT NULL,
  `observaciones` TEXT DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_activo` (`id_activo`),
  KEY `idx_usuario` (`id_usuario`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

---

## 🧪 Testing

### Prueba 1: Usuario Surtidor
```
✅ Login exitoso
✅ Ve opción "Descarga" en formulario
✅ Puede registrar cargas
✅ Puede registrar descargas
✅ Puede acceder a checklist
```

### Prueba 2: Usuario Control Combustible
```
✅ Login exitoso
✅ Ve opción "Descarga" en formulario
✅ Puede registrar cargas
✅ Puede registrar descargas
✅ Puede acceder a checklist
```

### Prueba 3: Usuario Operador/Gerencia/Otro
```
✅ Login exitoso (nuevo)
❌ NO ve opción "Descarga" en formulario
✅ Puede registrar cargas
❌ NO puede hacer descargas (bloqueado en backend si intenta)
✅ Puede acceder a checklist
```

### Prueba 4: Checklist (Todos)
```
✅ Búsqueda de activos funciona
✅ Checkboxes se marcan correctamente
✅ Porcentaje se calcula automáticamente
✅ Se guarda localmente
✅ Se sincroniza con servidor
```

---

## 📊 Estructura de Datos del Checklist

### Items de Verificación:
1. **Documentos** - Permiso circulación, seguro, revisión técnica
2. **Neumáticos** - Presión, dibujo, desgaste
3. **Luces** - Delanteras, traseras, direccionales
4. **Frenos** - Pastillas, líquido
5. **Aceite** - Nivel de aceite motor
6. **Refrigerante** - Nivel de refrigerante
7. **Batería** - Estado de batería
8. **Carrocería** - Estado general
9. **Limpieza** - Interior y exterior
10. **Herramientas** - Kit completo

### Datos Guardados:
```json
{
  "id_activo": 123,
  "activo_identificacion": "AA-BB-12",
  "id_usuario": 456,
  "usuario_nombre": "Juan Pérez",
  "timestamp": 1705324800000,
  "fecha": "2024-01-15T10:00:00.000Z",
  "checks": {
    "documentos": true,
    "neumaticos": true,
    "luces": false,
    "frenos": true,
    "aceite": true,
    "refrigerante": true,
    "bateria": false,
    "carroceria": true,
    "limpieza": true,
    "herramientas": false
  },
  "porcentaje_aprobacion": 80,
  "medicion_valor": 15000.5,
  "observaciones": "Requiere cambio de luces y batería",
  "synced": false
}
```

---

## 🛡️ Seguridad

### Frontend (UX):
- Oculta opciones según permisos
- Valida datos antes de enviar
- Muestra mensajes de error claros

### Backend (Crítico):
- ✅ NUNCA confiar solo en el frontend
- ✅ SIEMPRE validar permisos en servidor
- ✅ Verificar tipo de usuario en cada operación
- ✅ Retornar errores HTTP apropiados (403 Forbidden)

### Ejemplo de intento de bypass:
```javascript
// Usuario sin permiso intenta enviar descarga directamente
fetch('api/sync.php', {
  method: 'POST',
  body: JSON.stringify({ tipo_movimiento: 'descarga', ... })
})

// Respuesta del servidor:
// HTTP 403 Forbidden
// { "success": false, "error": "No tienes permisos..." }
```

---

## 📖 Documentación Completa

- **BACKEND_VALIDATIONS.md** - Especificaciones técnicas backend
- **CAMBIOS_IMPLEMENTADOS.md** - Listado detallado de cambios
- **Este archivo** - Guía de uso general

---

## 🚀 Compilar y Distribuir

### Compilar APK:
```bash
cd C:\cordova
cordova build android
```

### APK generado en:
```
platforms/android/app/build/outputs/apk/debug/app-debug.apk
```

### Para release:
```bash
cordova build android --release
```

---

## 💡 Tips

1. **Prueba siempre** con diferentes tipos de usuarios
2. **Sincroniza** periódicamente para no perder datos
3. **Los checklists** se guardan localmente si no hay internet
4. **La validación backend** es OBLIGATORIA para seguridad
5. **Versión actual**: v1.2-permisos

---

## 📞 Soporte

Cualquier duda o problema:
- Revisa los archivos de documentación
- Verifica logs del navegador (F12 → Console)
- Verifica logs del servidor PHP
- Verifica tabla de base de datos

---

**Desarrollado por**: Israel Valenzuela Lavín
**Para**: Constructora H Roman
**Versión**: 1.2-permisos
**Fecha**: Enero 2026
