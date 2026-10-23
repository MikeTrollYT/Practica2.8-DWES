<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Csrf;
use App\Models\Comment;
use App\Models\Post;

class CommentController
{
    public function __construct(
        private Comment $commentModel,
        private Post $postModel
    ) {
    }

    /** Guarda un comentario en el anuncio $id: POST /comments/store/3 */
    public function store(int $id): void
    {
        $this->requireValidCsrfToken();
        $this->requireLogin();

        if ($this->postModel->getById($id) === null) {
            $this->abort(404, 'El anuncio no existe.');
        }

        $content = trim($_POST['content'] ?? '');

        if ($content === '') {
            $_SESSION['flash'] = 'El comentario no puede estar vacío.';
            header('Location: /posts/' . $id . '#comments');
            exit;
        }

        $this->commentModel->insert([
            'post_id' => $id,
            // El autor sale SIEMPRE de la sesión, nunca del formulario.
            'user_id' => (int) $_SESSION['user_id'],
            'content' => $content,
        ]);

        header('Location: /posts/' . $id . '#comments');
        exit;
    }

    private function requireValidCsrfToken(): void
    {
        if (!Csrf::isValid()) {
            $this->abort(403, 'Petición no válida (token CSRF incorrecto). Recarga la página e inténtalo de nuevo.');
        }
    }

    private function requireLogin(): void
    {
        if (!isset($_SESSION['user_id'])) {
            $_SESSION['flash'] = 'Debes iniciar sesión para comentar.';
            header('Location: /login');
            exit;
        }
    }

    private function abort(int $code, string $message): never
    {
        http_response_code($code);
        extract(['code' => $code, 'message' => $message]);
        require __DIR__ . '/../Views/error.php';
        exit;
    }
}