<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($post['title']) ?></title>
    <link rel="stylesheet" href="/css/styles.css">
</head>
<body>
    <!-- Incluye la navegación de la aplicación. -->
    <?php require __DIR__ . '/../partials/header.php'; ?>

    <!-- Datos principales del anuncio. -->
    <h1><?= htmlspecialchars($post['title']) ?></h1>
    <p>Categoría: <?= htmlspecialchars($post['category']) ?></p>
    <p>Publicado: <?= htmlspecialchars($post['created_at']) ?></p>

    <!-- nl2br conserva los saltos de línea escritos por el usuario. -->
    <p><?= nl2br(htmlspecialchars($post['content'])) ?></p>

    <!-- Solo el autor del anuncio puede editarlo o eliminarlo. -->
    <?php if ($canEdit): ?>
        <a href="/posts/edit/<?= (int) $post['id'] ?>">Editar</a>

        <!-- El formulario usa POST porque eliminar modifica datos. -->
        <form method="post" action="/posts/delete/<?= (int) $post['id'] ?>"
              onsubmit="return confirm('¿Seguro que quieres borrar este anuncio?');">
            <!-- Token oculto para comprobar que la petición es legítima. -->
            <?= \App\Core\Csrf::field() ?>
            <button type="submit">Eliminar</button>
        </form>
    <?php endif; ?>

    <hr>

    <!-- Sección que contiene los comentarios del anuncio. -->
    <section id="comments">
        <!-- count() indica cuántos comentarios se han recibido desde el controlador. -->
        <h2>Comentarios (<?= count($comments) ?>)</h2>

        <!-- Se muestra un mensaje diferente cuando todavía no hay comentarios. -->
        <?php if (empty($comments)): ?>
            <p>Todavía no hay comentarios.</p>
        <?php else: ?>
            <!-- Recorre el array y crea un bloque HTML para cada comentario. -->
            <?php foreach ($comments as $comment): ?>
                <article>
                    <p>
                        <!-- Se muestran el autor y la fecha del comentario. -->
                        <strong><?= htmlspecialchars($comment['username']) ?></strong>
                        · <?= htmlspecialchars($comment['created_at']) ?>
                    </p>
                    <!-- Se conserva el formato original del texto escrito. -->
                    <p><?= nl2br(htmlspecialchars($comment['content'])) ?></p>
                </article>
            <?php endforeach; ?>
        <?php endif; ?>

        <!-- Solo los usuarios identificados pueden escribir comentarios. -->
        <?php if (isset($_SESSION['user_id'])): ?>
            <h3>Añadir comentario</h3>
            <form method="post" action="/comments/store/<?= (int) $post['id'] ?>">
                <!-- Protege el envío contra peticiones CSRF. -->
                <?= \App\Core\Csrf::field() ?>
                <label>Tu comentario<br>
                    <!-- required impide enviar el formulario vacío desde el navegador. -->
                    <textarea name="content" rows="4" cols="60" required></textarea>
                </label><br>
                <button type="submit">Comentar</button>
            </form>
        <?php else: ?>
            <!-- Los visitantes deben identificarse antes de comentar. -->
            <p><a href="/login">Inicia sesión</a> para comentar.</p>
        <?php endif; ?>
    </section>

    <!-- Enlace para volver al listado general de anuncios. -->
    <p><a href="/posts">Volver al listado</a></p>
</body>
</html>