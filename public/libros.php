<?php
declare(strict_types=1);
require_once __DIR__ . '/../app/Servicios/AdaptadorLibros.php';

// Proyecto final (Manual 4, 4.18-4.19): buscador de libros con Open
// Library. Config compartida con el resto de proveedores externos
// (config/servicios.php), sin credenciales porque la API es pública.
$config = require __DIR__ . '/../config/servicios.php';
$adaptador = new AdaptadorLibros($config['libros']);

$termino = trim((string) filter_input(INPUT_GET, 'q'));
$resultado = null;

if ($termino !== '') {
    $resultado = $adaptador->consultar(['q' => $termino]);
}
?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Buscador de libros | Proyecto final Ruta360</title>
    <link rel="stylesheet" href="assets/css/estilos.css">
</head>
<body>
<main>
    <p class="cuenta"><a href="rutas.php">&larr; Volver a Ruta360</a></p>
    <h1>Buscador de libros (Open Library)</h1>
    <p class="aviso">Proyecto final del Manual 4: integración con una API externa distinta a las que ya usa Ruta360, siguiendo el mismo método de configuración y fallo controlado.</p>

    <form method="get" action="libros.php" class="filtros">
        <div>
            <label for="q">Título o autor</label>
            <input type="text" name="q" id="q" value="<?= htmlspecialchars($termino) ?>" placeholder="p. ej. Cien años de soledad">
        </div>
        <button type="submit">Buscar</button>
        <a href="libros.php">Limpiar</a>
    </form>

    <?php if ($resultado === null): ?>
        <p class="aviso">Escribe un título o autor y pulsa «Buscar».</p>
    <?php elseif (!$resultado->disponible): ?>
        <p class="error"><?= htmlspecialchars($resultado->aviso ?? 'No se ha podido completar la búsqueda.') ?></p>
    <?php elseif ($resultado->datos === []): ?>
        <p class="aviso"><?= htmlspecialchars($resultado->aviso ?? 'No se han encontrado libros.') ?></p>
    <?php else: ?>
        <p>Se han encontrado <?= count($resultado->datos) ?> libro(s).</p>
        <div class="rejilla-rutas">
        <?php foreach ($resultado->datos as $libro): ?>
            <article class="tarjeta-ruta tarjeta-libro">
                <?php if ($libro['portada_id']): ?>
                    <img src="https://covers.openlibrary.org/b/id/<?= (int) $libro['portada_id'] ?>-M.jpg"
                         alt="Portada de <?= htmlspecialchars($libro['titulo']) ?>" loading="lazy">
                <?php endif; ?>
                <h2><?= htmlspecialchars($libro['titulo']) ?></h2>
                <?php if ($libro['autores']): ?>
                    <p class="lugar"><?= htmlspecialchars(implode(', ', array_slice($libro['autores'], 0, 3))) ?></p>
                <?php endif; ?>
                <?php if ($libro['anio']): ?>
                    <p>Primera publicación: <?= (int) $libro['anio'] ?></p>
                <?php endif; ?>
                <?php if ($libro['clave']): ?>
                    <a href="libro_detalle.php?clave=<?= urlencode($libro['clave']) ?>">Ver ficha</a>
                <?php endif; ?>
            </article>
        <?php endforeach; ?>
        </div>
    <?php endif; ?>
</main>
</body>
</html>
