<?php
include __DIR__ . '/vendor/autoload.php';

use Doctrine\DBAL\Connection;
use Symfony\Component\HttpFoundation\JsonResponse;
use \Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\RouteCollection;

$app = new \Silex\Application(['debug' => true]);

$app->register(new \Silex\Provider\DoctrineServiceProvider(), [
    'db.options' => [
        'driver' => 'pdo_sqlite',
        'path' => __DIR__ . '/api.sqlite',
    ]
]);

Symfony\Component\Debug\ExceptionHandler::register();

set_exception_handler(function($e) {
    echo json_encode(['error' => $e->getMessage()]);
});

$app->error(function (\Exception $e) {
    return new JsonResponse(['error' => $e->getMessage()]);
});

$app->before(function (Request $request) {
    if (strpos($request->headers->get('Content-Type'), 'application/json') === 0) {
        $data = json_decode($request->getContent(), true);
        $request->request->replace(is_array($data) ? $data : []);
    }
});

$app['fetch'] = $app->protect(function($id, $resource = 'book') use ($app) {
    /** @var Connection $conn */
    $conn = $app['db'];
    $book = $conn->createQueryBuilder()->select('*')->from($resource)
        ->where('id = :id')->setParameter('id', $id)->execute()->fetch();
    if(! $book) {
        $app->abort(404, 'Not found.');
    }
    return $book;
});

$check = function (Request $request, \Silex\Application $app) {
    /** @var Connection $conn */
    $conn = $app['db'];
    $user = $conn->createQueryBuilder()->select('*')->from('user')
        ->where('username = :u')->setParameter('u', $request->headers->get('php-auth-user'))
        ->execute()->fetch();
    if(! $user || ! password_verify($request->headers->get('php-auth-pw'), $user['password'])) {
        $app->abort(403, 'Not authorized.');
    }
};

$app->get('/', function(Request $request, \Silex\Application $app) {
    /** @var RouteCollection $routeCollection */
    $routeCollection = $app['routes'];
    $routes = [];
    foreach($routeCollection->getIterator() as $name => $route) {
        $routes[$name] = [
            'path' => $route->getPath(),
            'methods' => $route->getMethods(),
        ];
    }
    return new JsonResponse(['routes' => $routes]);
})->bind('api-routes');

$app->post('/register', function(Request $request, \Silex\Application $app) {
    /** @var Connection $conn */
    $conn = $app['db'];
    $conn->insert('user', [
        'username' => $request->get('username'),
        'password' => password_hash($request->get('username'), PASSWORD_DEFAULT),
    ]);
    return new JsonResponse($app['fetch']($conn->lastInsertId(), 'user'));
})->bind('register-user');

$app->get('/books', function(Request $request, \Silex\Application $app) {
    /** @var Connection $conn */
    $conn = $app['db'];
    $books = $conn->createQueryBuilder()->select('*')->from('book')
        ->where('title LIKE :t OR year = :y')
        ->setParameter('t', '%' . $request->get('title') . '%')
        ->setParameter('y', $request->get('year'))
        ->execute()->fetchAll();
    return new JsonResponse($books);
})->bind('all-books');

$app->get('/books/{id}', function(\Silex\Application $app, $id) {
    return new JsonResponse($app['fetch']($id));
})->bind('single-book');

$app->post('/books', function(Request $request, \Silex\Application $app) {
    /** @var Connection $conn */
    $conn = $app['db'];
    $conn->insert('book', $request->request->all());
    return new JsonResponse($app['fetch']($conn->lastInsertId()));
})->before($check)->bind('insert-book');

$app->patch('/books/{id}', function(Request $request, \Silex\Application $app, $id) {
    $app['fetch']($id);
    /** @var Connection $conn */
    $conn = $app['db'];
    $conn->update('book', $request->request->all(), ['id' => $id]);
    return new JsonResponse($app['fetch']($id));
})->before($check)->bind('update-book');

$app->delete('/books/{id}', function(\Silex\Application $app, $id) {
    /** @var Connection $conn */
    $conn = $app['db'];
    $conn->delete('book', ['id' => $id]);
    return new Response('', 204);
})->before($check)->bind('delete-book');

return $app;
