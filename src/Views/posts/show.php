<?php
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($post['title']) ?></title>
    <link rel="stylesheet" href="/css/styles.css">
</head>
<body>
    <?php require __DIR__ . '/../partials/header.php'; ?>

    <h1><?= htmlspecialchars($post['title']) ?></h1>
    <p>Categoría: <?= htmlspecialchars($post['category']) ?></p>
    <p>Publicado: <?= htmlspecialchars($post['created_at']) ?></p>

    <p><?= nl2br(htmlspecialchars($post['content'])) ?></p>

    <?php if ($canEdit): ?>
        <a href="/posts/edit/<?= (int) $post['id'] ?>">Editar</a>

        <form method="post" action="/posts/delete/<?= (int) $post['id'] ?>"
              onsubmit="return confirm('¿Seguro que quieres borrar este anuncio?');">
            <?= \App\Core\Csrf::field() ?>
            <button type="submit">Eliminar</button>
        </form>
    <?php endif; ?>

    <hr>

    <section id="comments">
        <h2>Comentarios (<?= count($comments) ?>)</h2>

        <?php if (empty($comments)): ?>
            <p>Todavía no hay comentarios.</p>
        <?php else: ?>
            <?php foreach ($comments as $comment): ?>
                <article>
                    <p>
                        <strong><?= htmlspecialchars($comment['username']) ?></strong>
                        · <?= htmlspecialchars($comment['created_at']) ?>
                    </p>
                    <p><?= nl2br(htmlspecialchars($comment['content'])) ?></p>
                </article>
            <?php endforeach; ?>
        <?php endif; ?>

        <?php if (isset($_SESSION['user_id'])): ?>
            <h3>Añadir comentario</h3>
            <form method="post" action="/comments/store/<?= (int) $post['id'] ?>">
                <?= \App\Core\Csrf::field() ?>
                <label>Tu comentario<br>
                    <textarea name="content" rows="4" cols="60" required></textarea>
                </label><br>
                <button type="submit">Comentar</button>
            </form>
        <?php else: ?>
            <p><a href="/login">Inicia sesión</a> para comentar.</p>
        <?php endif; ?>
    </section>

    <p><a href="/posts">Volver al listado</a></p>
</body>
</html>