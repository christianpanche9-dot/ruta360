<?php
declare(strict_types=1);
require_once __DIR__ . '/../../config/conexion.php';
header('Content-Type: application/json; charset=utf-8');

function responderJson(int $estado, array $contenido): never
{
    http_response_code($estado);
    echo json_encode($contenido, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

function leerJson(): array
{
    $cuerpo = file_get_contents('php://input');
    if ($cuerpo === false || trim($cuerpo) === '') {
        responderJson(400, ['ok' => false, 'error' => 'El cuerpo de la petición está vacío.']);
    }

    $datos = json_decode($cuerpo, true);
    if (json_last_error() !== JSON_ERROR_NONE || !is_array($datos)) {
        responderJson(400, ['ok' => false, 'error' => 'El cuerpo no contiene JSON válido.']);
    }

    return $datos;
}

function obtenerIdRuta(): int
{
    $idRuta = filter_input(
        INPUT_GET,
        'id_ruta',
        FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 1]]
    );
    if ($idRuta === false || $idRuta === null) {
        responderJson(400, ['ok' => false, 'error' => 'El identificador de la ruta no es válido.']);
    }
    return $idRuta;
}

// --- Autenticación mediante token Bearer (Manual 10) ---

function obtenerCabeceraAuthorization(): string
{
    $cabecera = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
    if ($cabecera === '' && function_exists('getallheaders')) {
        $headers = getallheaders();
        $cabecera = $headers['Authorization'] ?? $headers['authorization'] ?? '';
    }
    return trim($cabecera);
}

function extraerTokenBearer(): string
{
    $cabecera = obtenerCabeceraAuthorization();
    if (!preg_match('/^Bearer\s+([A-Fa-f0-9]{64})$/', $cabecera, $m)) {
        header('WWW-Authenticate: Bearer');
        responderJson(401, ['ok' => false, 'error' => 'Se necesita un token Bearer válido.']);
    }
    return $m[1];
}

function autenticarToken(PDO $pdo): array
{
    $token = extraerTokenBearer();
    $hash = hash('sha256', $token);

    $sql = 'SELECT u.id_usuario, u.nombre, u.email, u.rol, t.id_token
              FROM api_tokens t
              INNER JOIN usuarios u ON u.id_usuario = t.id_usuario
             WHERE t.token_hash = :token_hash
               AND t.revocado_en IS NULL
               AND (t.expira_en IS NULL OR t.expira_en > NOW())
               AND u.activo = 1
             LIMIT 1';
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['token_hash' => $hash]);
    $usuario = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($usuario === false) {
        header('WWW-Authenticate: Bearer');
        responderJson(401, ['ok' => false, 'error' => 'El token no es válido o ha caducado.']);
    }

    $stmtUso = $pdo->prepare('UPDATE api_tokens SET ultimo_uso = NOW() WHERE id_token = :id_token');
    $stmtUso->execute(['id_token' => $usuario['id_token']]);

    return $usuario;
}

const PERMISOS_ROL = [
    'lector' => [],
    'editor' => ['crear_ruta', 'editar_ruta'],
    'admin'  => ['crear_ruta', 'editar_ruta', 'eliminar_ruta']
];

function tienePermiso(string $rol, string $permiso): bool
{
    return in_array($permiso, PERMISOS_ROL[$rol] ?? [], true);
}

function exigirPermiso(PDO $pdo, string $permiso): array
{
    $usuario = autenticarToken($pdo);
    if (!tienePermiso($usuario['rol'], $permiso)) {
        responderJson(403, ['ok' => false, 'error' => 'No tienes permiso para esta operación.']);
    }
    return $usuario;
}

function existeCiudad(PDO $pdo, int $idCiudad): bool
{
    $sql = 'SELECT 1 FROM ciudades WHERE id_ciudad = :id_ciudad LIMIT 1';
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['id_ciudad' => $idCiudad]);
    return $stmt->fetchColumn() !== false;
}

