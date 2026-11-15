FROM nginx:1.27-alpine

COPY printhub-back/public/ /var/www/html/public/
COPY docker/nginx/backend.conf /etc/nginx/conf.d/default.conf

EXPOSE 80
