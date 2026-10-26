<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Csrf;
use App\Models\Comment;
use App\Models\Post;

class PostController
{
    /**
     * Recibe los modelos que permiten trabajar con anuncios y comentarios.
     */
    public function __construct(
        private Post $postModel,
        private Comment $commentModel
    ) {
    }

    /**
     * Muestra el listado de anuncios.
     *
     * Si la URL incluye ?search=..., solo se muestran los anuncios que
     * coinciden con el texto buscado. Si no se escribe nada, se muestran todos.
     */
    public function index(): void
    {
        $search = trim($_GET['search'] ?? '');

        // El operador ternario elige entre obtener todos los anuncios
        // o realizar una búsqueda.
        $posts = $search === ''
            ? $this->postModel->getAll()
            : $this->postModel->search($search);

        // Se envían los anuncios y el texto buscado a la vista.
        $this->render('posts/index', ['posts' => $posts ?? [], 'search' => $search]);
    }

    /**
     * Muestra un anuncio concreto junto con sus comentarios.
     *
     * El parámetro $id identifica el anuncio. Por ejemplo, /posts/3
     * muestra el anuncio cuyo identificador es 3.
     */
    public function show(int $id): void
    {
        $post = $this->postModel->getById($id);

        // Si no se encuentra el anuncio, se devuelve un error 404.
        if ($post === null) {
            $this->abort(404, 'El anuncio no existe.');
        }

        // canEdit indica a la vista si el usuario actual es el autor.
        $this->render('posts/show', [
            'post'     => $post,
            'canEdit'  => $this->isOwner($post),
            'comments' => $this->commentModel->getByPostId((int) $post['id']) ?? [],
        ]);
    }

    /**
     * Muestra el formulario para crear un anuncio nuevo.
     *
     * Solo los usuarios que han iniciado sesión pueden acceder a este
     * formulario.
     */
    public function create(): void
    {
        $this->requireLogin();

        // Se envían valores vacíos porque todavía no existe ningún anuncio.
        $this->render('posts/form', [
            'post'       => ['title' => '', 'content' => '', 'category' => ''],
            'heading'    => 'Nuevo anuncio',
            'formAction' => '/posts/store',
        ]);
    }

    /**
     * Valida y guarda un anuncio nuevo.
     *
     * El autor se obtiene de la sesión, de forma que el usuario no pueda
     * indicar desde el formulario que el anuncio pertenece a otra persona.
     */
    public function store(): void
    {
        $this->requireValidCsrfToken();
        $this->requireLogin();

        // Lee los campos del formulario y comprueba que son válidos.
        $data  = $this->readFormData();
        $error = $this->validate($data);

        // Si hay un error, se vuelve a mostrar el formulario conservando
        // los datos escritos por el usuario.
        if ($error !== null) {
            $this->render('posts/form', [
                'post'       => $data,
                'error'      => $error,
                'heading'    => 'Nuevo anuncio',
                'formAction' => '/posts/store',
            ]);
            return;
        }

        // El autor sale siempre de la sesión, nunca del formulario.
        $newId = $this->postModel->insert(array_merge($data, [
            'user_id' => (int) $_SESSION['user_id'],
        ]));

        // Después de guardar, se redirige al anuncio recién creado.
        header('Location: /posts/' . (int) $newId);
        exit;
    }

    /**
     * Muestra el formulario para editar un anuncio existente.
     *
     * loadOwnedPost comprueba que el usuario esté conectado y que sea
     * el propietario del anuncio.
     */
    public function edit(int $id): void
    {
        $post = $this->loadOwnedPost($id);

        $this->render('posts/form', [
            'post'       => $post,
            'heading'    => 'Editar anuncio',
            'formAction' => '/posts/update/' . (int) $post['id'],
        ]);
    }

    /**
     * Valida y guarda los cambios de un anuncio.
     *
     * Solo puede modificarlo el usuario que lo creó.
     */
    public function update(int $id): void
    {
        $this->requireValidCsrfToken();

        $post = $this->loadOwnedPost($id);

        // Lee y valida los nuevos datos antes de modificar la base de datos.
        $data  = $this->readFormData();
        $error = $this->validate($data);

        // Si los datos no son correctos, se conserva lo escrito y se muestra
        // de nuevo el formulario con el mensaje correspondiente.
        if ($error !== null) {
            $this->render('posts/form', [
                'post'       => $data,
                'error'      => $error,
                'heading'    => 'Editar anuncio',
                'formAction' => '/posts/update/' . (int) $post['id'],
            ]);
            return;
        }

        // Actualiza el anuncio utilizando su identificador.
        $this->postModel->update((int) $post['id'], $data);

        header('Location: /posts/' . (int) $post['id']);
        exit;
    }

