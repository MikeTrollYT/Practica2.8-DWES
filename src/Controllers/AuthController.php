<?php

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