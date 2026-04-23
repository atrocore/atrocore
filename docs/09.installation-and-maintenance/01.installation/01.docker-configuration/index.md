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

Configuration is hosted on our GitLab repository: [https://gitlab.atrocore.com/atrocore/docker](https://gitlab.atrocore.com/atrocore/docker)

You can choose any directory your user has access. You can use `git` to clone repository locally:

```bash
git clone https://gitlab.atrocore.com/atrocore/docker.git <my-atrocore-project>
```

> **<my-atrocore-project\>** is your project name

Or, you can download a zip archive from repository and unpack it into a suitable directory if you don't want to use
`git`.

### Configuring Traefik reverse proxy

Our Docker Compose stack comes with preconfigured Traefik and enabled by default Let's Encrypt SSL certificate provider.
If you already have Traefik on your server, delete `services` section from the `docker-compose.override.yaml` file.

If you need to disable SSL, you should perform the following steps:

1. Remove these SSL labels from `web` service in `docker-compose-override.yaml`:
```
  # Enable SSL for main AtroPIM instance
  - "traefik.http.routers.${PROXY_PRODUCTION_ROUTER}.tls=true"
  - "traefik.http.routers.${PROXY_PRODUCTION_ROUTER}.tls.certresolver=letencrypt"

    ....

  # Enable SSL for testing AtroPIM instance
  - "traefik.http.routers.${PROXY_TESTING_ROUTER}.tls=true"
  - "traefik.http.routers.${PROXY_TESTING_ROUTER}.tls.certresolver=letencrypt"
```

2. Replace `websecure` with `web` in labels of `web` service in `docker-compose-override.yaml`:
```
  - "traefik.http.routers.${PROXY_PRODUCTION_ROUTER}.entrypoints=websecure" # Replace 'websecure' with 'web' if you don't need SSL

    ....

  - "traefik.http.routers.${PROXY_TESTING_ROUTER}.entrypoints=websecure" # Replace 'websecure' with 'web' if you don't need SSL
```

3. Remove Let's Encrypt init command from `reverse-proxy` service in `docker-compose-override.yaml`:
```
    # Remove if you don't need SSL from Lets Encrypt
    command:
      - --certificatesResolvers.letencrypt.acme.email=${LETS_ENCRYPT_EMAIL}
```

4. Remove 443 port exposure from `reverse-proxy` service in `docker-compose-override.yaml`:
```
    ports:
      - "80:80"
     # Remove if you don't need SSL
      - "443:443"
```

5. Remove http to https redirect and Let's Encrypt certificate resolver from `traefik.yml`:
```
    # Comment section below if you don't need an SSL or you want to disable automatic redirect to HTTPS
    http:
      redirections:
        entryPoint:
          to: websecure
          scheme: https

    .....

    # Remove if you don't need Lets Encrypt SSL
    certificatesResolvers:
      letencrypt:
        acme:
          storage: /certs/acme.json
          caServer: https://acme-v02.api.letsencrypt.org/directory
          httpChallenge:
            entryPoint: web
```

### Configuring environment variables

Create your own environment configuration from example:

```bash
cp .env.example .env
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
5. After every change in `.env` file you need to rebuild your `web` container: `docker compose build web --no-cache`.
   Then you should delete your containers (`docker compose down`) and recreate them with a new version of image (
   `docker compose up -d`).
6. By default, HTTP requests are automatically redirected by Traefik to HTTPS. You can disable this behaviour by deleting
   `entryPoints.web.http.redirections` configuration in `traefik.yml` file. Remember to restart the Traefik container.
7. If you need to store all files on the host directory, not inside the volume, please uncomment marked lines in
   `docker-compose-override.yaml` file inside `volumes:` section:
```
# Uncomment if you want to store AtroCore and DB files in data/ folder
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

8. After the update to AtroCore 2.0, container configuration needs to be changed. Inside a directory with docker-compose
   run `git pull` to download the new migration script (or [download it manually](https://gitlab.atrocore.com/atrocore/docker/-/blob/master/migrate-config.sh?ref_type=heads)
   and place right to the `docker-compose.yaml` file) and run it with command `sudo ./migrate-config.sh`.
9. If you are planning to use `pdf-generator` module, set `BUILD_VARIANT` to `pdf` in `.env` file. Users with an already
   installed system should rebuild their `web` image and recreate a container. After that, on the
   `Administration / Settings` page in the `Variables` panel add new config `useChromeNoSandbox` as boolean value and
   set it to `true`.
