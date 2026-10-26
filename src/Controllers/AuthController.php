<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Csrf;
use App\Models\User;
use PDOException;

class AuthController
{
    /**
     * Guarda el modelo que se utilizará para consultar y guardar usuarios.
     *
     * El modelo es una clase que se encarga de hablar con la base de datos.
     */
    public function __construct(private User $userModel)
    {
    }

    /**
     * Muestra la página con el formulario de registro.
     */
    public function showRegisterForm(): void
    {
        $this->render('auth/register');
    }

    /**
     * Registra un usuario nuevo.
     *
     * Primero comprueba el token CSRF para evitar envíos de formularios
     * maliciosos. Después valida los datos, cifra la contraseña y guarda
     * el usuario en la base de datos.
     */
    public function register(): void
    {
        $this->requireValidCsrfToken();

        // trim() elimina espacios al principio y al final del texto recibido.
        $username = trim($_POST['username'] ?? '');
        $email    = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        // Si falta un dato o el email no tiene un formato válido, se vuelve
        // a mostrar el formulario junto con un mensaje de error.
        if ($username === '' || $password === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->render('auth/register', ['error' => 'Rellena todos los campos con datos válidos.']);
            return;
        }

        try {
            $this->userModel->insert([
                'username' => $username,
                'email'    => $email,
                // Nunca se guarda la contraseña original, sino una versión cifrada.
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

    /**
     * Muestra la página con el formulario de inicio de sesión.
     */
    public function showLoginForm(): void
    {
        $this->render('auth/login');
    }

    /**
     * Comprueba las credenciales e inicia la sesión del usuario.
     *
     * Si el email y la contraseña son correctos, guarda los datos básicos
     * del usuario en la sesión y lo redirige a la página principal.
     */
    public function login(): void
    {
        $this->requireValidCsrfToken();

        $email    = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';

        // El modelo devuelve el identificador del usuario o null si las
        // credenciales no coinciden.
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

    /**
     * Cierra la sesión actual y envía al usuario al formulario de login.
     */
    public function logout(): void
    {
        $this->startSessionIfNeeded();

        // Vacía todos los datos guardados en la sesión y la elimina.
        $_SESSION = [];
        session_destroy();

        header('Location: /login');
        exit;
    }

    /**
     * Comprueba que la petición contiene un token CSRF válido.
     *
     * El token CSRF permite verificar que el formulario procede de la
     * propia aplicación y no de una página externa.
     */
    private function requireValidCsrfToken(): void
    {
        $this->startSessionIfNeeded();

        if (!Csrf::isValid()) {
            $this->abort(403, 'Petición no válida (token CSRF incorrecto). Recarga la página e inténtalo de nuevo.');
        }
    }

    /**
     * Inicia la sesión solo si todavía no existe una sesión activa.
     *
     * PHP necesita una sesión iniciada para poder leer o escribir
     * información en la variable global $_SESSION.
     */
    private function startSessionIfNeeded(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    /**
     * Muestra una página de error y detiene la ejecución.
     *
     * @param int $code Código HTTP que se devolverá al navegador.
     * @param string $message Texto explicativo del error.
     */
    private function abort(int $code, string $message): never
    {
        http_response_code($code);
        $this->render('error', ['code' => $code, 'message' => $message]);
        exit;
    }

    /**
     * Carga una plantilla visual y le entrega los datos necesarios.
     *
     * @param string $view Ruta de la vista dentro de src/Views, sin ".php".
     * @param array $data Datos que la vista podrá utilizar.
     */
    private function render(string $view, array $data = []): void
    {
        // Convierte las claves de $data en variables disponibles en la vista.
        extract($data);
        require __DIR__ . '/../Views/' . $view . '.php';
    }
}