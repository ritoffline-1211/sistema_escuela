# CRUD Escuela

CRUD completo de **alumnos** y **grupos** sobre PHP + MySQL (XAMPP), manteniendo la arquitectura original: una API en `endpoints.php` y vistas que consumen con `fetch`.

## Requisitos

- XAMPP con Apache y MySQL activos.
- PHP 8.1 o superior (probado con PHP 8.2.12).
- Base de datos `escuela` en `localhost` (la crean las migraciones si no existe).
- Extensión `mysqli` y `curl` habilitadas (vienen con XAMPP).
- Acceso a la línea de comandos para ejecutar migraciones y tests.

## Puesta en marcha

1. Copia el proyecto en `C:\xampp\htdocs\escuela`.
2. Inicia **Apache** y **MySQL** desde el panel de XAMPP.
3. Aplica las migraciones de la base de datos:

   ```powershell
   C:\xampp\php\php.exe migrate.php
   ```

4. Abre `http://localhost/escuela/` e inicia sesión.

`index.php` redirige a `login.php` si no hay sesión; después del inicio de sesión va a `lista_alumnos.php`. Las migraciones crean la base `escuela`, las tablas y el historial de migraciones.

## Autenticación y usuarios

El sistema usa sesiones PHP (`escuela_sesion`). Todas las páginas están protegidas: al entrar sin sesión se redirige a `login.php`. El formulario incluye protección CSRF y las contraseñas se guardan con `password_hash()` (bcrypt).

Usuarios creados por la migración `008_usuarios.sql`:

| Usuario | Contraseña | Rol | Permisos |
| --- | --- | --- | --- |
| `ricardo` | `1211` | `admin` | CRUD completo |
| `profesor` | `1212` | `consulta` | Solo lectura |

El rol `consulta` ve los listados en **Modo consulta**, no ve los botones de alta/edición/eliminación, no puede abrir los formularios y la API le responde `403` si intenta modificar datos. El botón **Salir** cierra la sesión (`logout.php`).

### Token de la API y de la documentación

La API exige autenticación: acepta la sesión del navegador o un token especial. El token por defecto es:

```text
442e35cdf0b4225362746e3f8e7a17d4a393b25d2495a628
```

Se puede cambiar con la variable de entorno `API_TOKEN` o editando `config/auth.php`. El token se puede enviar de tres formas:

```bash
curl -H "Authorization: Bearer 442e35cdf0b4225362746e3f8e7a17d4a393b25d2495a628" \
  "http://localhost/escuela/endpoints.php?tipo=alumnos"

curl -H "X-API-Token: 442e35cdf0b4225362746e3f8e7a17d4a393b25d2495a628" \
  "http://localhost/escuela/endpoints.php?tipo=alumnos"

curl "http://localhost/escuela/endpoints.php?tipo=alumnos&token=442e35cdf0b4225362746e3f8e7a17d4a393b25d2495a628"
```

Sin sesión ni token, la API responde `401`. El token siempre actúa como administrador.

## Documentación de la API (Swagger)

La API está documentada con OpenAPI 3.0.3. **No hay enlace a Swagger en el frontend**: solo se puede abrir con el token:

```text
http://localhost/escuela/docs/index.php
```

Entra con el token desde el formulario o directamente:

```text
http://localhost/escuela/docs/index.php?token=442e35cdf0b4225362746e3f8e7a17d4a393b25d2495a628
```

El token queda guardado en la sesión y Swagger envía las cabeceras de autorización automáticamente en **Try it out**. **Cerrar acceso** lo elimina de la sesión. Sin token, `docs/openapi.php` responde `403` y el contrato no se expone.

Archivos:

| Archivo | Descripción |
| --- | --- |
| `docs/index.php` | Formulario de token y página que monta Swagger UI |
| `docs/openapi.php` | Entrega el contrato solo si la sesión tiene un token válido |
| `docs/openapi_data.php` | Contrato OpenAPI almacenado; no imprime nada si se pide directamente |
| `docs/swagger-ui/` | JavaScript y CSS de Swagger UI servidos localmente (no requiere CDN) |

