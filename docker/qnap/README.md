# QNAP TS-133 deployment: racket-finder / PHP 7.4

This configuration builds `lier72/opencart` branch `racket-finder` on a GitHub
ARM64 runner and publishes `ghcr.io/lier72/opencart:racket-finder-php74`.
It also publishes `php74-<full-commit-sha>` for selecting a specific release.
The NAS pulls the image; it does not compile PHP or run Composer.

PHP 7.4 is end-of-life. This is a LAN staging deployment. Full store behavior
and the imported database must be tested before considering public exposure.
The Apache rules use the branch's `.htaccess.txt`; production-specific rewrite
rules are not imported automatically. No cron jobs are scheduled.

## Add these files to the branch

Copy `.github/workflows/qnap-php74.yml` and the complete `docker/qnap/` directory
into the repository root (next to `upload/` and `composer.json`). Commit them
to `racket-finder` and push. The push starts the workflow automatically.

In GitHub, open Actions > Build QNAP PHP 7.4 image. If Actions is disabled on
this fork, enable it first and push a commit afterward. Manual dispatch may
not be shown until the workflow also exists on the default branch; the push
trigger does not have this restriction.

The workflow uses the built-in GITHUB_TOKEN with packages:write. No personal
token is required for the build. If an existing package rejects the push,
grant this repository Actions access in that package's settings.

The image installs locked core Composer dependencies and retains the branch's
bundled extension dependencies. Generated config files read environment
variables. Local database credentials, backup configs, SQL dumps and runtime
cache files are excluded. Review application code for embedded service secrets
before publishing any image. The workflow does not change package visibility.

## Prepare the NAS

After the workflow succeeds, copy `compose.yaml` and `.env.example` from this
directory to a dedicated NAS folder, for example
`/share/Container/uniqsport-racket-finder/` (create it if necessary).

In that folder:

```sh
cp .env.example .env
chmod 600 .env
```

Edit `.env`: set the NAS LAN IP, matching OPENCART_URL, and two different
random passwords. DB_PREFIX must match the cloned store database (`ocus_`).
Use single-quoted .env values if passwords contain dollar signs or hashes.
Port 8090 must be available. This publishes only on the specified LAN IP;
do not configure router forwarding for this staging setup.

If GHCR requires login, use `docker login ghcr.io -u lier72` and enter a
GitHub personal access token (classic) with read:packages at the password
prompt. Do not use your GitHub account password or send the token in chat.
Public packages can be pulled without login.

```sh
docker compose config --quiet
docker compose pull
docker compose up -d db
docker compose ps
```

Wait until db is healthy. This creates an empty database, not a working store.
Export a development copy of the existing store database without CREATE DATABASE
or USE statements targeting another schema. Import that copy:

```sh
docker compose exec -T db sh -c 'exec mariadb -uopencart -p"$MARIADB_PASSWORD" opencart' < store-copy.sql
```

Before starting the web service, adapt the cloned database's store URLs and
absolute image/storage paths for this environment. Review `setting` and `store`
tables, Journal settings and module-specific configuration. Disable real email,
payments and outbound order integrations in the development copy. MariaDB 10.11
is the proposed development database; importing from a different MySQL/MariaDB
version can require dump/collation adjustments.

## Product images and storage

Named volumes retain the database, image directory and storage across image
updates. On first use, Docker seeds the image volume from the image's files,
but the repository contains mostly demo images. Copy actual store images too.

Create the web container without starting it, then copy images from a NAS
folder containing the source image directory:

```sh
docker compose create web
web_id=$(docker compose ps -aq web)
docker cp /share/YourBackup/image/. "$web_id":/var/www/html/image/
docker compose run --rm --no-deps --entrypoint sh web -c 'chown -R www-data:www-data /var/www/html/image'
```

Replace the backup path. Copy required downloads/uploads similarly into
`/var/www/storage/download/` and `/var/www/storage/upload/`, creating directories
first if needed; make copied files writable by www-data. Do not copy vendor,
cache, sessions, logs or compiled modification files from the old host.
The image supplies vendor dependencies, and the entrypoint initializes storage.

```sh
docker compose up -d web
docker compose logs --tail=100 web
docker stats --no-stream
```

Open OPENCART_URL and its `admin/` path. Refresh OpenCart modifications and
Journal caches through admin. Verify login, product images, racket finder,
product options and a test cart. Check logs and NAS memory under load. Memory
limits are initial budgets (web 512 MB, database 384 MB), not a performance
guarantee for the TS-133.

## Deploy later commits

After the branch workflow succeeds:

```sh
docker compose pull web
docker compose up -d web
```

To select an older application image, set IMAGE_TAG to its `php74-<commit-sha>`
in .env and run those commands. Database migrations need a separate rollback
plan. Do not run `docker compose down -v` unless intentionally deleting data.

## Validation status

PHP configuration syntax and shell syntax were checked locally. Docker is not
installed in the preparation environment, so the image build and application
deployment are not yet validated. The workflow checks extension availability,
core autoloading, bundled Odoo/CDEK autoloading and Apache configuration before
pushing. Store/database integration testing remains a deployment step.
