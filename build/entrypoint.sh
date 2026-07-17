#!/bin/bash
echo "$PGPASS" > /var/www/.pgpass &&\
    chmod 600 /var/www/.pgpass &&\
    chown www-data:www-data /var/www/.pgpass &&\
    /var/www/html/vendor/bin/yaml-edit.php --src /var/www/html/config.yaml --src "{\"auth\": {\"defaultAuth\": [\"$ARCHE_LOGIN\", \"$ARCHE_PSWD\"]}}" /var/www/html/config.yaml &&\
    chown www-data:www-data /var/www/html/config.yaml
    

docker-php-entrypoint apache2-foreground

