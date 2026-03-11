<?php
/**
 * EV Log - Configurazione Globale e Connessione DB
 */

// Parametri Database letti dalle variabili ambiente Docker
$host = getenv('DB_HOST') ?: 'localhost';
$db   = getenv('DB_NAME') ?: 'volvo_trips';
$user = getenv('DB_USER') ?: 'root';
$pass = getenv('DB_PASS') ?: '';
$charset = 'utf8mb4';

// DSN PDO
$dsn = "mysql:host=$host;dbname=$db;charset=$charset";

// Opzioni PDO
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO($dsn, $user, $pass, $options);
} catch (\PDOException $e) {
    die("<div style='color:red; padding:20px; text-align:center;'>
    <b>Errore Critico Database:</b> " . $e->getMessage() . "</div>");
}

/**
 * Funzione per pulire le stringhe CSV (gestione encoding UTF-16/UTF-8)
 */
function clean_csv_string($str) {
    $str = str_replace("\0", "", $str);
    if (mb_detect_encoding($str, 'UTF-8', true) === false) {
        $str = mb_convert_encoding($str, 'UTF-8', 'UTF-16');
    }
    return trim($str);
}

/**
 * Funzione helper per costruire URL con parametri preservati
 */
function filterUrl($p) {
    return "?" . http_build_query(array_merge($_GET, $p));
}
?>