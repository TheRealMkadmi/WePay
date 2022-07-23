FROM php:7.3.32-apache-bullseye
RUN mkdir -p bootstrap/cache 
RUN mkdir  -p storage
RUN mkdir -p storage/app storage/framework/cache storage/framework/sessions storage/framework/testing storage/framework/views
RUN php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');" && \
    php -r "if (hash_file('sha384', 'composer-setup.php') === '906a84df04cea2aa72f40b5f787e49f22d4c2f19492ac310e8cba5b96ac8b64115ac402c8cd292b8a03482574915d1a8') { echo 'Installer verified'; } else { echo 'Installer corrupt'; unlink('composer-setup.php'); } echo PHP_EOL;" && \
    php composer-setup.php &&\
    php -r "unlink('composer-setup.php');"
RUN mv composer.phar /usr/local/bin/composer
RUN apt-get update
RUN apt-get install -y libgmp-dev libmcrypt-dev git zlib1g-dev libicu-dev g++ libcurl4-gnutls-dev libfreetype6-dev libjpeg62-turbo-dev libpng-dev zip libc-client-dev libkrb5-dev
RUN docker-php-ext-configure intl
RUN docker-php-ext-configure gd --with-freetype-dir=/usr/include/ --with-jpeg-dir=/usr/include/
RUN docker-php-ext-install json  hash mysqli gd curl mbstring gmp 
RUN docker-php-ext-configure imap --with-kerberos --with-imap-ssl
RUN docker-php-ext-install pdo pdo_mysql intl curl 
RUN apt-get install -y libzip-dev
RUN docker-php-ext-install bcmath zip
RUN docker-php-ext-install -j$(nproc) gd   

COPY . .
COPY /startup.sh /tmp
RUN chmod +x /tmp/startup.sh
RUN chmod -R 777 .
ENTRYPOINT [ "/tmp/startup.sh" ]


