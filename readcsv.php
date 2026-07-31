<?php
if (!isset($_GET['file'])) {
    http_response_code(400);
    echo "Fehler: Keine Datei angegeben.";
    exit;
}

$file = basename($_GET['file']);
$path = __DIR__ . DIRECTORY_SEPARATOR . $file;

if (!file_exists($path) || pathinfo($path, PATHINFO_EXTENSION) !== 'csv') {
    http_response_code(404);
    echo "Datei nicht gefunden.";
    exit;
}

header("Content-Type: text/plain; charset=UTF-8");
readfile($path);
