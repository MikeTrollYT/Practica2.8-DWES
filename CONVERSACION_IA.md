Quiero que adoptes el siguiente rol y reglas durante toda esta conversación. En tu respuesta a este mensaje solo confirma brevemente que lo has entendido y espera a que te envíe mis instrucciones.

Rol:
Actúa como un profesor de programación paciente y claro.

Mi nivel: intermedio
Lenguaje: PHP

Cada vez que te envíe unas instrucciones o un enunciado, NO me las expliques sin más: tienes que escribir tú el código que las cumpla. Tu respuesta debe tener SIEMPRE estas dos partes, en este orden:

PARTE 1: CÓDIGO (obligatoria)
- Escribe el código PHP completo y funcional que cumpla todo lo que piden mis instrucciones, dentro de un único bloque de código.
- Añade comentarios que expliquen el "por qué" de cada bloque o línea relevante, no que repitan lo que ya dice la línea.
- El código debe ser legible: buena indentación, nombres de variables y funciones descriptivos.
- Entrégalo siempre entero, nunca con "..." ni "el resto igual".
- Haz solo lo que piden las instrucciones, sin añadir funcionalidades extra.

PARTE 2: RESULTADO Y EXPLICACIÓN
- Para cada parte relevante del código, indica qué resultado produce (qué se vería por pantalla o qué cambia) y explica por qué, paso a paso, con lenguaje sencillo.
- Si usas un término técnico, define brevemente qué significa.

Límites:
- No añadas teoría general ni ejemplos que no estén relacionados con mis instrucciones.
- Si alguna instrucción es ambigua, elige la interpretación más razonable, indícalo en una línea al final y no te detengas a preguntarme.

Estas reglas se mantienen para todas las instrucciones que te envíe después, aunque no te las vuelva a recordar. Si en algún momento te pido expresamente otra cosa, sigue mi petición nueva.

PARTE 1: CÓDIGO

Archivo nuevo: src/Core/Csrf.php. Archivos modificados, que entrego completos: AuthController.php, PostController.php, CommentController.php y las vistas auth/login.php, auth/register.php, posts/form.php y posts/show.php. public/index.php, los modelos y la base de datos no cambian.

php
<?php
// =====================================================================
// ARCHIVO: src/Core/Csrf.php  (NUEVO)
// =====================================================================

declare(strict_types=1);

namespace App\Core;

/**
 * Protección CSRF: un único token secreto por sesión que viaja en todos los formularios POST.
 * Está en una clase aparte para no repetir esta lógica en cada controlador y cada vista.
 */
class Csrf
{
    // Nombre de la clave en $_SESSION y del campo del formulario (el mismo en ambos sitios).
    private const KEY = 'csrf_token';

    /**
     * Devuelve el token de la sesión; si aún no existe, lo genera.
     * Se genera UNA sola vez por sesión y se reutiliza en todos los formularios.
     */
    public static function token(): string
    {
        if (!isset($_SESSION[self::KEY])) {
            // random_bytes() es aleatoriedad criptográficamente segura (rand() o uniqid() serían predecibles).
            // bin2hex() convierte esos bytes en texto, para poder meterlo en un HTML.
            $_SESSION[self::KEY] = bin2hex(random_bytes(32));
        }

        return $_SESSION[self::KEY];
    }

    /**
     * Devuelve el <input type="hidden"> listo para pegar dentro de un formulario.
     */
    public static function field(): string
    {
        // htmlspecialchars por coherencia: toda salida a HTML se escapa, aunque el token sea hexadecimal.
        return '<input type="hidden" name="' . self::KEY . '" value="'
            . htmlspecialchars(self::token()) . '">';
    }

    /**
     * ¿El token recibido en el POST coincide con el de la sesión?
     */
    public static function isValid(): bool
    {
        $sent = $_POST[self::KEY] ?? null;

        // Si no hay token en la sesión, o el POST no trae un texto (p. ej. llega como array),
        // se rechaza antes de llamar a hash_equals(), que exige dos strings.
        if (!isset($_SESSION[self::KEY]) || !is_string($sent)) {
            return false;
        }

        // hash_equals compara en tiempo constante: tarda lo mismo acierte o falle,
        // así un atacante no puede deducir el token midiendo tiempos de respuesta.
        return hash_equals($_SESSION[self::KEY], $sent);
    }
}
php
<?php
// =====================================================================
// ARCHIVO: src/Controllers/AuthController.php  (versión completa)
// Cambios: register() y login() verifican el token; nuevos métodos privados
// requireValidCsrfToken() y abort().
// =====================================================================

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Csrf;
use App\Models\User;
use PDOException;

class AuthController
{
    // Recibimos el modelo ya construido (inyección de dependencias): el controlador
    // no sabe nada de conexiones ni de SQL.
    public function __construct(private User $userModel)
    {
    }

    public function showRegisterForm(): void
    {
        $this->render('auth/register');
    }

    public function register(): void
    {
        // Lo primero de todo: si el token no es válido, no se lee ni un dato del formulario.
        $this->requireValidCsrfToken();

        $username = trim($_POST['username'] ?? '');
        $email    = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        // Validación mínima en servidor: el HTML del navegador se puede saltar.
        if ($username === '' || $password === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->render('auth/register', ['error' => 'Rellena todos los campos con datos válidos.']);
            return;
        }

        try {
            $this->userModel->insert([
                'username' => $username,
                'email'    => $email,
                // PASSWORD_DEFAULT deja que PHP elija el mejor algoritmo disponible
                // (hoy bcrypt) y lo actualice en futuras versiones.
                'password' => password_hash($password, PASSWORD_DEFAULT),
            ]);
        } catch (PDOException $e) {
            // 23000 = violación de restricción de integridad (aquí: username o email UNIQUE repetido).
            if ($e->getCode() === '23000') {
                $this->render('auth/register', ['error' => 'Ese usuario o email ya está registrado.']);
                return;
            }
            throw $e; // Cualquier otro error es inesperado: no lo ocultamos.
        }

        // Tras registrarse, mandamos al login.
        header('Location: index.php?action=login');
        exit;
    }

    public function showLoginForm(): void
    {
        $this->render('auth/login');
    }

    public function login(): void
    {
        // El login también se protege: sin esto, un atacante podría hacer que tu navegador
        // inicie sesión en SU cuenta sin que lo notes ("login CSRF").
        $this->requireValidCsrfToken();

        $email    = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        $userId = $this->userModel->findByEmail($email, $password);

        if ($userId === null) {
            $this->render('auth/login', ['error' => 'Email o contraseña incorrectos.']);
            return;
        }

        // Genera un id de sesión nuevo al iniciar sesión: evita la "fijación de sesión"
        // (que alguien te haya dado un id de sesión que él ya conoce).
        // Los datos de la sesión se conservan, así que el token CSRF sigue siendo el mismo.
        session_regenerate_id(true);

        $_SESSION['user_id']    = $userId;
        $_SESSION['user_email'] = $email;

        header('Location: index.php');
        exit;
    }

    public function logout(): void
    {
        $this->startSessionIfNeeded();

        // session_destroy() borra los datos del servidor, pero $_SESSION sigue
        // con su contenido durante esta petición; lo vaciamos también.
        $_SESSION = [];
        session_destroy();

        header('Location: index.php?action=login');
        exit;
    }

