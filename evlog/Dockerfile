FROM php:8.2-apache

# Installa estensioni PHP necessarie per MySQL
RUN docker-php-ext-install mysqli pdo pdo_mysql

# Abilita mod_rewrite (utile per molte app PHP)
RUN a2enmod rewrite

# Imposta la directory di lavoro
WORKDIR /var/www/html

# Copia il codice dell'app
COPY src/ /var/www/html/

# Permessi corretti per Apache
RUN chown -R www-data:www-data /var/www/html

EXPOSE 80