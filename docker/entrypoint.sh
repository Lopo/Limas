#!/bin/env bash

cd /var/www/html

# migrate database always to newest state
php bin/console doctrine:migrations:migrate

# onetime generation
if [[ ! -f /var/www/html/data/.docker_installed ]]; then
  php bin/console limas:user:create --role super_admin admin admin@example.com admin
  php bin/console lexik:jwt:generate-keypair
  php bin/console limas:extjs:models
  php bin/console cache:warmup
  touch /var/www/html/data/.docker_installed
fi


# start apache
docker-php-entrypoint apache2-foreground