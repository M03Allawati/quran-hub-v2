#!/bin/bash
# Wait for MySQL to be ready
echo "Waiting for MySQL..."
until php -r "new PDO('mysql:host='.getenv('MYSQLHOST').';port='.getenv('MYSQLPORT').';dbname='.getenv('MYSQLDATABASE'), getenv('MYSQLUSER'), getenv('MYSQLPASSWORD'));" 2>/dev/null; do
    echo "MySQL not ready, waiting..."
    sleep 2
done
echo "MySQL ready!"

# Run database migrations
php -r "
\$pdo = new PDO(
    'mysql:host='.getenv('MYSQLHOST').';port='.getenv('MYSQLPORT').';dbname='.getenv('MYSQLDATABASE').';charset=utf8mb4',
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
    echo 'Database already initialized (' . count(\$tables) . ' tables)' . PHP_EOL;
}
"

# Start Apache
apache2-foreground
