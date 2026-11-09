# ====================================================================
# Red Hat OpenShift & Kubernetes Containerfile
# Base: Official PHP 8.2 with Apache (OpenShift SCC Compliant)
# ====================================================================
FROM php:8.2-apache

# Install PDO MySQL driver
RUN apt-get update && apt-get install -y --no-install-recommends \
    curl \
    && docker-php-ext-install pdo pdo_mysql \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Configure Apache for Red Hat OpenShift Security Context Constraints (SCC):
# 1. OpenShift runs containers under arbitrary non-root UIDs in GID 0 (root group).
# 2. Port 80 (< 1024) is blocked by default; reconfigure Apache to listen on port 8080.
# 3. Apache lock, run, and log directories must be group-writable (group 0).
RUN sed -i 's/80/8080/g' /etc/apache2/ports.conf /etc/apache2/sites-available/*.conf && \
    echo "ServerName localhost" >> /etc/apache2/apache2.conf && \
    a2enmod rewrite headers

# Copy application code into Apache document root
COPY . /var/www/html/

# Clean up any local credentials or unneeded files in the container image
RUN rm -f /var/www/html/config/config.local.php

# OpenShift permissions: grant group 0 read/write access to Apache directories and webroot
RUN chgrp -R 0 /var/www/html /var/log/apache2 /var/run/apache2 /etc/apache2 && \
    chmod -R g=u /var/www/html /var/log/apache2 /var/run/apache2 /etc/apache2

# Expose standard non-privileged HTTP port
EXPOSE 8080

# Switch to non-root user (OpenShift standard UID 1001)
USER 1001

# Start Apache in the foreground
CMD ["apache2-foreground"]

