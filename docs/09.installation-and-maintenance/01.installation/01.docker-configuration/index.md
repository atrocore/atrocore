---
title: Docker Configuration
---

This guide describes how to prepare your server for the installation of AtroCore Application. Please make sure your user
can use `sudo` command.

Our docker-compose stack comes with a preconfigured [Traefik](https://doc.traefik.io/traefik/) and Let's Encrypt SSL
certificate provider. You need just to set some configuration variables to have a ready to work system.

## Installing Docker Engine

To install Docker, your server must meet prerequisites (check [official docs](https://docs.docker.com/engine/install/ubuntu/#prerequisites)). Usually, a server with the fresh
installation of Ubuntu 20.04 (and newer) should work well.

To be sure your system does not have any conflicting packages, you need to uninstall them using the next command:

```bash
for pkg in docker.io docker-doc docker-compose docker-compose-v2 podman-docker containerd runc; do sudo apt-get remove $pkg; done
```

In this guide we will set up an apt repository to install Docker Engine. If you need more details, please
follow [official instructions](https://docs.docker.com/engine/install/ubuntu/#install-using-the-repository).

Lets setup Docker's apt repository:

```bash
sudo apt-get update
sudo apt-get install ca-certificates curl
sudo install -m 0755 -d /etc/apt/keyrings
sudo curl -fsSL https://download.docker.com/linux/ubuntu/gpg -o /etc/apt/keyrings/docker.asc
sudo chmod a+r /etc/apt/keyrings/docker.asc

echo \
  "deb [arch=$(dpkg --print-architecture) signed-by=/etc/apt/keyrings/docker.asc] https://download.docker.com/linux/ubuntu \
  $(. /etc/os-release && echo "${UBUNTU_CODENAME:-$VERSION_CODENAME}") stable" | \
  sudo tee /etc/apt/sources.list.d/docker.list > /dev/null

sudo apt-get update
```

And install the latest version of Docker:

```bash
sudo apt-get install docker-ce docker-ce-cli containerd.io docker-buildx-plugin docker-compose-plugin
```

To check if everything works well, you can start `hello-world` container:

```bash
sudo docker run hello-world
```

## Configuring Docker Compose services

### Downloading Docker Compose configuration

Configuration is hosted on our GitLab repository: [https://github.com/atrocore/docker](https://github.com/atrocore/docker)

You can choose any directory your user has access. You can use `git` to clone repository locally:

```bash
git clone https://github.com/atrocore/docker.git <my-atrocore-project>
```

> **<my-atrocore-project\>** is your project name

Or, you can download a zip archive from repository and unpack it into a suitable directory if you don't want to use
`git`.

### Selecting a deployment mode

The deployment mode is selected by the `COMPOSE_FILE` variable in `.env`. It layers one override file on top of the
base `docker-compose.yaml`:

| Mode | Override file | Description |
|------|---------------|-------------|
| Local | `docker-compose.local.yaml` | `web` is published directly on `LOCAL_PORT`, no reverse proxy |
| Traefik | `docker-compose.traefik.yaml` | Traefik reverse proxy, HTTP only |
| Traefik SSL | `docker-compose.traefik-ssl.yaml` | Traefik with HTTPS, Let's Encrypt and automatic HTTP to HTTPS redirect |

For example, to run behind Traefik with SSL, set:

```
COMPOSE_FILE=docker-compose.yaml:docker-compose.traefik-ssl.yaml
```

Use exactly one mode file at a time.

#### Required settings for Traefik SSL

The default `COMPOSE_FILE` uses the "Traefik SSL" mode. Before the first start, configure the following in `.env`,
otherwise Let's Encrypt cannot issue a certificate and the site stays unreachable:

- `PRODUCTION_DOMAIN` – a public domain, not `localhost`, with a DNS record pointing to this server
- `LETS_ENCRYPT_EMAIL` – a valid email address required by Let's Encrypt
- `PROXY_PRODUCTION_ROUTER` – a unique router name, without dots

Ports 80 and 443 must be reachable from the internet, since Let's Encrypt validates the domain over port 80.

!! A `localhost` domain does not work in this mode, because Let's Encrypt cannot issue a certificate for it. For local testing switch `COMPOSE_FILE` to `docker-compose.yaml:docker-compose.local.yaml`.

#### Running without SSL

To run behind Traefik without SSL, select `docker-compose.traefik.yaml` instead of `docker-compose.traefik-ssl.yaml`:

```
COMPOSE_FILE=docker-compose.yaml:docker-compose.traefik.yaml
```

This mode exposes only port 80 and uses `traefik.local.yml`, which has no HTTP to HTTPS redirect and no Let's Encrypt
resolver. No manual editing of labels or Traefik configuration is required.

#### Using an existing Traefik

If you already run Traefik on your server, select the Traefik mode and remove the bundled `reverse_proxy` service from
the chosen override file, so only the `web` labels are applied and your own Traefik handles routing.

### Configuring environment variables

Create your own environment configuration from example:

```bash
cp .env.example .env
```

To create the `.env` file and generate secure database passwords in one step, run the following command instead. It fills only the empty password fields, so existing values are kept:

```bash
cp .env.example .env && sed -i \
  -e "s|^POSTGRES_PASSWORD=$|POSTGRES_PASSWORD=$(openssl rand -hex 24)|" \
  -e "s|^POSTGRES_PIM_USER=$|POSTGRES_PIM_USER=atrocore|" \
  -e "s|^POSTGRES_PIM_PASSWORD=$|POSTGRES_PIM_PASSWORD=$(openssl rand -hex 24)|" \
  .env
```

The following variables in `.env` file are required to have a working AtroPIM instance:

- `SKELETON_VARIANT` (`pim-no-demo`, `atrocore` are allowed variants). Default is `pim-no-demo`
- `BUILD_VARIANT` — set of software to be installed inside built image. `base` (used by the most of the users) and
  `pdf` (`base` + Chromium & LibreOffice are installed) are allowed variants
- `POSTGRES_PASSWORD` — password of the `postgres` user of a database, should not be used to install AtroPIM.

#### Production instance

To configure a production AtroPIM instance, define the next environmental variables:

- `PRODUCTION_DOMAIN` — domain of your main AtroPIM instance
- `PRODUCTION_STABILITY` (`stable`, `rc`) — stability branch of your AtroPIM instance. Default value is `stable`
- `POSTGRES_PIM_USER`, `POSTGRES_PIM_PASSWORD`, and `POSTGRES_PIM_DB` — database credentials (user, password, DB name)
  used to install production AtroPIM instance.

#### Testing instance

If you need to have a testing AtroPIM instance, define the next environmental variables:

- `TESTING_DOMAIN`, `TESTING_STABILITY` — the same meaning as `PRODUCTION_DOMAIN`, `PRODUCTION_STABILITY`, but it's for
  testing instance
- `POSTGRES_PIM_USER`, `POSTGRES_PIM_PASSWORD`, and `POSTGRES_PIM_DB` — database credentials (user, password, DB name)
  used to install testing AtroPIM instance.

#### Traefik-specific variables

If you decide to use our Traefik service, you need to set the next variables:

- `PROXY_PRODUCTION_ROUTER` – identifier of Traefik router for the main AtroPIM instance. Do not use dots in the value
- `PROXY_TESTING_ROUTER` – the same meaning as `PROXY_PRODUCTION_ROUTER`, but for testing instance
- `LETS_ENCRYPT_EMAIL` – your email, required by Let's Encrypt (if you decide to use their SSL certificate)

> Please note that `PROXY_PRODUCTION_ROUTER` and `PROXY_TESTING_ROUTER` should be unique if you have multiple copies of
> current docker-compose environment on the same server, or you have your own configured Traefik instance.

### Deployment

After all configurations, start your containers with the command:

```bash
sudo docker compose up -d
```

You will need to wait until build process for `web` container is finished. When all containers are up and running, open
your AtroCore in a browser and finish the WEB installation. Make sure that you've selected PostgreSQL database
on `Database configuration` step.

### Storing data on the host

By default, AtroCore and database files are kept in Docker-managed volumes. If you prefer to store them in the `data/`
directory next to your Docker Compose files, create a `docker-compose.host-bind.yaml` file with the following content:

```yaml
volumes:
  web-data:
    driver: local
    driver_opts:
      type: none
      o: bind
      device: ./data/web/
  db-data:
    driver: local
    driver_opts:
      type: none
      o: bind
      device: ./data/db/
```

Then append it to `COMPOSE_FILE` in `.env`:

```
COMPOSE_FILE=docker-compose.yaml:docker-compose.local.yaml:docker-compose.host-bind.yaml
```

Create the target directories before starting the containers:

```bash
mkdir -p data/web data/db
```

### Adding a testing instance

The `web` image is built with a second, testing instance when `TESTING_DOMAIN` is defined (see [Configuring environment variables](#configuring-environment-variables)).
To expose it through Traefik alongside the production instance, create a `docker-compose.testing.yaml` file with the
following content:

```yaml
services:
  web:
    labels:
      - "traefik.http.services.${PROXY_TESTING_ROUTER}.loadbalancer.server.port=80"
      - "traefik.http.routers.${PROXY_TESTING_ROUTER}.rule=Host(`${TESTING_DOMAIN}`)"
      - "traefik.http.routers.${PROXY_TESTING_ROUTER}.entrypoints=websecure"
      - "traefik.http.routers.${PROXY_TESTING_ROUTER}.tls=true"
      - "traefik.http.routers.${PROXY_TESTING_ROUTER}.tls.certresolver=letencrypt"
```

Then append it to `COMPOSE_FILE` in `.env`:

```
COMPOSE_FILE=docker-compose.yaml:docker-compose.traefik-ssl.yaml:docker-compose.testing.yaml
```

Set `TESTING_DOMAIN` and `PROXY_TESTING_ROUTER` in `.env`. For an HTTP-only testing instance, change the `entrypoints`
label to `web` and remove the two `tls` labels.

### Additional Tips

1. For local installation with custom domains remember to add them to the `hosts` file (`/etc/hosts` for Linux,
   `C:\Windows\System32\drivers\etc\hosts` for Windows). For example, for the domain `pim.local` you need to add
   the next line to the `hosts` file:
```
127.0.0.1   pim.local
```

2. On installing AtroPIM, you need to enter `db` as a database host on the `Database Configuration` page.
3. It's highly recommended to use volumes since you can remove your containers and recreate them
   without the risk of losing data. By default, volumes are already configured. Follow [Docker Documentation](https://docs.docker.com/engine/storage/volumes/#back-up-restore-or-migrate-data-volumes)
   for instructions to back up your data inside volumes.
4. If you need to run additional scripts to configure a database, copy your scripts to the `.docker/postgres/scripts`
   directory. Find more information in the [postgres docs](https://github.com/docker-library/docs/blob/master/postgres/README.md#initialization-scripts) in the `Initialization scripts` section.
5. Variables baked into the `web` image at build time – `SKELETON_VARIANT`, `PRODUCTION_DOMAIN`, `PRODUCTION_STABILITY`,
   `TESTING_DOMAIN`, `TESTING_STABILITY`, `BUILD_VARIANT` and the database credentials – take effect only after
   rebuilding the image:
```bash
docker compose build web --no-cache
docker compose down
docker compose up -d
```
   The remaining variables, such as `COMPOSE_FILE`, `LOCAL_PORT`, `LETS_ENCRYPT_EMAIL` and the proxy router names, are
   applied by recreating the containers, without a rebuild:
```bash
docker compose up -d
```
6. By default, HTTP requests are automatically redirected by Traefik to HTTPS. You can disable this behaviour by deleting
   `entryPoints.web.http.redirections` configuration in `traefik.yml` file. Remember to restart the Traefik container.
7. To store all files on the host directory instead of a volume, see [Storing data on the host](#storing-data-on-the-host).

8. After the update to AtroCore 2.0, container configuration needs to be changed. Inside a directory with docker-compose
   run `git pull` to download the new migration script (or [download it manually](https://github.com/atrocore/docker/-/blob/master/migrate-config.sh?ref_type=heads)
   and place right to the `docker-compose.yaml` file) and run it with command `sudo ./migrate-config.sh`.
9. If you are planning to use `pdf-generator` module, set `BUILD_VARIANT` to `pdf` in `.env` file. Users with an already
   installed system should rebuild their `web` image and recreate a container. After that, on the
   `Administration / Settings` page in the `Variables` panel add new config `useChromeNoSandbox` as boolean value and
   set it to `true`.
