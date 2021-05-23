FROM php:7.4.19-apache

WORKDIR /var/www/html

COPY .docker/vhost.conf /etc/apache2/sites-available/000-default.conf

RUN usermod -u 1000 www-data && chown -R www-data:www-data /var/www && a2enmod rewrite

RUN docker-php-ext-install pdo pdo_mysql

COPY .docker/wait-for-it.sh /usr/bin/wait-for-it.sh 

RUN chmod +x /usr/bin/wait-for-it.sh