<?php
// ============================================================================
// SmartKlass - Configuration (TEMPLATE)
// ============================================================================
// 
// 1. Sur ton serveur O2switch, copie ce fichier en config.php
// 2. Remplis les 3 valeurs avec tes identifiants
// 3. config.php est dans .gitignore → ne sera jamais écrasé par un deploy
//

define('DB_HOST', 'localhost');
define('DB_NAME', 'TON_USER_smartklass');
define('DB_USER', 'TON_USER_smartklass');
define('DB_PASS', 'TON_MOT_DE_PASSE_BDD');

define('DB_CHARSET', 'utf8mb4');

// Clé API Anthropic (Claude) — pour la génération de cours/activités via IA
// Crée ta clé sur https://console.anthropic.com/
define('ANTHROPIC_API_KEY', 'sk-ant-YOUR_KEY_HERE');

function getDB() {
    static $pdo = null;
    if ($pdo === null) {
        try {
            $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        } catch (PDOException $e) {
            http_response_code(500);
            die(json_encode(['error' => 'Erreur de connexion à la base de données']));
        }
    }
    return $pdo;
}

function setupHeaders() {
    header('Content-Type: application/json; charset=utf-8');
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
    if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }
}

function generateId() { return substr(bin2hex(random_bytes(5)), 0, 9); }

function jsonResponse($data, $code = 200) {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

function getJsonBody() {
    return json_decode(file_get_contents('php://input'), true) ?? [];
}
