<?php
?>
<header>
    <nav>
        <a href="/">Tablón de anuncios</a>

        <?php if (isset($_SESSION['user_id'])): ?>
            <span><?= htmlspecialchars($_SESSION['user_email']) ?></span>
            <a href="/logout">Cerrar sesión</a>
        <?php else: ?>
            <a href="/login">Iniciar sesión</a>
        <?php endif; ?>
    </nav>

    <?php if (isset($_SESSION['flash'])): ?>
        <p style="color:darkorange"><?= htmlspecialchars($_SESSION['flash']) ?></p>
        <?php
        // Se borra justo después de mostrarlo para que no aparezca en la página siguiente.
        unset($_SESSION['flash']);
        ?>
    <?php endif; ?>
</header>