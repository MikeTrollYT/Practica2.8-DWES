# Tablón de anuncios - Práctica 2.8 DWES

Aplicación web de un tablón de anuncios desarrollada en PHP. Permite a los
usuarios registrarse, iniciar sesión, publicar anuncios clasificados por
categoría y buscar anuncios. Cada anuncio tiene una página de detalle donde
los usuarios autenticados pueden añadir comentarios.

El proyecto está construido siguiendo una estructura MVC sencilla y utiliza
Docker para levantar el servidor web, la base de datos MySQL y phpMyAdmin.

## Funcionalidades

- Registro de usuarios con email y contraseña cifrada.
- Inicio y cierre de sesión mediante sesiones PHP.
- Creación, edición y eliminación de anuncios por parte de su autor.
- Título, contenido y categoría para cada anuncio.
- Listado y búsqueda de anuncios mediante el parámetro `search`.
- Página de detalle con los comentarios del anuncio.
- Comentarios para usuarios autenticados.
- Control de permisos: solo el autor puede modificar o borrar sus anuncios.
- Protección de formularios mediante tokens CSRF.
- Validación de datos y respuestas de error HTTP (`403`, `404`, etc.).

## Tecnologías utilizadas

- PHP 8.3
- Apache
- MySQL 8.0
- PDO para el acceso a datos
- Composer para el autoload PSR-4
- Docker y Docker Compose
- phpMyAdmin para administrar la base de datos

## Estructura del proyecto

```text
Practica2.8-DWES/
├── db/
│   └── schema.sql                 # Creación de la base de datos y sus tablas
├── public/
│   ├── .htaccess                  # Reescritura de URLs hacia el front controller
│   ├── index.php                  # Punto de entrada y definición de rutas
│   └── css/
│       └── styles.css             # Estilos visuales de las páginas
├── src/
│   ├── Controllers/               # Lógica de las peticiones
│   │   ├── AuthController.php
│   │   ├── CommentController.php
│   │   ├── PostController.php
│   │   └── UserController.php
│   ├── Core/                      # Componentes comunes de la aplicación
│   │   ├── Csrf.php
│   │   ├── Database.php
│   │   └── Router.php
│   ├── Models/                    # Acceso a datos y entidades
│   │   ├── BaseModel.php
│   │   ├── Comment.php
│   │   ├── Post.php
│   │   └── User.php
│   └── Views/                     # Plantillas HTML y PHP
│       ├── auth/
│       │   ├── login.php
│       │   └── register.php
│       ├── posts/
│       │   ├── form.php
│       │   ├── index.php
│       │   └── show.php
│       ├── error.php
│       └── partials/
│           └── header.php
├── .gitignore
├── composer.json                  # Configuración del autoload de Composer
├── config.php                     # Configuración de conexión a MySQL
├── docker-compose.yml             # Servicios web, db y phpMyAdmin
├── Dockerfile                     # Imagen PHP + Apache
├── TESTING.md                     # Pruebas manuales del proyecto
└── README.md
```

## Arquitectura

La aplicación utiliza `public/index.php` como único punto de entrada. Este
archivo crea la conexión compartida a la base de datos, instancia los modelos
y controladores y registra las rutas en el enrutador.

- **Modelos:** encapsulan las consultas a MySQL mediante PDO y consultas
  preparadas.
- **Controladores:** reciben las peticiones, validan los datos, comprueban la
  sesión y coordinan modelos y vistas.
- **Vistas:** generan las páginas HTML que ve el usuario.
- **Core:** contiene servicios reutilizables como la conexión a la base de
  datos, el enrutador y la protección CSRF.

La base de datos contiene tres tablas principales: `users`, `posts` y
`comments`. Los anuncios pertenecen a un usuario y los comentarios pertenecen
tanto a un anuncio como a un usuario. Las relaciones utilizan borrado en
cascada para evitar registros huérfanos.

## Instalación con Docker

### Arranque

Desde la carpeta raíz del proyecto, ejecuta:

```bash
docker compose up --build
```

Después, abre las siguientes direcciones:

- Aplicación: <http://localhost:8080>
- phpMyAdmin: <http://localhost:8081>

La primera ejecución crea automáticamente la base de datos
`tablon_anuncios` usando [`db/schema.sql`](db/schema.sql). Los datos de MySQL
se conservan en el volumen Docker `db_data`.

Para detener los contenedores:

```bash
docker compose down
```

Para detenerlos y eliminar también los datos:

```bash
docker compose down -v
```

## Flujo básico de uso

1. Accede a la aplicación y crea una cuenta desde **Registrarse**.
2. Inicia sesión.
3. Crea un anuncio indicando título, categoría y contenido.
4. Consulta el anuncio publicado y añade comentarios si lo deseas.
5. Desde el detalle del anuncio, su autor puede editarlo o eliminarlo.
6. Usa el buscador del listado para localizar anuncios.

## Rutas principales

| Método | Ruta | Descripción |
| --- | --- | --- |
| `GET` | `/` o `/posts` | Lista de anuncios |
| `GET` | `/posts/{id}` | Muestra un anuncio y sus comentarios |
| `GET` | `/posts/create` | Formulario de nuevo anuncio |
| `POST` | `/posts/store` | Guarda un anuncio |
| `GET` | `/posts/edit/{id}` | Formulario de edición del autor |
| `POST` | `/posts/update/{id}` | Actualiza un anuncio |
| `POST` | `/posts/delete/{id}` | Elimina un anuncio |
| `POST` | `/comments/store/{id}` | Añade un comentario |
| `GET` | `/register` | Formulario de registro |
| `GET` | `/login` | Formulario de inicio de sesión |
| `GET` | `/logout` | Cierra la sesión |

La búsqueda se realiza, por ejemplo, con
`http://localhost:8080/posts?search=bicicleta`.

## Pruebas

El archivo [`TESTING.md`](TESTING.md) contiene una lista de comprobaciones
manuales para validar el registro, autenticación, gestión de anuncios,
comentarios, búsquedas y controles de seguridad.
