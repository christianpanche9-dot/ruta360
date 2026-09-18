<?php
require_once __DIR__ . '/../config/conexion.php';
require_once __DIR__ . '/../app/Servicios/meteorologia.php';

$idCiudad = filter_input(INPUT_GET, 'id_ciudad', FILTER_VALIDATE_INT);

if (!$idCiudad || $idCiudad < 1) {
    exit('La ciudad seleccionada no es válida.');
}

$sql = 'SELECT id_ciudad, nombre, pais, latitud, longitud
          FROM ciudades
         WHERE id_ciudad = :id_ciudad AND activa = 1';

$consulta = $pdo->prepare($sql);
$consulta->execute(['id_ciudad' => $idCiudad]);
$ciudad = $consulta->fetch();

if (!$ciudad) {
    exit('La ciudad no existe o no está disponible.');
}

$resultado = obtenerTiempoActual(
    (float)$ciudad['latitud'],
    (float)$ciudad['longitud']
);

$temperatura = null;
$viento = null;

if ($resultado['ok']) {
    $actual = $resultado['datos']['current'] ?? [];
    $temperatura = $actual['temperature_2m'] ?? null;
    $viento = $actual['wind_speed_10m'] ?? null;
}
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Tiempo en <?= htmlspecialchars($ciudad['nombre']) ?></title>
    <link rel="stylesheet" href="assets/css/estilos.css">
</head>
<body>
<main>
    <a href="index.php">&larr; Elegir otra ciudad</a>
    <h1>
        Tiempo en <?= htmlspecialchars($ciudad['nombre']) ?>,
        <?= htmlspecialchars($ciudad['pais']) ?>
    </h1>

    <?php if (!$resultado['ok']): ?>
        <p class="error"><?= htmlspecialchars($resultado['error']) ?></p>
    <?php else: ?>
        <p>Temperatura: <?= htmlspecialchars((string)$temperatura) ?> °C</p>
        <p>Viento: <?= htmlspecialchars((string)$viento) ?> km/h</p>
    <?php endif; ?>
</main>
</body>
</html>
