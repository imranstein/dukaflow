# Production image. Not Sail — Sail bind-mounts the repo and runs a dev
# runtime; this bakes a real, immutable build. Three stages: PHP deps,
# frontend build, then the runtime image with only what it needs to serve.

FROM composer:2 AS composer-deps
WORKDIR /app
# filament/support requires ext-intl, which composer's own image doesn't
# ship — composer install fails its platform check without this, confirmed
# by actually running it against this lock file, not assumed from a
# changelog. install-php-extensions is composer:2's own bundled installer.
RUN install-php-extensions intl
COPY composer.json composer.lock ./
# No scripts yet: artisan isn't present until the full source is copied, and
# composer's post-install hooks (package:discover, filament:upgrade) call
# it — they need intl loaded too, which is the other reason to actually
# install the extension here rather than just ignore the platform check.
RUN composer install --no-dev --no-interaction --no-progress --no-scripts --optimize-autoloader
COPY . .
RUN composer install --no-dev --no-interaction --no-progress --optimize-autoloader

FROM node:22-alpine AS frontend-build
WORKDIR /app
COPY package.json package-lock.json ./
RUN npm ci
COPY resources ./resources
COPY vite.config.js ./
RUN npm run build

FROM php:8.3-fpm-alpine AS runtime

# nginx and php-fpm in one container, not two behind a shared volume. A
# self-hoster running one instance of this app gets no benefit from
# splitting them, and splitting them means nginx and the app container both
# need the same built public/ directory in their own filesystem, which is
# the complexity this avoids.
#
# Checked against the base image rather than assumed: mbstring, dom, xml,
# openssl, pdo and opcache are already compiled in; mysqlnd is present but
# pdo_mysql itself is not. icu-dev is the only build-time header actually
# needed, for intl — bcmath and pdo_mysql need nothing extra.
#
# icu-dev pulls in icu-libs as a dependency, and nothing else in this image
# holds a reference to it — apk del icu-dev quietly takes icu-libs with it,
# which breaks intl at runtime with a missing libicuio.so.78. Caught by
# actually running this sequence, not by assuming the common
# add-dev/build/del-dev pattern holds here. Re-adding icu-libs by name after
# the delete keeps only the runtime piece.
RUN apk add --no-cache icu-dev nginx \
    && docker-php-ext-install -j"$(nproc)" pdo_mysql bcmath intl \
    && apk del icu-dev \
    && apk add --no-cache icu-libs

# The image otherwise runs on PHP's built-in defaults — no php.ini at all,
# only the -development/-production templates the base image ships but
# never activates. This picks the production one, then raises the upload
# limit to match nginx.conf's client_max_body_size so the two agree; nothing
# uploads files today, but a mismatched pair of limits is a surprise for
# whenever something does.
RUN cp "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini" \
    && sed -i \
        -e 's/^upload_max_filesize = .*/upload_max_filesize = 20M/' \
        -e 's/^post_max_size = .*/post_max_size = 20M/' \
        "$PHP_INI_DIR/php.ini"

# Boring, standard opcache settings for a single-app container — no
# hot-reload watching, since nothing in a built image changes at runtime.
RUN { \
        echo 'opcache.enable=1'; \
        echo 'opcache.validate_timestamps=0'; \
        echo 'opcache.max_accelerated_files=20000'; \
        echo 'opcache.memory_consumption=192'; \
    } > /usr/local/etc/php/conf.d/opcache-prod.ini

WORKDIR /var/www/html

COPY --from=composer-deps /app ./
COPY --from=frontend-build /app/public/build ./public/build

RUN chown -R www-data:www-data storage bootstrap/cache

COPY docker/nginx.conf /etc/nginx/http.d/default.conf
COPY docker/entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

EXPOSE 80

ENTRYPOINT ["/entrypoint.sh"]
CMD ["sh", "-c", "php-fpm -D && nginx -g 'daemon off;'"]
