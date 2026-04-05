<?php
// Check PHP syntax of the debug file
$file = 'debug-add-department.php';
$content = file_get_contents($file);

// Check for syntax errors
$check = shell_exec("php -l \"$file\" 2>&1");

echo json_encode([
    'file' => $file,
    'syntax_check' => $check,
    'has_errors' => strpos($check, 'No syntax errors') === false,
    'first_lines' => explode("\n", $content, 10)
]);
?>
