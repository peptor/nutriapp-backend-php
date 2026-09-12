# NutriApp Backend (PHP / Laravel)

Reimplementació completa del backend Node/Express/Prisma (`../backend`) fent servir **Laravel 13** + **Sanctum** + **Eloquent**, connectant a MySQL amb l'**esquema idèntic** (mateixos noms de taula i columna que Prisma), de manera que és intercanviable amb el backend Node a nivell de base de dades.

La carpeta `../backend` (Node) segueix intacta i funcional; aquesta és una implementació nova i independent.

## Equivalències amb el backend Node

| Node | Laravel |
|---|---|
| Prisma schema/migracions | `database/migrations/` (18 taules, mateixos noms) |
| `jsonwebtoken` (JWT manual) | Sanctum (tokens Bearer opacs, mateixa expiració de 7 dies) |
| `middleware/auth.ts` (`requireRole`) | `App\Http\Middleware\EnsureRole` (`role:NUTRICIONISTA,ADMIN`...) |
| `lib/validators.ts` (Zod) | `app/Http/Requests/*` (Form Requests) |
| `multer` + `/uploads` estàtic | `Storage::disk('public')` + `php artisan storage:link` |
| `lib/audit.ts`, `lib/url.ts`, `lib/fieldValues.ts` | `app/Support/AccessLogger.php`, `UrlHelper.php`, `FieldValueValidator.php` |
| `routes/*.ts` | `app/Http/Controllers/*Controller.php` + `routes/api.php` |

Totes les 47 rutes de l'API (`/api/auth`, `/patients`, `/routines`, `/records`, `/dashboard`, `/business`, `/admin`) estan implementades amb el mateix contracte (mateixos camps JSON en camelCase, mateixos codis d'error i missatges en català).

**Important**: els tokens que retorna aquest backend (`"token": "1|abc..."`) són tokens opacs de Sanctum, no JWT — però el frontend ja els fa servir només com a `Authorization: Bearer <token>`, així que no cal cap canvi al frontend.

## Requisits

PHP 8.4 amb les extensions: `openssl`, `pdo_mysql`, `mbstring`, `fileinfo`, `curl`, `gd`, `intl`, `zip` (totes activades a `C:\PHP\php.ini` en aquesta màquina).

## Arrencar en local (BD local)

```bash
composer install
php artisan storage:link   # només el primer cop
composer run dev           # equivalent a "npm run dev": arrenca amb .env (BD local)
```

La BD local (`.env`) és `nutricion_rutines_php` a MySQL local — **separada** de la que fa servir `backend` (Node), per no arriscar dades reals durant proves. Per apuntar a la mateixa BD que el Node, canvia `DB_DATABASE` a `.env`.

## Arrencar contra Hostinger (BD externa "pre")

```bash
composer run pre            # equivalent a "npm run pre": arrenca amb .env.pre (Hostinger)
```

`.env.pre` ja té les credencials de `srv713.hstgr.io` / `u109042620_nutri_test` (les mateixes que fa servir `backend/.env.pre`). **Encara no s'ha executat `php artisan migrate --env=pre` contra aquesta BD** — cal confirmar-ho abans, ja que és la mateixa BD externa que fa servir el backend Node en producció de proves.

## Migracions

```bash
php artisan migrate              # BD local
php artisan migrate --env=pre    # BD Hostinger (demana confirmació abans d'executar-ho)
```

## Pendent / fora d'abast d'aquesta conversió

- **Seed de dades** (catàleg d'aliments i rutines de biblioteca): l'esquema (`foods`, `food_categories`, `library_routines`...) existeix però no s'ha escrit cap seeder equivalent a `backend/prisma/seed.ts`. Si vols aquestes dades, cal important-les des del dump del Node o demanar un seeder Laravel.
- **Primer usuari ADMIN**: la BD fresca no en té cap. Crea'l amb `php artisan tinker`:
  ```php
  App\Models\User::create(['email'=>'...','passwordHash'=>Hash::make('...'),'name'=>'...','role'=>'ADMIN']);
  ```
- No s'ha desplegat enlloc (Render, Hostinger...) ni s'ha inicialitzat un repositori git per a aquesta carpeta.

## Provat manualment

Registre, login, `set-password` de pacient, creació de pacients (incl. multi-nutricionista), plantilles de rutina, assignació, registres diaris (individual i batch), resum d'evolució (`/dashboard/summary`), pujada de fotos/logo, i CRUD d'administració (usuaris, canvi de rol, access-logs) — tot verificat contra la BD local amb peticions HTTP reals.
