<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/**
 * Modelo de usuarios. Hereda getAll, getById, insert, update y delete de BaseModel
 * y solo añade lo propio de un usuario: localizarlo para el login.
 */
class User extends BaseModel
{
    public function __construct(Database $db)
    {
        // El nombre de la tabla queda fijado aquí, para que quien use User no tenga que pasarlo.
        parent::__construct($db, 'users');
    }

    /**
     * Comprueba email + contraseña y devuelve el id del usuario, o null si no coinciden.
     */
    public function findByEmail(string $email, string $password): ?int
    {
        // Buscamos solo por email. No se puede buscar también por contraseña en el SQL:
        // password_hash() genera un hash distinto cada vez (lleva un "salt" aleatorio),
        // así que el hash guardado nunca será igual al que calculemos de nuevo.
        $user = $this->db->fetchOne(
            'SELECT id, password FROM users WHERE email = :email',
            ['email' => $email]
        );

        // Mismo mensaje de fallo para "email inexistente" y "contraseña errónea":
        // así no revelamos a un atacante qué emails están registrados.
        if ($user === null || !password_verify($password, $user['password'])) {
            return null;
        }

        return (int) $user['id'];
    }
}