<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($heading) ?></title>
    <link rel="stylesheet" href="/css/styles.css">
</head>
<body>
    <?php require __DIR__ . '/../partials/header.php'; ?>

    <h1><?= htmlspecialchars($heading) ?></h1>

    <?php if (!empty($error)): ?>
        <p style="color:red"><?= htmlspecialchars($error) ?></p>
    <?php endif; ?>

    <form method="post" action="<?= htmlspecialchars($formAction) ?>">
        <?= \App\Core\Csrf::field() ?>
        <label>Título
            <input type="text" name="title" maxlength="150" required
                   value="<?= htmlspecialchars($post['title'] ?? '') ?>">
        </label><br>
        <label>Categoría
            <input type="text" name="category" maxlength="50" required
                   value="<?= htmlspecialchars($post['category'] ?? '') ?>">
        </label><br>
        <label>Contenido<br>
            <textarea name="content" rows="8" cols="60" required><?= htmlspecialchars($post['content'] ?? '') ?></textarea>
        </label><br>
        <button type="submit">Guardar</button>
    </form>

    <p><a href="/posts">Cancelar</a></p>
</body>
</html>