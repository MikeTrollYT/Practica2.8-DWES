<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Registro</title>
    <link rel="stylesheet" href="/css/styles.css">
</head>
<body>
    <?php require __DIR__ . '/../partials/header.php'; ?>

    <h1>Crear cuenta</h1>

    <?php if (!empty($error)): ?>
        <p style="color:red"><?= htmlspecialchars($error) ?></p>
    <?php endif; ?>

    <form method="post" action="/register">
        <?= \App\Core\Csrf::field() ?>
        <label>Usuario <input type="text" name="username" required></label><br>
        <label>Email <input type="email" name="email" required></label><br>
        <label>Contraseña <input type="password" name="password" required></label><br>
        <button type="submit">Registrarme</button>
    </form>

    <p>¿Ya tienes cuenta? <a href="/login">Inicia sesión</a></p>
</body>
</html>