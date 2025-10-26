<?php
/**
 * Dashboard Module: String Functions
 * Demonstrates CONCAT, SUBSTRING, UPPER, LOWER, LENGTH, etc.
 */

require_once __DIR__ . '/../config/database.php';

$db = new Database();
$conn = $db->getConnection();

$sql = "SELECT 
            u.user_id,
            CONCAT(u.f_name, ' ', u.l_name) AS full_name,
            UPPER(u.email) AS email_upper,
            LOWER(CONCAT(u.f_name, '.', u.l_name)) AS username_suggestion,
            SUBSTRING(u.email, 1, LOCATE('@', u.email) - 1) AS email_prefix,
            SUBSTRING(u.email, LOCATE('@', u.email) + 1) AS email_domain,
            LENGTH(u.email) AS email_length,
            REVERSE(u.f_name) AS first_name_reversed,
            CONCAT(
                LEFT(u.f_name, 1),
                LEFT(u.l_name, 1)
            ) AS initials,
            REPLACE(u.email, '@', ' [at] ') AS obfuscated_email
        FROM users u
        ORDER BY u.user_id
        LIMIT 10";

$result = $conn->query($sql);
$data = [];
if ($result) {
    while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
        $data[] = $row;
    }
}

return [
    'title' => 'String Functions & Manipulation',
    'description' => 'User Data Processing - Uses CONCAT, SUBSTRING, UPPER, LOWER, LENGTH, REPLACE, and more',
    'sql' => $sql,
    'data' => $data,
    'icon' => 'fa-font'
];