<?php
session_start([
    'cookie_httponly' => true,
    'cookie_secure' => isset($_SERVER['HTTPS']),
    'cookie_samesite' => 'Strict',
]);
require_once 'config/db.php';

// Función para validar el RUT
function validarRUT($rut) {
    return preg_match('/^\d{1,2}\.\d{3}\.\d{3}-[kK0-9]$/', $rut);
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $rut = $_POST['rut'];
    $password = $_POST['password'];

    // Eliminar puntos y guión para la validación
    $rutSinFormato = str_replace(['.', '-'], '', $rut);

    if (!validarRUT($rut)) {
        $error = "El RUT ingresado no tiene un formato válido.";
    } else {
        $stmt = $conn->prepare("SELECT id, rut, clave, tipo, nombre FROM usuarios WHERE rut = ?");
        $stmt->bind_param("s", $rut);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows === 1) {
            $user = $result->fetch_assoc();
            if (password_verify($password, $user['clave'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_tipo'] = $user['tipo'];
                $_SESSION['user_nombre'] = $user['nombre'];
                session_regenerate_id(true);

                // Registrar login en logs
                $stmt = $conn->prepare("INSERT INTO logs (usuario_id, accion) VALUES (?, 'Login exitoso')");
                $stmt->bind_param("i", $user['id']);
                $stmt->execute();

                // Redirigir según el tipo de usuario
                switch ($user['tipo']) {
                    case 'superusuario':
                        header("Location: super.php");
                        break;
                    case 'supervisor':
                        header("Location: supervisor.php");
                        break;
                    case 'control':
                        header("Location: control.php");
                        break;
                    default:
                        header("Location: index.php");
                        break;
                }
                exit();
            } else {
                $error = "Credenciales inválidas.";
                $_SESSION['login_attempts'] = ($_SESSION['login_attempts'] ?? 0) + 1;
            }
        } else {
            $error = "Credenciales inválidas.";
            $_SESSION['login_attempts'] = ($_SESSION['login_attempts'] ?? 0) + 1;
        }
    }
}
?>

<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Iniciar Sesión</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="style.css">
</head>
<body>
    <!-- Incluir la barra de navegación -->
    <?php include 'navbar.php'; ?>

    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">Iniciar Sesión</div>
                    <div class="card-body">
                        <?php if (isset($error)): ?>
                            <div class="alert alert-danger"><?php echo $error; ?></div>
                        <?php endif; ?>

                        <form method="POST">
                            <div class="mb-3">
                                <label>RUT:</label>
                                <input type="text" id="rut" name="rut" class="form-control" required maxlength="12">
                            </div>
                            <div class="mb-3">
                                <label>Contraseña:</label>
                                <input type="password" name="password" class="form-control" required>
                            </div>
                            <button type="submit" class="btn btn-primary">Ingresar</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Incluir el pie de página -->
    <?php include 'footer.php'; ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        const rutInput = document.getElementById('rut');

        // Formatear el RUT mientras se escribe
        rutInput.addEventListener('input', function (e) {
            let value = e.target.value.replace(/\./g, '').replace(/-/g, ''); // Quitar puntos y guión
            if (value.length > 1) {
                const rut = value.slice(0, -1); // Dígitos
                const dv = value.slice(-1); // Dígito verificador

                // Agregar puntos cada 3 dígitos y el guión antes del dígito verificador
                const formattedRut = rut.replace(/\B(?=(\d{3})+(?!\d))/g, '.') + '-' + dv;
                e.target.value = formattedRut;
            }
        });
    </script>
</body>
</html>
