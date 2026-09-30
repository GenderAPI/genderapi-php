<?php

// Router for `php -S 127.0.0.1:PORT stub-server.php`: a local stand-in for the API used by
// TransportTest. The first path segment selects the scenario, e.g. /echo/api/v2/gender.

declare(strict_types=1);

$uri = $_SERVER['REQUEST_URI'];
$path = (string) parse_url($uri, PHP_URL_PATH);
$log = getenv('STUB_LOG');
if (is_string($log) && $log !== '') {
    file_put_contents($log, $_SERVER['REQUEST_METHOD'] . ' ' . $uri . "\n", FILE_APPEND | LOCK_EX);
}
$scenario = explode('/', trim($path, '/'))[0];
$examples = json_decode((string) file_get_contents(__DIR__ . '/../fixtures/openapi-v2-examples.json'), true)['examples'];

function respond(int $status, array $body, string $type = 'application/json'): void
{
    http_response_code($status);
    header('Content-Type: ' . $type);
    header('X-Request-ID: stub-request-id');
    echo json_encode($body, JSON_UNESCAPED_SLASHES);
}

switch ($scenario) {
    case 'echo':
        $body = $examples['POST /api/v2/gender 200 dataset'];
        $body['echo'] = [
            'method' => $_SERVER['REQUEST_METHOD'],
            'uri' => $uri,
            'headers' => array_change_key_case(getallheaders(), CASE_LOWER),
            'body' => file_get_contents('php://input'),
        ];
        respond(200, $body);
        break;
    case 'redirect':
        http_response_code(302);
        header('Location: http://' . $_SERVER['HTTP_HOST'] . '/target/api/v2/gender');
        header('X-Request-ID: stub-request-id');
        echo 'Moved';
        break;
    case 'target':
        respond(200, ['reached' => true]);
        break;
    case 'slow':
        sleep(2);
        respond(200, $examples['POST /api/v2/gender 200 dataset']);
        break;
    case 'ratelimit':
        header('Retry-After: 7');
        respond(429, [
            'type' => 'urn:genderapi:problem:rate_limit_exceeded',
            'title' => 'rate limit exceeded',
            'status' => 429,
            'detail' => 'Too many requests.',
            'instance' => 'urn:uuid:33333333-3333-4333-8333-333333333333',
            'code' => 'rate_limit_exceeded',
            'request_id' => '33333333-3333-4333-8333-333333333333',
            'action' => 'wait_then_retry',
        ], 'application/problem+json');
        break;
    case 'html502':
        http_response_code(502);
        header('Content-Type: text/html');
        echo '<html><body>502 Bad Gateway</body></html>';
        break;
    default:
        respond(404, ['code' => 'not_found', 'status' => 404]);
}
