# Parapente Bella Vista — backend

API headless en Symfony 7.4. **No renderiza ninguna interfaz**: tanto la web pública
como el panel de administración viven en el proyecto Next.js `../frontendparapente`.

## Puesta en marcha

Requiere PHP 8.2+ y el MySQL de MAMP arrancado (puerto 8889).

```bash
composer install
```

Crea `.env.local` (no versionado) con:

```
DATABASE_URL="mysql://root:root@127.0.0.1:8889/parapente?serverVersion=5.7.44&charset=utf8mb4"
CORS_ALLOW_ORIGIN='^https?://(localhost|127\.0\.0\.1)(:[0-9]+)?$'
JWT_PASSPHRASE=<la que generó lexik:jwt:generate-keypair>
```

Después:

```bash
php bin/console doctrine:database:create
php bin/console doctrine:migrations:migrate
php bin/console lexik:jwt:generate-keypair
php bin/console app:seed:catalog
php bin/console app:create-admin correo@ejemplo.com 'unaContraseña'
symfony server:start -d --port=8000
```

`CORS_ALLOW_ORIGIN` es una **expresión regular**, no una URL.

## Esquema

El esquema lo gestiona **siempre Doctrine**: se editan las entidades de `src/Entity`
y se genera la migración con `make:migration`. Nunca SQL a mano ni
`doctrine:schema:update`.

```bash
php bin/console make:migration
php bin/console doctrine:migrations:migrate
php bin/console doctrine:schema:validate
```

### Modelo

- **`Service`** — un servicio o promoción. `type` distingue `standalone` de `promotion`.
  El precio es `DECIMAL(10,2)` con su `currency` (las promos van en euros y el vuelo
  suelto en dólares, como el material impreso). `people` guarda si el paquete es para
  una o dos personas.
- **`InclusionItem`** — catálogo reutilizable de lo que puede incluir un servicio,
  con su clave de icono. Se da de alta una vez.
- **`ServiceInclusion`** — une servicio y elemento, con el orden y un `labelOverride`
  opcional. Ahí es donde viven las variantes de un mismo concepto: "Torta de 1/2" y
  "Torta de 1/4" comparten elemento e icono, solo cambia el texto.

La FK hacia `inclusion_item` es `RESTRICT` a propósito: un elemento en uso no puede
borrarse sin quitarlo antes de las promociones que lo incluyen.

## Comandos propios

| Comando | Qué hace |
|---|---|
| `app:seed:catalog` | Carga el catálogo de partida. Idempotente por slug; `--force` sobrescribe. |
| `app:create-admin <correo> <contraseña>` | Crea la cuenta del panel, o le cambia la contraseña. |

`app:seed:catalog` no borra nada: sin `--force` deja intacto lo que ya exista, de modo
que no puede pisar lo editado desde el panel.

## API

Lectura pública:

| Método | Ruta |
|---|---|
| GET | `/api/services` (opcional `?type=standalone\|promotion`) |
| GET | `/api/services/{slug}` |
| GET | `/api/inclusion-items` |

Escritura, con `Authorization: Bearer <token>` y `ROLE_ADMIN`:

| Método | Ruta |
|---|---|
| POST | `/api/login` → `{ "token": "..." }` |
| GET | `/api/me` |
| GET POST PATCH DELETE | `/api/admin/services[/{id}]` |
| GET POST PATCH DELETE | `/api/admin/inclusion-items[/{id}]` |
| POST | `/api/admin/uploads` (multipart: `file`, `folder`) |

Un `PATCH` sobre un servicio con `inclusions` reemplaza el conjunto entero.

Las rutas de imagen del JSON son **relativas** (`/uploads/services/x.jpg`): el host lo
pone el frontend, así la base de datos no queda atada a un dominio.
