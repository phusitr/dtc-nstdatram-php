#!/bin/sh

/usr/bin/php -f /var/www/html/mqtt/busNstdaTracking/getAPIBusInfo.php >/dev/null 2>/dev/null &
sleep 1
/usr/bin/php -f /var/www/html/mqtt/busNstdaTracking/publishingBusinfo.php >/dev/null 2>/dev/null &
sleep 1
/usr/bin/php -f /var/www/html/mqtt/busNstdaTracking/logDataStored.php >/dev/null 2>/dev/null &

