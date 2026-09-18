<?php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

function exigirLogin(): void
{
    if (!isset($_SESSION['usuario'])) {
        header('Location: login.php');
        exit;
    }
}

function exigirRolWeb(array $roles): void
{
    exigirLogin();
    $rol = $_SESSION['usuario']['rol'] ?? '';
    if (!in_array($rol, $roles, true)) {
        http_response_code(403);
        exit('No tienes permiso para acceder a esta página.');
    }
}

function tokenCsrf(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function validarCsrf(): void
{
    $recibido = (string) ($_POST['csrf_token'] ?? '');
    $guardado = (string) ($_SESSION['csrf_token'] ?? '');
    if ($guardado === '' || !hash_equals($guardado, $recibido)) {
        http_response_code(403);
        exit('La petición no es válida. Recarga la página.');
    }
}
