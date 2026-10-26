<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Csrf;
use App\Models\Comment;
use App\Models\Post;

class CommentController
{
    /**
     * Recibe los modelos que este controlador necesita.
     *
     * Comment se ocupa de guardar comentarios y Post de consultar anuncios.
     */
    public function __construct(
        private Comment $commentModel,
        private Post $postModel
    ) {
    }

    /**
     * Guarda un comentario en el anuncio indicado.
     *
     * El número recibido en $id identifica el anuncio. Antes de guardar
     * el comentario se comprueba que la petición sea válida, que el usuario
     * haya iniciado sesión y que el anuncio exista.
     *
     * Ejemplo: POST /comments/store/3 guarda un comentario en el anuncio 3.
     */
    public function store(int $id): void
    {
        // Comprueba que el formulario procede de nuestra aplicación.
        $this->requireValidCsrfToken();

        // Solo los usuarios identificados pueden escribir comentarios.
        $this->requireLogin();

        // Si el anuncio no existe, se responde con el error HTTP 404.
        if ($this->postModel->getById($id) === null) {
            $this->abort(404, 'El anuncio no existe.');
        }

        // Se eliminan los espacios sobrantes del texto enviado por el formulario.
        $content = trim($_POST['content'] ?? '');

        // No se permiten comentarios vacíos. El mensaje flash se mostrará
        // después de volver a la página del anuncio.
        if ($content === '') {
            $_SESSION['flash'] = 'El comentario no puede estar vacío.';
            header('Location: /posts/' . $id . '#comments');
            exit;
        }

        // Guarda el comentario relacionándolo con el anuncio y con el usuario.
        $this->commentModel->insert([
            'post_id' => $id,
            // El autor sale siempre de la sesión, nunca de un dato enviado
            // por el formulario, para evitar suplantar a otro usuario.
            'user_id' => (int) $_SESSION['user_id'],
            'content' => $content,
        ]);

        // Vuelve al anuncio y coloca el navegador en la zona de comentarios.
        header('Location: /posts/' . $id . '#comments');
        exit;
    }

    /**
     * Comprueba que el formulario contiene un token CSRF correcto.
     *
     * Si el token no es válido, se detiene la petición y se muestra un error
     * HTTP 403, que significa que el servidor rechaza la petición.
     */
    private function requireValidCsrfToken(): void
    {
        if (!Csrf::isValid()) {
            $this->abort(403, 'Petición no válida (token CSRF incorrecto). Recarga la página e inténtalo de nuevo.');
        }
    }

    /**
     * Comprueba que existe un usuario identificado en la sesión.
     *
     * Si no hay usuario conectado, guarda un mensaje temporal y lo envía
     * a la página de inicio de sesión.
     */
    private function requireLogin(): void
    {
        if (!isset($_SESSION['user_id'])) {
            $_SESSION['flash'] = 'Debes iniciar sesión para comentar.';
            header('Location: /login');
            exit;
        }
    }

    /**
     * Muestra una página de error y termina la ejecución.
     *
     * @param int $code Código HTTP del error, por ejemplo 404 o 403.
     * @param string $message Explicación que se mostrará al usuario.
     */
    private function abort(int $code, string $message): never
    {
        http_response_code($code);

        // Estas variables estarán disponibles en la plantilla error.php.
        extract(['code' => $code, 'message' => $message]);
        require __DIR__ . '/../Views/error.php';
        exit;
    }
}