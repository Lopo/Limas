Dependencies
---

* PHP 8.4 and higher
  * php-curl
  * php-ldap
  * php-bcmath
  * php-gd
  * php-dom
  * php-ctype
  * php-iconv
  * php-redis
* [Composer](https://getcomposer.org/download/)
* MySQL 8.4 server
* Nginx or Apache
* Redis

---

1. Copy or clone this repository into a folder on your server
2. Configure your webserver to serve from the `public/` folder. See [here](https://symfony.com/doc/6.1/setup/web_server_configuration.html) for additional information.
3. Copy the global config file `cp .env .env.local` and edit `.env.local`:
   * Change the line `APP_ENV=dev` to `APP_ENV=prod`
4. Install composer dependencies and generate autoload files: `SYMFONY_ENV=prod composer install --no-dev -o`
5. Install client side dependencies and build it: `yarn install` and `yarn build`
6. Create database: `php bin/console doctrine:migrations:migrate`
7. Init database: `php bin/console doctrine:fixtures:load --group=setup --append`
8. Create superadmin account: `php bin/console limas:user:create --role super_admin admin admin@example.com admin`
   * _Optional_ protect it: `php bin/console limas:user:protect admin`
9. Generate models.js asset: `php bin/console limas:extjs:models`
10. Compile assets: `php bin/console asset-map:compile`
11. _Optional_ generate JWT keypair: `php bin/console lexik:jwt:generate-keypair`
12. _Optional_ (speeds up first load): Warmup cache: `php bin/console cache:warmup`
