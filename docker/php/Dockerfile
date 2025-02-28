FROM php:8.4-fpm AS base

ADD https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions /usr/local/bin/

RUN chmod uga+x /usr/local/bin/install-php-extensions && sync

RUN apt update \
    && apt install -y zlib1g-dev g++ git libicu-dev zip libzip-dev zip

RUN install-php-extensions intl opcache pdo_mysql zip apcu gd xml dom sysvsem xsl fileinfo

COPY ./docker/php/common-php.ini /usr/local/etc/php/conf.d/common-php.ini

WORKDIR /var/www/website

FROM base AS build

RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

FROM build AS dev

COPY ./docker/php/dev-php.ini /usr/local/etc/php/conf.d/dev-php.ini

RUN /usr/local/bin/composer completion bash | tee /etc/bash_completion.d/composer

RUN echo '\nif [ -f /etc/bash_completion ] && ! shopt -oq posix; then\n    . /etc/bash_completion\nfi\n' >> ~/.bashrc
RUN echo 'eval "$(/var/www/website/bin/console completion bash )"\n' >> ~/.bashrc

RUN install-php-extensions xdebug

FROM registry.kiora.tech/kiora/crm-gdb_php_base:0.3.0 AS prod

COPY . /var/www/website

COPY ./docker/php/prod-php.ini /usr/local/etc/php/conf.d/prod-php.ini

RUN chown -R www-data:www-data /var/www/website/var \
    && chmod -R 775 /var/www/website/var

#supervisor version
FROM prod AS supervisor

RUN apt-get update && apt-get install -y supervisor

COPY ./docker/supervisor/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]