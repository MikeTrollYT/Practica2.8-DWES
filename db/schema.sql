CREATE DATABASE IF NOT EXISTS tablon_anuncios
    CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE tablon_anuncios;

CREATE TABLE users (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username   VARCHAR(50)  NOT NULL UNIQUE,
    email      VARCHAR(100) NOT NULL UNIQUE,
    -- 255 caracteres: password_hash() puede generar hashes largos y su algoritmo puede cambiar en el futuro.
    password   VARCHAR(255) NOT NULL,
    created_at TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE posts (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id    INT UNSIGNED NOT NULL,
    title      VARCHAR(150) NOT NULL,
    content    TEXT         NOT NULL,
    -- Nueva: el enunciado pide poder buscar por categoría.
    category   VARCHAR(50)  NOT NULL DEFAULT 'General',
    created_at TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    -- Si se borra un usuario, se borran sus anuncios para no dejar registros huérfanos.
    CONSTRAINT fk_posts_user FOREIGN KEY (user_id)
        REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE comments (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    post_id    INT UNSIGNED NOT NULL,
    user_id    INT UNSIGNED NOT NULL,
    content    TEXT         NOT NULL,
    created_at TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_comments_post FOREIGN KEY (post_id)
        REFERENCES posts(id) ON DELETE CASCADE,
    CONSTRAINT fk_comments_user FOREIGN KEY (user_id)
        REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB;