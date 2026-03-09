<?php
/**
 * Shared utility functions for Random Name Picker
 */

/**
 * Check if user session is valid
 */
function checkSession() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (empty($_SESSION['emp_code'])) {
        return false;
    }
    return true;
}

/**
 * Get current user's emp_code from session
 */
function getEmpCode() {
    return $_SESSION['emp_code'] ?? null;
}

/**
 * Send JSON response and exit
 */
function jsonResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Send error JSON response
 */
function jsonError($message, $statusCode = 400) {
    jsonResponse(['success' => false, 'message' => $message], $statusCode);
}

/**
 * Send success JSON response
 */
function jsonSuccess($data = [], $message = 'success') {
    jsonResponse(array_merge(['success' => true, 'message' => $message], $data));
}

/**
 * Sanitize string input
 */
function sanitizeInput($input) {
    if (is_array($input)) {
        return array_map('sanitizeInput', $input);
    }
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}

/**
 * Validate required fields
 */
function validateRequired($data, $fields) {
    foreach ($fields as $field) {
        if (!isset($data[$field]) || trim($data[$field]) === '') {
            return $field;
        }
    }
    return true;
}

/**
 * Execute parameterized query and return results
 */
function dbQuery($conn, $sql, $params = []) {
    $stmt = sqlsrv_query($conn, $sql, $params);
    if ($stmt === false) {
        return false;
    }
    $results = [];
    while ($row = sqlsrv_fetch_array($stmt, SQLSRV_FETCH_ASSOC)) {
        $results[] = $row;
    }
    sqlsrv_free_stmt($stmt);
    return $results;
}

/**
 * Execute parameterized query (INSERT/UPDATE/DELETE) and return affected rows
 */
function dbExecute($conn, $sql, $params = []) {
    $stmt = sqlsrv_query($conn, $sql, $params);
    if ($stmt === false) {
        return false;
    }
    $rows = sqlsrv_rows_affected($stmt);
    sqlsrv_free_stmt($stmt);
    return $rows;
}

/**
 * Get last inserted ID (for IDENTITY columns)
 */
function dbLastInsertId($conn) {
    $result = dbQuery($conn, "SELECT SCOPE_IDENTITY() AS id");
    return $result ? (int)$result[0]['id'] : null;
}

/**
 * Decrypt function (matches index.php pattern)
 */
function decryptIt($q)
{
	$cryptKey  = 'Iloveyouallpann';
	$qDecoded = rtrim(openssl_decrypt(base64_decode($q), "AES-256-CBC", md5($cryptKey), 0, substr(md5(md5($cryptKey)), 0, 16)), "\0");
	//write_file("temp.txt","=".date("H:i:s")."=qDecoded===$qDecoded=\r\n","a");
	return ($qDecoded);
}
?>
