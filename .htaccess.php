# Develop by ครูโต้ง | hAcKEdpRO Pongwattana Suebsing
# Edit by 24 ตุลาคม 2568 22:48 น. จำนวน 50 บรรทัด
# .htaccess หลักสำหรับความปลอดภัย (Security Hardening)

# --- 1. ป้องกัน Directory Listing ---
# ปิดการแสดงรายการไฟล์ในโฟลเดอร์
Options -Indexes

# --- 2. การกำหนดหน้า Error ---
ErrorDocument 403 /learn/index.php
ErrorDocument 404 /learn/index.php

# --- 3. การตั้งค่าเริ่มต้น ---
DirectoryIndex index.php

<IfModule mod_rewrite.c>
    RewriteEngine On

    # --- 4. บังคับใช้ HTTPS (ถ้าใช้งานจริงบน Production) ---
    # RewriteCond %{HTTPS} off
    # RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]

    # --- 5. ป้องกันการเข้าถึงไฟล์สำคัญ ---
    # ป้องกันการเข้าถึงไฟล์ .htaccess, .htpasswd
    <Files ~ "^\.ht">
        Require all denied
    </Files>
    # ป้องกันการเข้าถึงไฟล์ config
    <Files "config.php">
        Require all denied
    </Files>

</IfModule>

# --- 6. Security Headers ---
# (สำคัญมากสำหรับ Production)
<IfModule mod_headers.c>
    # ป้องกัน Clickjacking
    Header always set X-Frame-Options "SAMEORIGIN"
    
    # ป้องกันการเดา MIME type
    Header always set X-Content-Type-Options "nosniff"
    
    # Referrer Policy
    Header always set Referrer-Policy "strict-origin-when-cross-origin"
    
    # Content Security Policy (CSP) - (นี่คือตัวอย่างแบบง่าย)
    # **ข้อควรระวัง:** ต้องเพิ่ม 'https://www.youtube.com' 'https://i.ytimg.com' 'https://s.ytimg.com' 
    # และ 'https://fonts.googleapis.com' 'https://fonts.gstatic.com' เพื่อให้ระบบทำงานได้
    Header set Content-Security-Policy "default-src 'self' https://fonts.googleapis.com https://fonts.gstatic.com; script-src 'self' 'unsafe-inline' https://www.youtube.com https://s.ytimg.com; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; frame-src 'self' https://www.youtube.com; img-src 'self' data: https:; font-src 'self' https://fonts.gstatic.com;"
    
    # HSTS (ถ้าใช้ HTTPS)
    # Header always set Strict-Transport-Security "max-age=31536000; includeSubDomains"
</IfModule>

# --- 7. การตั้งค่าการอัปโหลด (ถ้าจำเป็น) ---
# อาจจะต้องตั้งค่าขนาดไฟล์สูงสุดที่อัปโหลดได้ (เช่น 100M สำหรับวิดีโอ)
# php_value upload_max_filesize 100M
# php_value post_max_size 100M