<?php
declare(strict_types=1);
require_once __DIR__ . '/../app/Servicios/AdaptadorLibros.php';

$config = require __DIR__ . '/../config/servicios.php';
$adaptador = new AdaptadorLibros($config['libros']);

$clave = trim((string) filter_input(INPUT_GET, 'clave'));
$resultado = $clave !== ''
    ? $adaptador->detalle($clave)
    : null;
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Ficha de libro | Proyecto final Ruta360</title>
    <link rel="stylesheet" href="assets/css/estilos.css">
</head>
<body>
<main>
    <p class="cuenta"><a href="libros.php">&larr; Volver al buscador</a></p>

    <?php if ($resultado === null): ?>
        <h1>Ficha de libro</h1>
        <p class="error">No se ha indicado un libro.</p>
    <?php elseif (!$resultado->disponible): ?>
        <h1>Ficha de libro</h1>
        <p class="error"><?= htmlspecialchars($resultado->aviso ?? 'No se ha podido cargar la ficha.') ?></p>
    <?php else: ?>
        <?php $libro = $resultado->datos; ?>
        <h1><?= htmlspecialchars($libro['titulo']) ?></h1>
        <?php if ($libro['portada_id']): ?>
            <img src="https://covers.openlibrary.org/b/id/<?= (int) $libro['portada_id'] ?>-L.jpg"
                 alt="Portada de <?= htmlspecialchars($libro['titulo']) ?>" style="max-width:220px;border-radius:8px;">
        <?php endif; ?>
        <?php if ($libro['descripcion']): ?>
            <p><?= nl2br(htmlspecialchars($libro['descripcion'])) ?></p>
        <?php else: ?>
            <p class="aviso">Este libro no tiene descripción disponible en Open Library.</p>
        <?php endif; ?>
        <?php if ($libro['materias']): ?>
            <p><strong>Materias:</strong> <?= htmlspecialchars(implode(', ', $libro['materias'])) ?></p>
        <?php endif; ?>
    <?php endif; ?>
</main>
</body>
</html>