Ten en cuenta que:

- `tipo` es obligatorio y acepta `alumnos` o `grupos`.
- `id` es necesario para consultar un registro, editar, eliminar y duplicar.
- Para duplicar: `tipo=alumnos`, `id=<id>`, `accion=duplicar` y sin cuerpo.
- Las operaciones de escritura devuelven `201` al crear o duplicar y `200` al actualizar o eliminar.

## Estructura del proyecto

```text
escuela/
├── config/
│   ├── auth.php           # Token de la API y nombre de la sesión
│   └── database.php       # Configuración de conexión a MySQL
├── docs/
│   ├── index.php          # Acceso por token y Swagger UI
│   ├── openapi.php        # Contrato OpenAPI protegido
│   ├── openapi_data.php   # Datos del contrato OpenAPI 3.0.3
│   └── swagger-ui/        # Assets locales de Swagger UI
├── migrations/
│   ├── 001_create_grupos.sql
│   ├── 002_create_alumnos.sql
│   ├── 003_sp_crear_alumno.sql
│   ├── 004_sp_actualizar_alumno.sql
│   ├── 005_sp_crear_grupo.sql
│   ├── 006_sp_actualizar_grupo.sql
│   ├── 007_sp_duplicar_alumno.sql
│   └── 008_usuarios.sql   # Tabla de usuarios y cuentas iniciales
├── migrate.php            # Runner de migraciones (up / status)
├── auth.php               # Sesión, CSRF, login, token y autorización
├── login.php              # Formulario de inicio de sesión
├── logout.php             # Cierre de sesión con CSRF
├── endpoints.php          # API REST protegida (alumnos y grupos)
├── index.php              # Redirección según la sesión
├── lista_alumnos.php      # Listado, edición y eliminación de alumnos
├── crear_alumno.php       # Alta de alumno (solo admin)
├── editar_alumno.php      # Edición de alumno (?id=, solo admin)
├── lista_grupos.php       # Listado, edición y eliminación de grupos
├── crear_grupo.php        # Alta de grupo (solo admin)
├── editar_grupo.php       # Edición de grupo (?id=, solo admin)
├── app.js                 # Helpers de fetch, mensajes y carga de grupos
├── estilos.css            # Estilos de las vistas
├── README.md
└── tests/
    └── api_test.php       # Pruebas de la API y la autenticación
```

## Estructura de la base de datos

Base de datos: `escuela`  
Motor: InnoDB  
Cotejamiento: `utf8mb4_general_ci`

### Tabla `grupos`

| Columna | Tipo | Nulo | Clave | Descripción |
| --- | --- | --- | --- | --- |
| `IdGrupos` | `INT(11)` | No | PK, AUTO_INCREMENT | Identificador del grupo |
| `grupo` | `VARCHAR(50)` | No | — | Nombre del grupo |

### Tabla `alumnos`

| Columna | Tipo | Nulo | Clave | Descripción |
| --- | --- | --- | --- | --- |
| `IdAlumnos` | `INT(11)` | No | PK, AUTO_INCREMENT | Identificador del alumno |
| `nombre` | `VARCHAR(100)` | No | — | Nombre completo |
| `matricula` | `VARCHAR(20)` | No | — | Matrícula del alumno |
| `grado` | `VARCHAR(20)` | No | — | Grado escolar |
| `IdGrupos` | `INT(11)` | Sí | FK → `grupos.IdGrupos` | Grupo asignado |

### Tabla `migraciones`

Tabla interna creada y administrada por `migrate.php`.

| Columna | Tipo | Nulo | Clave | Descripción |
| --- | --- | --- | --- | --- |
| `id` | `INT UNSIGNED` | No | PK, AUTO_INCREMENT | Identificador del registro |
| `nombre` | `VARCHAR(255)` | No | UNIQUE | Nombre del archivo de migración aplicado |
| `aplicada_en` | `TIMESTAMP` | No | — | Fecha y hora de aplicación |

