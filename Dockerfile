FROM php:7.4-cli-alpine

# Installing PHP extensions
RUN apk update \
    && apk add --no-cache unzip \
    && apk add --no-cache --virtual .phpize-deps $PHPIZE_DEPS \
    && apk add --no-cache --virtual .php-deps \
        openssl-dev \
        bison \
# Swoole
    && curl https://codeload.github.com/swoole/swoole-src/zip/v4.4.12 > swoole.zip \
    && unzip -qq swoole.zip && mv swoole-src-4.4.12 swoole \
    && cd swoole; phpize; ./configure; make -j4; make install; cd - \
    # Clean-up
    && rm swoole.zip; rm -rf swoole \
    # Enable swoole extension with higher priority than pdo_pgsql
    && docker-php-ext-enable --ini-name 10-swoole.ini swoole \
    && php --ri swoole \
# libpq
    && curl https://codeload.github.com/postgres/postgres/zip/REL_12_1 > postgres.zip \
    && unzip -qq postgres.zip && mv postgres-REL_12_1 postgres \
    && cd postgres; ./configure --with-openssl --without-readline --without-zlib; cd - \
    # Patch libpq
    && sed -i "/#include \"postgres_ext.h\".*/i #include \"php\/ext\/swoole\/include\/socket_hook.h\"" postgres/src/interfaces/libpq/libpq-fe.h \
    # Make libpq
    && cd postgres/src/interfaces/libpq; make; make install; cd - \
    && cd postgres/src/bin/pg_config; make install; cd -; /usr/local/pgsql/bin/pg_config \
    && cd postgres/src/backend; make generated-headers; cd - \
    && cd postgres/src/include; make install; cd - \
    # Clean-up
    && rm postgres.zip && rm -rf postgres \
# pdo_pgsql
    && ln -s /usr/local/bin/php /usr/lib/libphp.so \
    && ln -s /usr/local/lib/php/extensions/no-debug-non-zts-20190902/swoole.so /usr/lib/libswoole.so \
    && ldconfig /usr/lib \
    && docker-php-source extract \
    && cd /usr/src/php/ext/pdo_pgsql; phpize; LIBS="-lswoole -lphp" ./configure; make; make install; cd - \
    # Clean-up
    && docker-php-source delete \
    # Enable pdo_pgsql extension with lower priority than swoole
    && docker-php-ext-enable --ini-name 20-pdo_pgsql.ini pdo_pgsql \
    && php --ri pdo_pgsql \
# Remove unnecessary dependencies
    && apk del --no-network .phpize-deps \
    && apk del --no-network .php-deps
