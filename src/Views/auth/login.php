<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Iniciar sesión</title>
    <link rel="stylesheet" href="/css/styles.css">
</head>
<body>
    <?php require __DIR__ . '/../partials/header.php'; ?>

    <h1>Iniciar sesión</h1>

    <?php if (!empty($error)): ?>
        <p style="color:red"><?= htmlspecialchars($error) ?></p>
    <?php endif; ?>

    <form method="post" action="/login">
        <?= \App\Core\Csrf::field() ?>
        <label>Email <input type="email" name="email" required></label><br>
        <label>Contraseña <input type="password" name="password" required></label><br>
        <button type="submit">Entrar</button>
    </form>

    <p>¿No tienes cuenta? <a href="/register">Regístrate</a></p>
</body>
</html>