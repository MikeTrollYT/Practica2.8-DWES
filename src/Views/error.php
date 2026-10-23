<?php
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Error <?= (int) $code ?></title>
</head>
<body>
    <?php require __DIR__ . '/partials/header.php'; ?>

    <h1>Error <?= (int) $code ?></h1>
    <p><?= htmlspecialchars($message) ?></p>
    <p><a href="/">Volver al inicio</a></p>
</body>
</html>