<?php
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Error <?= (int) $code ?></title>
    <link rel="stylesheet" href="/css/styles.css">
</head>
<body>
    <?php require __DIR__ . '/partials/header.php'; ?>

    <h1>Error <?= (int) $code ?></h1>
    <p><?= htmlspecialchars($message) ?></p>
    <p><a href="/">Volver al inicio</a></p>
</body>
</html>