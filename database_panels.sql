-- =====================================================================
--  Avatar Electric - Panel / Cabinet tracking
--  Run AFTER database.sql (+ database_overview.sql)
--  mysql -u root avatar_schedule < database_panels.sql
--
--  This REPLACES any previous project_panels table with the richer
--  per-cabinet tracking schema.
-- =====================================================================

USE `avatar_schedule`;

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS `project_panels`;
SET FOREIGN_KEY_CHECKS = 1;

CREATE TABLE `project_panels` (
  `id`                    INT AUTO_INCREMENT PRIMARY KEY,
  `project_id`            INT UNSIGNED NOT NULL,
  `panel_no`              VARCHAR(100),
  `panel_name`            VARCHAR(255),
  `panel_type`            VARCHAR(50),
  `panel_size`            VARCHAR(100),
  `delivery_group`        VARCHAR(50),
  `target_delivery_date`  DATE,
  `actual_delivery_date`  DATE,
  `status` ENUM(
    'pending',
    'design',
    'material',
    'production',
    'wiring',
    'qc',
    'ready_delivery',
    'delivered',
    'overdue'
  ) DEFAULT 'pending',
  `status_mode`           ENUM('AUTO','MANUAL') NOT NULL DEFAULT 'AUTO',
  `manual_status`         VARCHAR(80) NULL DEFAULT NULL,
  `progress_percent`      INT DEFAULT 0,
  `responsible`           VARCHAR(100),
  `remark`                TEXT,
  `sort_order`            INT DEFAULT 0,
  `created_at`            TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at`            TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY `idx_panel_prj` (`project_id`),
  KEY `idx_panel_status` (`status`),
  KEY `idx_panel_group` (`delivery_group`),
  CONSTRAINT `fk_panel_prj2` FOREIGN KEY (`project_id`)
    REFERENCES `projects`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- =====================================================================
--  Sample panels  (progress_percent follows the status->percent map)
-- =====================================================================
INSERT INTO `project_panels`
 (`project_id`,`panel_no`,`panel_name`,`panel_type`,`panel_size`,`delivery_group`,
  `target_delivery_date`,`actual_delivery_date`,`status`,`progress_percent`,`responsible`,`remark`,`sort_order`) VALUES
-- Project 1 (all delivered)
 (1,'AV-P1-01','ตู้ MDB หลัก','MDB','2000x800x600','A','2026-03-15','2026-03-14','delivered',100,'สมชาย วัฒนกุล','ทดสอบผ่าน',1),
 (1,'AV-P1-02','ตู้ DB ชั้นการผลิต 1','DB','1800x600x400','A','2026-03-15','2026-03-14','delivered',100,'สมชาย วัฒนกุล','',2),
 (1,'AV-P1-03','ตู้ DB ชั้นการผลิต 2','DB','1800x600x400','A','2026-03-20','2026-03-20','delivered',100,'กฤษณะ มหาชัย','',3),
 (1,'AV-P1-04','ตู้ Capacitor Bank','CAP','1600x600x400','B','2026-03-25','2026-03-24','delivered',100,'สมชาย วัฒนกุล','PF 0.98',4),
 (1,'AV-P1-05','ตู้ Control Grounding','CTRL','800x600x300','B','2026-03-28','2026-03-28','delivered',100,'กฤษณะ มหาชัย','',5),
-- Project 2 (mixed; P2-05 overdue)
 (2,'AV-P2-01','ตู้ PLC Main','PLC','2000x800x600','A','2026-04-10','2026-04-09','delivered',100,'อรพรรณ ศรีสุข','',1),
 (2,'AV-P2-02','ตู้ Remote I/O #1','RIO','1600x600x400','A','2026-06-20',NULL,'qc',85,'นภัสสร โพธิ์ทอง','รอผล QC',2),
 (2,'AV-P2-03','ตู้ Remote I/O #2','RIO','1600x600x400','A','2026-06-25',NULL,'wiring',65,'นภัสสร โพธิ์ทอง','',3),
 (2,'AV-P2-04','ตู้ HMI Operator','HMI','1200x800x400','B','2026-07-05',NULL,'production',45,'อรพรรณ ศรีสุข','',4),
 (2,'AV-P2-05','ตู้ Power Supply','PWR','1400x600x400','B','2026-05-20',NULL,'material',25,'นภัสสร โพธิ์ทอง','รออุปกรณ์นำเข้า (เลยกำหนด)',5),
 (2,'AV-P2-06','ตู้ Network Switch','NET','800x600x300','B','2026-07-15',NULL,'design',10,'อรพรรณ ศรีสุข','',6),
-- Project 3
 (3,'AV-P3-01','String Combiner Box A','COMB','600x400x200','A','2026-04-25','2026-04-24','delivered',100,'ธนกร ไพศาล','',1),
 (3,'AV-P3-02','String Combiner Box B','COMB','600x400x200','A','2026-04-25','2026-04-25','delivered',100,'ธนกร ไพศาล','',2),
 (3,'AV-P3-03','ตู้ Inverter Station 1','INV','2000x800x600','B','2026-06-10',NULL,'ready_delivery',95,'ธนกร ไพศาล','พร้อมส่ง',3),
 (3,'AV-P3-04','ตู้ Inverter Station 2','INV','2000x800x600','B','2026-06-15',NULL,'wiring',65,'ธนกร ไพศาล','',4),
 (3,'AV-P3-05','ตู้ AC Distribution','ACDB','1800x800x500','C','2026-06-20',NULL,'production',45,'ธนกร ไพศาล','',5),
-- Project 6
 (6,'AV-P6-01','ตู้จ่ายไฟ EV Main','PWR','2000x800x600','A','2026-06-20',NULL,'production',45,'ธนกร ไพศาล','',1),
 (6,'AV-P6-02','ตู้ DC Charger Control','CTRL','1600x600x400','A','2026-07-10',NULL,'material',25,'ธนกร ไพศาล','รอนำเข้า',2),
 (6,'AV-P6-03','ตู้ AC Charger Control','CTRL','1600x600x400','B','2026-07-20',NULL,'pending',0,'ธนกร ไพศาล','',3),
-- Project 9 (18 panels -> tests auto-pagination on the overview image)
 (9,'AV-P9-01','ตู้ BAS Main Controller','BAS','2000x800x600','A','2026-05-10','2026-05-09','delivered',100,'นภัสสร โพธิ์ทอง','',1),
 (9,'AV-P9-02','ตู้ DDC ชั้น 1','DDC','1200x600x400','A','2026-05-15','2026-05-15','delivered',100,'นภัสสร โพธิ์ทอง','',2),
 (9,'AV-P9-03','ตู้ DDC ชั้น 2','DDC','1200x600x400','A','2026-06-20',NULL,'qc',85,'นภัสสร โพธิ์ทอง','',3),
 (9,'AV-P9-04','ตู้ DDC ชั้น 3','DDC','1200x600x400','A','2026-06-22',NULL,'wiring',65,'นภัสสร โพธิ์ทอง','',4),
 (9,'AV-P9-05','ตู้ควบคุมแอร์ AHU-1','HVAC','1600x600x400','B','2026-06-25',NULL,'production',45,'อรพรรณ ศรีสุข','',5),
 (9,'AV-P9-06','ตู้ควบคุมแอร์ AHU-2','HVAC','1600x600x400','B','2026-06-28',NULL,'production',45,'อรพรรณ ศรีสุข','',6),
 (9,'AV-P9-07','ตู้ควบคุมปั๊มน้ำ','PUMP','1400x600x400','B','2026-07-01',NULL,'wiring',65,'อรพรรณ ศรีสุข','',7),
 (9,'AV-P9-08','ตู้ Fire Integration','FIRE','1200x600x400','B','2026-07-03',NULL,'qc',85,'นภัสสร โพธิ์ทอง','',8),
 (9,'AV-P9-09','ตู้ Lighting Control 1','LTG','1000x600x300','C','2026-06-30',NULL,'material',25,'อรพรรณ ศรีสุข','',9),
 (9,'AV-P9-10','ตู้ Lighting Control 2','LTG','1000x600x300','C','2026-07-05',NULL,'design',10,'อรพรรณ ศรีสุข','',10),
 (9,'AV-P9-11','ตู้ Access Control','ACS','800x600x300','C','2026-07-10',NULL,'pending',0,'นภัสสร โพธิ์ทอง','',11),
 (9,'AV-P9-12','ตู้ CCTV Power','CCTV','1000x600x300','C','2026-07-12',NULL,'production',45,'นภัสสร โพธิ์ทอง','',12),
 (9,'AV-P9-13','ตู้ Energy Meter','MTR','800x600x300','D','2026-07-15',NULL,'wiring',65,'อรพรรณ ศรีสุข','',13),
 (9,'AV-P9-14','ตู้ UPS Distribution','UPS','1600x800x500','D','2026-07-18',NULL,'qc',85,'อรพรรณ ศรีสุข','',14),
 (9,'AV-P9-15','ตู้ Server Room PDU','PDU','1800x800x600','D','2026-07-20',NULL,'ready_delivery',95,'นภัสสร โพธิ์ทอง','พร้อมส่ง',15),
 (9,'AV-P9-16','ตู้ Generator Control','GEN','1600x600x400','D','2026-05-30',NULL,'material',25,'อรพรรณ ศรีสุข','เลยกำหนด',16),
 (9,'AV-P9-17','ตู้ Weather Station','WS','600x400x200','D','2026-07-25',NULL,'design',10,'นภัสสร โพธิ์ทอง','',17),
 (9,'AV-P9-18','ตู้ Spare/Future','SPARE','1200x600x400','D','2026-08-01',NULL,'pending',0,'อรพรรณ ศรีสุข','',18);

-- ---------------------------------------------------------------------
--  Sync each project's overall progress from the average of its panels
--  (the app also recomputes this automatically on any panel change)
-- ---------------------------------------------------------------------
UPDATE `projects` p
JOIN (
    SELECT project_id, ROUND(AVG(progress_percent)) AS avg_p
    FROM `project_panels` GROUP BY project_id
) x ON x.project_id = p.id
SET p.progress = x.avg_p;

-- end of file
