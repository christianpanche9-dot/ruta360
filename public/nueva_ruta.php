<?php
require_once __DIR__ . '/../config/bootstrap.php';
exigirRolWeb(['editor', 'admin']);
require_once __DIR__ . '/../app/Servicios/cliente_rutas.php';

$valores = [
    'id_ciudad' => '',
    'titulo' => '',
    'descripcion' => '',
    'duracion_minutos' => '',
    'distancia_km' => '',
    'dificultad' => ''
];
$errores = [];
$mensaje = '';
$idRutaCreada = null;

$resultadoCiudades = obtenerCiudades();
$ciudades = $resultadoCiudades['ok'] ? $resultadoCiudades['datos'] : [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validarCsrf();

    foreach ($valores as $campo => $valor) {
        $valores[$campo] = trim((string) ($_POST[$campo] ?? ''));
    }

    $datosEnviados = $valores;
    if ($datosEnviados['dificultad'] === '') {
        unset($datosEnviados['dificultad']);
    }

    $resultado = crearRuta($datosEnviados);
    if ($resultado['ok']) {
        $mensaje = 'Ruta creada correctamente.';
        $idRutaCreada = (int) $resultado['datos']['id_ruta'];
        $valores = array_fill_keys(array_keys($valores), '');
    } else {
        $mensaje = $resultado['error'] ?? 'No se ha podido crear la ruta.';
        $errores = $resultado['errores'] ?? [];
    }
}
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Nueva ruta | Ruta360</title>
    <link rel="stylesheet" href="assets/css/estilos.css">
</head>
<body>
<main>
    <h1>Nueva ruta</h1>

    <?php if ($mensaje !== ''): ?>
        <div class="aviso"><?= htmlspecialchars($mensaje) ?></div>
    <?php endif; ?>

    <?php if ($idRutaCreada !== null): ?>
        <p><a href="ver_ruta.php?id_ruta=<?= $idRutaCreada ?>">Ver la ruta creada</a></p>
    <?php endif; ?>

    <?php if (!$resultadoCiudades['ok']): ?>
        <p class="error"><?= htmlspecialchars($resultadoCiudades['error']) ?></p>
    <?php endif; ?>

    <form method="post">
        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(tokenCsrf()) ?>">

        <label>Ciudad
            <select name="id_ciudad" required>
                <option value="">Selecciona una ciudad</option>
                <?php foreach ($ciudades as $ciudad): ?>
                    <option value="<?= (int) $ciudad['id_ciudad'] ?>"
                        <?= (string) $ciudad['id_ciudad'] === $valores['id_ciudad'] ? 'selected' : '' ?>>
                        <?= htmlspecialchars($ciudad['nombre']) ?>, <?= htmlspecialchars($ciudad['pais']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>
        <small><?= htmlspecialchars($errores['id_ciudad'] ?? '') ?></small>

        <label>Título
            <input type="text" name="titulo" minlength="5" maxlength="120"
                   required value="<?= htmlspecialchars($valores['titulo']) ?>">
        </label>
        <small><?= htmlspecialchars($errores['titulo'] ?? '') ?></small>

        <label>Descripción
            <textarea name="descripcion" minlength="10" maxlength="1000"
                required><?= htmlspecialchars($valores['descripcion']) ?></textarea>
        </label>
        <small><?= htmlspecialchars($errores['descripcion'] ?? '') ?></small>

        <label>Duración (minutos)
            <input type="number" name="duracion_minutos" min="15" max="1440"
                   required value="<?= htmlspecialchars($valores['duracion_minutos']) ?>">
        </label>
        <small><?= htmlspecialchars($errores['duracion_minutos'] ?? '') ?></small>

        <label>Distancia (km)
            <input type="number" name="distancia_km" min="0.1" max="1000" step="0.1"
                   required value="<?= htmlspecialchars($valores['distancia_km']) ?>">
        </label>
        <small><?= htmlspecialchars($errores['distancia_km'] ?? '') ?></small>

        <label>Dificultad
            <select name="dificultad">
                <option value="" <?= $valores['dificultad'] === '' ? 'selected' : '' ?>>Sin especificar</option>
                <option value="facil" <?= $valores['dificultad'] === 'facil' ? 'selected' : '' ?>>Fácil</option>
                <option value="media" <?= $valores['dificultad'] === 'media' ? 'selected' : '' ?>>Media</option>
                <option value="alta" <?= $valores['dificultad'] === 'alta' ? 'selected' : '' ?>>Alta</option>
            </select>
        </label>
        <small><?= htmlspecialchars($errores['dificultad'] ?? '') ?></small>

        <button type="submit">Crear ruta</button>
    </form>

    <p><a href="rutas.php">Volver al listado</a></p>
</main>
</body>
</html>