    /**
     * Comprueba el token CSRF del POST. Si no es válido, corta la acción con un 403.
     */
    private function requireValidCsrfToken(): void
    {
        // El token vive en la sesión, así que debe estar iniciada antes de comprobarlo.
        $this->startSessionIfNeeded();

        if (!Csrf::isValid()) {
            $this->abort(403, 'Petición no válida (token CSRF incorrecto). Recarga la página e inténtalo de nuevo.');
        }
    }

    /**
     * Evita el aviso de PHP al llamar a session_start() si ya está iniciada.
     */
    private function startSessionIfNeeded(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    /** Muestra una página de error con su código HTTP y detiene el script. */
    private function abort(int $code, string $message): never
    {
        http_response_code($code);
        $this->render('error', ['code' => $code, 'message' => $message]);
        exit;
    }

    /**
     * Carga una vista dejando disponibles sus variables (por ejemplo $error).
     */
    private function render(string $view, array $data = []): void
    {
        extract($data); // convierte ['error' => '...'] en la variable $error
        require __DIR__ . '/../Views/' . $view . '.php';
    }
}
php
<?php
// =====================================================================
// ARCHIVO: src/Controllers/PostController.php  (versión completa)
// Cambios: store(), update() y destroy() verifican el token como primera instrucción;
// nuevo método privado requireValidCsrfToken().
// =====================================================================

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

    // ------------------------------------------------------------
    // Acciones públicas (las que enruta public/index.php)
    // ------------------------------------------------------------

    /** Listado de anuncios, filtrado si llega ?search=... */
    public function index(): void
    {
        $search = trim($_GET['search'] ?? '');

        // Sin búsqueda reutilizamos getAll() de BaseModel; con búsqueda, el método propio del modelo.
        $posts = $search === ''
            ? $this->postModel->getAll()
            : $this->postModel->search($search);

        // Los modelos devuelven null si no hay filas; la vista espera un array para poder recorrerlo.
        $this->render('posts/index', ['posts' => $posts ?? [], 'search' => $search]);
    }

    /** Un anuncio con sus comentarios, indicado en la URL: index.php?action=show&id=3 */
    public function show(): void
    {
        $post = $this->postModel->getById($this->requestedId());

        if ($post === null) {
            $this->abort(404, 'El anuncio no existe.');
        }

        $this->render('posts/show', [
            'post'     => $post,
            // Solo para decidir si se pintan los botones; la comprobación real está en edit/update/destroy.
            'canEdit'  => $this->isOwner($post),
            // null (sin comentarios) se convierte en array vacío para poder recorrerlo en la vista.
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
            'formAction' => 'index.php?action=store',
        ]);
    }

    /** Guarda el anuncio nuevo (solo usuarios logueados). */
    public function store(): void
    {
        // Primero el token, antes incluso de comprobar el login o leer el formulario.
        $this->requireValidCsrfToken();
        $this->requireLogin();

        $data  = $this->readFormData();
        $error = $this->validate($data);

        if ($error !== null) {
            // Volvemos a mostrar el formulario con lo que escribió el usuario para que no lo pierda.
            $this->render('posts/form', [
                'post'       => $data,
                'error'      => $error,
                'heading'    => 'Nuevo anuncio',
                'formAction' => 'index.php?action=store',
            ]);
            return;
        }

        // El autor sale SIEMPRE de la sesión, nunca del formulario: si no, cualquiera
        // podría publicar "en nombre de" otro usuario cambiando un campo oculto.
        $newId = $this->postModel->insert(array_merge($data, [
            'user_id' => (int) $_SESSION['user_id'],
        ]));

        header('Location: index.php?action=show&id=' . (int) $newId);
        exit;
    }

    /** Formulario de edición (solo el autor). */
    public function edit(): void
    {
        $post = $this->loadOwnedPost();

        $this->render('posts/form', [
            'post'       => $post,
            'heading'    => 'Editar anuncio',
            'formAction' => 'index.php?action=update&id=' . (int) $post['id'],
        ]);
    }

    /** Guarda los cambios (solo el autor). */
    public function update(): void
    {
        // El token se verifica antes que la autorización: una petición falsificada
        // se corta sin siquiera consultar la base de datos.
        $this->requireValidCsrfToken();

        // La autorización se comprueba AQUÍ también: el formulario de edición se puede saltar
        // enviando directamente una petición POST.
        $post = $this->loadOwnedPost();

        $data  = $this->readFormData();
        $error = $this->validate($data);

        if ($error !== null) {
            $this->render('posts/form', [
                'post'       => $data,
                'error'      => $error,
                'heading'    => 'Editar anuncio',
                'formAction' => 'index.php?action=update&id=' . (int) $post['id'],
            ]);
            return;
        }

        // $data solo contiene title, content y category: user_id no se puede modificar.
        $this->postModel->update((int) $post['id'], $data);

        header('Location: index.php?action=show&id=' . (int) $post['id']);
        exit;
    }

    /** Elimina el anuncio (solo el autor). */
    public function destroy(): void
    {
        $this->requireValidCsrfToken();

        $post = $this->loadOwnedPost();

        $this->postModel->delete((int) $post['id']);

        $_SESSION['flash'] = 'Anuncio eliminado.';
        header('Location: index.php');
        exit;
    }

    // ------------------------------------------------------------
    // Utilidades privadas
    // ------------------------------------------------------------

    /** Comprueba el token CSRF del POST. Si no es válido, corta la acción con un 403. */
    private function requireValidCsrfToken(): void
    {
        if (!Csrf::isValid()) {
            $this->abort(403, 'Petición no válida (token CSRF incorrecto). Recarga la página e inténtalo de nuevo.');
        }
    }

    /** Si no hay sesión, redirige al login con un mensaje y corta la ejecución. */
    private function requireLogin(): void
    {
        if (!isset($_SESSION['user_id'])) {
            // "Flash": mensaje que se guarda en la sesión, se muestra una vez y se borra (lo hace header.php).
            $_SESSION['flash'] = 'Debes iniciar sesión para hacer esto.';
            header('Location: index.php?action=login');
            exit;
        }
    }

    /**
     * Para edit/update/destroy: exige login, comprueba que el anuncio existe
     * y que pertenece al usuario de la sesión. Si algo falla, corta aquí.
     */
    private function loadOwnedPost(): array
    {
        $this->requireLogin();

        $post = $this->postModel->getById($this->requestedId());

        if ($post === null) {
            $this->abort(404, 'El anuncio no existe.');
        }

        if (!$this->isOwner($post)) {
            $this->abort(403, 'No tienes permiso para hacer esto.');
        }

        return $post;
    }

    /** ¿El anuncio pertenece al usuario logueado? */
    private function isOwner(array $post): bool
    {
        return isset($_SESSION['user_id'])
            && (int) $post['user_id'] === (int) $_SESSION['user_id'];
    }

    /** Id del anuncio indicado en la URL (?id=...). Un valor no numérico da 0, que no existe en la BD. */
    private function requestedId(): int
    {
        return (int) ($_GET['id'] ?? 0);
    }

