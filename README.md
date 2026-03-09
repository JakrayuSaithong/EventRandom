# Random Picker (ระบบสุ่มรางวัล)

ระบบสุ่มรางวัลผ่านเว็บไซต์ที่ออกแบบตามหลัก Material Design 3 (M3) และรองรับการทำงานแยกหน้าจอ (Admin, Remote, Display) เพื่อความสะดวกในการจัดการงานอีเวนต์

## โครงสร้างและการออกแบบระบบ (System Design)

ระบบถูกออกแบบให้แยกหน้าที่การทำงาน (Separation of Concerns) อย่างชัดเจนผ่าน 3 หน้าจอหลัก ได้แก่:

### 1. Admin Panel (`admin.php`)
เป็นส่วนสำหรับผู้ดูแลระบบในการจัดการข้อมูลทั้งหมด:
- **Event Management:** สร้าง แก้ไข และลบรายการกิจกรรมสุ่มรางวัล
- **Participants Management:** เพิ่ม ลบ และตัดสิทธิ์ (Disqualify) รายชื่อผู้เข้าร่วมที่หน้าสัมผัส (UI)
- **Prize Management:** เพิ่มและจัดการของรางวัล พร้อมกำหนดจำนวน
- **Winner Tracking:** ตรวจสอบรายชื่อผู้โชคดีทั้งหมด

### 2. Remote Control (`remote.php`)
ทำหน้าที่เป็นรีโมทสั่งการ หรือตัวควบคุม (Controller) สำหรับผู้ดำเนินรายการ:
- เลือกกิจกรรมและรางวัลที่ต้องการสุ่ม
- ตั้งค่าจำนวนคนที่ต้องการสุ่มในแต่ละรอบ (1 คน หรือหลายคนพร้อมกัน)
- กดเริ่มสุ่ม (Draw) ซึ่งจะส่งคำสั่งไปยังหน้า Display ตามเวลาจริง (Real-time) ผ่านระบบ Database Polling (Ajax)

### 3. Display Screen (`display.php`)
หน้าจอสำหรับแสดงผลขึ้นจอใหญ่ (Projector / LED Screen) ระหว่างจัดกิจกรรม:
- รับคำสั่งจาก Remote อัตโนมัติ (Listener)
- แสดงผลการสุ่มด้วยแอนิเมชัน (Motion Design) เช่น Slot Animation, การหมุน, และ Confetti Effect
- ออกแบบโดยอิงแนวคิด "Motion guides attention" ทำให้ผู้ชมตื่นเต้นและโฟกัสที่ชื่อผู้โชคดี 

## เทคโนโลยีสแตค (Tech Stack)
- **Frontend:** HTML5, CSS3 (Vanilla + CSS Variables), Javascript (ES6+)
- **Backend:** PHP รองรับการทำงานแบบ Session และ RESTful-like API ผ่านโฟลเดอร์ `ajax/`
- **Database:** SQL Server (ผ่าน `sqlsrv`)
- **UI & UX:** Material Design 3, SweetAlert2 (แจ้งเตือน), FontAwesome 6 (ไอคอน)

## โฟลว์การทำงาน (Workflow)
1. **Login:** ผู้ใช้เข้าสู่ระบบและยืนยันตัวตนผ่าน `index.php` โดยรับ Token ที่ถูกเข้ารหัส (`$_GET['DataE']`) เพื่อความปลอดภัย
2. **Setup:** Admin ทำการตั้งค่ารายการ, ใส่รายชื่อผู้มีสิทธิ์, และกำหนดของรางวัลผ่านหน้า `admin.php`
3. **Execution:** ผู้ควบคุมเปิด `display.php` ขึ้นจอใหญ่ และใช้ `remote.php` ในการเลือกรางวัลและสั่งสุ่ม
4. **Trigger:** `remote.php` จะเรียก API เพื่อสุ่มรายชื่อจากฐานข้อมูล บันทึกสถานะ และส่ง Signalling ไปยังหน้ากาก Display
5. **Animation:** `display.php` ตรวจพบสถานะใหม่ จะเล่นแอนิเมชันเปิดตัวผู้โชคดี