### Tabla `usuarios`

Creada por la migración `008_usuarios.sql`.

| Columna | Tipo | Nulo | Clave | Descripción |
| --- | --- | --- | --- | --- |
| `id` | `INT UNSIGNED` | No | PK, AUTO_INCREMENT | Identificador del usuario |
| `usuario` | `VARCHAR(50)` | No | UNIQUE | Nombre de usuario |
| `nombre` | `VARCHAR(100)` | No | — | Nombre mostrado en la navegación |
| `password_hash` | `VARCHAR(255)` | No | — | Contraseña cifrada con `password_hash()` |
| `rol` | `ENUM('admin', 'consulta')` | No | — | Permisos del usuario |
| `activo` | `TINYINT(1)` | No | — | `1` permite iniciar sesión |
| `creado_en` | `TIMESTAMP` | No | — | Fecha de creación |

### Relación

```text
grupos (1) ────< (N) alumnos
```

Un grupo puede tener muchos alumnos. La llave foránea `alumnos.IdGrupos` referencia a `grupos.IdGrupos` con la restricción por defecto (`RESTRICT`), por eso la API responde `409` si se intenta eliminar un grupo que todavía tiene alumnos.

### SQL de creación

```sql
CREATE DATABASE IF NOT EXISTS escuela
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_general_ci;

USE escuela;

CREATE TABLE grupos (
  IdGrupos INT(11) NOT NULL AUTO_INCREMENT,
  grupo VARCHAR(50) NOT NULL,
  PRIMARY KEY (IdGrupos)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE alumnos (
  IdAlumnos INT(11) NOT NULL AUTO_INCREMENT,
  nombre VARCHAR(100) NOT NULL,
  matricula VARCHAR(20) NOT NULL,
  grado VARCHAR(20) NOT NULL,
  IdGrupos INT(11) DEFAULT NULL,
  PRIMARY KEY (IdAlumnos),
  KEY IdGrupos (IdGrupos),
  CONSTRAINT alumnos_ibfk_1
    FOREIGN KEY (IdGrupos) REFERENCES grupos (IdGrupos)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

CREATE TABLE usuarios (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT,
  usuario VARCHAR(50) NOT NULL,
  nombre VARCHAR(100) NOT NULL,
  password_hash VARCHAR(255) NOT NULL,
  rol ENUM('admin', 'consulta') NOT NULL DEFAULT 'consulta',
  activo TINYINT(1) NOT NULL DEFAULT 1,
  creado_en TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY usuario (usuario)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;
```

> `matricula` no es única en el esquema actual. Si necesitas impedir duplicados, agrega `UNIQUE KEY` sobre esa columna.

## Migraciones

Las migraciones están en `migrations/` y se aplican en orden alfabético. El runner `migrate.php`:

- Crea la base de datos `escuela` si no existe.
- Crea la tabla `migraciones` para llevar el historial.
- Ejecuta únicamente los archivos que todavía no están registrados.
- Usa `CREATE TABLE IF NOT EXISTS`, por lo que es seguro ejecutarlo sobre una base que ya tiene las tablas.

### Aplicar migraciones pendientes

```powershell
C:\xampp\php\php.exe migrate.php
```

Salida esperada la primera vez:

```text
Base de datos: escuela
Migraciones pendientes: 8

Aplicando 001_create_grupos.sql... OK
Aplicando 002_create_alumnos.sql... OK
Aplicando 003_sp_crear_alumno.sql... OK
Aplicando 004_sp_actualizar_alumno.sql... OK
Aplicando 005_sp_crear_grupo.sql... OK
Aplicando 006_sp_actualizar_grupo.sql... OK
Aplicando 007_sp_duplicar_alumno.sql... OK
Aplicando 008_usuarios.sql... OK

Migraciones aplicadas: 8
```

En ejecuciones posteriores:

```text
Base de datos: escuela
No hay migraciones pendientes.
```