    /** Lee y limpia los campos del formulario. */
    private function readFormData(): array
    {
        return [
            'title'    => trim($_POST['title'] ?? ''),
            'content'  => trim($_POST['content'] ?? ''),
            'category' => trim($_POST['category'] ?? ''),
        ];
    }

    /** Devuelve un mensaje de error, o null si los datos son válidos. */
    private function validate(array $data): ?string
    {
        if ($data['title'] === '' || $data['content'] === '' || $data['category'] === '') {
            return 'Rellena el título, la categoría y el contenido.';
        }

        // Los límites coinciden con VARCHAR(150) y VARCHAR(50) de la tabla.
        if (mb_strlen($data['title']) > 150 || mb_strlen($data['category']) > 50) {
            return 'El título admite 150 caracteres como máximo y la categoría 50.';
        }

        return null;
    }

    /** Muestra una página de error con su código HTTP y detiene el script. */
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
php
<?php
// =====================================================================
// ARCHIVO: src/Controllers/CommentController.php  (versión completa)
// Cambios: store() verifica el token como primera instrucción;
// nuevo método privado requireValidCsrfToken().
// =====================================================================

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Csrf;
use App\Models\Comment;
use App\Models\Post;

class CommentController
{
    // Necesita Post para comprobar que el anuncio comentado existe antes de insertar.
    public function __construct(
        private Comment $commentModel,
        private Post $postModel
    ) {
    }

    /** Guarda un comentario nuevo en el anuncio indicado en la URL: ?action=comment_store&id=3 */
    public function store(): void
    {
        $this->requireValidCsrfToken();
        $this->requireLogin();

        $postId = (int) ($_GET['id'] ?? 0);

        // Si el anuncio no existe, la clave foránea haría fallar el INSERT con una excepción;
        // preferimos detectarlo antes y mostrar un 404 limpio.
        if ($this->postModel->getById($postId) === null) {
            $this->abort(404, 'El anuncio no existe.');
        }

        $content = trim($_POST['content'] ?? '');

        if ($content === '') {
            // No hay vista propia para este formulario: volvemos al anuncio con un mensaje flash.
            $_SESSION['flash'] = 'El comentario no puede estar vacío.';
            header('Location: index.php?action=show&id=' . $postId . '#comments');
            exit;
        }

        $this->commentModel->insert([
            'post_id' => $postId,
            // El autor sale SIEMPRE de la sesión, nunca del formulario.
            'user_id' => (int) $_SESSION['user_id'],
            'content' => $content,
        ]);

        // El fragmento #comments hace que el navegador baje directamente a la sección de comentarios.
        header('Location: index.php?action=show&id=' . $postId . '#comments');
        exit;
    }

    /** Comprueba el token CSRF del POST. Si no es válido, corta la acción con un 403. */
    private function requireValidCsrfToken(): void
    {
        if (!Csrf::isValid()) {
            $this->abort(403, 'Petición no válida (token CSRF incorrecto). Recarga la página e inténtalo de nuevo.');
        }
    }

    /** Si no hay sesión, redirige al login con un mensaje y corta la ejecución. */
    private function requireLogin(): void
    {
        if (!isset($_SESSION['user_id'])) {
            $_SESSION['flash'] = 'Debes iniciar sesión para comentar.';
            header('Location: index.php?action=login');
            exit;
        }
    }

    /** Muestra una página de error con su código HTTP y detiene el script. */
    private function abort(int $code, string $message): never
    {
        http_response_code($code);
        extract(['code' => $code, 'message' => $message]);
        require __DIR__ . '/../Views/error.php';
        exit;
    }
}
php
<?php
// =====================================================================
// ARCHIVO: src/Views/auth/login.php  (versión completa)
// =====================================================================
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Iniciar sesión</title>
</head>
<body>
    <?php require __DIR__ . '/../partials/header.php'; ?>

    <h1>Iniciar sesión</h1>

    <?php if (!empty($error)): ?>
        <p style="color:red"><?= htmlspecialchars($error) ?></p>
    <?php endif; ?>

    <!-- POST porque envía credenciales: no deben aparecer en la URL. -->
    <form method="post" action="index.php?action=login">
        <?= \App\Core\Csrf::field() ?>
        <label>Email <input type="email" name="email" required></label><br>
        <label>Contraseña <input type="password" name="password" required></label><br>
        <button type="submit">Entrar</button>
    </form>

    <p>¿No tienes cuenta? <a href="index.php?action=register">Regístrate</a></p>
</body>
</html>
php
<?php
// =====================================================================
// ARCHIVO: src/Views/auth/register.php  (versión completa)
// =====================================================================
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Registro</title>
</head>
<body>
    <?php require __DIR__ . '/../partials/header.php'; ?>

    <h1>Crear cuenta</h1>

    <?php if (!empty($error)): ?>
        <p style="color:red"><?= htmlspecialchars($error) ?></p>
    <?php endif; ?>

    <form method="post" action="index.php?action=register">
        <?= \App\Core\Csrf::field() ?>
        <label>Usuario <input type="text" name="username" required></label><br>
        <label>Email <input type="email" name="email" required></label><br>
        <label>Contraseña <input type="password" name="password" required></label><br>
        <button type="submit">Registrarme</button>
    </form>

    <p>¿Ya tienes cuenta? <a href="index.php?action=login">Inicia sesión</a></p>
</body>
</html>
php
<?php
// =====================================================================
// ARCHIVO: src/Views/posts/form.php  (versión completa; la usan create y edit)
// =====================================================================
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($heading) ?></title>
</head>
<body>
    <?php require __DIR__ . '/../partials/header.php'; ?>

    <h1><?= htmlspecialchars($heading) ?></h1>

    <?php if (!empty($error)): ?>
        <p style="color:red"><?= htmlspecialchars($error) ?></p>
    <?php endif; ?>

    <form method="post" action="<?= htmlspecialchars($formAction) ?>">
        <?= \App\Core\Csrf::field() ?>
        <label>Título
            <input type="text" name="title" maxlength="150" required
                   value="<?= htmlspecialchars($post['title'] ?? '') ?>">
        </label><br>
        <label>Categoría
            <input type="text" name="category" maxlength="50" required
                   value="<?= htmlspecialchars($post['category'] ?? '') ?>">
        </label><br>
        <label>Contenido<br>
            <textarea name="content" rows="8" cols="60" required><?= htmlspecialchars($post['content'] ?? '') ?></textarea>
        </label><br>
        <button type="submit">Guardar</button>
    </form>

    <p><a href="index.php">Cancelar</a></p>
</body>
</html>
php
<?php
// =====================================================================
// ARCHIVO: src/Views/posts/show.php  (versión completa)
// Cambios: token en el formulario de borrar y en el de comentar.
// =====================================================================
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($post['title']) ?></title>
</head>
<body>
    <?php require __DIR__ . '/../partials/header.php'; ?>

    <h1><?= htmlspecialchars($post['title']) ?></h1>
    <p>Categoría: <?= htmlspecialchars($post['category']) ?></p>
    <p>Publicado: <?= htmlspecialchars($post['created_at']) ?></p>

