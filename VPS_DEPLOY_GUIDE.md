# 🚀 Руководство по развертыванию SoftProjects Invoice Helper на VPS (Nginx + PHP)

- **Домен:** `invoice.soft-projects.io`
- **Путь на сервере:** `/var/www/softprojects/invoice-helper`
- **Стек:** Nginx + PHP-FPM + wkhtmltopdf + Let's Encrypt SSL (без Docker)

---

## ⚡ Способ 1: Автоматическая установка в 1 команду (Рекомендуется)

### 1. Скопируйте файлы проекта на ваш VPS сервер:
```bash
# Выполните на локальном компьютере:
rsync -avz --exclude '.git' ./ root@IP_ВАШЕГО_VPS:/tmp/invoice-helper-deploy/
```

### 2. Зайдите на VPS по SSH и запустите скрипт:
```bash
ssh root@IP_ВАШЕГО_VPS
cd /tmp/invoice-helper-deploy
sudo bash deploy/deploy-vps.sh
```

### Что скрипт сделает автоматически:
1. Установит `Nginx`, `PHP-FPM`, `php-curl`, `php-mbstring`, `php-xml`, `wkhtmltopdf` и `certbot`.
2. Скопирует проект в директорию `/var/www/softprojects/invoice-helper`.
3. Настроит права доступа (`www-data:www-data`, `777` на папку кэша и PDF `storage/`).
4. Создаст конфиг виртуального хоста Nginx для `invoice.soft-projects.io`.
5. Выпустит бесплатный SSL-сертификат (HTTPS) от Let's Encrypt.

---

## 🛠️ Способ 2: Ручная пошаговая настройка на VPS

Если вы хотите настроить всё вручную:

### 1. Установите необходимые пакеты на VPS:
```bash
sudo apt update
sudo apt install -y nginx php-fpm php-cli php-curl php-mbstring php-xml php-zip wkhtmltopdf fonts-dejavu-core fonts-noto-core certbot python3-certbot-nginx
```

### 2. Создайте рабочую директорию и скопируйте файлы:
```bash
sudo mkdir -p /var/www/softprojects/invoice-helper
sudo mkdir -p /var/www/softprojects/invoice-helper/storage/catalogs
sudo mkdir -p /var/www/softprojects/invoice-helper/storage/temp

# Скопируйте файлы из platform/ в /var/www/softprojects/invoice-helper/
sudo cp -r platform/* /var/www/softprojects/invoice-helper/

# Назначьте права:
sudo chown -R www-data:www-data /var/www/softprojects
sudo chmod -R 755 /var/www/softprojects/invoice-helper
sudo chmod -R 777 /var/www/softprojects/invoice-helper/storage
```

### 3. Создайте конфиг Nginx:
Создайте файл `/etc/nginx/sites-available/invoice.soft-projects.io`:
```nginx
server {
    listen 80;
    listen [::]:80;
    server_name invoice.soft-projects.io;

    root /var/www/softprojects/invoice-helper;
    index index.php index.html;

    access_log /var/log/nginx/invoice.soft-projects.io.access.log;
    error_log /var/log/nginx/invoice.soft-projects.io.error.log;

    client_max_body_size 64M;

    gzip on;
    gzip_vary on;
    gzip_min_length 1024;
    gzip_proxied any;
    gzip_types text/plain text/css application/json application/javascript text/xml application/xml text/javascript;

    location / {
        try_files $uri $uri/ /index.php?$args;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        
        # Подставьте вашу версию php-fpm (например php8.2-fpm или php8.3-fpm):
        fastcgi_pass unix:/run/php/php-fpm.sock;
        
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        fastcgi_read_timeout 180s;
        fastcgi_send_timeout 180s;
        include fastcgi_params;
    }

    location ~ /\. {
        deny all;
    }
}
```

### 4. Активируйте сайт в Nginx и перезапустите:
```bash
sudo ln -sf /etc/nginx/sites-available/invoice.soft-projects.io /etc/nginx/sites-enabled/
sudo nginx -t
sudo systemctl reload nginx
```

### 5. Получите бесплатный SSL (HTTPS):
```bash
sudo certbot --nginx -d invoice.soft-projects.io
```

---

## 🔐 Вход в платформу:
- **Адрес:** `https://invoice.soft-projects.io`
- **Логин (Username):** `SoftProjects`
- **Пароль (Password):** `7Gq`W~<Bd82A`
