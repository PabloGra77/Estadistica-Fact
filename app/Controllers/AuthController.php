<?php
/**
 * AuthController — login y logout.
 */
$action = $uri;

if ($action === '/logout' && $method === 'POST') {
    Security::verifyCsrf();
    Auth::logout();
    header('Location: /login');
    exit;
}

// POST /login
if ($action === '/login' && $method === 'POST') {
    Security::verifyCsrf();

    $usuario  = Security::sanitizeString($_POST['usuario'] ?? '', 100);
    $password = $_POST['password'] ?? '';

    // Quitar dominio si lo pegaron completo
    $usuario = str_ireplace(EMAIL_DOMAIN, '', $usuario);
    $usuario = trim($usuario);

    if ($usuario === '' || $password === '') {
        header('Location: /login?error=1');
        exit;
    }

    $result = Auth::login($usuario, $password);

    if ($result['ok']) {
        header('Location: /');
        exit;
    }

    header('Location: /login?error=' . urlencode($result['error']));
    exit;
}

// GET /login
if (Auth::check()) {
    header('Location: /');
    exit;
}

$errorCode = Security::sanitizeString($_GET['error'] ?? '', 1);
$timeout   = !empty($_GET['timeout']);

$errorMsg = match ($errorCode) {
    '1' => 'Correo o contraseña incorrectos.',
    '2' => 'Cuenta bloqueada temporalmente. Intente en ' . LOCKOUT_MINUTES . ' minutos.',
    default => ($timeout ? 'Su sesión expiró por inactividad.' : null),
};

require BASE_PATH . '/app/Views/auth/login.php';
