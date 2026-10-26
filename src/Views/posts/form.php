<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($heading) ?></title>
    <link rel="stylesheet" href="/css/styles.css">
</head>
<body>
    <!-- Incluye la navegación y los datos del usuario conectado. -->
    <?php require __DIR__ . '/../partials/header.php'; ?>

    <!-- Título que indica al usuario qué operación está realizando. -->
    <h1><?= htmlspecialchars($heading) ?></h1>

    <!-- Solo se muestra si el controlador ha enviado un mensaje de error. -->
    <?php if (!empty($error)): ?>
        <p style="color:red"><?= htmlspecialchars($error) ?></p>
    <?php endif; ?>

    <!-- $formAction permite reutilizar este formulario para crear o editar. -->
    <form method="post" action="<?= htmlspecialchars($formAction) ?>">
        <!-- Campo oculto que protege el formulario contra ataques CSRF. -->
        <?= \App\Core\Csrf::field() ?>

        <!-- El atributo required obliga a rellenar el campo en el navegador.
             maxlength limita el número máximo de caracteres. -->
        <label>Título
            <input type="text" name="title" maxlength="150" required
                   value="<?= htmlspecialchars($post['title'] ?? '') ?>">
        </label><br>

        <!-- Categoría del anuncio, con un máximo de 50 caracteres. -->
        <label>Categoría
            <input type="text" name="category" maxlength="50" required
                   value="<?= htmlspecialchars($post['category'] ?? '') ?>">
        </label><br>

        <!-- Área de texto para escribir el contenido del anuncio. -->
        <label>Contenido<br>
            <textarea name="content" rows="8" cols="60" required><?= htmlspecialchars($post['content'] ?? '') ?></textarea>
        </label><br>

        <!-- Envía los datos al controlador indicado en $formAction. -->
        <button type="submit">Guardar</button>
    </form>

    <!-- Permite abandonar el formulario y volver al listado de anuncios. -->
    <p><a href="/posts">Cancelar</a></p>
</body>
</html>