<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/**
 * Modelo de anuncios. Hereda el CRUD de BaseModel y solo añade la búsqueda.
 */
class Post extends BaseModel
{
    public function __construct(Database $db)
    {
        parent::__construct($db, 'posts');
    }

    /**
     * Devuelve los anuncios cuyo título o categoría contienen $term, o null si no hay ninguno.
     */
    public function search(string $term): ?array
    {
        // Se usan DOS marcadores distintos (:title_term y :category_term) aunque valgan lo mismo:
        // con EMULATE_PREPARES = false, MySQL no permite repetir un marcador con nombre en la misma consulta.
        // Los % van en el VALOR (no en el SQL) para que el término del usuario siga siendo solo un dato.
        return $this->db->fetchAll(
            'SELECT * FROM posts WHERE title LIKE :title_term OR category LIKE :category_term',
            [
                'title_term'    => '%' . $term . '%',
                'category_term' => '%' . $term . '%',
            ]
        );
    }
}