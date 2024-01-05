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
* [Composer](https://getcomposer.org/download/)
* MySQL 5.7 server
* Nginx or Apache

---

1. Copy or clone this repository into a folder on your server
3. Configure your webserver to serve from the `public/` folder. See [here](https://symfony.com/doc/6.1/setup/web_server_configuration.html) for additional information.
4. Copy the global config file `cp .env .env.local` and edit `.env.local`:
   * Change the line `APP_ENV=dev` to `APP_ENV=prod`
5. Install composer dependencies and generate autoload files: `composer install --no-dev -o`
6. Install client side dependencies and build it: `yarn install` and `yarn build`
7. Create database: `php bin/console doctrine:migrations:migrate`
8. Create superadmin account: `php bin/console limas:user:create --role super_admin admin admin@example.com admin`
9. Generate models.js asset: `php bin/console limas:extjs:models`
10. _Optional_ generate JWT keypair: `php bin/console lexik:jwt:generate-keypair`
11. _Optional_ (speeds up first load): Warmup cache: `php bin/console cache:warmup`
