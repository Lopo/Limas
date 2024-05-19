Dependencies
---

* PHP 8.2 and higher
  * php-curl
  * php-ldap
  * php-bcmath
  * php-gd
  * php-dom
  * php-ctype
  * php-iconv
  * php-redis
* [Composer](https://getcomposer.org/download/)
* MySQL 5.7 server
* Nginx or Apache
* Redis

---

1. Copy or clone this repository into a folder on your server
3. Configure your webserver to serve from the `public/` folder. See [here](https://symfony.com/doc/7.0/setup/web_server_configuration.html) for additional information.
4. Copy the global config file `cp .env .env.local` and edit `.env.local`:
   * Change the line `APP_ENV=dev` to `APP_ENV=prod`
5. Install composer dependencies and generate autoload files: `SYMFONY_ENV=prod composer install -o`
6. Install client side dependencies and build it: `yarn install` and `yarn build`
7. Create database: `php bin/console doctrine:migrations:migrate`
8. Create superadmin account: `php bin/console limas:user:create --role super_admin admin admin@example.com admin`
   * _Optional_ protect it: `php bin/console limas:user:protect admin`
9. Generate models.js asset: `php bin/console limas:extjs:models`
10. _Optional_ generate JWT keypair: `php bin/console lexik:jwt:generate-keypair`
11. _Optional_ (speeds up first load): Warmup cache: `php bin/console cache:warmup`


## Docker Usage

Manually build the docker image with: `docker build -t local-limas-build .`

Run the image with: `docker run --name limas -p 8080:80 -v "./data:/var/www/html/data" -e "APP_ENV=prod" -e "DATABASE_URL=mysql://username:password@mysql_host:3306/database-name?serverVersion=5.7.9&charset=utf8mb4"`

On the first start an admin user with `admin@example.com` and `admin` will be created, as well as the database migrations, which run on every start.

A docker-compose example can be found here:
```yaml
services:
  db:
    image: mysql
    restart: unless-stopped
    ports:
      - 3306:3306
    environment:
      MYSQL_ROOT_PASSWORD: example
      MYSQL_DATABASE: limas
      MYSQL_USER: limas
      MYSQL_PASSWORD: limas
  limas:
    image: local-limas-build
    restart: unless-stopped
    ports:
      - 8080:80
    volumes:
      - ./data:/var/www/html/data
    environment:
      APP_ENV: prod
      DATABASE_URL: mysql://limas:limas@db:3306/limas?serverVersion=5.7.9&charset=utf8mb4
  redis:
    image: redis:7-alpine
    restart: unless-stopped
```

Connect to the application via `http://localhost:8080` (or the corresponding hostname of your server)

To add SSL, you need a reverse proxy with SSL configured, like traefik.

All Environment variables with their default values:

| Environment variable name | default value                                                              |
|---------------------------|----------------------------------------------------------------------------|
| REDIS_DSN                 | `redis://redis`                                                            |
| APP_ENV                   | `prod` (can only be set to `dev` or `prod`)                                |
| APP_SECRET                | _empty_                                                                    |
| DATABASE_URL              | `mysql://limas:limas@mysql:3306/limas?serverVersion=5.7.9&charset=utf8mb4` |
| NEXAR_ID                  | `client`                                                                   |
| NEXAR_SECRET              | `secret`                                                                   |
| NEXAR_COUNTRY             | `DE`                                                                       |
| NEXAR_CURRENCY            | `EUR`                                                                      |
