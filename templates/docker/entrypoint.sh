#!/bin/sh
# Brings the database schema up to the extensions' (new tables and columns) before Apache starts. The caches go first:
# var/ is a volume, so the previous image's TCA cache would otherwise hide new columns from the setup.
set -e

cd /var/www/html
su -s /bin/sh www-data -c 'vendor/bin/typo3 cache:flush && vendor/bin/typo3 extension:setup --no-interaction'

exec "$@"
