<?php
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Tablón de anuncios</title>
    <link rel="stylesheet" href="/css/styles.css">
</head>
<body>
    <?php require __DIR__ . '/../partials/header.php'; ?>

    <h1>Anuncios</h1>

    <!-- GET a /posts: la búsqueda llega como /posts?search=mueble -->
    <form method="get" action="/posts">
        <input type="text" name="search" placeholder="Título o categoría"
               value="<?= htmlspecialchars($search) ?>">
        <button type="submit">Buscar</button>
    </form>

    <p><a href="/posts/create">Nuevo anuncio</a></p>

    <?php if (empty($posts)): ?>
        <p>No hay anuncios.</p>
    <?php else: ?>
        <ul>
            <?php foreach ($posts as $post): ?>
                <li>
                    <a href="/posts/<?= (int) $post['id'] ?>">
                        <?= htmlspecialchars($post['title']) ?>
                    </a>
                    (<?= htmlspecialchars($post['category']) ?>)
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</body>
</html>