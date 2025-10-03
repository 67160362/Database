# PHP Responsive Login (MySQLi + Bootstrap 5)

โครงตัวอย่างหน้า Login แบบ Responsive ด้วย **PHP (MySQLi)** + **Bootstrap 5** พร้อม Session, CSRF, และการแฮชรหัสผ่าน

## ไฟล์ในโปรเจกต์
- `config_mysqli.php` – ตั้งค่าเชื่อมต่อฐานข้อมูลและเริ่ม session
- `csrf.php` – ฟังก์ชัน CSRF token
- `login.php` – หน้าแบบฟอร์ม (responsive)
- `login_process.php` – ตรวจผู้ใช้/รหัสผ่าน (prepared statements)
- `dashboard.php` – หน้าหลังล็อกอิน (ต้องมี session)
- `logout.php` – ออกจากระบบ
- `users.sql` – สร้างตารางผู้ใช้
- `make_user.php` – สร้าง user ตัวอย่าง (password แบบแฮช)

## วิธีใช้งาน
1) สร้างฐานข้อมูลชื่อ `myapp` ใน MySQL
2) รัน `users.sql` เพื่อสร้างตาราง `users`
3) เปิดและแก้ไขค่าการเชื่อมต่อใน `config_mysqli.php` ให้ตรงกับเครื่อง
4) รัน `make_user.php` หนึ่งครั้งเพื่อสร้างผู้ใช้ตัวอย่าง:
   - อีเมล: `demo@example.com`
   - รหัสผ่าน: `Password123!`
5) เปิด `http://localhost/login.php` แล้วทดสอบล็อกอิน

> โปรดเปิด HTTPS ในโปรดักชัน และพิจารณาเพิ่ม rate limiting/CAPTCHA, lockout ชั่วคราว และ Content Security Policy (CSP)
