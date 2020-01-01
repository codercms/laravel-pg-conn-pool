# laravel-pg-conn-pool
Laravel PostgreSQL connection pool

**WARNING**: Package is not production ready yet (only for testing purposes).

Reasons:
1. Gaining more performance to the application
2. Swoole Coroutine Postgres client isn't PDO compatible (it requires big changes in the existing code)
and doesn't have such functionality like MySQL Coroutine client

Requirements:
1. PHP 7.4
2. Laravel 6.x
3. Swoole 4.x and higher
4. Specially compiled pdo_pgsql extension

## How it works?
When Laravel is running under Swoole each request is handled in a new coroutine.
When DatabaseManager::connect is called, connection pool is initialized at once, and connection is returned from the pool 
(Swoole Channel is used). Each coroutine will get a new connection from the pool.
After request is handled by an application a connection is returned to the pool.
This approach gives an ability to handle many requests (depends on the pool size) without blocking an entire thread (worker).

## Usage
Configuration (`config/database.php`):
```
'pgsql_pool' => [
    // driver name that supports connection pooling
    'driver' => 'pgsql_pool',
    // how much connections is needed
    'poolSize' => 4,

    // default postgres driver config directives below
    'url' => env('DATABASE_URL'),
    'host' => env('DB_HOST', '127.0.0.1'),
    'port' => env('DB_PORT', '5432'),
    'database' => env('DB_DATABASE', 'forge'),
    'username' => env('DB_USERNAME', 'forge'),
    'password' => env('DB_PASSWORD', ''),
    'charset' => 'utf8',
    'prefix' => '',
    'prefix_indexes' => true,
    'schema' => 'public',
    'sslmode' => 'prefer',
],
```

Add `PooledConnectionReturnMiddleware` to the app global middlewares list (`app/Http/Kernel.php` - `$middleware`).

## Building pdo_pgsql
Dockerfile with build example of pdo_pgsql in this repo.

How to compile pdo_pgsql for non-blocking (coroutine) mode:

0. Install PHP dev package (php7.4-dev)
1. Get swoole sources (for libs and headers) - https://github.com/swoole/swoole-src
2. Get postgres sources (for libpq and headers) - https://github.com/postgres/postgres
3. Get PHP sources (for pdo_pgsql building) - https://github.com/php/php-src
4. Build swoole (guide is described in the swoole repo)
5. Prepare postgres to build libpq: `cd postgres && ./configure --with-openssl --without-readline --without-zlib`
6. Go to `postgres/src/interfaces/libpq` path
6. Patch libpq source file `libpq-fe.h`, add `#include "php/ext/swoole/include/socket_hook.h"` 
below all "#include" directives (read more here - https://wiki.swoole.com/wiki/page/983.html).
On your system include may be different, for example on Ubuntu it's: `php/20190902/ext/swoole/include/socket_hook.h`.
**WARNING** `20190902` is a ZendAPI version for PHP 7.4.0, to determine your ZendAPI version you should run `phpize -v`.
7. Make libpq: `make && make install`
8. Make pg_config: Go to `postgres/src/bin/pg_config` and run `make install`
9. Make headers: Go to `postgres/src/backend` and run `make generated-headers`
10. Make includes: Go to `postgres/src/include` and run `make install`
11. Register PHP binary and swoole.so compiled files as a libs:
    ```
    ln -s /usr/local/bin/php /usr/lib/libphp.so
    ln -s /usr/local/lib/php/extensions/no-debug-non-zts-20190902/swoole.so /usr/lib/libswoole.so
    ldconfig /usr/lib
    ```
12. Build pdo_pgsql:
Go to PHP pdo_pgsql source path (for example - `/usr/src/php/ext/pdo_pgsql`)
    ```
    phpize
    LIBS="-lswoole -lphp" ./configure
    make
    make install
    ```
13. Make sure then new pdo_pgsql extension is loaded after swoole extension.
14. You can use now coroutine-based pdo_pgsql.
