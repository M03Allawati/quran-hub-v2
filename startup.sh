#!/bin/bash
set -e

echo "Starting Quran Hub v2..."
echo "MYSQLHOST: $MYSQLHOST"
echo "MYSQLPORT: $MYSQLPORT"
echo "MYSQLDATABASE: $MYSQLDATABASE"

# Wait for MySQL with timeout
echo "Waiting for MySQL..."
MAX_TRIES=30
TRIES=0
while [ $TRIES -lt $MAX_TRIES ]; do
    if php -r "
        try {
            \$pdo = new PDO(
                'mysql:host=' . getenv('MYSQLHOST') . ';port=' . getenv('MYSQLPORT') . ';dbname=' . getenv('MYSQLDATABASE'),
                getenv('MYSQLUSER'),
                getenv('MYSQLPASSWORD'),
                [PDO::ATTR_TIMEOUT => 3]
            );
            echo 'OK';
            exit(0);
        } catch (Exception \$e) {
            echo \$e->getMessage();
            exit(1);
        }
    " 2>&1 | grep -q "OK"; then
        echo "MySQL ready!"
        break
    fi
    TRIES=$((TRIES + 1))
    echo "Attempt $TRIES/$MAX_TRIES - MySQL not ready, waiting..."
    sleep 2
done

if [ $TRIES -ge $MAX_TRIES ]; then
    echo "WARNING: MySQL not responding after 60s, starting Apache anyway"
fi

# Run database migrations
echo "Checking database..."
php -r "
try {
    \$pdo = new PDO(
        'mysql:host=' . getenv('MYSQLHOST') . ';port=' . getenv('MYSQLPORT') . ';dbname=' . getenv('MYSQLDATABASE') . ';charset=utf8mb4',
        getenv('MYSQLUSER'),
        getenv('MYSQLPASSWORD')
    );
    \$tables = \$pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
    if (count(\$tables) < 5) {
        echo 'Running database setup...' . PHP_EOL;
        \$sql = file_get_contents('/var/www/html/database.sql');
        \$pdo->exec(\$sql);
        echo 'Database initialized!' . PHP_EOL;
    } else {
        echo 'Database already has ' . count(\$tables) . ' tables' . PHP_EOL;
    }
} catch (Exception \$e) {
    echo 'DB setup skipped: ' . \$e->getMessage() . PHP_EOL;
}
"

# Start Apache in foreground
echo "Starting Apache..."
exec apache2-foreground
