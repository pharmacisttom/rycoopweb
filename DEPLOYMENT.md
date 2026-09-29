# Production Deployment Guide — RayongCoop Digital Portal

คู่มือการติดตั้งและปรับใช้บนระบบแม่ข่าย **Ubuntu 24.04 LTS + Nginx + PHP-FPM 8.4 + MySQL 8+**

---

## 1. การติดตั้ง Server Packages (Ubuntu 24.04 LTS)

```bash
# Update repositories
sudo apt update && sudo apt upgrade -y

# Add Ondřej Surý PHP PPA for PHP 8.4
sudo apt install -y software-properties-common
sudo add-apt-repository ppa:ondrej/php -y
sudo apt update

# Install Nginx, MySQL, PHP 8.4 and extensions
sudo apt install -y nginx mysql-server composer \
    php8.4-fpm php8.4-mysql php8.4-mbstring php8.4-xml php8.4-curl \
    php8.4-gd php8.4-zip php8.4-bcmath php8.4-intl php8.4-fileinfo
```

---

## 2. การตั้งค่า Nginx Virtual Host

ก่อนเริ่ม Nginx ให้สร้าง React production build บนเครื่อง release หรือดาวน์โหลด
artifact `rayongcoop-react-build` จาก CI ไปไว้ที่ `public/app/`:

```bash
npm ci
npm run build
```

ห้าม commit ไฟล์ใน `public/app/` เพราะเป็นไฟล์ที่สร้างอัตโนมัติจาก source code
และต้องสร้างใหม่ทุกครั้งที่ deploy

สร้างไฟล์การตั้งค่า `/etc/nginx/sites-available/rayongcoop.conf`:

```nginx
server {
    listen 80;
    server_name portal.rayongcoop.com www.portal.rayongcoop.com;
    root /var/www/rayongcoop/public;

    index index.html index.php;

    # Security Headers
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-XSS-Protection "1; mode=block" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header Referrer-Policy "strict-origin-when-cross-origin" always;
    add_header Strict-Transport-Security "max-age=31536000; includeSubDomains" always;

    # React SPA Assets & Routing (/app/ and root fallback)
    location /app/ {
        try_files $uri $uri/ /app/index.html;
    }

    # API & Authentication Endpoints -> PHP FastCGI
    location ~ ^/(api|csrf-token|login|logout|change-password|verify-receipt) {
        try_files $uri /index.php?$query_string;
    }

    # PHP Front Controller
    location / {
        try_files $uri $uri/ /app/index.html /index.php?$query_string;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/var/run/php/php8.4-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    # Block access to hidden files (.env, .git)
    location ~ /\.(?!well-known).* {
        deny all;
    }

    # Serve uploads stored outside the public document root
    location /storage/uploads/ {
        alias /var/www/rayongcoop/storage/uploads/;
        try_files $uri =404;
        add_header X-Content-Type-Options "nosniff" always;
    }

    # Never execute uploaded files as PHP
    location ~* ^/(storage/uploads|public/uploads)/.*\.php$ {
        deny all;
    }

    # Static asset caching
    location ~* \.(jpg|jpeg|png|gif|ico|css|js|webp|svg|woff2)$ {
        expires 30d;
        add_header Cache-Control "public, no-transform";
    }

    client_max_body_size 25M;
}
```

เปิดใช้งานไซต์และ Reload Nginx:
```bash
sudo ln -s /etc/nginx/sites-available/rayongcoop.conf /etc/nginx/sites-enabled/
sudo nginx -t
sudo systemctl reload nginx
```

---

## 3. การตั้งค่า SSL ด้วย Let's Encrypt (Certbot)
```bash
sudo apt install -y certbot python3-certbot-nginx
sudo certbot --nginx -d portal.rayongcoop.com -d www.portal.rayongcoop.com
```

---

## 4. การตั้งค่าสิทธิ์โฟลเดอร์ (Folder Permissions)
```bash
sudo chown -R www-data:www-data /var/www/rayongcoop
sudo chmod -R 755 /var/www/rayongcoop
sudo chmod -R 775 /var/www/rayongcoop/storage
```

---

## 5. ค่า Environment สำหรับ Production

สร้าง `.env` บนเซิร์ฟเวอร์จากค่า secrets ของระบบเท่านั้น ห้ามคัดลอก `.env.example`
ไปใช้โดยตรง และต้องกำหนดอย่างน้อย:

```ini
APP_ENV=production
APP_DEBUG=false
APP_URL=https://rayongcoop.tomvisolution.tech
FEATURE_MEMBER_PORTAL=false
FEATURE_MEMBER_LOGIN=false
FEATURE_MEMBER_ESERVICE=false
FEATURE_MEMBER_FINANCIAL_DATA=false
VITE_FEATURE_MEMBER_PORTAL=false
APP_KEY=<unique-secret>
SESSION_SECURE=true
INITIAL_ADMIN_PASSWORD=<unique-password-at-least-16-characters>
```

ให้รัน `php bin/console db:migrate` ก่อน `php bin/console db:seed` เพื่อสร้างตาราง
rate limit และโครงสร้างข้อมูลที่จำเป็นทั้งหมด

---

## 6. การตั้งค่า Cron Jobs สำหรับงานอัตโนมัติ
เปิด Crontab ของผู้ใช้ `www-data`:
```bash
sudo crontab -u www-data -e
```

เพิ่มคำสั่ง:
```cron
# รันงานตรวจสอบวันหมดอายุของ Popup และ Announcement ทุก 10 นาที
*/10 * * * * php /var/www/rayongcoop/bin/console cron:run >> /var/www/rayongcoop/storage/logs/cron.log 2>&1

# สำรองฐานข้อมูลอัตโนมัติทุกวันเวลา 01:00 น.
0 1 * * * php /var/www/rayongcoop/bin/console backup:run >> /var/www/rayongcoop/storage/logs/backup.log 2>&1
```

---

## 7. แผนการ Rollback (Rollback Protocol)
1. สลับ Git Branch / Commit กลับไปยังเวอร์ชันก่อนหน้า:
   ```bash
   git reset --hard <PREVIOUS_TAG>
   composer install --no-dev --optimize-autoloader
   ```
2. กู้คืนฐานข้อมูลจากไฟล์สำรองใน `/var/www/rayongcoop/storage/backups/`:
   ```bash
   mysql -u root -p rayongcoop_db < /var/www/rayongcoop/storage/backups/backup_YYYY_MM_DD.sql
   ```
3. เคลียร์ PHP-FPM Cache:
   ```bash
   sudo systemctl restart php8.4-fpm
   ```