function buscarRuta(PDO $pdo, int $idRuta): ?array
{
    $sql = 'SELECT id_ruta, id_ciudad, titulo, descripcion,
                   duracion_minutos, distancia_km, dificultad, activa
              FROM rutas
             WHERE id_ruta = :id_ruta
             LIMIT 1';
    $stmt = $pdo->prepare($sql);
    $stmt->execute(['id_ruta' => $idRuta]);
    $ruta = $stmt->fetch(PDO::FETCH_ASSOC);
    return $ruta === false ? null : $ruta;
}

function exigirRutaEditable(PDO $pdo, int $idRuta): array
{
    $ruta = buscarRuta($pdo, $idRuta);
    if ($ruta === null) {
        responderJson(404, ['ok' => false, 'error' => 'La ruta indicada no existe.']);
    }
    if (!(bool) $ruta['activa']) {
        responderJson(409, ['ok' => false, 'error' => 'La ruta está inactiva y no puede editarse.']);
    }
    return $ruta;
}

function validarRuta(array $datos): array
{
    $errores = [];

    $idCiudad = filter_var(
        $datos['id_ciudad'] ?? null,
        FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 1]]
    );
    if ($idCiudad === false) {
        $errores['id_ciudad'] = 'Selecciona una ciudad válida.';
    }

    $titulo = trim((string) ($datos['titulo'] ?? ''));
    $longitudTitulo = mb_strlen($titulo);
    if ($longitudTitulo < 5 || $longitudTitulo > 120) {
        $errores['titulo'] = 'El título debe tener entre 5 y 120 caracteres.';
    }

    $descripcion = trim((string) ($datos['descripcion'] ?? ''));
    $longitudDescripcion = mb_strlen($descripcion);
    if ($longitudDescripcion < 10 || $longitudDescripcion > 1000) {
        $errores['descripcion'] = 'La descripción debe tener entre 10 y 1000 caracteres.';
    }

    $duracion = filter_var(
        $datos['duracion_minutos'] ?? null,
        FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 15, 'max_range' => 1440]]
    );
    if ($duracion === false) {
        $errores['duracion_minutos'] = 'La duración debe estar entre 15 y 1440 minutos.';
    }

    $distancia = filter_var($datos['distancia_km'] ?? null, FILTER_VALIDATE_FLOAT);
    if ($distancia === false || $distancia <= 0 || $distancia > 1000) {
        $errores['distancia_km'] = 'La distancia debe ser mayor que 0 y máximo 1000 km.';
    }

    $dificultad = $datos['dificultad'] ?? null;
    if ($dificultad !== null && $dificultad !== ''
        && !in_array($dificultad, ['facil', 'media', 'alta'], true)) {
        $errores['dificultad'] = 'La dificultad debe ser facil, media o alta.';
    }

    return $errores;
}

function crearRuta(PDO $pdo): never
{
    $datos = leerJson();

    $errores = validarRuta($datos);
    if ($errores !== []) {
        responderJson(422, ['ok' => false, 'error' => 'Revisa los datos enviados.', 'errores' => $errores]);
    }

    $idCiudad = (int) $datos['id_ciudad'];
    if (!existeCiudad($pdo, $idCiudad)) {
        responderJson(404, ['ok' => false, 'error' => 'La ciudad indicada no existe.']);
    }

    $columnas = ['id_ciudad', 'titulo', 'descripcion', 'duracion_minutos', 'distancia_km'];
    $parametros = [
        'id_ciudad' => $idCiudad,
        'titulo' => trim((string) $datos['titulo']),
        'descripcion' => trim((string) $datos['descripcion']),
        'duracion_minutos' => (int) $datos['duracion_minutos'],
        'distancia_km' => (float) $datos['distancia_km']
    ];

    $dificultad = $datos['dificultad'] ?? null;
    if ($dificultad !== null && $dificultad !== '') {
        $columnas[] = 'dificultad';
        $parametros['dificultad'] = $dificultad;
    }

    $marcadores = array_map(static fn(string $columna): string => ':' . $columna, $columnas);

    try {
        $sql = 'INSERT INTO rutas (' . implode(', ', $columnas) . ')
                VALUES (' . implode(', ', $marcadores) . ')';
        $stmt = $pdo->prepare($sql);
        $stmt->execute($parametros);

        $idRuta = (int) $pdo->lastInsertId();
        $url = 'ruta.php?id_ruta=' . $idRuta;

        header('Location: ' . $url);
        responderJson(201, [
            'ok' => true,
            'mensaje' => 'Ruta creada correctamente.',
            'datos' => ['id_ruta' => $idRuta, 'url' => $url]
        ]);
    } catch (PDOException $e) {
        error_log($e->getMessage());
        responderJson(500, ['ok' => false, 'error' => 'No se ha podido crear la ruta.']);
    }
}

