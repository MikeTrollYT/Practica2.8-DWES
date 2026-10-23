<?php

declare(strict_types=1);

use App\Controllers\AuthController;
use App\Controllers\CommentController;
use App\Controllers\PostController;
use App\Core\Database;
use App\Core\Router;
use App\Models\Comment;
use App\Models\Post;
use App\Models\User;

require __DIR__ . '/../vendor/autoload.php';

session_start();

// Una sola conexión compartida por todos los modelos.
$db = new Database();

$userModel    = new User($db);
$postModel    = new Post($db);
$commentModel = new Comment($db);

$auth     = new AuthController($userModel);
$posts    = new PostController($postModel, $commentModel);
$comments = new CommentController($commentModel, $postModel);

$router = new Router();

// ---- Anuncios ----
$router->get('/',                   [$posts, 'index']);
$router->get('/posts',              [$posts, 'index']);
$router->get('/posts/create',       [$posts, 'create']);
$router->post('/posts/store',       [$posts, 'store']);
$router->get('/posts/{id}',         [$posts, 'show']);
$router->get('/posts/edit/{id}',    [$posts, 'edit']);
$router->post('/posts/update/{id}', [$posts, 'update']);
$router->post('/posts/delete/{id}', [$posts, 'destroy']);

// ---- Comentarios (el {id} es el del anuncio comentado) ----
$router->post('/comments/store/{id}', [$comments, 'store']);

// ---- Autenticación ----
$router->get('/register',  [$auth, 'showRegisterForm']);
$router->post('/register', [$auth, 'register']);
$router->get('/login',     [$auth, 'showLoginForm']);
$router->post('/login',    [$auth, 'login']);
$router->get('/logout',    [$auth, 'logout']);

// REQUEST_URI es la URL pedida por el navegador, p. ej. "/posts/delete/3".
$router->dispatch($_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI']);