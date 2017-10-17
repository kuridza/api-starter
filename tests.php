<?php
require __DIR__ . '/app.php';

$passed = 0;
$failed = 0;
$output = '';
function testOutput($input, $expected, $message, $request, $body) {
    global $passed, $failed, $output;
    $p = preg_match('/' . $input . '/', $expected);
    if($p) $passed++; else $failed++;
    if (php_sapi_name() === 'cli') {
        $output .= $message . PHP_EOL;
        $output .= $request . PHP_EOL;
        $output .= 'Response: ' . json_encode(json_decode($body), JSON_PRETTY_PRINT) . PHP_EOL;
        $output .= 'Input / Expected' . PHP_EOL . $input . ' / ' . $expected . PHP_EOL;
        $output .= $p ? 'Test passed.' : 'Test failed';
        $output .= PHP_EOL . '____________________________________________' . PHP_EOL;
    } else {
        $output .= '<h1>' . $message . '</h1>';
        $output .= '<h2>' . $request . '</h2>';
        $output .= 'Response: <pre>' . json_encode(json_decode($body), JSON_PRETTY_PRINT) . '</pre>';
        $output .= 'Input / Expected <br>' . $input . ' / ' . $expected . '<br>';
        $output .= $p ? 'Test passed.' : 'Test failed';
        $output .= '<hr>';
    }
}

$uri = 'http://localhost:8000';

$client = new \GuzzleHttp\Client(['base_uri' => $uri, 'http_errors' => false]);

$response = $client->get('/book');

testOutput($response->getStatusCode(), 200, 'Ok request', 'GET /book', $response->getBody());

$response = $client->get('/book/0');

testOutput($response->getStatusCode(), 404, 'Not found request', 'GET /book/0', $response->getBody());

$response = $client->post('/book');

testOutput($response->getStatusCode(), 403, 'Unauthorized request', 'POST /book', $response->getBody());

$response = $client->post('/book', ['auth' => ['nikola', '1234']]);

testOutput($response->getStatusCode(), 500, 'Bad request', 'POST /book', $response->getBody());

$response = $client->post('/book', [
    'auth' => ['nikola', '1234'],
    'json' => ['title' => 'Telefonoteka', 'author' => 'TFT', 'year' => 2016],
]);

$id = \GuzzleHttp\json_decode($response->getBody(), true)['id'];

testOutput($response->getStatusCode(), 200, 'Insert request', 'POST /book', $response->getBody());

$response = $client->patch('/book/' . $id, [
    'auth' => ['nikola', '1234'],
    'json' => ['title' => 'TeleFonoTeka', 'author' => 'TFT', 'year' => 2017],
]);

testOutput($response->getStatusCode(), 200, 'Update request', 'PATCH /book', $response->getBody());

$response = $client->delete('/book/' . $id, ['auth' => ['nikola', '1234']]);

testOutput($response->getStatusCode(), 204, 'Delete request', 'DELETE /book', $response->getBody());

echo 'Passed test: ' . $passed . PHP_EOL;
echo 'Failed test: ' . $failed . '<hr>';

echo $output;
