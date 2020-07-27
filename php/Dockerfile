FROM php:7.3.5-fpm-alpine

# PHP-FPM production settings
RUN mv $PHP_INI_DIR/php.ini-production $PHP_INI_DIR/php.ini

# Software updates & installation
RUN apk update && \
    apk upgrade && \
    apk add fcgi && \
    apk add tzdata && \
    docker-php-ext-install mysqli && \
    rm -f /var/cache/apk/*

# Enable PHP-FPM status page, disable PHP-FPM access.log, set PHP-FPM ondemand, and make PHP-FPM Healtcheck runnable
RUN set -xe && \
    echo "pm.status_path = /status"                 >> /usr/local/etc/php-fpm.d/zz-docker.conf && \
    echo "access.log = /dev/null"                   >> /usr/local/etc/php-fpm.d/zz-docker.conf && \
    echo "pm = ondemand"                            >> /usr/local/etc/php-fpm.d/zz-docker.conf && \
    echo "pm.max_children = 20"                     >> /usr/local/etc/php-fpm.d/zz-docker.conf && \
    echo "pm.process_idle_timeout = 20s"            >> /usr/local/etc/php-fpm.d/zz-docker.conf && \
    echo "pm.max_requests = 500"                    >> /usr/local/etc/php-fpm.d/zz-docker.conf && \
    echo "listen = /sock/app.sock"                  >> /usr/local/etc/php-fpm.d/zz-docker.conf && \
    echo "listen.mode = 0660"                       >> /usr/local/etc/php-fpm.d/zz-docker.conf && \
    echo "listen.backlog = 65536"                   >> /usr/local/etc/php-fpm.d/zz-docker.conf && \
    printf '[PHP]\ndate.timezone = "Europe/Rome"\n' >> /usr/local/etc/php/conf.d/tzone.ini
