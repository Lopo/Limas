FROM php:8.3-apache-bookworm AS php

WORKDIR /build

# install dependencies to build image
RUN \
    apt update && \
    apt install libldap2-dev wget zlib1g-dev libpng-dev libzip-dev curl libcurl4-gnutls-dev libxml2-dev -y && \
    rm -rf /var/lib/apt/lists/* && \
    docker-php-ext-configure ldap --with-libdir=lib/x86_64-linux-gnu/ && \
    docker-php-ext-configure pdo --with-libdir=lib/x86_64-linux-gnu/ && \
    docker-php-ext-install ldap pdo && \
    docker-php-ext-install zip curl bcmath dom ctype iconv pdo_mysql gd

RUN pecl install --force redis \
&& rm -rf /tmp/pear \
&& docker-php-ext-enable redis


# copy files
COPY . .
# get php composer
RUN wget https://raw.githubusercontent.com/composer/getcomposer.org/76a7060ccb93902cd7576b67264ad91c8a2700e2/web/installer -O - | php --
# install composer dependencies
ENV COMPOSER_ALLOW_SUPERUSER=1
RUN SYMFONY_ENV=prod php composer.phar install -o



FROM node:20-bullseye-slim AS node

WORKDIR /build
# build frontend files
COPY --from=php /build .
RUN yarn install --frozen-lockfile
RUN yarn build
RUN rm -r node_modules/



FROM php:8.3-apache-bookworm

WORKDIR /build

# add dependencies to final runtime image
RUN \
    apt update && \
    apt install libldap2-dev wget zlib1g-dev libpng-dev libzip-dev curl libcurl4-gnutls-dev libxml2-dev -y && \
    rm -rf /var/lib/apt/lists/* && \
    docker-php-ext-configure ldap --with-libdir=lib/x86_64-linux-gnu/ && \
    docker-php-ext-configure pdo --with-libdir=lib/x86_64-linux-gnu/ && \
    docker-php-ext-install ldap pdo && \
    docker-php-ext-install zip curl bcmath dom ctype iconv pdo_mysql gd

RUN pecl install --force redis \
&& rm -rf /tmp/pear \
&& docker-php-ext-enable redis

COPY --from=node /build /var/www/html

# apply apache2 config for symfony
RUN bash -c 'echo -e "<VirtualHost *:80>\n\
        ServerAdmin webmaster@localhost\n\
        DocumentRoot /var/www/html/public/\n\
        <Directory /var/www/html/public>\n\
            AllowOverride None\n\
            Require all granted\n\
            FallbackResource /index.php\n\
        </Directory>\n\
\n\
        ErrorLog \${APACHE_LOG_DIR}/error.log\n\
        CustomLog \${APACHE_LOG_DIR}/access.log combined\n\
\n\
</VirtualHost>\
" > /etc/apache2/sites-available/000-default.conf'

EXPOSE 80

WORKDIR /var/www/html

# specify environment variables for .env file

ENV REDIS_DSN="redis://redis"
ENV APP_ENV="prod"
ENV APP_SECRET=""
ENV DATABASE_URL="mysql://limas:limas@mysql:3306/limas?serverVersion=5.7.9&charset=utf8mb4"
ENV NEXAR_ID="client"
ENV NEXAR_SECRET="secret"
# ISO 3166 (alpha-2) country code
ENV NEXAR_COUNTRY="DE"
# ISO 4217 currency code
ENV NEXAR_CURRENCY="EUR"

VOLUME ["/var/www/html/data"]

# run build commands
RUN php bin/console limas:extjs:models
RUN php bin/console cache:warmup

# overwrite entrypoint for custom startup commands

RUN chmod +x /var/www/html/docker/entrypoint.sh

ENTRYPOINT ["/var/www/html/docker/entrypoint.sh"]