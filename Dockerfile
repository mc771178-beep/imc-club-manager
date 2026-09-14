FROM wordpress:6.6.2-php8.2-apache

COPY --chown=www-data:www-data . /usr/src/wordpress/wp-content/plugins/imc-club-manager/
