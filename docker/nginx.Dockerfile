FROM nginx:1.25-alpine
COPY docker/nginx.conf /etc/nginx/conf.d/default.conf
COPY public /var/www/html/public
