<?php
require_once __DIR__ . '/../config/conexion.php';

$consulta = $pdo->query(
    'SELECT id_ciudad, nombre, pais
       FROM ciudades
      WHERE activa = 1
      ORDER BY nombre'
);

$ciudades = $consulta->fetchAll();
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Ruta360</title>
    <link rel="stylesheet" href="assets/css/estilos.css">
</head>
<body>
<?php $avisoOperativo = config('app', 'aviso'); ?>
<?php if (!empty($avisoOperativo)): ?>
<div class="aviso-mantenimiento"><?= htmlspecialchars($avisoOperativo, ENT_QUOTES, 'UTF-8') ?></div>
<?php endif; ?>
<main>
    <h1>Ruta360 — Consulta el tiempo de un destino (entorno de pruebas)</h1>

    <form action="tiempo.php" method="get">
        <label for="id_ciudad">Ciudad</label>
        <select name="id_ciudad" id="id_ciudad" required>
            <option value="">Selecciona una ciudad</option>
            <?php foreach ($ciudades as $ciudad): ?>
                <option value="<?= (int)$ciudad['id_ciudad'] ?>">
                    <?= htmlspecialchars($ciudad['nombre']) ?>
                    (<?= htmlspecialchars($ciudad['pais']) ?>)
                </option>
            <?php endforeach; ?>
        </select>

        <button type="submit">Consultar tiempo</button>
    </form>
</main>
</body>
</html>
