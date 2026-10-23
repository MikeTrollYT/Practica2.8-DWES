<?php

declare(strict_types=1);

namespace App\Core;

use PDO;
use PDOStatement;

/**
 * Capa de acceso a datos reutilizable.
 * Los modelos usarán esta clase y nunca crearán su propio PDO.
 */
class Database
{
    // Privada: nadie fuera de esta clase debe tocar el PDO directamente.
    private PDO $pdo;

    public function __construct()
    {
        // __DIR__ evita problemas de rutas relativas según desde dónde se ejecute el script.
        $config = require __DIR__ . '/../../config.php';
        $db = $config['db'];

        $dsn = sprintf(
            'mysql:host=%s;dbname=%s;charset=%s',
            $db['host'],
            $db['dbname'],
            $db['charset']
        );

        $this->pdo = new PDO($dsn, $db['user'], $db['pass'], [
            // Lanza excepciones ante errores SQL, en vez de fallar en silencio.
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            // Las filas llegan como array asociativo ['columna' => valor], sin duplicados numéricos.
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            // Prepared statements reales del servidor: mejor protección contra inyección SQL.
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    }

    /**
     * Prepara y ejecuta una consulta con parámetros.
     * Devuelve siempre el PDOStatement para que los otros métodos lo reutilicen.
     */
    public function query(string $sql, array $params = []): PDOStatement
    {
        // Separar SQL y datos (prepare + execute) es lo que evita la inyección SQL.
        $statement = $this->pdo->prepare($sql);
        $statement->execute($params);

        return $statement;
    }

    /**
     * Devuelve todas las filas, o null si la consulta no devuelve ninguna.
     */
    public function fetchAll(string $sql, array $params = []): ?array
    {
        $rows = $this->query($sql, $params)->fetchAll();

        // fetchAll() devuelve [] cuando no hay resultados; lo convertimos a null según el enunciado.
        return $rows === [] ? null : $rows;
    }

    /**
     * Devuelve solo la primera fila, o null si no hay ninguna.
     */
    public function fetchOne(string $sql, array $params = []): ?array
    {
        $row = $this->query($sql, $params)->fetch();

        // fetch() devuelve false cuando no hay más filas; lo convertimos a null.
        return $row === false ? null : $row;
    }

    /**
     * Devuelve el id autogenerado por el último INSERT.
     * Hace falta porque $pdo es privado y los modelos no pueden llamarlo.
     */
    public function lastInsertId(): string
    {
        return $this->pdo->lastInsertId();
    }
}