### Ver el estado

```powershell
C:\xampp\php\php.exe migrate.php status
```

```text
Base de datos: escuela
Migraciones encontradas: 8

[APLICADA]   001_create_grupos.sql
[APLICADA]   002_create_alumnos.sql
[APLICADA]   003_sp_crear_alumno.sql
[APLICADA]   004_sp_actualizar_alumno.sql
[APLICADA]   005_sp_crear_grupo.sql
[APLICADA]   006_sp_actualizar_grupo.sql
[APLICADA]   007_sp_duplicar_alumno.sql
[APLICADA]   008_usuarios.sql

Pendientes: 0
```

### Crear una nueva migración

1. Crea un archivo con el siguiente número en `migrations/`, por ejemplo `009_add_unique_matricula.sql`.
2. Escribe el SQL.
3. Ejecuta `C:\xampp\php\php.exe migrate.php`.

Reglas:

- No modifiques una migración que ya fue aplicada; crea una nueva.
- MySQL auto-confirma las sentencias DDL (`CREATE`, `ALTER`, `DROP`), por lo que no hay rollback automático. Para reducir el riesgo si una migración falla a mitad de camino, escribe migraciones idempotentes (`IF NOT EXISTS`, `IF EXISTS`, etc.).
- Si una migración falla, corrige el archivo o crea una migración nueva y vuelve a ejecutar el runner.

### Probar las migraciones en otra base

Puedes usar las variables de entorno de conexión para validar sobre una base temporal:

```powershell
$env:DB_NAME = "escuela_migracion_test"
C:\xampp\php\php.exe migrate.php
C:\xampp\php\php.exe migrate.php status
```

## Stored procedures

Las operaciones de escritura de alumnos y grupos se ejecutan con stored procedures creados por las migraciones `003` a `007`:

| Procedimiento | Operación |
| --- | --- |
| `sp_crear_alumno` | Alta de alumno |
| `sp_actualizar_alumno` | Edición de alumno |
| `sp_crear_grupo` | Alta de grupo |
| `sp_actualizar_grupo` | Edición de grupo |
| `sp_duplicar_alumno` | Duplicado de alumno |

Cada procedimiento:

- Abre una transacción con `START TRANSACTION`.
- Valida las reglas de negocio (existencia del grupo o del alumno, nombre de grupo duplicado).
- Confirma con `COMMIT` o revierte con `ROLLBACK` ante cualquier `SQLEXCEPTION`.
- Lanza errores de negocio con `SIGNAL SQLSTATE '45000'`; la API los traduce a `400` o `404` según el mensaje.

El duplicado de alumno crea un registro nuevo que conserva el grado y el grupo del original, agrega ` (copia)` al nombre y genera una matrícula con el formato `COPIA-<id_origen>-<id_nuevo>`.

Ver los procedimientos instalados:

```sql
SHOW PROCEDURE STATUS WHERE Db = 'escuela';
```

## Cómo llamar a los endpoints

URL base:

```text
http://localhost/escuela/endpoints.php
```

El recurso se indica con `tipo=alumnos` o `tipo=grupos`. Todas las peticiones requieren sesión iniciada o token; sin ellos la API responde `401`. Las operaciones de escritura requieren rol `admin` o token (una sesión `consulta` recibe `403`).

| Método | URL | Acción |
| --- | --- | --- |
| `GET` | `endpoints.php?tipo=alumnos` | Lista alumnos con su grupo |
| `GET` | `endpoints.php?tipo=alumnos&id=1` | Obtiene un alumno |
| `POST` | `endpoints.php?tipo=alumnos` | Crea un alumno |
| `POST` | `endpoints.php?tipo=alumnos&id=1&accion=duplicar` | Duplica un alumno |
| `PUT` | `endpoints.php?tipo=alumnos&id=1` | Actualiza todos los datos |
| `PATCH` | `endpoints.php?tipo=alumnos&id=1` | Actualiza solo los campos enviados |
| `DELETE` | `endpoints.php?tipo=alumnos&id=1` | Elimina un alumno |
| `GET` | `endpoints.php?tipo=grupos` | Lista grupos con total de alumnos |
| `GET` | `endpoints.php?tipo=grupos&id=1` | Obtiene un grupo |
| `POST` | `endpoints.php?tipo=grupos` | Crea un grupo |
| `PUT` | `endpoints.php?tipo=grupos&id=1` | Actualiza el grupo |
| `PATCH` | `endpoints.php?tipo=grupos&id=1` | Actualiza parcialmente el grupo |
| `DELETE` | `endpoints.php?tipo=grupos&id=1` | Elimina un grupo |

