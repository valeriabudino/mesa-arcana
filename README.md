# Mesa Arcana

Lecturas de tarot en tiempo real: una tarotista y quien consulta, frente a la misma mesa 3D.
Se mezcla el mazo, quien consulta elige sus cartas del abanico y se revelan en las dos
pantallas a la vez, mientras conversan por chat. También hay sí o no, cruz celta y carta astral.

- **Backend:** Laravel 13 (PHP 8.4+), MySQL, Laravel Reverb (websockets) y Fortify (login).
- **Frontend:** Three.js (la mesa), GSAP (animaciones), Tailwind y Web Audio (música y sonidos generados por código).
- **Carta astral:** `astronomy-engine`, ejecutado con Node desde el servidor.

## Requisitos

Solo hacen falta **Docker** y **Git**. PHP, Composer, Node y MySQL corren dentro de los
contenedores ([Laravel Sail](https://laravel.com/docs/sail)), así que no hay que instalarlos.

- **Windows:** Docker Desktop con WSL2. Cloná y corré todo **dentro de WSL** (por ejemplo, Ubuntu), no en una carpeta de Windows.
- **macOS:** Docker Desktop.
- **Linux:** Docker Engine con el plugin `docker compose`.

El navegador tiene que soportar **WebGL 2** (cualquier Chrome, Firefox, Edge o Safari actual).

## Levantar la app

```bash
# 1. Clonar
git clone https://github.com/valeriabudino/mesa-arcana.git
cd mesa-arcana

# 2. Configuración: el ejemplo ya viene listo para desarrollo local
cp .env.example .env

# 3. Dependencias de PHP (con un contenedor descartable: no hace falta PHP instalado)
docker run --rm -u "$(id -u):$(id -g)" -v "$(pwd):/var/www/html" -w /var/www/html \
    laravelsail/php84-composer:latest composer install --ignore-platform-reqs

# 4. Levantar los contenedores (la primera vez tarda unos minutos: arma la imagen)
./vendor/bin/sail up -d

# 5. Clave de la app, base de datos y datos iniciales (las 78 cartas, ciudades y una tarotista)
./vendor/bin/sail artisan key:generate
./vendor/bin/sail artisan migrate --seed

# 6. Dependencias de JavaScript
./vendor/bin/sail npm install
```

Después, en **dos terminales** que quedan abiertas:

```bash
# Terminal 1: Vite (compila el frontend y recarga al guardar)
./vendor/bin/sail npm run dev

# Terminal 2: Reverb (el tiempo real: la mesa, el chat, la fila de espera)
./vendor/bin/sail artisan reverb:start --host=0.0.0.0 --port=8080
```

Listo: abrí **http://localhost**.

> **Tip:** para no escribir `./vendor/bin/sail` cada vez, agregá
> `alias sail='sh $([ -f sail ] && echo sail || echo vendor/bin/sail)'` a tu `~/.bashrc` o `~/.zshrc`.

## Probarla

Se necesitan dos personas, así que usá **dos navegadores** (o una ventana normal y una privada):

1. **Tarotista:** entrá a http://localhost, tocá **Soy tarotista** e iniciá sesión con
   `tarotista@mesa.test` / `password`. Activá **Disponible**.
2. **Consulta:** en el otro navegador, tocá **Quiero una consulta**, escribí un nombre y entrá a la fila.
   Quien consulta no necesita cuenta.
3. **Tarotista:** tocá **Atender**. Se abre la sala para las dos personas.
4. **Consulta:** pedí una lectura desde la barra de abajo. La tarotista la acepta en el chat,
   mezcla, y quien consulta elige sus cartas del abanico.

Para crear otra cuenta de tarotista:

```bash
./vendor/bin/sail artisan tarotista:crear
```

**Música y sonidos:** los navegadores no dejan sonar nada hasta el primer clic en la página.
Se prenden y apagan con los botones de arriba a la derecha.

**Laboratorio de la mesa:** en http://localhost/laboratorio-mesa se pueden probar las
animaciones sin sockets ni otra persona (solo existe con `APP_ENV=local`).

## Comandos útiles

```bash
./vendor/bin/sail artisan test       # tests (Pest)
./vendor/bin/sail composer lint      # formato del código PHP (Pint)
./vendor/bin/sail stop               # apagar los contenedores (los datos quedan)
./vendor/bin/sail down -v            # borrar todo, incluida la base
./vendor/bin/sail artisan migrate:fresh --seed   # base de datos de cero
```

## Compartirla por internet (opcional)

Para que alguien la pruebe desde otro lugar, se puede usar un túnel de
[cloudflared](https://developers.cloudflare.com/cloudflare-one/connections/connect-networks/downloads/)
(no necesita cuenta). Hacen falta **dos túneles**: uno para la app y otro para Reverb.

```bash
# En dos terminales: cada una imprime una URL https://….trycloudflare.com
cloudflared tunnel --url http://localhost:80     # la app
cloudflared tunnel --url http://localhost:8080   # Reverb
```

Con el servidor de desarrollo de Vite, el navegador de la otra persona buscaría los archivos en
*su* `localhost`. Por eso hay que cortar `npm run dev` (Ctrl+C) y compilar, indicando el túnel de Reverb:

```bash
rm -f public/hot
./vendor/bin/sail exec -e VITE_REVERB_HOST=<url-del-túnel-de-reverb-sin-https> \
    -e VITE_REVERB_PORT=443 -e VITE_REVERB_SCHEME=https laravel.test npm run build
```

Compartí la URL del túnel de la app. Las URLs de los túneles rápidos cambian cada vez que se
reinicia cloudflared: en ese caso hay que volver a compilar. Para volver a trabajar en local,
corré otra vez `./vendor/bin/sail npm run dev`.

## Problemas comunes

- **"Address already in use" al hacer `sail up`:** algún puerto ya está ocupado. Cambialo en
  `.env` y volvé a correr `./vendor/bin/sail up -d`:
  - puerto 80 (la app): `APP_PORT=8000` y `APP_URL=http://localhost:8000`;
  - puerto 3306 (MySQL): `FORWARD_DB_PORT=3307`;
  - puerto 8080 (Reverb): `REVERB_SERVER_PORT=8081` y `VITE_REVERB_PORT=8081` (el navegador se conecta a ese).
- **La mesa no se mueve o el chat no llega a la otra persona:** falta Reverb (terminal 2).
- **"Vite manifest not found" o la página sin estilos:** falta `npm run dev` (terminal 1) o `npm run build`.
- **La mesa no aparece y dice "Tu navegador no tiene WebGL 2":** activá la aceleración por
  hardware del navegador o probá con otro.
- **Errores de permisos en `storage/`** (sobre todo en Linux): corré
  `docker compose exec -u root laravel.test chown -R sail:sail storage bootstrap/cache`.

## Cómo está organizado

| Dónde | Qué hay |
| --- | --- |
| `app/Services/` | Las reglas: la fila de espera, las lecturas (mezclar, elegir, revelar), el chat y la carta astral. |
| `app/Events/` | Lo que viaja por Reverb a las dos pantallas. |
| `resources/js/mesa/` | La mesa 3D (`escena.js`, `animaciones.js`, `decoracion.js`), el chat y la barra de acciones. |
| `resources/js/sonido.js` | Música y efectos, generados con Web Audio. |
| `resources/astro/calcular.mjs` | El cálculo astronómico de la carta astral. |
| `tests/` | Tests de Pest (corren sobre SQLite en memoria en GitHub Actions). |

La regla de oro del tiempo real: **un clic no mueve nada**. Hace un pedido al servidor, que
valida y avisa por Reverb, y recién ahí se anima en las dos pantallas a la vez.

Las imágenes de las cartas son del mazo Rider-Waite-Smith (dominio público): ver `public/cartas/CREDITOS.txt`.
