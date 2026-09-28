<?php
// --- Master Vercel Serverless PHP Router ----------------------
$uri = parse_url($_SERVER["REQUEST_URI"] ?? "/", PHP_URL_PATH);

if ($uri === "/" || $uri === "" || $uri === "/index.php") {
    require __DIR__ . "/../index.php";
    exit;
}

$cleanUri = ltrim($uri, "/");

if (str_starts_with($cleanUri, "api/")) {
    $apiFile = __DIR__ . "/../" . $cleanUri;
    if (file_exists($apiFile) && !is_dir($apiFile)) {
        require $apiFile;
        exit;
    }
}

$target = __DIR__ . "/../" . $cleanUri;
if (file_exists($target) && !is_dir($target)) {
    require $target;
    exit;
}

if (file_exists($target . ".php")) {
    require $target . ".php";
    exit;
}

require __DIR__ . "/../404.php";

