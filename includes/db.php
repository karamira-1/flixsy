<?php
// =======================================
// FLIXSY DATABASE CONNECTION (includes/db.php)
// Real PDO connection with error handling
// =======================================

// Database configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'flixsy');
define('DB_USER', 'root');
define('DB_PASS', ''); // Set your password here

// Create PDO connection
try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
        ]
    );
} catch (PDOException $e) {
    // Log error (don't show to users in production)
    error_log("Database connection failed: " . $e->getMessage());
    
    // Show user-friendly error
    die("Database connection failed. Please contact support.");
}

// =======================================
// HELPER FUNCTIONS
// =======================================

/**
 * Execute a query with parameters
 * @param string $sql SQL query
 * @param array $params Parameters to bind
 * @return PDOStatement|false
 */
function dbQuery($sql, $params = []) {
    global $pdo;
    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    } catch (PDOException $e) {
        error_log("Query failed: " . $e->getMessage() . " | SQL: " . $sql);
        return false;
    }
}

/**
 * Fetch single row
 * @param string $sql SQL query
 * @param array $params Parameters to bind
 * @return array|false
 */
function dbFetchOne($sql, $params = []) {
    $stmt = dbQuery($sql, $params);
    return $stmt ? $stmt->fetch() : false;
}

/**
 * Fetch all rows
 * @param string $sql SQL query
 * @param array $params Parameters to bind
 * @return array
 */
function dbFetchAll($sql, $params = []) {
    $stmt = dbQuery($sql, $params);
    return $stmt ? $stmt->fetchAll() : [];
}

/**
 * Execute INSERT/UPDATE/DELETE and return affected rows
 * @param string $sql SQL query
 * @param array $params Parameters to bind
 * @return int Number of affected rows
 */
function dbExecute($sql, $params = []) {
    $stmt = dbQuery($sql, $params);
    return $stmt ? $stmt->rowCount() : 0;
}

/**
 * Get last insert ID
 * @return string
 */
function dbLastInsertId() {
    global $pdo;
    return $pdo->lastInsertId();
}

/**
 * Begin transaction
 */
function dbBeginTransaction() {
    global $pdo;
    $pdo->beginTransaction();
}

/**
 * Commit transaction
 */
function dbCommit() {
    global $pdo;
    $pdo->commit();
}

/**
 * Rollback transaction
 */
function dbRollback() {
    global $pdo;
    $pdo->rollBack();
}

// =======================================
// CONNECTION SUCCESS
// =======================================
// Uncomment line below for debugging
// echo "Database connected successfully!";
?>
