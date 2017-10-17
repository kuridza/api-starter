<?php
require __DIR__ . '/app.php';

$uri = 'http://localhost:8000';
$client = new \GuzzleHttp\Client(['base_uri' => $uri, 'http_errors' => false]);

$passed = 0;
$failed = 0;
$output = '';
$body = '';
function testOutput($message, $method, $path, $data = [], $expected = 200) {
    global $passed, $failed, $output, $client, $body;
    $response = $client->{$method}($path, $data);
    $input = $response->getStatusCode();
    $body = $response->getBody();
    $body = json_decode($body, true);
    $p = preg_match('/' . $input . '/', $expected);
    if($p) $passed++; else $failed++;
    if (php_sapi_name() === 'cli') {
        $output .= $message . PHP_EOL;
        $output .= $method . ' ' . $path . PHP_EOL;
        $output .= 'Response: ' . json_encode($body, JSON_PRETTY_PRINT) . PHP_EOL;
        $output .= 'Input / Expected' . PHP_EOL . $input . ' / ' . $expected . PHP_EOL;
        $output .= $p ? 'Test passed.' : 'Test failed';
        $output .= PHP_EOL . '____________________________________________' . PHP_EOL;
    } else {
        $output .= '<h1>' . $message . '</h1>';
        $output .= '<h2>' . $method . ' ' . $path . '</h2>';
        $output .= 'Response: <pre>' . json_encode($body, JSON_PRETTY_PRINT) . '</pre>';
        $output .= 'Input / Expected <br>' . $input . ' / ' . $expected . '<br>';
        $output .= $p ? 'Test passed.' : 'Test failed';
        $output .= '<hr>';
    }
}

testOutput('Ok request', 'GET', '/book');
testOutput('Not found request', 'GET', '/book/0', [], 404);
testOutput('Unauthorized request', 'POST', '/book', [], 403);
testOutput('Bad request', 'POST', '/book', ['auth' => ['nikola', '1234']], 500);

testOutput('Insert request', 'POST', '/book', [
    'auth' => ['nikola', '1234'],
    'json' => ['title' => 'Telefonoteka', 'author' => 'TFT', 'year' => 2016],
]);

$id = $body['id'];

testOutput('Update request', 'PATCH', '/book/' . $id, [
    'auth' => ['nikola', '1234'],
    'json' => ['title' => 'TeleFonoTeka', 'author' => 'TFT', 'year' => 2017],
]);

testOutput('Delete request', 'DELETE', '/book/' . $id, ['auth' => ['nikola', '1234']], 204);

testOutput('Batch insert request', 'POST', '/batch/book', [
    'auth' => ['nikola', '1234'],
    'json' => [
        ['title' => 'Telefonoteka', 'author' => 'TFT', 'year' => 2016],
        ['title' => 'Telefonoteka', 'author' => 'TFT', 'year' => 2016],
        ['title' => 'Telefonoteka', 'author' => 'TFT', 'year' => 2016],
    ],
]);

$ids = array_column($body, 'id');

testOutput('Batch update request', 'PATCH', '/batch/book', [
    'auth' => ['nikola', '1234'],
    'json' => [
        ['id' => $ids[0], 'title' => 'Telefonoteka1', 'author' => 'TFT', 'year' => 2016],
        ['id' => $ids[1], 'title' => 'Telefonoteka2', 'author' => 'LCD', 'year' => 2016],
        ['id' => $ids[2], 'title' => 'Telefonoteka3', 'author' => 'OSD'],
    ],
]);

testOutput('Batch delete request', 'DELETE', '/batch/book', [
    'auth' => ['nikola', '1234'],
    'json' => [
        ['id' => $ids[0]],
        ['id' => $ids[1]],
        ['id' => $ids[2]],
    ],
], 204);

echo 'Passed test: ' . $passed . PHP_EOL;
echo 'Failed test: ' . $failed . '<hr>';

echo $output;
