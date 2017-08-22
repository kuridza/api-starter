<?php
require __DIR__ . '/app.php';

$passed = 0;
$failed = 0;
function testOutput($input, $expected, $message, $request, $body) {
    global $passed, $failed;
    $p = preg_match('/' . $input . '/', $expected);
    if($p) $passed++; else $failed++;
    if (php_sapi_name() === 'cli') {
        echo $message . PHP_EOL;
        echo $request . PHP_EOL;
        echo 'Response: ' . json_encode(json_decode($body), JSON_PRETTY_PRINT) . PHP_EOL;
        echo 'Input / Expected' . PHP_EOL . $input . ' / ' . $expected . PHP_EOL;
        echo $p ? 'Test passed.' : 'Test failed';
        echo PHP_EOL . '____________________________________________' . PHP_EOL;
    } else {
        echo '<h1>' . $message . '</h1>';
        echo '<h2>' . $request . '</h2>';
        echo 'Response: <pre>' . json_encode(json_decode($body), JSON_PRETTY_PRINT) . '</pre>';
        echo 'Input / Expected <br>' . $input . ' / ' . $expected . '<br>';
        echo $p ? 'Test passed.' : 'Test failed';
        echo '<hr>';
    }
}

$uri = 'http://localhost:8000';

$client = new \GuzzleHttp\Client(['base_uri' => $uri, 'http_errors' => false]);

$response = $client->get('/books');

testOutput($response->getStatusCode(), 200, 'Ok request', 'GET /books', $response->getBody());

$response = $client->get('/books/0');

testOutput($response->getStatusCode(), 404, 'Not found request', 'GET /books/0', $response->getBody());

$response = $client->post('/books');

testOutput($response->getStatusCode(), 403, 'Unauthorized request', 'POST /books', $response->getBody());

$response = $client->post('/books', ['auth' => ['nikola', '1234']]);

testOutput($response->getStatusCode(), 500, 'Bad request', 'POST /books', $response->getBody());

$response = $client->post('/books', [
    'auth' => ['nikola', '1234'],
    'json' => ['title' => 'Telefonoteka', 'author' => 'TFT', 'year' => 2016],
]);

$id = \GuzzleHttp\json_decode($response->getBody(), true)['id'];

testOutput($response->getStatusCode(), 200, 'Insert request', 'POST /books', $response->getBody());

$response = $client->patch('/books/' . $id, [
    'auth' => ['nikola', '1234'],
    'json' => ['title' => 'TeleFonoTeka', 'author' => 'TFT', 'year' => 2017],
]);

testOutput($response->getStatusCode(), 200, 'Update request', 'PATCH /books', $response->getBody());

$response = $client->delete('/books/' . $id, ['auth' => ['nikola', '1234']]);

testOutput($response->getStatusCode(), 204, 'Delete request', 'DELETE /books', $response->getBody());

echo 'Passed test: ' . $passed . PHP_EOL;
echo 'Failed test: ' . $failed;