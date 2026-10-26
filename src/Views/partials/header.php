<?php
?>
<header>
    <nav>
        <!-- Enlace principal para volver al tablón de anuncios. -->
        <a href="/">Tablón de anuncios</a>

        <!-- Si hay un usuario guardado en la sesión, significa que ha iniciado sesión. -->
        <?php if (isset($_SESSION['user_id'])): ?>
            <!-- Se escapa el email antes de mostrarlo en HTML por seguridad. -->
            <span><?= htmlspecialchars($_SESSION['user_email']) ?></span>
            <a href="/logout">Cerrar sesión</a>
        <?php else: ?>
            <!-- Los usuarios no identificados solo pueden acceder al login. -->
            <a href="/login">Iniciar sesión</a>
        <?php endif; ?>
    </nav>

    <!-- Los mensajes flash se muestran una sola vez, normalmente después
         de crear, modificar o eliminar algún elemento. -->
    <?php if (isset($_SESSION['flash'])): ?>
        <p style="color:darkorange"><?= htmlspecialchars($_SESSION['flash']) ?></p>
        <?php
        // Se borra justo después de mostrarlo para que no aparezca de nuevo.
        unset($_SESSION['flash']);
        ?>
    <?php endif; ?>
</header>