<?php

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