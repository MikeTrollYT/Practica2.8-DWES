<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/**
 * Modelo de comentarios. El CRUD genérico (insert, getById...) viene de BaseModel;
 * aquí solo añadimos lo propio de la relación 1:N con los anuncios.
 */
class Comment extends BaseModel
{
    public function __construct(Database $db)
    {
        parent::__construct($db, 'comments');
    }

    /**
     * Devuelve los comentarios de un anuncio, del más antiguo al más reciente,
     * o null si el anuncio no tiene ninguno.
     */
    public function getByPostId(int $postId): ?array
    {
        // JOIN con users para traer el nombre del autor en la misma consulta;
        // si no, tendríamos que hacer una consulta extra por cada comentario.
        // El "id" como segundo criterio desempata comentarios creados en el mismo segundo.
        return $this->db->fetchAll(
            'SELECT comments.id, comments.content, comments.created_at, users.username
               FROM comments
         INNER JOIN users ON users.id = comments.user_id
              WHERE comments.post_id = :post_id
           ORDER BY comments.created_at ASC, comments.id ASC',
            ['post_id' => $postId]
        );
    }
}