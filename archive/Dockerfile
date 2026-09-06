# The original 2014 "Restaurant Automation System", run as it was written — on
# PHP 5.6 with the long-removed `mysql_*` extension. This is why the app wouldn't
# start on a modern PHP: it predates mysqli/PDO-only runtimes. Nothing here is
# modernised; it exists so you can see where the project began. The rebuilt app
# lives at the repository root (see the top-level README).
FROM php:5.6-apache

# The ancient `mysql` extension the 2014 code calls (mysql_connect/mysql_query).
RUN docker-php-ext-install mysql

# Restore the 2014 shared-hosting PHP defaults the code assumes: output_buffering
# ON (so its `session_start()`/`header()` calls after output still work — the real
# reason it "wouldn't start" on a modern default config), and errors hidden as in a
# production host. No application code is modified.
RUN { echo "output_buffering = 4096"; echo "display_errors = Off"; } > /usr/local/etc/php/conf.d/ras-legacy.ini

# Serve the app from the Apache document root.
COPY . /var/www/html/
