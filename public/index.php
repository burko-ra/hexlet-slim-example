<?php

require __DIR__ . '/../vendor/autoload.php';

use Slim\Factory\AppFactory;
use DI\Container;
use Slim\Middleware\MethodOverrideMiddleware;

$container = new Container();
$container->set('renderer', function () {
    // Параметром передается базовая директория, в которой будут храниться шаблоны
    return new \Slim\Views\PhpRenderer(__DIR__ . '/../templates');
});
$container->set('flash', function () {
    return new \Slim\Flash\Messages();
});

$app = AppFactory::createFromContainer($container);
$app->addErrorMiddleware(true, true, true);
$app->add(MethodOverrideMiddleware::class);

$router = $app->getRouteCollector()->getRouteParser();

session_start();

$app->get('/', function ($request, $response) {
    return $this->get('renderer')->render($response, 'index.phtml');
});

$app->get('/users', function ($request, $response) {
    $flash = $this->get('flash')->getMessages();
    $users = $_SESSION['users'] ?? [];

    $activeUser = $_SESSION['activeUser'];
    $term = $request->getQueryParam('term');

    $filteredUsers = collect($users)
        ->filter(function ($user) use ($term) {
            if (!$term) {
                return true;
            }
            return str_contains(strtolower($user['name']), strtolower($term));
        })
        ->all();

    $params = ['users' => $filteredUsers, 'term' => $term, 'flash' => $flash, 'activeUser' => $activeUser];
    return $this->get('renderer')->render($response, 'users/index.phtml', $params);
})->setName('users');

$app->post('/users', function ($request, $response) use ($router) {
    $user = $request->getParsedBodyParam('user');
    $validator = new Validator();
    $errors = $validator->validate($user);

    if (count($errors) !== 0) {
        $params = ['user' => $user, 'errors' => $errors];
        return $this->get('renderer')->render($response, "users/new.phtml", $params);
    }

    $users = $_SESSION['users'] ?? [];
    if (count($users) !== 0) {
        $keys = array_map(fn($item) => $item['id'], $users);
        $id = (int) max($keys) + 1;
    } else {
        $id = 1;
    }
    $user['id'] = $id;

    $_SESSION['users'][] = $user;
    $this->get('flash')->addMessage('success', 'User was added successfully');
    return $response->withRedirect($router->urlFor('users'), 302);
});

$app->get('/users/new', function ($request, $response) {
    $params = [
        'user' => ['id' => '', 'name' => '', 'email' => '', 'password' => '', 'passwordConfirmation' => '', 'city' => ''],
        'errors' => []
    ];
    return $this->get('renderer')->render($response, "users/new.phtml", $params);
});

$app->get('/login', function ($request, $response) {
    $params = [
        'user' => ['email' => ''],
        'errors' => []
    ];
    return $this->get('renderer')->render($response, "users/login.phtml", $params);
});

$app->post('/login', function ($request, $response) use ($router) {
    $guest = $request->getParsedBodyParam('user');
    $email = $guest['email'];

    $users = $_SESSION['users'] ?? [];

    $filteredUsers = collect($users)
        ->first(fn($item) => $item['email'] == $email);

    $errors = [];

    if (empty($filteredUsers))  {
        $errors['email'] = 'Пользователя с таким email не существует';
        $params = [
            'user' => ['email' => $email],
            'errors' => $errors
        ];
        return $this->get('renderer')->render($response, "users/login.phtml", $params);
    }
    $activeUser = $filteredUsers;
    $_SESSION['activeUser'] = $activeUser;
    return $response->withRedirect($router->urlFor('users'), 302); 
});

$app->post('/logout', function ($request, $response) use ($router) {
    $activeUser = [];
    $_SESSION['activeUser'] = $activeUser;
    return $response->withRedirect($router->urlFor('users'), 302); 
});

$app->get('/users/{id}', function ($request, $response, array $args) {
    $id = $args['id'];

    $users = $_SESSION['users'] ?? [];

    $user = collect($users)
        ->first(fn($item) => $item['id'] == $id);

    if (empty($user)) {
        return $response->withStatus(404);
    }
    $params = ['user' => $user];
    return $this->get('renderer')->render($response, 'users/show.phtml', $params);
})->setName('user');

$app->get('/users/{id}/edit', function ($request, $response, array $args) {
    $id = $args['id'];

    $users = $_SESSION['users'] ?? [];

    $user = collect($users)
        ->first(fn($item) => $item['id'] == $id);

    if (empty($user)) {
        return $response->withStatus(404);
    }
    $params = ['user' => $user, 'errors' => []];
    return $this->get('renderer')->render($response, 'users/edit.phtml', $params);
});

$app->patch('/users/{id}', function ($request, $response, array $args) use ($router) {
    $id = $args['id'];

    $users = $_SESSION['users'] ?? [];

    $user = collect($users)
        ->first(fn($item) => $item['id'] == $id);

    if (empty($user)) {
        return $response->withStatus(404);
    }

    $data = $request->getParsedBodyParam('user');

    $validator = new Validator();

    $errors = $validator->validate($data);

    if (count($errors) === 0) {
        $user['name'] = $data['name'];
        $user['email'] = $data['email'];
        $user['password'] = $data['password'];
        $user['passwordConfirmation'] = $data['passwordConfirmation'];
        $user['city'] = $data['city'];

        $users = array_map(function ($item) use ($user) {
            if ($item['id'] == $user['id']) {
                return $user;
            }
            return $item;
        }, $users);

        $_SESSION['users'] = $users;

        $this->get('flash')->addMessage('success', 'User data was updated successfully');
        return $response->withRedirect($router->urlFor('user', ['id' => $id]), 302);
    }

    $params = ['user' => $user, 'errors' => $errors];
    return $this->get('renderer')->render($response, 'users/edit.phtml', $params);
});

$app->get('/users/{id}/delete', function ($request, $response, array $args) {
    $id = $args['id'];

    $users = $_SESSION['users'] ?? [];

    $user = collect($users)
        ->first(fn($item) => $item['id'] == $id);

    if (empty($user)) {
        return $response->withStatus(404);
    }
    $params = ['user' => $user];
    return $this->get('renderer')->render($response, 'users/delete.phtml', $params);
});

$app->delete('/users/{id}', function ($request, $response, array $args) {
    $id = $args['id'];

    $users = $_SESSION['users'] ?? [];

    $user = collect($users)
        ->first(fn($item) => $item['id'] == $id);

    if (empty($user)) {
        return $response->withStatus(404)->write('here');
    }

    $users = collect($users)
        ->filter(fn($item) => $item['id'] !== $user['id'])
        ->all();
    
    $_SESSION['users'] = $users;
    $this->get('flash')->addMessage('success', 'User was removed successfully');
    return $response->withRedirect("/users", 302);
});

$app->run();

