<?php
if (is_file(__DIR__ . '/../core/access_control.php')) {
	require_once __DIR__ . '/../core/access_control.php';
	startowa_start_session();
} else {
	if (session_status() !== PHP_SESSION_ACTIVE) {
		session_start();
	}
	if (!function_exists('startowa_redirect')) {
		function startowa_redirect(string $path): void
		{
			$normalized = ltrim($path, '/');
			if (strpos($normalized, 'public/') === 0) {
				$normalized = substr($normalized, 7);
			}

			header('Location: ' . $normalized);
			exit();
		}
	}
}

$_SESSION = [];

if (ini_get('session.use_cookies')) {
	$params = session_get_cookie_params();
	setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
}

session_destroy();
startowa_redirect('public/login.php');
