# TESTING.md — Casos de prueba manuales del Tablón de anuncios

**URLs de la práctica:** web en `http://localhost:8080` · phpMyAdmin en `http://localhost:8081` (usuario `root`, contraseña `josech123`).

**Preparación recomendada:** necesitas **dos usuarios** (Usuario A y Usuario B) para probar la autorización. Usa un navegador normal para A y una ventana de incógnito (o un segundo navegador) para B, porque cada uno tiene su propia sesión.

**Cómo usar este archivo:** realiza cada prueba, marca el resultado y escribe tus observaciones en el apartado **Comentario**.

- Resultado: `[ ]` sin probar · `[x]` correcto · `[!]` falla

---

## 1. Infraestructura y base de datos

### 1.1 Arranque de los contenedores
- **Pasos:** ejecuta `docker compose up -d` y luego `docker compose ps`.
- **Esperado:** los servicios `web`, `db` y `phpmyadmin` aparecen en estado `Up`.
- **Resultado:** `[X]`

**Comentario:**

&nbsp;

---

### 1.2 Acceso a la web
- **Pasos:** abre `http://localhost:8080`.
- **Esperado:** se muestra el listado de anuncios (vacío o con anuncios), sin errores de PHP.
- **Resultado:** `[X]`

**Comentario:**

&nbsp;

---

### 1.3 Acceso a phpMyAdmin
- **Pasos:** abre `http://localhost:8081` e inicia sesión con `root` / `root`.
- **Esperado:** aparece la base de datos `tablon_anuncios`.
- **Resultado:** `[X]`

**Comentario:**

&nbsp;

---

### 1.4 Estructura de las tablas
- **Pasos:** en phpMyAdmin, abre `tablon_anuncios` y revisa las tablas.
- **Esperado:** existen `users`, `posts` y `comments`. `posts` incluye la columna `category`. Hay claves foráneas `posts.user_id`, `comments.post_id` y `comments.user_id`.
- **Resultado:** `[X]`

**Comentario:**

&nbsp;

---

### 1.5 Autoload de Composer
- **Pasos:** comprueba que existe la carpeta `vendor/` y que la web carga sin el error `Class "App\Core\Database" not found`.
- **Esperado:** no aparece ese error.
- **Resultado:** `[X]`

**Comentario:**

&nbsp;

---

### 1.6 `config.php` fuera del control de versiones
- **Pasos:** ejecuta `git status` (o `git check-ignore config.php`).
- **Esperado:** `config.php` y `vendor/` no aparecen como archivos para subir; están ignorados.
- **Resultado:** `[X]`

**Comentario:**

&nbsp;

---

## 2. Registro de usuarios

### 2.1 Registro correcto
- **Pasos:** ve a `/register` y rellena usuario, email válido y contraseña. Envía el formulario.
- **Esperado:** redirige a `/login`.
- **Resultado:** `[X]`

**Comentario:**

&nbsp;

---

### 2.2 Nombre de usuario duplicado
- **Pasos:** intenta registrar un usuario con un `username` ya existente y un email distinto.
- **Esperado:** el mismo mensaje de error que en 2.3.
- **Resultado:** `[X]`

**Comentario:**

&nbsp;

---

## 3. Inicio y cierre de sesión

### 3.1 Login correcto
- **Pasos:** ve a `/login` e inicia sesión con un usuario registrado.
- **Esperado:** redirige a `/` y la cabecera muestra tu email y el enlace "Cerrar sesión".
- **Resultado:** `[X]`

**Comentario:**

&nbsp;

---

### 3.2 Contraseña incorrecta
- **Pasos:** inicia sesión con un email existente y una contraseña errónea.
- **Esperado:** aparece "Email o contraseña incorrectos." y no se inicia sesión.
- **Resultado:** `[X]`

**Comentario:**

&nbsp;

---

## 4. Anuncios: lectura y búsqueda

### 4.1 Listado de anuncios
- **Pasos:** crea al menos 3 anuncios con distintos títulos y categorías y abre `/posts`.
- **Esperado:** aparecen todos, con título (enlazado) y categoría.
- **Resultado:** `[X]`

**Comentario:**

&nbsp;

---

### 4.2 Listado vacío
- **Pasos:** con la tabla `posts` vacía, abre `/posts`.
- **Esperado:** aparece "No hay anuncios." sin errores.
- **Resultado:** `[X]`

**Comentario:**

&nbsp;

---

### 4.3 Ver un anuncio
- **Pasos:** pulsa el título de un anuncio del listado.
- **Esperado:** la URL es `/posts/<id>` y se ven título, categoría, fecha y contenido. Los saltos de línea del contenido se respetan.
- **Resultado:** `[X]`

**Comentario:**

&nbsp;

---

### 4.4 Anuncio inexistente
- **Pasos:** abre `/posts/9999`.
- **Esperado:** página "Error 404 – El anuncio no existe.".
- **Resultado:** `[X]`

**Comentario:**

&nbsp;

---

## 5. Anuncios: creación, edición y borrado

### 5.1 Crear sin estar logueado
- **Pasos:** sin sesión, abre `/posts/create`.
- **Esperado:** redirige a `/login` con el mensaje "Debes iniciar sesión para hacer esto.".
- **Resultado:** `[X]`

**Comentario:**

&nbsp;

---

### 5.2 Editar un anuncio propio
- **Pasos:** pulsa "Editar" en un anuncio tuyo, cambia el título y guarda.
- **Esperado:** el formulario viene relleno con los datos actuales. Tras guardar, redirige al anuncio y se ve el cambio.
- **Resultado:** `[X]`

**Comentario:**

&nbsp;

---

### 5.3 Borrar un anuncio propio
- **Pasos:** pulsa "Eliminar" en un anuncio tuyo y acepta la confirmación.
- **Esperado:** redirige a `/posts`, aparece "Anuncio eliminado." y el anuncio ya no está.
- **Resultado:** `[X]`

**Comentario:**

&nbsp;

---

## 6. Comentarios

### 6.1 Formulario según sesión
- **Pasos:** abre un anuncio con sesión iniciada y luego sin ella.
- **Esperado:** con sesión aparece el formulario "Añadir comentario". Sin sesión aparece el enlace "Inicia sesión para comentar".
- **Resultado:** `[X]`

**Comentario:**

&nbsp;

---

### 6.2 Comentar
- **Pasos:** escribe un comentario y pulsa "Comentar".
- **Esperado:** vuelves al anuncio (bajando a la sección de comentarios) y ves tu comentario con tu nombre de usuario y la fecha.
- **Resultado:** `[X]`

**Comentario:**

&nbsp;

---

## 8. Rutas limpias

### 8.1 Rutas principales
- **Pasos:** visita `/`, `/posts`, `/posts/create`, `/posts/<id>`, `/posts/edit/<id>`, `/login`, `/register`.
- **Esperado:** todas cargan la página correcta, sin `index.php?action=...` en la barra de direcciones.
- **Resultado:** `[X]`

**Comentario:**

&nbsp;

---

### 8.2 Ruta inexistente
- **Pasos:** abre `/esto-no-existe`.
- **Esperado:** error 404 "La página que buscas no existe.".
- **Resultado:** `[X]`

**Comentario:**

&nbsp;

---