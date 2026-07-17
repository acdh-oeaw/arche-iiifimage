FROM php:8.5-apache
LABEL org.opencontainers.image.authors="mzoltak@oeaw.ac.at"
ADD https://github.com/mlocati/docker-php-extension-installer/releases/latest/download/install-php-extensions /usr/local/bin/
RUN chmod +x /usr/local/bin/install-php-extensions &&\
    install-php-extensions yaml pdo_pgsql imagick @composer  &&\
    mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini" &&\
    a2enmod rewrite
COPY docroot /var/www/html
COPY entrypoint.sh /usr/local/bin/entrypoint.sh
COPY 000-default.conf /etc/apache2/sites-enabled/000-default.conf
RUN cd /var/www/html &&\
    composer update -o --no-dev &&\
    chown -R www-data:www-data /var/www/html
EXPOSE 80
ENTRYPOINT ["entrypoint.sh"]
RUN apt update && apt install -y vim