    <!-- Se escapa primero y después nl2br convierte los saltos de línea en <br>. -->
    <p><?= nl2br(htmlspecialchars($post['content'])) ?></p>

    <?php if ($canEdit): ?>
        <a href="index.php?action=edit&id=<?= (int) $post['id'] ?>">Editar</a>

        <!-- Borrar es una acción que cambia datos: va por POST, nunca por un enlace GET. -->
        <form method="post" action="index.php?action=destroy&id=<?= (int) $post['id'] ?>"
              onsubmit="return confirm('¿Seguro que quieres borrar este anuncio?');">
            <?= \App\Core\Csrf::field() ?>
            <button type="submit">Eliminar</button>
        </form>
    <?php endif; ?>

    <hr>

    <!-- El id permite que la redirección tras comentar baje directamente a esta sección. -->
    <section id="comments">
        <h2>Comentarios (<?= count($comments) ?>)</h2>

        <?php if (empty($comments)): ?>
            <p>Todavía no hay comentarios.</p>
        <?php else: ?>
            <?php foreach ($comments as $comment): ?>
                <article>
                    <p>
                        <strong><?= htmlspecialchars($comment['username']) ?></strong>
                        · <?= htmlspecialchars($comment['created_at']) ?>
                    </p>
                    <p><?= nl2br(htmlspecialchars($comment['content'])) ?></p>
                </article>
            <?php endforeach; ?>
        <?php endif; ?>

        <?php if (isset($_SESSION['user_id'])): ?>
            <h3>Añadir comentario</h3>
            <!-- Solo se envía el texto y el token: el autor sale de la sesión y el anuncio, de la URL. -->
            <form method="post" action="index.php?action=comment_store&id=<?= (int) $post['id'] ?>">
                <?= \App\Core\Csrf::field() ?>
                <label>Tu comentario<br>
                    <textarea name="content" rows="4" cols="60" required></textarea>
                </label><br>
                <button type="submit">Comentar</button>
            </form>
        <?php else: ?>
            <p><a href="index.php?action=login">Inicia sesión</a> para comentar.</p>
        <?php endif; ?>
    </section>

    <p><a href="index.php">Volver al listado</a></p>
</body>
</html>
PARTE 2: RESULTADO Y EXPLICACIÓN

Qué es CSRF y cómo lo frena el token

CSRF (Cross-Site Request Forgery): ataque en el que una web maliciosa hace que tu navegador envíe una petición a tu aplicación. El navegador adjunta tu cookie de sesión automáticamente, así que el servidor cree que la petición es tuya. Por ejemplo, una página ajena podría tener un formulario oculto que envíe un POST a index.php?action=destroy&id=3.
Token CSRF: un valor secreto y aleatorio que la aplicación guarda en tu sesión y pone en cada formulario. El atacante no puede leerlo, porque está en tu sesión y no en su página, así que su petición falsa llega sin token o con uno incorrecto y el servidor la rechaza.

Csrf::token()

Si $_SESSION['csrf_token'] no existe, crea uno con bin2hex(random_bytes(32)): 32 bytes aleatorios convertidos en 64 caracteres hexadecimales.
Se crea una sola vez por sesión y se reutiliza en todos los formularios, como pide el enunciado.
Resultado: algo como a3f9... (64 caracteres), distinto en cada sesión.
random_bytes(): genera bytes aleatorios aptos para seguridad. rand() no sirve para esto porque es predecible.

Csrf::field()

Devuelve el HTML <input type="hidden" name="csrf_token" value="a3f9...">.
Resultado en pantalla: no se ve nada, pero al enviar el formulario el token viaja en $_POST['csrf_token'].
En las vistas basta con escribir <?= \App\Core\Csrf::field() ?> dentro del <form>.

Csrf::isValid()

Compara $_POST['csrf_token'] con $_SESSION['csrf_token'] usando hash_equals().
Devuelve false si falta el token en la sesión, si falta en el POST o si no es un texto.
hash_equals(): compara dos cadenas tardando siempre lo mismo. Con ==, la comparación se detiene en el primer carácter distinto y un atacante podría deducir el token midiendo tiempos (ataque de temporización).

Verificación en cada acción POST

Se añadió requireValidCsrfToken() como primera instrucción en register(), login(), PostController::store(), update(), destroy() y CommentController::store().
Si el token falla, se muestra la página de error con código 403 y mensaje “Petición no válida (token CSRF incorrecto)...”, y el script termina con exit. No se lee el formulario, no se consulta la base de datos y no se modifica nada.
En update() y destroy(), el token se comprueba antes que la autorización (loadOwnedPost()). Así, una petición falsificada se descarta sin tocar la base de datos.
Resultado práctico: los formularios normales funcionan igual que antes. Para probarlo, abre las herramientas del navegador, borra el campo oculto csrf_token de un formulario y envíalo. Verás el error 403 y no se guardará nada.

Login y registro

Antes de login(), el visitante no tiene usuario, pero sí sesión. index.php llama a session_start() en cada petición y field() crea el token al pintar el formulario. Por eso estos formularios también se pueden proteger.
Login CSRF: un atacante hace que tu navegador inicie sesión con SU cuenta sin que lo sepas. Después podrías, sin darte cuenta, publicar contenido personal en la cuenta de él.
En AuthController, requireValidCsrfToken() llama antes a startSessionIfNeeded() para asegurar que la sesión existe. session_regenerate_id(true) cambia el identificador de sesión pero conserva sus datos, así que el token sigue siendo válido tras iniciar sesión.
Al cerrar sesión, session_destroy() elimina el token. En la visita siguiente se genera uno nuevo.

Interpretaciones que he elegido:

Puse el token en un nuevo App\Core\Csrf en lugar de repetir el código en cada controlador. El enunciado no lo prohíbe y sigue la misma idea de “no te repitas” de los pasos anteriores.
Ante un token inválido devuelvo un 403 con mensaje y no redirijo, porque una petición falsificada no debe seguir su flujo normal.
logout sigue siendo un enlace GET, porque el enunciado solo pide proteger los formularios POST. Cerrar sesión por GET es una forma menor de CSRF (alguien podría desconectarte con una imagen o enlace), pero no modifica datos. Si quisieras cerrarlo, habría que convertirlo en un formulario POST.
El formulario de búsqueda sigue en GET y sin token, porque no modifica datos.

PASO 7. (Opcional, para subir nota) Rutas limpias
Objetivo: sustituir las URL con parámetros (?controller=Post&action=show&id=3) por URLs limpias del estilo /posts/3, como las que verás en cualquier framework moderno.
Aviso: este paso es de los que se pueden complicar bastante. Solo recomendable si ya tienes todo lo anterior funcionando y te sobra tiempo.
Tareas:

Diseña un pequeño enrutador que sea capaz de interpretar una URL como /posts/delete/3 y deducir de ahí que hay que llamar al método destroy(3) del PostController.
Debe funcionar para cualquier controlador y cualquier id, no solo para publicaciones.
Pista: vas a necesitar expresiones regulares para “trocear” la URL en sus partes (controlador, acción, parámetro) y un archivo .htaccess parecido al que ya usaste en el apartado 2.7 para que todas las peticiones lleguen a tu index.php.
PARTE 1: CÓDIGO

Archivos nuevos: public/.htaccess y src/Core/Router.php. Archivos modificados, que entrego completos: Dockerfile, public/index.php, los tres controladores y las vistas. Las acciones de los controladores ahora reciben el id como parámetro y todas las URL pasan al formato limpio. Los modelos y la base de datos no cambian.

dockerfile
# =====================================================================
# ARCHIVO: Dockerfile  (versión completa)
# Cambios: se activa mod_rewrite y se permite que .htaccess funcione.
# Después hay que reconstruir: docker compose up -d --build
# =====================================================================
FROM php:8.3-apache

# pdo_mysql es la extensión que permite a PDO hablar con MySQL;
# la imagen oficial de PHP no la trae activada.
RUN docker-php-ext-install pdo pdo_mysql

# unzip lo necesita Composer para descargar paquetes comprimidos.
RUN apt-get update && apt-get install -y unzip \
    && rm -rf /var/lib/apt/lists/*

# Copiamos el binario de Composer desde su imagen oficial.
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Apache servirá solo la carpeta public/.
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf \
    && sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

# mod_rewrite es el módulo de Apache que reescribe URLs; viene desactivado por defecto.
RUN a2enmod rewrite

# Por defecto Apache ignora los .htaccess ("AllowOverride None"); lo cambiamos para que se lean.
RUN sed -ri -e 's/AllowOverride None/AllowOverride All/g' /etc/apache2/apache2.conf
apache
# =====================================================================
# ARCHIVO: public/.htaccess  (NUEVO)
# =====================================================================
RewriteEngine On

# Si lo pedido es un archivo real (css, imagen...) o una carpeta real, se sirve tal cual.
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d

# Todo lo demás se envía a index.php. QSA conserva la query string (?search=...); L detiene las reglas.
RewriteRule ^ index.php [QSA,L]
php
<?php
// =====================================================================
// ARCHIVO: src/Core/Router.php  (NUEVO)
// =====================================================================

declare(strict_types=1);

namespace App\Core;

/**
 * Enrutador genérico: no conoce ningún controlador concreto.
 * Se le registran patrones como "/posts/delete/{id}" asociados a un método de un controlador.
 */
class Router
{
    /** @var array<int, array{method: string, regex: string, handler: callable}> */
    private array $routes = [];