`GET endpoints.php` sin `tipo` devuelve la lista de alumnos por compatibilidad.

### Cuerpos JSON

Alumno:

```json
{
  "nombre": "Juan Perez",
  "matricula": "001",
  "grado": "Primero",
  "IdGrupos": 1
}
```

Grupo:

```json
{
  "grupo": "Grupo A"
}
```

### Ejemplos con curl

Define el token una vez:

```bash
API_TOKEN=442e35cdf0b4225362746e3f8e7a17d4a393b25d2495a628
```

Listar alumnos:

```bash
curl -H "Authorization: Bearer $API_TOKEN" \
  "http://localhost/escuela/endpoints.php?tipo=alumnos"
```

Obtener un alumno:

```bash
curl -H "Authorization: Bearer $API_TOKEN" \
  "http://localhost/escuela/endpoints.php?tipo=alumnos&id=1"
```

Crear un alumno:

```bash
curl -X POST "http://localhost/escuela/endpoints.php?tipo=alumnos" \
  -H "Authorization: Bearer $API_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"nombre":"Juan Perez","matricula":"001","grado":"Primero","IdGrupos":1}'
```

Duplicar un alumno:

```bash
curl -X POST "http://localhost/escuela/endpoints.php?tipo=alumnos&id=1&accion=duplicar" \
  -H "Authorization: Bearer $API_TOKEN"
```

Respuesta:

```json
{
  "ok": true,
  "mensaje": "Alumno duplicado correctamente.",
  "id": 5
}
```

Actualizar parcialmente:

```bash
curl -X PATCH "http://localhost/escuela/endpoints.php?tipo=alumnos&id=1" \
  -H "Authorization: Bearer $API_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"grado":"Segundo"}'
```

Eliminar:

```bash
curl -X DELETE "http://localhost/escuela/endpoints.php?tipo=alumnos&id=1" \
  -H "Authorization: Bearer $API_TOKEN"
```

Crear un grupo:

```bash
curl -X POST "http://localhost/escuela/endpoints.php?tipo=grupos" \
  -H "Authorization: Bearer $API_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{"grupo":"Grupo C"}'
```

### Ejemplo con JavaScript

```js
const respuesta = await fetch("endpoints.php?tipo=alumnos", {
    method: "POST",
    headers: { "Content-Type": "application/json" },
    body: JSON.stringify({
        nombre: "Juan Perez",
        matricula: "001",
        grado: "Primero",
        IdGrupos: 1
    })
});

const datos = await respuesta.json();
```

Desde una vista del sistema, el navegador envía la cookie de sesión automáticamente, así que no hace falta añadir el token.

### Respuestas

Lista de alumnos:

```json
[
  {
    "IdAlumnos": "1",
    "nombre": "Juan Perez",
    "matricula": "001",
    "grado": "Primero",
    "IdGrupos": "1",
    "grupo": "Grupo A"
  }
]
```

Lista de grupos:

```json
[
  {
    "IdGrupos": "1",
    "grupo": "Grupo A",
    "total_alumnos": "2"
  }
]
```

Crear, editar o eliminar correctamente:

```json
{
  "ok": true,
  "mensaje": "Alumno registrado correctamente.",
  "id": 4
}
```

Error de validación:

```json
{
  "ok": false,
  "mensaje": "Revisa los datos del alumno.",
  "errores": [
    "El nombre es obligatorio.",
    "La matrícula es obligatoria."
  ]
}
```

