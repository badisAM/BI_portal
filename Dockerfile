FROM php:8.2-apache
COPY . /var/www/html/
RUN a2enmod rewrite && chown -R www-data:www-data /var/www/html
EXPOSE 80