function listarRutas(PDO $pdo): never
{
    // --- Filtro id_ciudad ---
    $textoCiudad = filter_input(INPUT_GET, 'id_ciudad');
    $idCiudad = null;
    if ($textoCiudad !== null && $textoCiudad !== '') {
        $idCiudad = filter_var($textoCiudad, FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]]);
        if ($idCiudad === false) {
            responderJson(400, ['ok' => false,
                'error' => 'El filtro id_ciudad no es válido.']);
        }
    }

    // --- Filtro duracion_maxima ---
    $textoDuracion = filter_input(INPUT_GET, 'duracion_maxima');
    $duracionMaxima = null;
    if ($textoDuracion !== null && $textoDuracion !== '') {
        $duracionMaxima = filter_var($textoDuracion, FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]]);
        if ($duracionMaxima === false) {
            responderJson(400, ['ok' => false,
                'error' => 'El filtro duracion_maxima no es válido.']);
        }
    }

    // --- Filtro dificultad ---
    $dificultad = filter_input(INPUT_GET, 'dificultad');
    if ($dificultad !== null && $dificultad !== '') {
        if (!in_array($dificultad, ['facil', 'media', 'alta'], true)) {
            responderJson(400, ['ok' => false,
                'error' => 'El filtro dificultad no es válido.']);
        }
    } else {
        $dificultad = null;
    }

    try {
        $sql = 'SELECT r.id_ruta, r.titulo, r.duracion_minutos,
                       r.distancia_km, r.dificultad,
                       c.id_ciudad, c.nombre AS ciudad, c.pais,
                       COUNT(p.id_punto) AS numero_puntos
                  FROM rutas r
                  INNER JOIN ciudades c ON c.id_ciudad = r.id_ciudad
                  LEFT JOIN puntos_interes p ON p.id_ruta = r.id_ruta
                 WHERE r.activa = 1 AND c.activa = 1';

        $parametros = [];

        if ($idCiudad !== null) {
            $sql .= ' AND r.id_ciudad = :id_ciudad';
            $parametros['id_ciudad'] = $idCiudad;
        }

        if ($duracionMaxima !== null) {
            $sql .= ' AND r.duracion_minutos <= :duracion_maxima';
            $parametros['duracion_maxima'] = $duracionMaxima;
        }

        if ($dificultad !== null) {
            $sql .= ' AND r.dificultad = :dificultad';
            $parametros['dificultad'] = $dificultad;
        }

        $sql .= ' GROUP BY r.id_ruta, r.titulo, r.duracion_minutos,
                           r.distancia_km, r.dificultad,
                           c.id_ciudad, c.nombre, c.pais
                  ORDER BY c.nombre, r.titulo';

        $consulta = $pdo->prepare($sql);
        $consulta->execute($parametros);
        $filas = $consulta->fetchAll();

        $rutas = array_map(static fn(array $fila): array => [
            'id_ruta' => (int) $fila['id_ruta'],
            'titulo' => $fila['titulo'],
            'duracion_minutos' => (int) $fila['duracion_minutos'],
            'distancia_km' => (float) $fila['distancia_km'],
            'dificultad' => $fila['dificultad'],
            'ciudad' => [
                'id_ciudad' => (int) $fila['id_ciudad'],
                'nombre' => $fila['ciudad'],
                'pais' => $fila['pais']
            ],
            'numero_puntos' => (int) $fila['numero_puntos']
        ], $filas);

        responderJson(200, [
            'ok' => true,
            'filtros' => [
                'id_ciudad' => $idCiudad,
                'duracion_maxima' => $duracionMaxima,
                'dificultad' => $dificultad
            ],
            'total' => count($rutas),
            'datos' => $rutas
        ]);
    } catch (PDOException $e) {
        error_log($e->getMessage());
        responderJson(500, ['ok' => false,
            'error' => 'No se ha podido consultar la colección.']);
    }
}

