# ============================================================
# Roh & Sih — imagem de produção para EasyPanel
# PHP procedural + Apache. Docroot = sistema/public.
# api/ é exposto por Alias; config/, database/ e storage/ ficam
# FORA do webroot (inacessíveis pela internet).
# ============================================================
FROM php:8.3-apache

# Extensões necessárias (PDO MySQL) + módulos Apache
RUN apt-get update \
    && apt-get install -y --no-install-recommends libzip-dev unzip \
    && docker-php-ext-install pdo pdo_mysql zip \
    && a2enmod rewrite headers \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Configuração de PHP e do virtual host
COPY sistema/deploy/php.ini /usr/local/etc/php/conf.d/roh-e-sih.ini
COPY sistema/deploy/apache.conf /etc/apache2/sites-available/000-default.conf

# Copia SOMENTE a aplicação (a biblioteca IA Dev não vai para a imagem)
COPY sistema/ /var/www/html/

# Permissões e pasta de vídeos gravável
RUN chown -R www-data:www-data /var/www/html \
    && find /var/www/html -type d -exec chmod 755 {} \; \
    && find /var/www/html -type f -exec chmod 644 {} \; \
    && mkdir -p /var/www/html/storage/videos \
    && chown -R www-data:www-data /var/www/html/storage

HEALTHCHECK --interval=30s --timeout=5s --retries=3 \
  CMD curl -f http://localhost/health.php || exit 1

EXPOSE 80
