# Imagem oficial do PHP 8.2 já com Apache (o projeto exige PHP 8.1+)
FROM php:8.2-apache

# Extensão para o PHP conversar com o MySQL (PDO MySQL)
RUN docker-php-ext-install pdo_mysql

# mod_rewrite/headers e AllowOverride All: necessários para os .htaccess do projeto funcionarem
RUN a2enmod rewrite headers \
 && sed -ri 's!AllowOverride None!AllowOverride All!g' /etc/apache2/apache2.conf

# Copia o projeto para a pasta pública do Apache
COPY . /var/www/html/

# Permissões para o Apache (www-data) ler os arquivos
RUN chown -R www-data:www-data /var/www/html

EXPOSE 80
