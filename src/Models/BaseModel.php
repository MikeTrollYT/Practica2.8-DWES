<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Database;

/**
 * Modelo base con las operaciones CRUD comunes a todas las tablas.
 * Es abstracta para que nadie pueda hacer "new BaseModel()": solo tiene
 * sentido a través de modelos concretos (User, Post, Comment...).
 */
abstract class BaseModel
{
    // "protected": los modelos hijos necesitan acceder a ellos para añadir
    // sus propias consultas, pero el código externo no debe tocarlos.
    protected Database $db;
    protected string $table;

    /**
     * La instancia de Database se recibe desde fuera (inyección de dependencias)
     * en lugar de crearla aquí: así todos los modelos pueden compartir la misma
     * conexión y el código es más fácil de probar.
     */
    public function __construct(Database $db, string $table)
    {
        $this->db = $db;
        $this->table = $table;
    }

    /**
     * Devuelve todos los registros de la tabla, o null si está vacía.
     */
    public function getAll(): ?array
    {
        // El nombre de la tabla va concatenado porque PDO no permite usar
        // marcadores (:tabla) para nombres de tablas o columnas. Es seguro
        // mientras el valor lo fije el propio modelo hijo y nunca el usuario.
        return $this->db->fetchAll("SELECT * FROM {$this->table}");
    }

    /**
     * Devuelve el registro con ese id, o null si no existe.
     */
    public function getById(int $id): ?array
    {
        // Aquí SÍ usamos marcador: $id es un dato y debe ir separado del SQL.
        return $this->db->fetchOne(
            "SELECT * FROM {$this->table} WHERE id = :id",
            ['id' => $id]
        );
    }

    /**
     * Elimina el registro con ese id.
     * Usa query() de Database: ya prepara y ejecuta, y aquí no necesitamos
     * leer resultados, así que descartamos el PDOStatement devuelto.
     */
    public function delete(int $id): void
    {
        $this->db->query(
            "DELETE FROM {$this->table} WHERE id = :id",
            ['id' => $id]
        );
    }

    /**
     * Inserta un registro a partir de pares campo => valor.
     * No pone tipo de retorno porque el enunciado no lo fija; devuelvo
     * el id generado por si el controlador lo necesita (p. ej. para redirigir).
     */
    public function insert(array $data)
    {
        // Las claves del array son los nombres de columna.
        $columns = array_keys($data);

        // Un marcador con nombre por columna: ":email", ":username"...
        $placeholders = array_map(fn(string $column) => ':' . $column, $columns);

        $sql = sprintf(
            'INSERT INTO %s (%s) VALUES (%s)',
            $this->table,
            implode(', ', $columns),
            implode(', ', $placeholders)
        );

        // $data ya tiene la forma ['email' => ..., ...], que es la que
        // espera execute() para marcadores con nombre.
        $this->db->query($sql, $data);

        // Devolvemos el id autogenerado (ver nota sobre lastInsertId al final).
        return $this->db->lastInsertId();
    }

    /**
     * Modifica el registro $id con los pares campo => valor de $data.
     */
    public function update(int $id, array $data)
    {
        // Construimos "campo = :campo" por cada columna a modificar.
        $assignments = array_map(
            fn(string $column) => "{$column} = :{$column}",
            array_keys($data)
        );

        $sql = sprintf(
            'UPDATE %s SET %s WHERE id = :id',
            $this->table,
            implode(', ', $assignments)
        );

        // Añadimos el id con otra clave ("id") para no mezclarlo con los
        // campos de $data. Si $data trajera una clave "id", chocaría:
        // por eso el id del parámetro $id manda (va después y lo sobrescribe).
        $params = $data;
        $params['id'] = $id;

        $this->db->query($sql, $params);
    }
}