function validarPut(array $datos): array
{
    $campos = ['id_ciudad', 'titulo', 'descripcion', 'duracion_minutos', 'distancia_km'];
    $errores = [];
    foreach ($campos as $campo) {
        if (!array_key_exists($campo, $datos)) {
            $errores[$campo] = 'Este campo es obligatorio en PUT.';
        }
    }
    return $errores + validarRuta($datos);
}

function ejecutarUpdateCompleto(PDO $pdo, int $idRuta, array $datos): void
{
    $dificultad = $datos['dificultad'] ?? null;
    if ($dificultad === '') {
        $dificultad = null;
    }

    $sql = 'UPDATE rutas
               SET id_ciudad = :id_ciudad,
                   titulo = :titulo,
                   descripcion = :descripcion,
                   duracion_minutos = :duracion_minutos,
                   distancia_km = :distancia_km,
                   dificultad = :dificultad
             WHERE id_ruta = :id_ruta AND activa = 1';
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        'id_ciudad' => (int) $datos['id_ciudad'],
        'titulo' => trim((string) $datos['titulo']),
        'descripcion' => trim((string) $datos['descripcion']),
        'duracion_minutos' => (int) $datos['duracion_minutos'],
        'distancia_km' => (float) $datos['distancia_km'],
        'dificultad' => $dificultad ?? 'facil',
        'id_ruta' => $idRuta
    ]);
}

function actualizarRutaCompleta(PDO $pdo): never
{
    $idRuta = obtenerIdRuta();
    exigirRutaEditable($pdo, $idRuta);
    $datos = leerJson();
    $errores = validarPut($datos);

    if ($errores !== []) {
        responderJson(422, ['ok' => false, 'error' => 'Revisa los datos enviados.', 'errores' => $errores]);
    }
    if (!existeCiudad($pdo, (int) $datos['id_ciudad'])) {
        responderJson(404, ['ok' => false, 'error' => 'La ciudad indicada no existe.']);
    }

    try {
        ejecutarUpdateCompleto($pdo, $idRuta, $datos);
        responderJson(200, [
            'ok' => true,
            'mensaje' => 'Ruta actualizada correctamente.',
            'datos' => buscarRuta($pdo, $idRuta)
        ]);
    } catch (PDOException $e) {
        error_log($e->getMessage());
        responderJson(500, ['ok' => false, 'error' => 'No se ha podido actualizar la ruta.']);
    }
}

function validarPatch(array $datos): array
{
    $permitidos = ['id_ciudad', 'titulo', 'descripcion', 'duracion_minutos', 'distancia_km', 'dificultad'];
    $errores = [];

    if ($datos === []) {
        return ['general' => 'Envía al menos un campo para modificar.'];
    }

    foreach (array_keys($datos) as $campo) {
        if (!in_array($campo, $permitidos, true)) {
            $errores[$campo] = 'Este campo no se puede modificar.';
        }
    }

    $completos = array_intersect_key($datos, array_flip($permitidos));
    $base = [
        'id_ciudad' => 1,
        'titulo' => 'Título válido',
        'descripcion' => 'Descripción válida para comprobar el contrato.',
        'duracion_minutos' => 60,
        'distancia_km' => 1,
        'dificultad' => 'facil'
    ];
    $erroresCompletos = validarRuta(array_replace($base, $completos));

    return $errores + array_intersect_key($erroresCompletos, $completos);
}

