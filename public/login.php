<?php
declare(strict_types=1);
require_once __DIR__ . '/../config/conexion.php';
require_once __DIR__ . '/../config/bootstrap.php';

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim((string) ($_POST['email'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');

    $stmt = $pdo->prepare(
        'SELECT id_usuario, nombre, email, password_hash, rol
           FROM usuarios WHERE email = :email AND activo = 1 LIMIT 1'
    );
    $stmt->execute(['email' => $email]);
    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($usuario && password_verify($password, $usuario['password_hash'])) {
        session_regenerate_id(true);
        $_SESSION['usuario'] = [
            'id_usuario' => (int) $usuario['id_usuario'],
            'nombre' => $usuario['nombre'],
            'rol' => $usuario['rol']
        ];
        header('Location: rutas.php');
        exit;
    }
    $error = 'Email o contraseña incorrectos.';
}
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Acceso | Ruta360</title>
    <link rel="stylesheet" href="assets/css/estilos.css">
</head>
<body>
<main>
    <h1>Acceso a Ruta360</h1>
    <?php if ($error !== ''): ?>
        <div class="aviso"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>
    <form method="post">
        <label>Email
            <input type="email" name="email" required>
        </label>
        <label>Contraseña
            <input type="password" name="password" required>
        </label>
        <button type="submit">Entrar</button>
    </form>
    <p><a href="rutas.php">Volver al listado</a></p>
</main>
</body>
</html>
