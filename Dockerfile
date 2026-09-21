FROM php:8.2-cli-alpine

# Install system dependencies, curl, wkhtmltopdf, and fonts
RUN apk add --no-cache \
    curl \
    wkhtmltopdf \
    freetype \
    libpng \
    libjpeg-turbo \
    font-noto \
    font-noto-cjk \
    ttf-dejavu \
    bash

# Set working directory
WORKDIR /var/www/html

# Copy platform files
COPY platform/ /var/www/html/platform/

# Create storage directories and set permissions
RUN mkdir -p /var/www/html/platform/storage/catalogs \
    /var/www/html/platform/storage/temp && \
    chmod -R 777 /var/www/html/platform/storage

# Expose HTTP port
EXPOSE 8000

# Healthcheck
HEALTHCHECK --interval=30s --timeout=5s --start-period=5s --retries=3 \
    CMD curl -f http://127.0.0.1:8000/api.php?action=list_shops || exit 1

# Start built-in web server
CMD ["php", "-S", "0.0.0.0:8000", "-t", "platform"]
