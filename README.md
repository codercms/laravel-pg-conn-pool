# laravel-pg-conn-pool
Laravel PostgreSQL connection pool

**WARNING**: The package is not production-ready yet (only for testing purposes).

Reasons:
1. Gaining more performance to the application

Requirements:
1. PHP 7.4
2. Laravel 6.x
3. Swoole 4.x and higher
4. ext-pq

## Usage
Configuration (`config/database.php`):
```
'pgsql_pool' => [
    // driver name that supports connection pooling
    'driver' => 'pgsql_pool',

    // how much connections is needed
    'max_active' => 4,
    // minimum number of active connections
    'min_active' => 2,
    // how mush time to wait for an avaiable connection
    'max_wait_time' => 5.0, // 5 seconds
    // automatically close connections which are idle more than a minute
    'max_idle_time' => 60,
    // how often to check idle connections
    'validation_interval' => 30.0, // every 30 seconds
     
    // for more paremeters read the - https://github.com/makise-co/pool
    // parameter names in the connection config should be in snake_case

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
## Notes
* The following methods on LazyConnection may not work (because they rely on the real connection):
    * setPdo
    * setReadPdo
    * disconnect
    * reconnect
* All connections management is performed by connection pool, not by Laravel
* Each coroutine can take only one connection from the pool
