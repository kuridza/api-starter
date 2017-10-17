<?php
include __DIR__ . '/vendor/autoload.php';

use Doctrine\DBAL\Connection;
use Silex\Application;
use Symfony\Component\HttpFoundation\JsonResponse;
use \Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\RouteCollection;

$app = new Application(['debug' => true]);

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
    if (preg_match('/json/', $request->headers->get('Content-Type'))) {
        $data = json_decode($request->getContent(), true);
        $request->request->replace(is_array($data) ? $data : []);
    }
});

$app['fetch'] = $app->protect(function($id, $resource) use ($app) {
    /** @var Connection $conn */
    $conn = $app['db'];
    $item = $conn->createQueryBuilder()->select('*')->from($resource)
        ->where('id = :id')->setParameter('id', $id)->execute()->fetch();
    if(! $item) {
        $app->abort(404, 'Not found.');
    }
    return $item;
});

$check = function (Request $request, Application $app) {
    /** @var Connection $conn */
    $conn = $app['db'];
    $user = $conn->createQueryBuilder()->select('*')->from('user')
        ->where('username = :u')->setParameter('u', $request->headers->get('php-auth-user'))
        ->execute()->fetch();
    if(! $user || ! password_verify($request->headers->get('php-auth-pw'), $user['password'])) {
        $app->abort(403, 'Not authorized.');
    }
};

$app->get('/', function(Request $request, Application $app) {
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

$app->post('/register', function(Request $request, Application $app) {
    /** @var Connection $conn */
    $conn = $app['db'];
    $conn->insert('user', [
        'username' => $request->get('username'),
        'password' => password_hash($request->get('username'), PASSWORD_DEFAULT),
    ]);
    return new JsonResponse($app['fetch']($conn->lastInsertId(), 'user'));
})->bind('register-user');

$app->get('/{resource}', function(Request $request, Application $app, $resource) {
    /** @var Connection $conn */
    $conn = $app['db'];
    $items = $conn->createQueryBuilder()->select('*')->from($resource);

    $fields = $conn->getSchemaManager()->listTableColumns($resource);
    $fields = array_keys($fields);

    foreach($request->query->get('filter', []) as $key => $value) {
        if(! in_array($key, $fields)) { continue; }
        $items->andWhere($key . ' LIKE :v')
            ->setParameter('v', '%' . $value . '%');
    }
    $max = 10;
    $items->setMaxResults($max)->setFirstResult(($request->get('page', 1) - 1) * $max);
    $items = $items->execute()->fetchAll();
    return new JsonResponse($items);
})->bind('all');

$app->get('/{resource}/{id}', function(Application $app, $resource, $id) {
    return new JsonResponse($app['fetch']($id, $resource));
})->bind('single');

$app->post('/{resource}', function(Request $request, Application $app, $resource) {
    /** @var Connection $conn */
    $conn = $app['db'];
    $conn->insert($resource, $request->request->all());
    return new JsonResponse($app['fetch']($conn->lastInsertId(), $resource));
})->before($check)->bind('insert');

$app->post('/batch/{resource}', function(Request $request, Application $app, $resource) {
    /** @var Connection $conn */
    $conn = $app['db'];
    $inserted = [];
    foreach ($request->request->all() as $row) {
        $conn->insert($resource, $row);
        $inserted[] = $app['fetch']($conn->lastInsertId(), $resource);
    }
    return new JsonResponse($inserted);
})->before($check)->bind('batch-insert');

$app->patch('/batch/{resource}', function(Request $request, Application $app, $resource) {
    /** @var Connection $conn */
    $conn = $app['db'];
    $updated = [];
    foreach ($request->request->all() as $row) {
        $conn->update($resource, $row, ['id' => $row['id']]);
        $updated[] = $app['fetch']($row['id'], $resource);
    }
    return new JsonResponse($updated);
})->before($check)->bind('batch-update');

$app->patch('/{resource}/{id}', function(Request $request, Application $app, $resource, $id) {
    /** @var Connection $conn */
    $conn = $app['db'];
    $conn->update($resource, $request->request->all(), ['id' => $id]);
    return new JsonResponse($app['fetch']($id, $resource));
})->before($check)->bind('update');

$app->delete('/batch/{resource}', function(Application $app, Request $request, $resource) {
    /** @var Connection $conn */
    $conn = $app['db'];
    foreach ($request->request->all() as $row) {
        $conn->delete($resource, ['id' => $row['id']]);
    }
    return new Response('', 204);
})->before($check)->bind('batch-delete');

$app->delete('/{resource}/{id}', function(Application $app, $resource, $id) {
    /** @var Connection $conn */
    $conn = $app['db'];
    $conn->delete($resource, ['id' => $id]);
    return new Response('', 204);
})->before($check)->bind('delete');

$app->post('/create/{resource}', function(Application $app, Request $request, $resource) {
    /** @var Connection $conn */
    $conn = $app['db'];
    $schema = new \Doctrine\DBAL\Schema\Schema();
    $table = $schema->createTable($resource);
    $table->addColumn('id', 'integer');
    $table->setPrimaryKey(['id']);
    foreach ($request->request->all() as $column => $type) {
        $table->addColumn($column, $type);
    }
    $queries = $schema->toSql(new \Doctrine\DBAL\Platforms\SqlitePlatform());
    foreach ($queries as $query) {
        $conn->exec($query);
    }
    return new JsonResponse($request->request->all());
});

return $app;
