#!/bin/bash
set -e

echo "================================="
echo "Starting Quran Hub v2..."
echo "================================="
echo "MYSQLHOST: $MYSQLHOST"
echo "MYSQLPORT: $MYSQLPORT"
echo "MYSQLDATABASE: $MYSQLDATABASE"

# Wait for MySQL
echo "Waiting for MySQL..."
MAX_TRIES=30
TRIES=0
while [ $TRIES -lt $MAX_TRIES ]; do
    if mysql -h"$MYSQLHOST" -P"$MYSQLPORT" -u"$MYSQLUSER" -p"$MYSQLPASSWORD" -e "SELECT 1" >/dev/null 2>&1; then
        echo "MySQL ready!"
        break
    fi
    TRIES=$((TRIES + 1))
    echo "Attempt $TRIES/$MAX_TRIES - MySQL not ready, waiting..."
    sleep 2
done

# Check database
echo "Checking database..."
TABLE_COUNT=$(mysql -h"$MYSQLHOST" -P"$MYSQLPORT" -u"$MYSQLUSER" -p"$MYSQLPASSWORD" -N -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='$MYSQLDATABASE'" 2>/dev/null || echo "0")

if [ "$TABLE_COUNT" -lt "5" ]; then
    echo "Importing database.sql..."
    mysql -h"$MYSQLHOST" -P"$MYSQLPORT" -u"$MYSQLUSER" -p"$MYSQLPASSWORD" "$MYSQLDATABASE" < /var/www/html/database.sql && echo "Database imported!" || echo "Import failed"
else
    echo "Database has $TABLE_COUNT tables"
fi

# Start Apache in foreground
echo "Starting Apache..."
. /etc/apache2/envvars
exec apache2 -DFOREGROUND
