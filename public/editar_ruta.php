<?php
require_once __DIR__ . '/../config/bootstrap.php';
exigirRolWeb(['editor', 'admin']);
require_once __DIR__ . '/../app/Servicios/cliente_rutas.php';

$idRuta = filter_input(INPUT_GET, 'id_ruta', FILTER_VALIDATE_INT,
    ['options' => ['min_range' => 1]]);

if ($idRuta === false || $idRuta === null) {
    http_response_code(400);
    exit('Identificador de ruta no válido.');
}

$resultadoConsulta = obtenerRutaApi($idRuta);
if (!($resultadoConsulta['ok'] ?? false)) {
    http_response_code($resultadoConsulta['estado'] ?? 500);
    exit(htmlspecialchars($resultadoConsulta['error'] ?? 'No se ha podido cargar la ruta.'));
}

$rutaActual = $resultadoConsulta['datos'];
$valores = [
    'id_ciudad' => (string) $rutaActual['ciudad']['id_ciudad'],
    'titulo' => $rutaActual['titulo'],
    'descripcion' => $rutaActual['descripcion'],
    'duracion_minutos' => (string) $rutaActual['duracion_minutos'],
    'distancia_km' => (string) $rutaActual['distancia_km'],
    'dificultad' => $rutaActual['dificultad'] ?? ''
];
$errores = [];
$mensaje = '';

$resultadoCiudades = obtenerCiudades();
$ciudades = $resultadoCiudades['ok'] ? $resultadoCiudades['datos'] : [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    validarCsrf();

    $campos = ['id_ciudad', 'titulo', 'descripcion', 'duracion_minutos', 'distancia_km', 'dificultad'];
    foreach ($campos as $campo) {
        $valores[$campo] = trim((string) ($_POST[$campo] ?? ''));
    }

    $datosEnviados = $valores;
    if ($datosEnviados['dificultad'] === '') {
        unset($datosEnviados['dificultad']);
    }

    $resultado = actualizarRuta($idRuta, $datosEnviados);
    if ($resultado['ok'] ?? false) {
        header('Location: ver_ruta.php?id_ruta=' . $idRuta . '&actualizada=1');
        exit;
    }

    $mensaje = $resultado['error'] ?? 'No se ha podido actualizar la ruta.';
    $errores = $resultado['errores'] ?? [];
}
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Editar ruta | Ruta360</title>
    <link rel="stylesheet" href="assets/css/estilos.css">
</head>
<body>
<main>
    <h1>Editar ruta</h1>

    <?php if ($mensaje !== ''): ?>
        <div class="aviso"><?= htmlspecialchars($mensaje) ?></div>
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

        <button type="submit">Guardar cambios</button>
    </form>

    <p><a href="ver_ruta.php?id_ruta=<?= $idRuta ?>">Cancelar y volver a la ficha</a></p>
</main>
</body>
</html>
