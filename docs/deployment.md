# Production Deployment Guide

## 1. System Requirements
- **Web Server**: Apache 2.4+ (with `mod_rewrite` enabled) or Nginx 1.20+
- **PHP Version**: PHP 8.1 or higher (PHP 8.2+ recommended)
  - Required Extensions: `curl`, `mbstring`, `mysqli`, `pdo_mysql`, `openssl`, `json`, `fileinfo`
- **Database Engine**: MySQL 8.0+ or MariaDB 10.4+
- **Operating System**: Windows Server / Linux (Ubuntu 22.04 LTS / Debian 12)

---

## 2. Local XAMPP Setup (Windows)

### 2.1 Directory Placement
Place the project repository inside your XAMPP `htdocs` directory:
```
D:\xampp\htdocs\StudentOS-AI-project\
```

### 2.2 Apache Virtual Host (Optional but Recommended)
To run via a clean local domain like `http://studentos.local`:
Add to `D:\xampp\apache\conf\extra\httpd-vhosts.conf`:
```apache
<VirtualHost *:80>
    ServerAdmin webmaster@studentos.local
    DocumentRoot "D:/xampp/htdocs/StudentOS-AI-project"
    ServerName studentos.local
    <Directory "D:/xampp/htdocs/StudentOS-AI-project">
        Options Indexes FollowSymLinks MultiViews
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```
Add to `C:\Windows\System32\drivers\etc\hosts`:
```
127.0.0.1 studentos.local
```

### 2.3 Starting Services & Seeding Data
1. Launch **XAMPP Control Panel**.
2. Click **Start** for **Apache** and **MySQL**.
3. Open terminal or phpMyAdmin to import the schema:
   ```powershell
   mysql -u root -e "CREATE DATABASE IF NOT EXISTS studentos_ai CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
   mysql -u root studentos_ai < D:\xampp\htdocs\StudentOS-AI-project\database\schema.sql
   mysql -u root studentos_ai < D:\xampp\htdocs\StudentOS-AI-project\database\seed.sql
   ```
4. Access `http://localhost/StudentOS-AI-project/frontend/login.php`.

---

## 3. Production Linux / Nginx Setup

### 3.1 Nginx Server Block
```nginx
server {
    listen 80;
    server_name studentos.yourdomain.com;
    root /var/www/StudentOS-AI-project;
    index index.php index.html;

    location / {
        try_files $uri $uri/ /frontend/index.php?$args;
    }

    location /api/ {
        rewrite ^/api/(.*)$ /backend/API/index.php?endpoint=$1 last;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
    }

    location ~ /\.(ht|env) {
        deny all;
    }
}
```

### 3.2 File Permissions
```bash
chown -R www-data:www-data /var/www/StudentOS-AI-project
chmod -R 755 /var/www/StudentOS-AI-project
chmod -R 775 /var/www/StudentOS-AI-project/storage
chmod -R 775 /var/www/StudentOS-AI-project/logs
```

### 3.3 SSL Certificate
Obtain a free SSL certificate using Let's Encrypt:
```bash
certbot --nginx -d studentos.yourdomain.com
```
Update `frontend/includes/config.php`:
```php
ini_set('session.cookie_secure', 1); // Enable HTTPS cookies
```