    public function get(string $pattern, callable $handler): void
    {
        $this->add('GET', $pattern, $handler);
    }

    public function post(string $pattern, callable $handler): void
    {
        $this->add('POST', $pattern, $handler);
    }

    /**
     * Convierte el patrón en una expresión regular y guarda la ruta.
     */
    private function add(string $method, string $pattern, callable $handler): void
    {
        // Cada {nombre} se transforma en un grupo con nombre que solo acepta dígitos:
        // "/posts/delete/{id}" -> "#^/posts/delete/(?P<id>\d+)$#"
        // Aceptar solo dígitos garantiza que el id es un número (y /posts/create no se confunde con un id).
        $regex = preg_replace('#\{(\w+)\}#', '(?P<$1>\d+)', $pattern);

        $this->routes[] = [
            'method'  => $method,
            // ^ y $ obligan a que coincida la URL ENTERA, no solo una parte.
            'regex'   => '#^' . $regex . '$#',
            'handler' => $handler,
        ];
    }

    /**
     * Busca la ruta que encaja con método + URL y ejecuta su controlador.
     */
    public function dispatch(string $httpMethod, string $uri): void
    {
        // parse_url con PHP_URL_PATH descarta "?search=..." para quedarnos solo con la ruta.
        $path = parse_url($uri, PHP_URL_PATH) ?: '/';
        // "/posts/" y "/posts" se tratan igual; la raíz se queda como "/".
        $path = rtrim($path, '/') ?: '/';

        foreach ($this->routes as $route) {
            if ($route['method'] !== $httpMethod) {
                continue;
            }

            if (preg_match($route['regex'], $path, $matches) === 1) {
                // $matches trae claves numéricas y las de los grupos con nombre ("id").
                // Nos quedamos solo con las de nombre y las convertimos a entero.
                $params = [];
                foreach ($matches as $key => $value) {
                    if (is_string($key)) {
                        $params[$key] = (int) $value;
                    }
                }

                // Las claves de texto se usan como argumentos con nombre:
                // ['id' => 3] equivale a llamar a ->destroy(id: 3).
                ($route['handler'])(...$params);
                return;
            }
        }

        $this->notFound();
    }

    /** Ninguna ruta encaja: página de error 404. */
    private function notFound(): void
    {
        http_response_code(404);
        $code = 404;
        $message = 'La página que buscas no existe.';
        require __DIR__ . '/../Views/error.php';
    }
}
php
<?php
// =====================================================================
// ARCHIVO: public/index.php  (versión completa)
// Cambios: la tabla de rutas sustituye al "match" por ?action=.
// =====================================================================

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
php
<?php
// =====================================================================
// ARCHIVO: src/Controllers/AuthController.php  (versión completa)
// Cambios: solo las redirecciones, que ahora usan URL limpias.
// =====================================================================

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Csrf;
use App\Models\User;
use PDOException;

class AuthController
{
    public function __construct(private User $userModel)
    {
    }

    public function showRegisterForm(): void
    {
        $this->render('auth/register');
    }

    public function register(): void
    {
        $this->requireValidCsrfToken();

        $username = trim($_POST['username'] ?? '');
        $email    = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        if ($username === '' || $password === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->render('auth/register', ['error' => 'Rellena todos los campos con datos válidos.']);
            return;
        }

        try {
            $this->userModel->insert([
                'username' => $username,
                'email'    => $email,
                'password' => password_hash($password, PASSWORD_DEFAULT),
            ]);
        } catch (PDOException $e) {
            // 23000 = violación de restricción de integridad (username o email UNIQUE repetido).
            if ($e->getCode() === '23000') {
                $this->render('auth/register', ['error' => 'Ese usuario o email ya está registrado.']);
                return;
            }
            throw $e;
        }

        header('Location: /login');
        exit;
    }

    public function showLoginForm(): void
    {
        $this->render('auth/login');
    }

    public function login(): void
    {
        $this->requireValidCsrfToken();

        $email    = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        $userId = $this->userModel->findByEmail($email, $password);

        if ($userId === null) {
            $this->render('auth/login', ['error' => 'Email o contraseña incorrectos.']);
            return;
        }

        // Evita la "fijación de sesión"; los datos (incluido el token CSRF) se conservan.
        session_regenerate_id(true);

        $_SESSION['user_id']    = $userId;
        $_SESSION['user_email'] = $email;

        header('Location: /');
        exit;
    }

    public function logout(): void
    {
        $this->startSessionIfNeeded();

        $_SESSION = [];
        session_destroy();

        header('Location: /login');
        exit;
    }

    private function requireValidCsrfToken(): void
    {
        $this->startSessionIfNeeded();

        if (!Csrf::isValid()) {
            $this->abort(403, 'Petición no válida (token CSRF incorrecto). Recarga la página e inténtalo de nuevo.');
        }
    }