### Códigos de estado

| Código | Significado |
| --- | --- |
| `200` | Consulta, actualización o eliminación correcta |
| `201` | Recurso creado correctamente |
| `400` | Datos inválidos o incompletos |
| `401` | Falta la sesión o el token es inválido |
| `403` | La sesión no tiene permisos para escribir |
| `404` | Recurso no encontrado |
| `405` | Método HTTP no permitido |
| `409` | Conflicto, por ejemplo borrar un grupo con alumnos |
| `500` | Error de base de datos o conexión |

### Compatibilidad con formularios y `_method`

La API acepta `application/json` y también `application/x-www-form-urlencoded` o `multipart/form-data` en `POST`.

Si el cliente no puede enviar `PUT`, `PATCH` o `DELETE`, se puede usar el parámetro `_method`:

```bash
curl -X POST "http://localhost/escuela/endpoints.php?tipo=alumnos&id=1&_method=DELETE" \
  -H "Authorization: Bearer $API_TOKEN"
```

## Tests de la API

El proyecto incluye pruebas de todos los endpoints y validaciones, sin dependencias externas:

```powershell
C:\xampp\php\php.exe tests\api_test.php
```

Si `php` está en el `PATH`:

```bash
php tests/api_test.php
```

Requisitos:

- Apache y MySQL activos.
- Migraciones aplicadas (`C:\xampp\php\php.exe migrate.php`).
- La API debe responder en `http://localhost/escuela/endpoints.php`.

Se puede indicar otra URL (la de las páginas se deduce de la de la API):

```powershell
$env:API_BASE_URL = "http://localhost/escuela/endpoints.php"
C:\xampp\php\php.exe tests\api_test.php
```

Si las páginas están en otra ruta, usa `$env:WEB_BASE_URL`.

Cobertura de las 44 pruebas:

- Autenticación de la API: `401` sin token, con token inválido y en escrituras; token por `Authorization`, `X-API-Token` y URL.
- Página de login, redirección de páginas protegidas, contraseña incorrecta, login de `ricardo` y de `profesor`.
- Permisos: el admin puede escribir con sesión, la sesión `consulta` recibe `403` y no abre formularios.
- Cierre de sesión y vuelta al login.
- Documentación: contrato protegido, formulario de token, token inválido, token válido y cierre del acceso.
- `GET` de listas, recurso por defecto y recurso inexistente.
- `GET` de alumno y grupo por `id`, incluyendo `404`.
- `POST` de alumno y grupo, incluyendo validaciones y duplicados.
- `POST` de duplicado de alumno, verificando nombre, matrícula, grado y grupo.
- `PUT` y `PATCH` de alumno y grupo.
- `DELETE` de alumno y grupo, incluyendo `404`.
- Conflicto `409` al borrar un grupo con alumnos.
- Envío con formulario (`application/x-www-form-urlencoded`).
- Compatibilidad con `_method=DELETE`.
- Respuesta `405` para métodos no permitidos.

Las pruebas crean registros temporales con prefijos `TEST-API-` (grupos) y `TST-` (matrículas), y los eliminan al iniciar y al finalizar, incluso si alguna prueba falla. También usan archivos de cookies temporales para las sesiones de prueba. No modifican los datos existentes.

## Notas

- La conexión a MySQL se configura en `config/database.php` y la usan tanto `endpoints.php` como `migrate.php`. Por defecto usa `root` sin contraseña, la configuración de XAMPP.
- Se puede sobrescribir con las variables de entorno `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER` y `DB_PASS`.
- El token de la API y el nombre de la sesión se configuran en `config/auth.php`; el token se puede sobrescribir con `API_TOKEN`.
- Las altas y ediciones se ejecutan mediante stored procedures con transacción (ver [Stored procedures](#stored-procedures)).
- Todas las consultas usan sentencias preparadas.
- Los errores de base de datos no exponen el SQL al cliente.
