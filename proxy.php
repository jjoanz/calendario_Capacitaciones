<?php
// Habilitar CORS para que el navegador acepte la respuesta
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header("Content-Type: text/csv; charset=utf-8"); // Especificar CSV con UTF-8

// Manejar preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Verificar que se envió la URL
if (!isset($_GET['url'])) {
    http_response_code(400);
    echo "Error: Se requiere el parámetro 'url'";
    exit;
}

$url = $_GET['url'];

// Validar que sea un URL permitido (opcional pero recomendable)
$allowed_domains = ['docs.google.com', 'sheets.googleapis.com'];
$parsed_url = parse_url($url);

if (!isset($parsed_url['host']) || !in_array($parsed_url['host'], $allowed_domains)) {
    http_response_code(403);
    echo "Error: Dominio no permitido. Solo se permiten: " . implode(', ', $allowed_domains);
    exit;
}

// Hacer la petición al recurso externo
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);
curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (compatible; ProDominicana-Calendar/1.0)');

// Headers adicionales para Google Sheets
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Accept: text/csv,application/csv,text/plain',
    'Accept-Charset: utf-8'
]);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

if (curl_errno($ch)) {
    $error = curl_error($ch);
    curl_close($ch);
    http_response_code(500);
    echo "Error al obtener el recurso: " . $error;
    exit;
}

curl_close($ch);

// Verificar código de respuesta HTTP
if ($http_code !== 200) {
    http_response_code($http_code);
    echo "Error HTTP $http_code al acceder al recurso";
    exit;
}

// Verificar que la respuesta no esté vacía
if (empty($response)) {
    http_response_code(204);
    echo "El recurso está vacío o no contiene datos";
    exit;
}

// Verificar si el contenido parece ser CSV válido
$lines = explode("\n", $response);
if (count($lines) < 2) {
    http_response_code(422);
    echo "Error: El contenido no parece ser un CSV válido (menos de 2 líneas)";
    exit;
}

// Log básico para debugging (opcional)
error_log("Proxy CSV: Obtenido CSV con " . count($lines) . " líneas desde " . $parsed_url['host']);

// Devolver la respuesta al navegador
echo $response;
?>