    /**
     * Elimina un anuncio.
     *
     * La operación requiere un token CSRF válido y que el usuario sea
     * el propietario del anuncio.
     */
    public function destroy(int $id): void
    {
        $this->requireValidCsrfToken();

        $post = $this->loadOwnedPost($id);

        // Borra el anuncio usando el identificador de la base de datos.
        $this->postModel->delete((int) $post['id']);

        // Guarda un mensaje para mostrarlo después de la redirección.
        $_SESSION['flash'] = 'Anuncio eliminado.';
        header('Location: /posts');
        exit;
    }

    // ------------------------------------------------------------
    // Utilidades privadas
    // ------------------------------------------------------------

    /**
     * Comprueba que el formulario contiene un token CSRF válido.
     */
    private function requireValidCsrfToken(): void
    {
        if (!Csrf::isValid()) {
            $this->abort(403, 'Petición no válida (token CSRF incorrecto). Recarga la página e inténtalo de nuevo.');
        }
    }

    /**
     * Comprueba que hay un usuario identificado en la sesión.
     *
     * Si no lo hay, guarda un mensaje temporal y lo envía al login.
     */
    private function requireLogin(): void
    {
        if (!isset($_SESSION['user_id'])) {
            $_SESSION['flash'] = 'Debes iniciar sesión para hacer esto.';
            header('Location: /login');
            exit;
        }
    }

    /**
     * Busca un anuncio que pertenezca al usuario actual.
     *
     * Se utiliza antes de editar, actualizar o borrar. Comprueba que el
     * usuario haya iniciado sesión, que el anuncio exista y que sea suyo.
     * Si alguna comprobación falla, se detiene la petición.
     */
    private function loadOwnedPost(int $id): array
    {
        $this->requireLogin();

        $post = $this->postModel->getById($id);

        // 404 significa que el anuncio solicitado no existe.
        if ($post === null) {
            $this->abort(404, 'El anuncio no existe.');
        }

        // 403 significa que existe, pero el usuario no tiene permiso.
        if (!$this->isOwner($post)) {
            $this->abort(403, 'No tienes permiso para hacer esto.');
        }

        return $post;
    }

    /**
     * Indica si el anuncio pertenece al usuario conectado.
     */
    private function isOwner(array $post): bool
    {
        return isset($_SESSION['user_id'])
            && (int) $post['user_id'] === (int) $_SESSION['user_id'];
    }

    /**
     * Lee y limpia los campos enviados por el formulario.
     *
     * Devuelve un array con los datos preparados para validar o guardar.
     */
    private function readFormData(): array
    {
        return [
            'title'    => trim($_POST['title'] ?? ''),
            'content'  => trim($_POST['content'] ?? ''),
            'category' => trim($_POST['category'] ?? ''),
        ];
    }

    /**
     * Comprueba que los datos del anuncio cumplen las reglas del formulario.
     *
     * Devuelve null si todo es correcto o un mensaje de error si hay
     * algún campo vacío o demasiado largo.
     */
    private function validate(array $data): ?string
    {
        // Los tres campos son obligatorios.
        if ($data['title'] === '' || $data['content'] === '' || $data['category'] === '') {
            return 'Rellena el título, la categoría y el contenido.';
        }

        // mb_strlen cuenta caracteres correctamente, también con tildes
        // y otros caracteres que ocupan más de un byte.
        if (mb_strlen($data['title']) > 150 || mb_strlen($data['category']) > 50) {
            return 'El título admite 150 caracteres como máximo y la categoría 50.';
        }

        // null indica que no se ha encontrado ningún error.
        return null;
    }

    /**
     * Muestra una página de error y detiene la ejecución.
     *
     * @param int $code Código HTTP del error, como 403 o 404.
     * @param string $message Texto explicativo que verá el usuario.
     */
    private function abort(int $code, string $message): never
    {
        http_response_code($code);
        $this->render('error', ['code' => $code, 'message' => $message]);
        exit;
    }

    /**
     * Carga una plantilla y le pasa los datos que necesita.
     *
     * @param string $view Nombre de la vista, sin la extensión ".php".
     * @param array $data Datos que estarán disponibles dentro de la vista.
     */
    private function render(string $view, array $data = []): void
    {
        // Convierte las claves del array en variables utilizables por la vista.
        extract($data);
        require __DIR__ . '/../Views/' . $view . '.php';
    }
}