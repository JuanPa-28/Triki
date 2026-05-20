<?php
// =====================================================
//  config.php — Configuración de la base de datos
//  ¡CAMBIA estos valores con los de tu servidor!
// =====================================================

define('DB_HOST', 'localhost');      // Servidor (casi siempre localhost)
define('DB_USER', 'root');           // Tu usuario de MySQL / phpMyAdmin
define('DB_PASS', '');               // Tu contraseña (vacía en XAMPP por defecto)
define('DB_NAME', 'xo_arena');       // Nombre de la base de datos

// Cabeceras para permitir peticiones desde el HTML (CORS)
header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Responder al preflight de CORS
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// ── Crear conexión PDO ──────────────────────────────
function getDB(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        try {
            $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
        } catch (PDOException $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Error de conexión: ' . $e->getMessage()]);
            exit();
        }
    }
    return $pdo;
}

// ── Helper para respuestas JSON ─────────────────────
function respond(array $data, int $code = 200): void {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit();
}
