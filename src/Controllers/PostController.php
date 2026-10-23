<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Csrf;
use App\Models\Comment;
use App\Models\Post;

class PostController
{
    public function __construct(
        private Post $postModel,
        private Comment $commentModel
    ) {
    }

    /** Listado de anuncios, filtrado si llega ?search=... */
    public function index(): void
    {
        $search = trim($_GET['search'] ?? '');

        $posts = $search === ''
            ? $this->postModel->getAll()
            : $this->postModel->search($search);

        $this->render('posts/index', ['posts' => $posts ?? [], 'search' => $search]);
    }

    /** Un anuncio con sus comentarios: /posts/3 */
    public function show(int $id): void
    {
        $post = $this->postModel->getById($id);

        if ($post === null) {
            $this->abort(404, 'El anuncio no existe.');
        }

        $this->render('posts/show', [
            'post'     => $post,
            'canEdit'  => $this->isOwner($post),
            'comments' => $this->commentModel->getByPostId((int) $post['id']) ?? [],
        ]);
    }

    /** Formulario de creación (solo usuarios logueados). */
    public function create(): void
    {
        $this->requireLogin();

        $this->render('posts/form', [
            'post'       => ['title' => '', 'content' => '', 'category' => ''],
            'heading'    => 'Nuevo anuncio',
            'formAction' => '/posts/store',
        ]);
    }

    /** Guarda el anuncio nuevo (solo usuarios logueados). */
    public function store(): void
    {
        $this->requireValidCsrfToken();
        $this->requireLogin();

        $data  = $this->readFormData();
        $error = $this->validate($data);

        if ($error !== null) {
            $this->render('posts/form', [
                'post'       => $data,
                'error'      => $error,
                'heading'    => 'Nuevo anuncio',
                'formAction' => '/posts/store',
            ]);
            return;
        }

        // El autor sale SIEMPRE de la sesión, nunca del formulario.
        $newId = $this->postModel->insert(array_merge($data, [
            'user_id' => (int) $_SESSION['user_id'],
        ]));

        header('Location: /posts/' . (int) $newId);
        exit;
    }

    /** Formulario de edición (solo el autor): /posts/edit/3 */
    public function edit(int $id): void
    {
        $post = $this->loadOwnedPost($id);

        $this->render('posts/form', [
            'post'       => $post,
            'heading'    => 'Editar anuncio',
            'formAction' => '/posts/update/' . (int) $post['id'],
        ]);
    }

    /** Guarda los cambios (solo el autor): POST /posts/update/3 */
    public function update(int $id): void
    {
        $this->requireValidCsrfToken();

        $post = $this->loadOwnedPost($id);

        $data  = $this->readFormData();
        $error = $this->validate($data);

        if ($error !== null) {
            $this->render('posts/form', [
                'post'       => $data,
                'error'      => $error,
                'heading'    => 'Editar anuncio',
                'formAction' => '/posts/update/' . (int) $post['id'],
            ]);
            return;
        }

        $this->postModel->update((int) $post['id'], $data);

        header('Location: /posts/' . (int) $post['id']);
        exit;
    }

    /** Elimina el anuncio (solo el autor): POST /posts/delete/3 */
    public function destroy(int $id): void
    {
        $this->requireValidCsrfToken();

        $post = $this->loadOwnedPost($id);

        $this->postModel->delete((int) $post['id']);

        $_SESSION['flash'] = 'Anuncio eliminado.';
        header('Location: /posts');
        exit;
    }

    // ------------------------------------------------------------
    // Utilidades privadas
    // ------------------------------------------------------------

    private function requireValidCsrfToken(): void
    {
        if (!Csrf::isValid()) {
            $this->abort(403, 'Petición no válida (token CSRF incorrecto). Recarga la página e inténtalo de nuevo.');
        }
    }

    private function requireLogin(): void
    {
        if (!isset($_SESSION['user_id'])) {
            $_SESSION['flash'] = 'Debes iniciar sesión para hacer esto.';
            header('Location: /login');
            exit;
        }
    }

    /**
     * Para edit/update/destroy: exige login, comprueba que el anuncio existe
     * y que pertenece al usuario de la sesión. Si algo falla, corta aquí.
     */
    private function loadOwnedPost(int $id): array
    {
        $this->requireLogin();

        $post = $this->postModel->getById($id);

        if ($post === null) {
            $this->abort(404, 'El anuncio no existe.');
        }

        if (!$this->isOwner($post)) {
            $this->abort(403, 'No tienes permiso para hacer esto.');
        }

        return $post;
    }

    private function isOwner(array $post): bool
    {
        return isset($_SESSION['user_id'])
            && (int) $post['user_id'] === (int) $_SESSION['user_id'];
    }

    private function readFormData(): array
    {
        return [
            'title'    => trim($_POST['title'] ?? ''),
            'content'  => trim($_POST['content'] ?? ''),
            'category' => trim($_POST['category'] ?? ''),
        ];
    }

    private function validate(array $data): ?string
    {
        if ($data['title'] === '' || $data['content'] === '' || $data['category'] === '') {
            return 'Rellena el título, la categoría y el contenido.';
        }

        if (mb_strlen($data['title']) > 150 || mb_strlen($data['category']) > 50) {
            return 'El título admite 150 caracteres como máximo y la categoría 50.';
        }

        return null;
    }

    private function abort(int $code, string $message): never
    {
        http_response_code($code);
        $this->render('error', ['code' => $code, 'message' => $message]);
        exit;
    }

    private function render(string $view, array $data = []): void
    {
        extract($data);
        require __DIR__ . '/../Views/' . $view . '.php';
    }
}