#!/bin/sh

set -e

NGINX_ROOT=/usr/share/nginx/html

cp /logis/swagger-initializer.js "$NGINX_ROOT/swagger-initializer.js"
gzip < "$NGINX_ROOT/swagger-initializer.js" > "$NGINX_ROOT/swagger-initializer.js.gz"