function ejecutarPatch(PDO $pdo, int $idRuta, array $datos): void
{
    $columnas = ['id_ciudad', 'titulo', 'descripcion', 'duracion_minutos', 'distancia_km', 'dificultad'];
    $set = [];
    $parametros = ['id_ruta' => $idRuta];

    foreach ($columnas as $columna) {
        if (array_key_exists($columna, $datos)) {
            $set[] = $columna . ' = :' . $columna;
            $parametros[$columna] = $datos[$columna];
        }
    }

    $sql = 'UPDATE rutas SET ' . implode(', ', $set) .
           ' WHERE id_ruta = :id_ruta AND activa = 1';
    $stmt = $pdo->prepare($sql);
    $stmt->execute($parametros);
}

function actualizarRutaParcial(PDO $pdo): never
{
    $idRuta = obtenerIdRuta();
    exigirRutaEditable($pdo, $idRuta);
    $datos = leerJson();
    $errores = validarPatch($datos);

    if ($errores !== []) {
        responderJson(422, ['ok' => false, 'error' => 'Revisa los datos enviados.', 'errores' => $errores]);
    }
    if (array_key_exists('id_ciudad', $datos) && !existeCiudad($pdo, (int) $datos['id_ciudad'])) {
        responderJson(404, ['ok' => false, 'error' => 'La ciudad indicada no existe.']);
    }

    try {
        ejecutarPatch($pdo, $idRuta, $datos);
        responderJson(200, [
            'ok' => true,
            'mensaje' => 'Ruta modificada correctamente.',
            'datos' => buscarRuta($pdo, $idRuta)
        ]);
    } catch (PDOException $e) {
        error_log($e->getMessage());
        responderJson(500, ['ok' => false, 'error' => 'No se ha podido modificar la ruta.']);
    }
}

function eliminarRuta(PDO $pdo): never
{
    $idRuta = obtenerIdRuta();
    $ruta = buscarRuta($pdo, $idRuta);

    if ($ruta === null) {
        responderJson(404, ['ok' => false, 'error' => 'La ruta indicada no existe.']);
    }
    if (!(bool) $ruta['activa']) {
        responderJson(200, ['ok' => true, 'mensaje' => 'La ruta ya estaba eliminada.']);
    }

    try {
        $stmt = $pdo->prepare('UPDATE rutas SET activa = 0 WHERE id_ruta = :id_ruta');
        $stmt->execute(['id_ruta' => $idRuta]);
        responderJson(200, [
            'ok' => true,
            'mensaje' => 'Ruta eliminada correctamente.',
            'datos' => ['id_ruta' => $idRuta]
        ]);
    } catch (PDOException $e) {
        error_log($e->getMessage());
        responderJson(500, ['ok' => false, 'error' => 'No se ha podido eliminar la ruta.']);
    }
}

$metodo = $_SERVER['REQUEST_METHOD'];

if ($metodo === 'GET') {
    listarRutas($pdo);
}

if ($metodo === 'POST') {
    exigirPermiso($pdo, 'crear_ruta');
    crearRuta($pdo);
}

if ($metodo === 'PUT') {
    exigirPermiso($pdo, 'editar_ruta');
    actualizarRutaCompleta($pdo);
}

if ($metodo === 'PATCH') {
    exigirPermiso($pdo, 'editar_ruta');
    actualizarRutaParcial($pdo);
}

if ($metodo === 'DELETE') {
    exigirPermiso($pdo, 'eliminar_ruta');
    eliminarRuta($pdo);
}

header('Allow: GET, POST, PUT, PATCH, DELETE');
responderJson(405, ['ok' => false, 'error' => 'Método no permitido.']);