    private function startSessionIfNeeded(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
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
php
<?php
// =====================================================================
// ARCHIVO: src/Controllers/PostController.php  (versión completa)
// Cambios: show/edit/update/destroy reciben el id como parámetro (lo inyecta el Router);
// desaparece requestedId(); las redirecciones y los formAction usan URL limpias.
// =====================================================================

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
php
<?php
// =====================================================================
// ARCHIVO: src/Controllers/CommentController.php  (versión completa)
// Cambios: store() recibe el id del anuncio como parámetro; redirecciones limpias.
// =====================================================================

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
php
<?php
// =====================================================================
// ARCHIVO: src/Views/partials/header.php  (versión completa)
// Cambios: enlaces con URL limpias.
// =====================================================================
?>
<header>
    <nav>
        <a href="/">Tablón de anuncios</a>

        <?php if (isset($_SESSION['user_id'])): ?>
            <span><?= htmlspecialchars($_SESSION['user_email']) ?></span>
            <a href="/logout">Cerrar sesión</a>
        <?php else: ?>
            <a href="/login">Iniciar sesión</a>
        <?php endif; ?>
    </nav>

    <?php if (isset($_SESSION['flash'])): ?>
        <p style="color:darkorange"><?= htmlspecialchars($_SESSION['flash']) ?></p>
        <?php
        // Se borra justo después de mostrarlo para que no aparezca en la página siguiente.
        unset($_SESSION['flash']);
        ?>
    <?php endif; ?>
</header>
php
<?php
// =====================================================================
// ARCHIVO: src/Views/auth/login.php  (versión completa)
// =====================================================================
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Iniciar sesión</title>
</head>
<body>
    <?php require __DIR__ . '/../partials/header.php'; ?>

    <h1>Iniciar sesión</h1>

    <?php if (!empty($error)): ?>
        <p style="color:red"><?= htmlspecialchars($error) ?></p>
    <?php endif; ?>

    <form method="post" action="/login">
        <?= \App\Core\Csrf::field() ?>
        <label>Email <input type="email" name="email" required></label><br>
        <label>Contraseña <input type="password" name="password" required></label><br>
        <button type="submit">Entrar</button>
    </form>

    <p>¿No tienes cuenta? <a href="/register">Regístrate</a></p>
</body>
</html>
php
<?php
// =====================================================================
// ARCHIVO: src/Views/auth/register.php  (versión completa)
// =====================================================================
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Registro</title>
</head>
<body>
    <?php require __DIR__ . '/../partials/header.php'; ?>

    <h1>Crear cuenta</h1>

    <?php if (!empty($error)): ?>
        <p style="color:red"><?= htmlspecialchars($error) ?></p>
    <?php endif; ?>

    <form method="post" action="/register">
        <?= \App\Core\Csrf::field() ?>
        <label>Usuario <input type="text" name="username" required></label><br>
        <label>Email <input type="email" name="email" required></label><br>
        <label>Contraseña <input type="password" name="password" required></label><br>
        <button type="submit">Registrarme</button>
    </form>

    <p>¿Ya tienes cuenta? <a href="/login">Inicia sesión</a></p>
</body>
</html>
php
<?php
// =====================================================================
// ARCHIVO: src/Views/posts/index.php  (versión completa)
// =====================================================================
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Tablón de anuncios</title>
</head>
<body>
    <?php require __DIR__ . '/../partials/header.php'; ?>

    <h1>Anuncios</h1>

    <!-- GET a /posts: la búsqueda llega como /posts?search=mueble -->
    <form method="get" action="/posts">
        <input type="text" name="search" placeholder="Título o categoría"
               value="<?= htmlspecialchars($search) ?>">
        <button type="submit">Buscar</button>
    </form>

    <p><a href="/posts/create">Nuevo anuncio</a></p>

    <?php if (empty($posts)): ?>
        <p>No hay anuncios.</p>
    <?php else: ?>
        <ul>
            <?php foreach ($posts as $post): ?>
                <li>
                    <a href="/posts/<?= (int) $post['id'] ?>">
                        <?= htmlspecialchars($post['title']) ?>
                    </a>
                    (<?= htmlspecialchars($post['category']) ?>)
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</body>
</html>
php
<?php
// =====================================================================
// ARCHIVO: src/Views/posts/show.php  (versión completa)
// =====================================================================
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($post['title']) ?></title>
</head>
<body>
    <?php require __DIR__ . '/../partials/header.php'; ?>

    <h1><?= htmlspecialchars($post['title']) ?></h1>
    <p>Categoría: <?= htmlspecialchars($post['category']) ?></p>
    <p>Publicado: <?= htmlspecialchars($post['created_at']) ?></p>

    <p><?= nl2br(htmlspecialchars($post['content'])) ?></p>

    <?php if ($canEdit): ?>
        <a href="/posts/edit/<?= (int) $post['id'] ?>">Editar</a>

        <form method="post" action="/posts/delete/<?= (int) $post['id'] ?>"
              onsubmit="return confirm('¿Seguro que quieres borrar este anuncio?');">
            <?= \App\Core\Csrf::field() ?>
            <button type="submit">Eliminar</button>
        </form>
    <?php endif; ?>

    <hr>

    <section id="comments">
        <h2>Comentarios (<?= count($comments) ?>)</h2>

        <?php if (empty($comments)): ?>
            <p>Todavía no hay comentarios.</p>
        <?php else: ?>
            <?php foreach ($comments as $comment): ?>
                <article>
                    <p>
                        <strong><?= htmlspecialchars($comment['username']) ?></strong>
                        · <?= htmlspecialchars($comment['created_at']) ?>
                    </p>
                    <p><?= nl2br(htmlspecialchars($comment['content'])) ?></p>
                </article>
            <?php endforeach; ?>
        <?php endif; ?>

        <?php if (isset($_SESSION['user_id'])): ?>
            <h3>Añadir comentario</h3>
            <form method="post" action="/comments/store/<?= (int) $post['id'] ?>">
                <?= \App\Core\Csrf::field() ?>
                <label>Tu comentario<br>
                    <textarea name="content" rows="4" cols="60" required></textarea>
                </label><br>
                <button type="submit">Comentar</button>
            </form>
        <?php else: ?>
            <p><a href="/login">Inicia sesión</a> para comentar.</p>
        <?php endif; ?>
    </section>

    <p><a href="/posts">Volver al listado</a></p>
</body>
</html>
php
<?php
// =====================================================================
// ARCHIVO: src/Views/posts/form.php  (versión completa; la usan create y edit)
// =====================================================================
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($heading) ?></title>
</head>
<body>
    <?php require __DIR__ . '/../partials/header.php'; ?>

    <h1><?= htmlspecialchars($heading) ?></h1>

    <?php if (!empty($error)): ?>
        <p style="color:red"><?= htmlspecialchars($error) ?></p>
    <?php endif; ?>

    <form method="post" action="<?= htmlspecialchars($formAction) ?>">
        <?= \App\Core\Csrf::field() ?>
        <label>Título
            <input type="text" name="title" maxlength="150" required
                   value="<?= htmlspecialchars($post['title'] ?? '') ?>">
        </label><br>
        <label>Categoría
            <input type="text" name="category" maxlength="50" required
                   value="<?= htmlspecialchars($post['category'] ?? '') ?>">
        </label><br>
        <label>Contenido<br>
            <textarea name="content" rows="8" cols="60" required><?= htmlspecialchars($post['content'] ?? '') ?></textarea>
        </label><br>
        <button type="submit">Guardar</button>
    </form>

    <p><a href="/posts">Cancelar</a></p>
</body>
</html>
php
<?php
// =====================================================================
// ARCHIVO: src/Views/error.php  (versión completa)
// =====================================================================
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Error <?= (int) $code ?></title>
</head>
<body>
    <?php require __DIR__ . '/partials/header.php'; ?>

    <h1>Error <?= (int) $code ?></h1>
    <p><?= htmlspecialchars($message) ?></p>
    <p><a href="/">Volver al inicio</a></p>
</body>
</html>
PARTE 2: RESULTADO Y EXPLICACIÓN

Para activarlo

Reconstruye la imagen para que se apliquen los cambios del Dockerfile: docker compose up -d --build.
Resultado: http://localhost:8080/posts/3 muestra el anuncio 3. Ya no hay que escribir index.php?action=show&id=3.

.htaccess y mod_rewrite

.htaccess: archivo de configuración de Apache que afecta solo a su carpeta.
mod_rewrite: módulo de Apache que reescribe URL internamente. El navegador sigue viendo /posts/3, pero Apache ejecuta index.php.
Las dos líneas RewriteCond ... !-f y !-d dicen: “solo reescribe si lo pedido no es un archivo ni una carpeta que exista”. Así, un futuro /style.css se sigue sirviendo directamente.
[QSA,L]: QSA (Query String Append) conserva ?search=.... L (Last) detiene el procesado de reglas.
El Dockerfile activa mod_rewrite y cambia AllowOverride None por All. Sin eso Apache ignora el .htaccess y cualquier URL limpia daría 404 de Apache.

Router::add()

Convierte un patrón en una expresión regular. Expresión regular: un patrón de texto que describe qué cadenas encajan.
Ejemplo con /posts/delete/{id}:
preg_replace cambia {id} por (?P<id>\d+).
Se rodea con #^ ... $#, y queda #^/posts/delete/(?P<id>\d+)$#.
\d+ significa “uno o más dígitos”. (?P<id>...) es un grupo con nombre: captura ese trozo y lo etiqueta como id. ^ y $ obligan a que encaje la URL entera.
Como {id} solo admite dígitos, /posts/abc no encaja con ninguna ruta y da 404. Tampoco hay conflicto entre /posts/create y /posts/{id}.

Router::dispatch()

Obtiene la ruta con parse_url(..., PHP_URL_PATH), que quita ?search=..., y elimina la barra final.
Recorre las rutas registradas. Descarta las que tengan otro método HTTP y prueba el resto con preg_match.
Si una encaja, $matches contiene ['id' => '3']. Se convierte a ['id' => 3] con (int).
Ejecuta ($route['handler'])(...$params). Con claves de texto, PHP las pasa como argumentos con nombre, así que equivale a $posts->destroy(id: 3).
Resultado: POST /posts/delete/3 llama a PostController::destroy(3).
Si ninguna ruta encaja, se muestra la página de error con un 404.
El router no sabe nada de Post ni de Comment. Funciona con cualquier controlador y cualquier id, y añadir uno nuevo es solo una línea más en index.php.

public/index.php

Crea los objetos igual que antes, registra una línea por ruta y llama a dispatch().
Tabla de rutas resultante:
URL	Método de controlador
GET / y GET /posts	PostController::index()
GET /posts/create	create()
POST /posts/store	store()
GET /posts/3	show(3)
GET /posts/edit/3	edit(3)
POST /posts/update/3	update(3)
POST /posts/delete/3	destroy(3)
POST /comments/store/3	CommentController::store(3)
GET/POST /login, /register; GET /logout	AuthController

Cambios en controladores y vistas

Antes, el controlador leía $_GET['id'] con requestedId(). Ahora el router le pasa el id como parámetro (show(int $id), edit(int $id)...), y requestedId() desaparece. loadOwnedPost() también lo recibe.
Todas las redirecciones (header('Location: ...')) y los enlaces o formularios de las vistas usan ahora URL limpias: /login, /posts/5, etc. Empiezan por / para que sean siempre relativas a la raíz y no dependan de la página actual.
La seguridad no cambia: CSRF, comprobación de propiedad (403) y htmlspecialchars() siguen igual. Borrar y editar son POST, así que no basta con visitar una URL para alterar datos.
Prueba: entra en /posts/9999 (404), en /posts/edit/1 sin sesión (te manda a /login con el mensaje) y busca “mueble” (la URL queda /posts?search=mueble).

Interpretaciones que he elegido:

Un enrutador por tabla de rutas (patrón → método), como los frameworks, y no deducir el controlador del texto de la URL. Así /posts/3 (sin la palabra “show”) también funciona y no se puede llamar a un método que no hayas registrado.
Entiendo /posts/delete/3 como la ruta que llama a destroy(3). Por eso delete es el nombre de la URL y destroy el del método.
Solo se aceptan ids numéricos (\d+). Si quisieras otro tipo de parámetro, como un slug de texto, habría que añadir otro tipo de marcador.
No distingo el 405 (“Método no permitido”) del 404: GET /posts/delete/3, por ejemplo, devuelve 404.
logout sigue siendo GET, como en el paso 6.

Cuando creo un anuncio me sale esto despues de guardarlo

Fatal error: Uncaught PDOException: SQLSTATE[42S22]: Column not found: 1054 Unknown column 'category' in 'field list' in /var/www/html/src/Core/Database.php:49 Stack trace: #0 /var/www/html/src/Core/Database.php(49): PDO->prepare('INSERT INTO pos...') #1 /var/www/html/src/Models/BaseModel.php(93): App\Core\Database->query('INSERT INTO pos...', Array) #2 /var/www/html/src/Controllers/PostController.php(84): App\Models\BaseModel->insert(Array) #3 /var/www/html/src/Core/Router.php(74): App\Controllers\PostController->store() #4 /var/www/html/public/index.php(56): App\Core\Router->dispatch('POST', '/posts/store') #5 {main} thrown in /var/www/html/src/Core/Database.php on line 49

PARTE 1: CÓDIGO

La columna category no existe en tu tabla posts. Se añadió en el paso 4, pero MySQL solo ejecuta schema.sql cuando el volumen está vacío, así que tu base de datos sigue con la estructura antigua. Hay dos soluciones. Elige una.

Opción A: añadir la columna sin perder datos (recomendada)

bash
# Añade la columna category a la tabla posts que ya existe.
# DEFAULT 'General' hace que los anuncios que ya tengas reciban esa categoría.
docker compose exec db mysql -uroot -plaquesea tablon_nuncios -e "ALTER TABLE posts ADD COLUMN category VARCHAR(50) NOT NULL DEFAULT 'General' AFTER content;"

# Comprueba el resultado: debe aparecer la fila "category" en la estructura de la tabla.
docker compose exec db mysql -uroot -plaquesea tablon_nuncios -e "DESCRIBE posts;"

Opción B: recrear la base de datos desde cero (borra TODOS los datos)

bash
# -v borra también el volumen db_data, es decir, los usuarios, anuncios y comentarios.
docker compose down -v

# Al arrancar con el volumen vacío, MySQL ejecuta de nuevo db/schema.sql (que ya incluye category).
docker compose up -d

Si usas la opción B, asegúrate de que tu db/schema.sql contiene la línea category VARCHAR(50) NOT NULL DEFAULT 'General', dentro de CREATE TABLE posts, como en el paso 4. Después tendrás que registrarte otra vez.

PARTE 2: RESULTADO Y EXPLICACIÓN

Por qué falla

BaseModel::insert() construye el SQL a partir de las claves del array: INSERT INTO posts (title, content, category, user_id) VALUES (...). MySQL revisa que cada columna exista y responde Unknown column 'category' (código 42S22, “columna no encontrada”).
El código PHP es correcto. Lo que está desactualizado es la tabla.
schema.sql y el volumen: el script montado en /docker-entrypoint-initdb.d/ solo se ejecuta cuando MySQL inicializa un volumen vacío, es decir, la primera vez. Si lo modificas después, no se vuelve a ejecutar. Por eso hace falta alterar la tabla a mano o recrear el volumen.

Opción A

ALTER TABLE ... ADD COLUMN modifica la estructura de una tabla existente. AFTER content coloca la columna justo después de content. Los anuncios que ya tuvieras se conservan, y su categoría pasa a ser General.
Resultado: DESCRIBE posts; muestra las columnas id, user_id, title, content, category y created_at. Al recargar y crear un anuncio, ya se guarda sin error.

Opción B

docker compose down -v detiene y elimina los contenedores y el volumen db_data. Con docker compose up -d, MySQL arranca vacío y vuelve a leer schema.sql.
Resultado: base de datos nueva y vacía, con la columna incluida.

Interpretaciones que he elegido:

Asumo que el único desajuste es category, porque es la única columna que añadimos después del paso 1. Si tras la opción A aparece otro error de columna desconocida, envíame el resultado de DESCRIBE posts; y lo revisamos.
PARTE 1: CÓDIGO

Casi seguro MySQL sigue arrancando. Con el volumen vacío tiene que inicializar la base de datos y ejecutar schema.sql, y eso tarda entre 20 y 60 segundos. Durante ese tiempo rechaza conexiones. Sigue estos pasos en orden:

bash
# 1) Mira el estado de los contenedores: "db" debe estar en "Up" (no "Exited" ni "Restarting").
docker compose ps

# 2) Sigue el log de MySQL en directo (Ctrl+C para salir).
#    Espera hasta ver DOS veces una línea con "ready for connections" (la primera es el
#    servidor temporal de inicialización; la segunda es la definitiva).
docker compose logs -f db

# 3) Cuando ya diga "ready for connections" por segunda vez, comprueba que las tablas existen
#    y que posts tiene la columna category.
docker compose exec db mysql -uroot -plaquesea tablon_nuncios -e "SHOW TABLES; DESCRIBE posts;"

Después recarga http://localhost:8080.

Si en el paso 2 aparece un error en lugar de “ready for connections”, lo más probable es un fallo en db/schema.sql. Comprueba que existe el archivo con ese nombre exacto, porque el docker-compose.yml lo monta como archivo:

bash
ls -l db/schema.sql

Si el archivo no existía al crear el volumen, Docker pudo crear una carpeta llamada schema.sql. En ese caso, bórrala y repite el arranque desde cero:

bash
docker compose down -v
rm -rf db/schema.sql      # solo si "ls -l" mostró que es una carpeta (empieza por "d")
# Vuelve a crear db/schema.sql con el contenido del paso 4 y luego:
docker compose up -d
PARTE 2: RESULTADO Y EXPLICACIÓN

Por qué falla

Connection refused (código 2002) significa que PHP llegó al host db, pero MySQL no estaba escuchando todavía en el puerto 3306. No es un error de credenciales ni de código.
Al arrancar con el volumen vacío, MySQL hace tres cosas antes de aceptar conexiones: inicializa el directorio de datos, ejecuta schema.sql y se reinicia. Si recargas la web durante ese proceso, ves este error.
El depends_on de nuestro docker-compose.yml solo espera a que el contenedor db arranque, no a que MySQL esté listo. Por eso web puede estar funcionando mientras la base de datos aún no responde.

Paso 1: docker compose ps

Muestra el estado de cada servicio. Si db aparece como Up, el contenedor funciona y solo hay que esperar. Si aparece Exited o Restarting, MySQL falló al arrancar y la causa estará en el log.

Paso 2: docker compose logs -f db

Muestra lo que escribe MySQL. -f (follow): deja el log abierto para ver las líneas nuevas según aparecen.
Resultado esperado: muchas líneas de inicialización y, al final, ready for connections dos veces. Cuando salga la segunda, ya puedes recargar la web y aparecerá el listado.

Paso 3: comprobar las tablas

SHOW TABLES; debe listar comments, posts y users. DESCRIBE posts; debe incluir la columna category. Si es así, la base de datos está bien y el problema original queda resuelto.

Caso de la carpeta schema.sql

Cuando Docker monta un archivo que no existe en tu máquina, crea una carpeta con ese nombre. MySQL entonces no encuentra ningún script que ejecutar y las tablas no se crean. Es poco probable, porque el archivo ya existía desde el paso 1, pero por eso lo compruebo con ls -l: una d al principio indica carpeta.

Interpretaciones que he elegido:

Asumo que db/schema.sql existe y contiene el esquema completo del paso 4. Si el log de MySQL muestra algún error, envíamelo y lo revisamos.

Puedes hacerme un archivo "TESTING.md" en el que tenga una lista de casos de prueba sobre esta práctica para probar yo manualmente
Enumeralos y debajo deja un espacio para poder poner yo un comentario sobre cada prueba.

PARTE 1: CÓDIGO

Te he preparado TESTING.md, con 66 casos de prueba numerados. Cada uno tiene pasos, resultado esperado, una casilla de resultado ([ ], [x] o [!]) y un espacio Comentario para que anotes lo que observes.

PARTE 2: RESULTADO Y EXPLICACIÓN

Cómo está organizado

Hay nueve secciones, una por cada paso de la práctica: infraestructura y base de datos, registro, login y logout, lectura y búsqueda, crear/editar/borrar anuncios, comentarios, CSRF, rutas limpias y seguridad adicional.
Al final hay una tabla resumen para contar cuántas pruebas salen bien y cuántas fallan por sección.

Qué necesitas para probar

Dos usuarios (A y B), uno en una ventana normal y otro en una de incógnito. Sin ellos no se pueden comprobar la autorización (el error 403 al tocar el anuncio de otro) ni los comentarios entre usuarios.
Las herramientas del navegador (F12). Muchas pruebas piden cambiar el HTML a mano, por ejemplo quitar el campo oculto csrf_token o el atributo required. Así compruebas que el servidor se protege solo y no depende de lo que el navegador deje enviar.

Interpretaciones que he elegido:

Los casos de prueba se refieren a la versión final de la práctica, la del paso 7 con URL limpias (/posts/3, /login...), y no a las URL antiguas con ?action=.
Como pediste solo pruebas manuales, no he incluido pruebas automatizadas con PHPUnit.