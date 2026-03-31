# Validaciones de Backend - Sistema de Permisos

## Resumen de Cambios
La aplicación ahora implementa un sistema de permisos diferenciado:
- **Acceso completo** (carga Y descarga): `surtidor` y `control combustible`
- **Acceso limitado** (solo carga): Todos los demás roles
- **Checklist**: Accesible para todos los usuarios autenticados

## Archivos Backend que Requieren Actualización

### 1. `sync.php` - Validación de movimientos
**Ubicación**: `/crm/api/sync.php`

**Cambios necesarios**:
```php
// Verificar permisos para descarga
if ($payload['tipo_movimiento'] === 'descarga') {
    $user_tipo = strtolower(trim($_SESSION['user']['tipo']));
    $allowed_roles = ['surtidor', 'control combustible'];

    if (!in_array($user_tipo, $allowed_roles)) {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'error' => 'No tienes permisos para realizar descargas. Solo usuarios de tipo "surtidor" o "control combustible" pueden hacer descargas.'
        ]);
        exit;
    }
}
```

### 2. `login.php` - Mantener acceso amplio
**Ubicación**: `/crm/api/login.php`

**Cambios necesarios**:
- **ANTES**: Solo permitía roles específicos (`surtidor`, `gerencia`, `control gps`, `control combustible`)
- **AHORA**: Permitir login a TODOS los roles válidos en la base de datos
- Los permisos de descarga se controlan en `sync.php`, no en login

```php
// Permitir login a cualquier usuario autenticado
// NO restringir por tipo de usuario en el login
// La restricción se hace a nivel de operaciones (sync.php)
```

### 3. `guardar_checklist.php` - NUEVO endpoint
**Ubicación**: `/crm/api/guardar_checklist.php`

**Crear nuevo archivo**:
```php
<?php
require_once 'config.php';
require_once 'session_check.php'; // Verificar sesión activa

header('Content-Type: application/json');

// Verificar método
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Método no permitido']);
    exit;
}

// Obtener datos
$data = json_decode(file_get_contents('php://input'), true);

// Validar datos requeridos
if (!isset($data['id_activo']) || !isset($data['checks']) || !isset($data['id_usuario'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Datos incompletos']);
    exit;
}

// Preparar datos para insertar
$id_activo = intval($data['id_activo']);
$id_usuario = intval($data['id_usuario']);
$checks = json_encode($data['checks']);
$porcentaje_aprobacion = floatval($data['porcentaje_aprobacion'] ?? 0);
$medicion_valor = isset($data['medicion_valor']) ? floatval($data['medicion_valor']) : null;
$observaciones = $data['observaciones'] ?? null;
$fecha = date('Y-m-d H:i:s');

try {
    $conn = getDBConnection(); // Tu función de conexión a DB

    // Insertar checklist
    $stmt = $conn->prepare("
        INSERT INTO checklists
        (id_activo, id_usuario, fecha, checks, porcentaje_aprobacion, medicion_valor, observaciones)
        VALUES (?, ?, ?, ?, ?, ?, ?)
    ");

    $stmt->bind_param(
        'iissdds',
        $id_activo,
        $id_usuario,
        $fecha,
        $checks,
        $porcentaje_aprobacion,
        $medicion_valor,
        $observaciones
    );

    if ($stmt->execute()) {
        echo json_encode([
            'success' => true,
            'id' => $stmt->insert_id,
            'message' => 'Checklist guardado correctamente'
        ]);
    } else {
        throw new Exception('Error al guardar checklist');
    }

    $stmt->close();
    $conn->close();

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>
```

### 4. Tabla de base de datos para checklists
**SQL para crear la tabla**:

```sql
CREATE TABLE IF NOT EXISTS `checklists` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `id_activo` INT(11) NOT NULL,
  `id_usuario` INT(11) NOT NULL,
  `fecha` DATETIME NOT NULL,
  `checks` JSON NOT NULL COMMENT 'Objeto JSON con los items del checklist',
  `porcentaje_aprobacion` DECIMAL(5,2) DEFAULT 0,
  `medicion_valor` DECIMAL(10,2) DEFAULT NULL COMMENT 'Kilometraje u horómetro al momento del checklist',
  `observaciones` TEXT DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_activo` (`id_activo`),
  KEY `idx_usuario` (`id_usuario`),
  KEY `idx_fecha` (`fecha`),
  CONSTRAINT `fk_checklist_activo` FOREIGN KEY (`id_activo`) REFERENCES `activos` (`id_activo`) ON DELETE CASCADE,
  CONSTRAINT `fk_checklist_usuario` FOREIGN KEY (`id_usuario`) REFERENCES `usuarios` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

## Ejemplo de estructura JSON para `checks`:
```json
{
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
}
```

## Testing

### Casos de prueba recomendados:

1. **Usuario surtidor**:
   - ✅ Debe poder hacer carga
   - ✅ Debe poder hacer descarga
   - ✅ Debe poder ver checklist
   - ✅ Debe poder guardar checklist

2. **Usuario control combustible**:
   - ✅ Debe poder hacer carga
   - ✅ Debe poder hacer descarga
   - ✅ Debe poder ver checklist
   - ✅ Debe poder guardar checklist

3. **Cualquier otro usuario autenticado** (gerencia, operador, etc.):
   - ✅ Debe poder hacer login
   - ✅ Debe poder hacer carga
   - ❌ NO debe ver opción de descarga en UI
   - ❌ NO debe poder hacer descarga (validado en backend)
   - ✅ Debe poder ver checklist
   - ✅ Debe poder guardar checklist

4. **Intentos de bypass**:
   - ❌ Usuario sin permiso intenta POST a sync.php con tipo_movimiento=descarga
   - Resultado esperado: Error 403 - Forbidden

## Notas de Seguridad

- Las validaciones de frontend (ocultar opción descarga) son para UX
- Las validaciones de backend son OBLIGATORIAS para seguridad
- Nunca confiar solo en validaciones de frontend
- Siempre validar tipo de usuario en el servidor antes de permitir operaciones sensibles
