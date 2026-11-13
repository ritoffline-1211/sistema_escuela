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

4. Abre `http://localhost/escuela/`.

`index.php` redirige a `lista_alumnos.php`. Las migraciones crean la base `escuela`, las tablas y el historial de migraciones.

## Documentación de la API (Swagger)

La API está documentada con OpenAPI 3.0.3 y se puede explorar y probar desde Swagger UI:

```text
http://localhost/escuela/docs/index.html
```

También hay un enlace **API (Swagger)** en la barra de navegación del sistema.

Archivos:

| Archivo | Descripción |
| --- | --- |
| `docs/openapi.json` | Contrato OpenAPI con todos los endpoints, parámetros, esquemas y ejemplos |
| `docs/index.html` | Página que monta Swagger UI |
| `docs/swagger-ui/` | JavaScript y CSS de Swagger UI servidos localmente (no requiere CDN) |

Desde Swagger UI puedes usar **Try it out** para ejecutar cada operación contra `http://localhost/escuela/endpoints.php`. Ten en cuenta que:

- `tipo` es obligatorio y acepta `alumnos` o `grupos`.
- `id` es necesario para consultar un registro, editar, eliminar y duplicar.
- Para duplicar: `tipo=alumnos`, `id=<id>`, `accion=duplicar` y sin cuerpo.
- Las operaciones de escritura devuelven `201` al crear o duplicar y `200` al actualizar o eliminar.

## Estructura del proyecto

```text
escuela/
├── config/
│   └── database.php       # Configuración de conexión a MySQL
├── docs/
│   ├── index.html         # Swagger UI
│   ├── openapi.json       # Contrato OpenAPI 3.0.3
│   └── swagger-ui/        # Assets locales de Swagger UI
├── migrations/
│   ├── 001_create_grupos.sql
│   ├── 002_create_alumnos.sql
│   ├── 003_sp_crear_alumno.sql
│   ├── 004_sp_actualizar_alumno.sql
│   ├── 005_sp_crear_grupo.sql
│   ├── 006_sp_actualizar_grupo.sql
│   └── 007_sp_duplicar_alumno.sql
├── migrate.php            # Runner de migraciones (up / status)
├── endpoints.php          # API REST (alumnos y grupos)
├── index.php              # Redirección a la lista de alumnos
├── lista_alumnos.php      # Listado, edición y eliminación de alumnos
├── crear_alumno.php       # Alta de alumno
├── editar_alumno.php      # Edición de alumno (?id=)
├── lista_grupos.php       # Listado, edición y eliminación de grupos
├── crear_grupo.php        # Alta de grupo
├── editar_grupo.php       # Edición de grupo (?id=)
├── app.js                 # Helpers de fetch, mensajes y carga de grupos
├── estilos.css            # Estilos de las vistas
├── README.md
└── tests/
    └── api_test.php       # Pruebas de la API (sin dependencias)
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
Migraciones pendientes: 7

Aplicando 001_create_grupos.sql... OK
Aplicando 002_create_alumnos.sql... OK
Aplicando 003_sp_crear_alumno.sql... OK
Aplicando 004_sp_actualizar_alumno.sql... OK
Aplicando 005_sp_crear_grupo.sql... OK
Aplicando 006_sp_actualizar_grupo.sql... OK
Aplicando 007_sp_duplicar_alumno.sql... OK

Migraciones aplicadas: 7
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
Migraciones encontradas: 7

[APLICADA]   001_create_grupos.sql
[APLICADA]   002_create_alumnos.sql
[APLICADA]   003_sp_crear_alumno.sql
[APLICADA]   004_sp_actualizar_alumno.sql
[APLICADA]   005_sp_crear_grupo.sql
[APLICADA]   006_sp_actualizar_grupo.sql
[APLICADA]   007_sp_duplicar_alumno.sql

Pendientes: 0
```

### Crear una nueva migración

1. Crea un archivo con el siguiente número en `migrations/`, por ejemplo `008_add_unique_matricula.sql`.
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

El recurso se indica con `tipo=alumnos` o `tipo=grupos`.

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

Listar alumnos:

```bash
curl "http://localhost/escuela/endpoints.php?tipo=alumnos"
```

Obtener un alumno:

```bash
curl "http://localhost/escuela/endpoints.php?tipo=alumnos&id=1"
```

Crear un alumno:

```bash
curl -X POST "http://localhost/escuela/endpoints.php?tipo=alumnos" \
  -H "Content-Type: application/json" \
  -d '{"nombre":"Juan Perez","matricula":"001","grado":"Primero","IdGrupos":1}'
```

Duplicar un alumno:

```bash
curl -X POST "http://localhost/escuela/endpoints.php?tipo=alumnos&id=1&accion=duplicar"
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
  -H "Content-Type: application/json" \
  -d '{"grado":"Segundo"}'
```

Eliminar:

```bash
curl -X DELETE "http://localhost/escuela/endpoints.php?tipo=alumnos&id=1"
```

Crear un grupo:

```bash
curl -X POST "http://localhost/escuela/endpoints.php?tipo=grupos" \
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
| `404` | Recurso no encontrado |
| `405` | Método HTTP no permitido |
| `409` | Conflicto, por ejemplo borrar un grupo con alumnos |
| `500` | Error de base de datos o conexión |

### Compatibilidad con formularios y `_method`

La API acepta `application/json` y también `application/x-www-form-urlencoded` o `multipart/form-data` en `POST`.

Si el cliente no puede enviar `PUT`, `PATCH` o `DELETE`, se puede usar el parámetro `_method`:

```bash
curl -X POST "http://localhost/escuela/endpoints.php?tipo=alumnos&id=1&_method=DELETE"
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

Se puede indicar otra URL:

```powershell
$env:API_BASE_URL = "http://localhost/escuela/endpoints.php"
C:\xampp\php\php.exe tests\api_test.php
```

Cobertura de las 28 pruebas:

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

Las pruebas crean registros temporales con prefijos `TEST-API-` (grupos) y `TST-` (matrículas), y los eliminan al iniciar y al finalizar, incluso si alguna prueba falla. No modifican los datos existentes.

## Notas

- La conexión a MySQL se configura en `config/database.php` y la usan tanto `endpoints.php` como `migrate.php`. Por defecto usa `root` sin contraseña, la configuración de XAMPP.
- Se puede sobrescribir con las variables de entorno `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER` y `DB_PASS`.
- Las altas y ediciones se ejecutan mediante stored procedures con transacción (ver [Stored procedures](#stored-procedures)).
- Todas las consultas usan sentencias preparadas.
- Los errores de base de datos no exponen el SQL al cliente.
