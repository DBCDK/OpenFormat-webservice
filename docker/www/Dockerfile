FROM docker.dbc.dk/dbc-apache-php7
MAINTAINER D-scrum <d-scrum@dbc.dk>

# clean up apache root
RUN rm $APACHE_ROOT/index.html

# copy webservice code to image
ADD src $APACHE_ROOT

# set permissions
RUN chown -R www-data:www-data /var/www/html

# update verbose_class
COPY verbose_class.php $APACHE_ROOT/OLS_class_lib

# add symbolic link to server.php
RUN ln -s $APACHE_ROOT/server.php $APACHE_ROOT/index.php

RUN echo "error_reporting = E_ALL & ~E_DEPRECATED & ~E_STRICT & ~E_NOTICE" >> /etc/php/7.3/apache2/php.ini  \
 && perl -pi -e 's/MaxRequestWorkers.*/MaxRequestWorkers\t 50/' /etc/apache2/mods-available/mpm_prefork.conf

EXPOSE 80

COPY config.sh /config.sh
COPY apache_security.conf /etc/apache2/conf-enabled/
COPY 000-default.conf /etc/apache2/sites-enabled/000-default.conf

ENTRYPOINT ["/entrypoint.sh"]
