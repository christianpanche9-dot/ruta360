<?php
require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/../app/Repositorios/RepositorioRuta.php';
require_once __DIR__ . '/../app/Servicios/PlanificadorRuta.php';
require_once __DIR__ . '/../app/Servicios/AdaptadorMeteorologia.php';
require_once __DIR__ . '/../app/Servicios/AdaptadorTransporte.php';
require_once __DIR__ . '/../app/Servicios/AdaptadorDistanciasSoap.php';
require_once __DIR__ . '/../app/Servicios/vista_externos.php';

$idRuta = filter_input(INPUT_GET, 'id_ruta', FILTER_VALIDATE_INT,
    ['options' => ['min_range' => 1]]);

$ruta = null;
$puntos = [];
$externos = [];
$errorCarga = null;

if ($idRuta === false || $idRuta === null) {
    $errorCarga = 'Selecciona una ruta válida.';
} else {
    $config = require __DIR__ . '/../config/servicios.php';
    $proveedores = [
        new AdaptadorMeteorologia($config['meteorologia']),
        new AdaptadorTransporte($config['transporte']),
        new AdaptadorDistanciasSoap($config['distancias_soap']['wsdl'])
    ];
    $planificador = new PlanificadorRuta(new RepositorioRuta(), $proveedores);

    try {
        $ficha = $planificador->preparar($idRuta);
        $ruta = $ficha['ruta'];
        $puntos = $ruta['puntos_interes'] ?? [];
        $externos = $ficha['externos'];
    } catch (RuntimeException $e) {
        $errorCarga = 'La ruta no existe o no está disponible.';
    }
}

$rolActual = $_SESSION['usuario']['rol'] ?? null;
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Detalle de ruta | Ruta360</title>
    <link rel="stylesheet" href="assets/css/estilos.css">
</head>
<body>
<main>
<?php if (isset($_GET['actualizada'])): ?>
    <div class="aviso">Ruta actualizada correctamente.</div>
<?php endif; ?>

<?php if ($errorCarga !== null): ?>
    <h1>No se ha podido cargar la ruta</h1>
    <p class="error">
        <?= htmlspecialchars($errorCarga) ?>
    </p>
<?php else: ?>
    <h1><?= htmlspecialchars($ruta['titulo']) ?></h1>
    <p>
        <?= htmlspecialchars($ruta['ciudad']['nombre']) ?>,
        <?= htmlspecialchars($ruta['ciudad']['pais']) ?>
    </p>
    <p><?= htmlspecialchars($ruta['descripcion']) ?></p>
    <p>Duración: <?= (int)$ruta['duracion_minutos'] ?> minutos</p>
    <p>Distancia: <?= (float)$ruta['distancia_km'] ?> km</p>

    <?php if (isset($ruta['dificultad'])): ?>
        <p>Dificultad: <?= htmlspecialchars((string)$ruta['dificultad']) ?></p>
    <?php endif; ?>

    <?php if (isset($ruta['numero_puntos'])): ?>
        <p>Lugares incluidos: <?= (int) $ruta['numero_puntos'] ?></p>
    <?php endif; ?>

    <?php foreach ($externos as $resultado): ?>
        <section class="servicio-externo servicio-<?= htmlspecialchars($resultado->proveedor) ?>">
            <h2><?= htmlspecialchars(nombreLegibleProveedor($resultado->proveedor)) ?></h2>
            <?php if ($resultado->disponible): ?>
                <?php mostrarDatosExterno($resultado->proveedor, $resultado->datos); ?>
                <?php if ($resultado->origen !== 'servicio'): ?>
                    <p class="aviso-origen">Origen: <?= htmlspecialchars($resultado->origen) ?></p>
                <?php endif; ?>
            <?php else: ?>
                <p class="aviso"><?= htmlspecialchars($resultado->aviso ?? 'No disponible') ?></p>
            <?php endif; ?>
        </section>
    <?php endforeach; ?>

    <h2>Puntos de interés</h2>
    <?php if (!$puntos): ?>
        <p>Esta ruta todavía no tiene puntos de interés.</p>
    <?php else: ?>
        <ol>
        <?php foreach ($puntos as $punto): ?>
            <li>
                <strong><?= htmlspecialchars($punto['nombre']) ?></strong>
                &mdash; <?= htmlspecialchars($punto['descripcion']) ?>
            </li>
        <?php endforeach; ?>
        </ol>
    <?php endif; ?>

    <?php if (in_array($rolActual, ['editor', 'admin'], true)): ?>
        <p>
            <a href="editar_ruta.php?id_ruta=<?= (int) $ruta['id_ruta'] ?>">Editar ruta</a>
        </p>
    <?php endif; ?>
    <?php if ($rolActual === 'admin'): ?>
        <form method="post" action="eliminar_ruta.php"
              onsubmit="return confirm('¿Eliminar esta ruta?');">
            <input type="hidden" name="id_ruta" value="<?= (int) $ruta['id_ruta'] ?>">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars(tokenCsrf()) ?>">
            <button type="submit" class="peligro">Eliminar ruta</button>
        </form>
    <?php endif; ?>
<?php endif; ?>

    <p><a href="rutas.php">Volver al listado</a></p>
</main>
</body>
</html>
