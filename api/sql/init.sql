-- =============================================================
-- 安全阀检验数字管理平台 数据库初始化脚本
-- phpstudy：可在 phpMyAdmin 中导入本文件，或执行：
--   mysql -uroot -proot < init.sql
-- =============================================================

CREATE DATABASE IF NOT EXISTS `inspection` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE `inspection`;

-- 系统账号表
CREATE TABLE IF NOT EXISTS `sys_user` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `account` VARCHAR(50) NOT NULL UNIQUE COMMENT '登录账号',
  `password` CHAR(32) NOT NULL COMMENT '密码 md5',
  `name` VARCHAR(50) NOT NULL COMMENT '姓名',
  `phone` VARCHAR(20) DEFAULT '' COMMENT '手机号',
  `unit_name` VARCHAR(150) DEFAULT '' COMMENT '单位名称',
  `site` VARCHAR(255) DEFAULT '' COMMENT '站点(逗号分隔)',
  `identity` VARCHAR(20) DEFAULT '检验人员' COMMENT '身份：管理员/录入人员/检验人员/审核员/打印人员/录入审核',
  `permissions` TEXT COMMENT '权限标识，逗号分隔，*为全部权限（role_id 为 0 时生效）',
  `role_id` INT NOT NULL DEFAULT 0 COMMENT '关联角色，0=使用自身 permissions',
  `sign` VARCHAR(255) DEFAULT '' COMMENT '签名图片路径',
  `status` TINYINT DEFAULT 1 COMMENT '1启用 0停用',
  `last_time` DATETIME DEFAULT NULL COMMENT '最后登录时间',
  `last_ip` VARCHAR(50) DEFAULT '' COMMENT '最后登录IP',
  `login_token` VARCHAR(64) DEFAULT '' COMMENT '登录token',
  `token_expire` INT DEFAULT 0 COMMENT 'token过期时间戳',
  `is_del` TINYINT DEFAULT 0,
  `create_time` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='系统账号';

-- C端校验设备操作员（与B端sys_user完全隔离，仅能登录C端接口）
CREATE TABLE IF NOT EXISTS `c_user` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `account` VARCHAR(30) NOT NULL UNIQUE COMMENT '登录账号',
  `password` CHAR(32) NOT NULL COMMENT '密码 md5',
  `name` VARCHAR(50) DEFAULT '' COMMENT '姓名（作为工单校验员）',
  `phone` VARCHAR(20) DEFAULT '' COMMENT '手机号',
  `device_sn` VARCHAR(50) DEFAULT '' COMMENT '绑定校验设备编号',
  `status` TINYINT DEFAULT 1 COMMENT '1启用 0停用',
  `fail_count` TINYINT DEFAULT 0 COMMENT '连续密码错误次数',
  `lock_until` INT DEFAULT 0 COMMENT '锁定截止时间戳',
  `login_token` VARCHAR(64) DEFAULT '' COMMENT '登录token',
  `token_expire` INT DEFAULT 0 COMMENT 'token过期时间戳',
  `last_time` DATETIME DEFAULT NULL COMMENT '最后登录时间',
  `last_ip` VARCHAR(50) DEFAULT '' COMMENT '最后登录IP',
  `is_del` TINYINT DEFAULT 0,
  `create_time` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='C端校验设备操作员';

-- 使用单位表
CREATE TABLE IF NOT EXISTS `unit` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_name` VARCHAR(50) DEFAULT '' COMMENT '姓名',
  `mobile` VARCHAR(20) DEFAULT '' COMMENT '手机号',
  `name` VARCHAR(150) NOT NULL COMMENT '单位名称',
  `identity` VARCHAR(30) DEFAULT '送检单位' COMMENT '身份',
  `password` VARCHAR(64) DEFAULT '' COMMENT '登录密码(md5)',
  `address` VARCHAR(255) DEFAULT '' COMMENT '单位地址',
  `client` VARCHAR(50) DEFAULT '' COMMENT '联系人',
  `client_tel` VARCHAR(20) DEFAULT '' COMMENT '联系电话',
  `entrust_client` VARCHAR(50) DEFAULT '' COMMENT '委托人',
  `entrust_tel` VARCHAR(20) DEFAULT '' COMMENT '委托人电话',
  `bank_name` VARCHAR(100) DEFAULT '' COMMENT '开户行',
  `apply_unit` VARCHAR(150) DEFAULT '' COMMENT '申请单位',
  `tax_no` VARCHAR(50) DEFAULT '' COMMENT '纳税人识别号',
  `last_login_time` DATETIME NULL COMMENT '最后登录时间',
  `last_login_ip` VARCHAR(50) DEFAULT '' COMMENT '最后登录IP',
  `is_del` TINYINT DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='使用单位';

-- 安全阀库
CREATE TABLE IF NOT EXISTS `safety_valve` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `equip_type` VARCHAR(30) DEFAULT '安全阀' COMMENT '设备类型',
  `protected_equip_type` VARCHAR(30) DEFAULT '' COMMENT '被保护设备种类',
  `working_media` VARCHAR(30) DEFAULT '' COMMENT '工作介质',
  `set_press` DECIMAL(6,2) DEFAULT NULL COMMENT '要求整定压力MPa',
  `working_press` DECIMAL(6,2) DEFAULT NULL COMMENT '工作压力MPa',
  `design_press` DECIMAL(6,2) DEFAULT NULL COMMENT '设计压力MPa',
  `working_temp` VARCHAR(30) DEFAULT '常温' COMMENT '工作温度',
  `tag_no` VARCHAR(50) DEFAULT '' COMMENT '安装位置',
  `id_no` VARCHAR(50) DEFAULT '' COMMENT '设备代码',
  `vavle_model` VARCHAR(80) DEFAULT '' COMMENT '安全阀型号',
  `vavle_type` VARCHAR(50) DEFAULT '' COMMENT '安全阀类型',
  `manufacturer` VARCHAR(150) DEFAULT '' COMMENT '制造单位',
  `license_no` VARCHAR(80) DEFAULT '' COMMENT '制造许可证号',
  `diameter_nominal` INT DEFAULT NULL COMMENT '公称通径mm',
  `seat_size` INT DEFAULT NULL COMMENT '流道直径mm',
  `press_nominal` DECIMAL(6,2) DEFAULT NULL COMMENT '公称压力MPa',
  `press_class_range_min` DECIMAL(6,2) DEFAULT NULL COMMENT '压力级别范围起',
  `press_class_range_max` DECIMAL(6,2) DEFAULT NULL COMMENT '压力级别范围止',
  `manu_date` VARCHAR(20) DEFAULT '' COMMENT '出厂日期',
  `manu_no` VARCHAR(50) DEFAULT '' COMMENT '出厂编号',
  `tightness_press` DECIMAL(6,2) DEFAULT NULL COMMENT '密封试验压力MPa',
  `press_correction` VARCHAR(50) DEFAULT '无' COMMENT '背压修正',
  `is_new` VARCHAR(4) DEFAULT '是' COMMENT '是否新阀',
  `is_removed_cleaning` VARCHAR(4) DEFAULT '是' COMMENT '旧阀是否拆卸清洗',
  `is_complete` VARCHAR(4) DEFAULT '是' COMMENT '阀体及零部件是否完整',
  `is_nameplate` VARCHAR(4) DEFAULT '是' COMMENT '是否有金属铭牌',
  `parity` VARCHAR(20) DEFAULT '离线校验' COMMENT '校验方式',
  `check_method` VARCHAR(100) DEFAULT NULL COMMENT '在线校验方法',
  `status` VARCHAR(10) DEFAULT '正常' COMMENT '状态',
  `unique_code` VARCHAR(50) DEFAULT '' COMMENT '唯一识别',
  `check_time` VARCHAR(20) DEFAULT '' COMMENT '检验时间',
  `next_check_time` VARCHAR(20) DEFAULT '' COMMENT '下次检验时间',
  `period_years` INT DEFAULT 1 COMMENT '周期(年)',
  `cust_name` VARCHAR(150) DEFAULT '' COMMENT '送检单位名称',
  `region` VARCHAR(100) DEFAULT '' COMMENT '区域',
  `create_time` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `is_del` TINYINT DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='安全阀库';

-- 委托单
CREATE TABLE IF NOT EXISTS `commission` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `code` VARCHAR(30) NOT NULL DEFAULT '' COMMENT '委托单号',
  `cust_id` INT DEFAULT NULL COMMENT '使用单位ID',
  `cust_name` VARCHAR(150) COMMENT '使用单位名称',
  `address` VARCHAR(255) DEFAULT '' COMMENT '使用单位地址',
  `client` VARCHAR(50) DEFAULT '' COMMENT '使用单位联系人',
  `client_tel` VARCHAR(20) DEFAULT '' COMMENT '使用单位联系电话',
  `applicant` VARCHAR(150) DEFAULT '' COMMENT '委托单位',
  `cust_address` VARCHAR(255) DEFAULT '' COMMENT '委托单位地址',
  `contact` VARCHAR(50) DEFAULT '' COMMENT '委托人',
  `contact_tel` VARCHAR(20) DEFAULT '' COMMENT '委托人联系电话',
  `exam_name` VARCHAR(150) DEFAULT '' COMMENT '检验单位',
  `plan_receiver_time` VARCHAR(30) DEFAULT '' COMMENT '预约取阀时间',
  `deliverer` VARCHAR(50) DEFAULT '' COMMENT '送阀人',
  `delivery_time` VARCHAR(30) DEFAULT '' COMMENT '送阀时间',
  `status` ENUM('sign','pending','approved','rejected') DEFAULT 'sign' COMMENT 'sign等待签名/pending等待预检/approved等待检验/rejected已退回',
  `reject_reason` VARCHAR(255) DEFAULT '' COMMENT '退回原因',
  `precheck` TINYINT DEFAULT 0 COMMENT '是否通过预检',
  `paid` TINYINT DEFAULT 0 COMMENT '收费状态 0待收费 1已收费',
  `total_price` DECIMAL(10,2) DEFAULT 0 COMMENT '总校验费',
  `repair_price` DECIMAL(10,2) DEFAULT 0 COMMENT '总维修费',
  `seller_price` DECIMAL(10,2) DEFAULT 0 COMMENT '总销售',
  `payable_amount` DECIMAL(10,2) DEFAULT 0 COMMENT '应开金额',
  `actual_amount` DECIMAL(10,2) DEFAULT 0 COMMENT '实开金额',
  `payment_method` VARCHAR(50) DEFAULT '' COMMENT '收款方式',
  `payment_date` DATE DEFAULT NULL COMMENT '收款日期',
  `billing_unit` VARCHAR(150) DEFAULT '' COMMENT '开票单位',
  `invoice_number` VARCHAR(100) DEFAULT '' COMMENT '发票号码',
  `invoice_date` DATE DEFAULT NULL COMMENT '开票日期',
  `seller` VARCHAR(50) DEFAULT '' COMMENT '销售',
  `overbilled_inspection` DECIMAL(10,2) DEFAULT 0 COMMENT '检验多开',
  `overbilled_seller` DECIMAL(10,2) DEFAULT 0 COMMENT '销售多开',
  `fee_note` VARCHAR(255) DEFAULT '' COMMENT '收费备注',
  `sign_token` VARCHAR(64) DEFAULT '' COMMENT '手机签名令牌',
  `sign_img` VARCHAR(255) DEFAULT '' COMMENT '手机签名图片路径',
  `sign_time` DATETIME DEFAULT NULL COMMENT '手机签名时间',
  `serial_no` VARCHAR(50) DEFAULT NULL COMMENT '开抽流水号（预检时录入，全局唯一）',
  `create_by` VARCHAR(50) DEFAULT '' COMMENT '创建人',
  `create_time` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `is_del` TINYINT DEFAULT 0,
  UNIQUE KEY `uk_code` (`code`),
  UNIQUE KEY `uk_serial_no` (`serial_no`),
  KEY `idx_status` (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='委托单';

-- 委托单安全阀清单（快照）
CREATE TABLE IF NOT EXISTS `commission_valve` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `commission_id` INT NOT NULL,
  `lib_id` INT DEFAULT 0 COMMENT '关联安全阀库id',
  `equip_type` VARCHAR(30) DEFAULT '安全阀',
  `protected_equip_type` VARCHAR(30) DEFAULT '',
  `working_media` VARCHAR(30) DEFAULT '',
  `set_press` DECIMAL(6,2) DEFAULT NULL,
  `working_press` DECIMAL(6,2) DEFAULT NULL,
  `design_press` DECIMAL(6,2) DEFAULT NULL,
  `working_temp` VARCHAR(30) DEFAULT '常温',
  `tag_no` VARCHAR(50) DEFAULT '',
  `id_no` VARCHAR(50) DEFAULT '',
  `vavle_model` VARCHAR(80) DEFAULT '',
  `vavle_type` VARCHAR(50) DEFAULT '',
  `manufacturer` VARCHAR(150) DEFAULT '',
  `license_no` VARCHAR(80) DEFAULT '',
  `diameter_nominal` INT DEFAULT NULL,
  `seat_size` INT DEFAULT NULL,
  `press_nominal` DECIMAL(6,2) DEFAULT NULL,
  `press_class_range_min` DECIMAL(6,2) DEFAULT NULL,
  `press_class_range_max` DECIMAL(6,2) DEFAULT NULL,
  `manu_date` VARCHAR(20) DEFAULT '',
  `manu_no` VARCHAR(50) DEFAULT '',
  `tightness_press` DECIMAL(6,2) DEFAULT NULL,
  `press_correction` VARCHAR(50) DEFAULT '无',
  `is_new` VARCHAR(4) DEFAULT '是',
  `is_removed_cleaning` VARCHAR(4) DEFAULT '是',
  `is_complete` VARCHAR(4) DEFAULT '是',
  `is_nameplate` VARCHAR(4) DEFAULT '是',
  `parity` VARCHAR(20) DEFAULT '离线校验',
  `check_method` VARCHAR(100) DEFAULT NULL COMMENT '在线校验方法',
  `period_years` INT DEFAULT 1 COMMENT '周期(年)',
  KEY `idx_commission` (`commission_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='委托单安全阀清单';

-- 检验工单（含安全阀快照）
CREATE TABLE IF NOT EXISTS `workorder` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `commission_id` INT NOT NULL COMMENT '所属委托单',
  `valve_id` INT NOT NULL DEFAULT 0 COMMENT '对应委托单安全阀清单ID（0=历史数据，按出厂编号回查）',
  `code` VARCHAR(30) DEFAULT '' COMMENT '委托单号',
  `order_code` VARCHAR(30) NOT NULL DEFAULT '' COMMENT '检验编号',
  `order_code2` VARCHAR(50) DEFAULT '' COMMENT '校验编号2',
  `sn` VARCHAR(50) DEFAULT '' COMMENT 'SN',
  `sn_code` VARCHAR(50) DEFAULT '' COMMENT 'sn_code',
  `cust_name` VARCHAR(150) DEFAULT '' COMMENT '使用单位',
  `client` VARCHAR(50) DEFAULT '' COMMENT '使用单位联系人',
  `client_tel` VARCHAR(20) DEFAULT '' COMMENT '使用单位联系电话',
  `status` ENUM('rejected','wait','inspection','audit','approval','print','done') DEFAULT 'wait' COMMENT 'rejected校验退回/wait待检验/inspection检验中/audit待审核/approval待审批/print待打印/done已完成',
  `reject_reason` VARCHAR(255) DEFAULT '' COMMENT '退回原因',
  `inspector` VARCHAR(50) DEFAULT '' COMMENT '校验员',
  `inspector_time` DATETIME DEFAULT NULL COMMENT '校验时间',
  `period_years` INT DEFAULT 1 COMMENT '周期(年)',
  `update_time` DATETIME DEFAULT CURRENT_TIMESTAMP COMMENT '状态时间',
  `create_time` DATETIME DEFAULT CURRENT_TIMESTAMP COMMENT '委托日期',
  `is_del` TINYINT DEFAULT 0,
  `equip_type` VARCHAR(30) DEFAULT '安全阀',
  `protected_equip_type` VARCHAR(30) DEFAULT '',
  `working_media` VARCHAR(30) DEFAULT '',
  `set_press` DECIMAL(6,2) DEFAULT NULL,
  `working_press` DECIMAL(6,2) DEFAULT NULL,
  `design_press` DECIMAL(6,2) DEFAULT NULL,
  `working_temp` VARCHAR(30) DEFAULT '常温',
  `tag_no` VARCHAR(50) DEFAULT '',
  `id_no` VARCHAR(50) DEFAULT '',
  `vavle_model` VARCHAR(80) DEFAULT '',
  `vavle_type` VARCHAR(50) DEFAULT '',
  `manufacturer` VARCHAR(150) DEFAULT '',
  `license_no` VARCHAR(80) DEFAULT '',
  `diameter_nominal` INT DEFAULT NULL,
  `seat_size` INT DEFAULT NULL,
  `press_nominal` DECIMAL(6,2) DEFAULT NULL,
  `press_class_range_min` DECIMAL(6,2) DEFAULT NULL,
  `press_class_range_max` DECIMAL(6,2) DEFAULT NULL,
  `manu_date` VARCHAR(20) DEFAULT '',
  `manu_no` VARCHAR(50) DEFAULT '',
  `tightness_press` DECIMAL(6,2) DEFAULT NULL,
  `press_correction` VARCHAR(50) DEFAULT '无',
  `is_new` VARCHAR(4) DEFAULT '是',
  `is_removed_cleaning` VARCHAR(4) DEFAULT '是',
  `is_complete` VARCHAR(4) DEFAULT '是',
  `is_nameplate` VARCHAR(4) DEFAULT '是',
  `parity` VARCHAR(20) DEFAULT '离线校验',
  `check_method` VARCHAR(100) DEFAULT NULL COMMENT '在线校验方法',
  KEY `idx_status` (`status`),
  KEY `idx_commission` (`commission_id`),
  KEY `idx_order_code` (`order_code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='检验工单';

-- 检验结果（与工单一对一）
CREATE TABLE IF NOT EXISTS `workorder_result` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `workorder_id` INT NOT NULL UNIQUE,
  `order_code` VARCHAR(30) DEFAULT '',
  `tightness_press` DECIMAL(6,2) COMMENT '要求密封压力',
  `set_press` DECIMAL(6,2) COMMENT '要求整定压力MPa',
  `test_media` VARCHAR(20) COMMENT '校验介质',
  `test_temprature` VARCHAR(20) COMMENT '校验介质温度',
  `parity` VARCHAR(20) COMMENT '校验方式',
  `visual_examination` VARCHAR(10) COMMENT '外观检查',
  `disassembly_inspection` VARCHAR(10) COMMENT '拆卸检查',
  `note` VARCHAR(255) COMMENT '备注',
  `pressure_gauge_num` VARCHAR(30) COMMENT '压力表编号',
  `pressure_gauge_lifespan` VARCHAR(20) COMMENT '压力表有效期',
  `pressure_gauge_num2` VARCHAR(30) COMMENT '压力表2编号',
  `pressure_gauge_lifespan2` VARCHAR(20) COMMENT '压力表2有效期',
  `pressure_gauge_accuracy` VARCHAR(20) COMMENT '压力表精度',
  `pressure_gauge_range` VARCHAR(20) COMMENT '压力表量程MPa',
  `device_model` VARCHAR(50) COMMENT '校验设备型号',
  `device_sn` VARCHAR(50) COMMENT '校验设备编号',
  `testing_pressure1` DECIMAL(6,2) COMMENT '整定压力1',
  `testing_pressure2` DECIMAL(6,2) COMMENT '整定压力2',
  `testing_pressure3` DECIMAL(6,2) COMMENT '整定压力3',
  `test_status` VARCHAR(20) COMMENT '校验结果：合格/维修后合格/不合格',
  `create_time` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='检验结果';

-- =============================================================
-- 种子数据
-- =============================================================

INSERT INTO `sys_user` (`account`,`password`,`name`,`phone`,`unit_name`,`identity`,`permissions`,`status`) VALUES
('admin',    MD5('admin123'), '系统管理员', '13800000001', '平台运营中心', '管理员', '*', 1),
('whyh_006', MD5('123456'),   '周宽',       '13800000002', '武汉翼华技术设备有限公司', '检验员',
 'dashboard,commission,commission:add,commission:add:submit,commission:add:valve,commission:list,commission:list:view,commission:list:print,commission:list:pending,workorder,workorder:view,workorder:reject,workorder:reject:resubmit,workorder:wait,workorder:wait:submit,workorder:inspection,workorder:inspection:result,workorder:audit,workorder:audit:pass,workorder:audit:reject,workorder:approval,workorder:approval:pass,workorder:approval:reject,workorder:print,workorder:print:print,workorder:print:export,workorder:all,workorder:all:print,workorder:all:export,system,system:user', 1);

INSERT INTO `unit` (`name`,`address`,`client`,`client_tel`) VALUES
('武汉翼华技术设备有限公司','武汉东湖高新区光谷大道1号','刘建国','13970000001'),
('武汉经发粮食物流产业投资有限公司','武汉市黄陂区临空产业园2号','陈明华','13970000002'),
('武汉欣达康源装备有限公司','武汉市蔡甸区工业园3号','张丽','13970000003'),
('河北首衡商业管理有限公司孝感分公司','孝感市孝南区4号','王海涛','13970000004'),
('湖北东霖新材料有限公司','孝感市高新区5号','赵国庆','13970000005'),
('武汉环投千子山环境产业有限公司','武汉市江夏区6号','钱进','13970000006'),
('华润武钢总医院','武汉市青山区7号','孙梅','13970000007'),
('公安县兴成燃气有限公司','荆州市公安县8号','周军','13970000008'),
('武汉联德化学品有限公司','武汉市化工区9号','吴霞','13970000009'),
('武汉中车长客轨道车辆有限公司','武汉市江夏区10号','郑涛','13970000010');

INSERT INTO `safety_valve`
(`equip_type`,`protected_equip_type`,`working_media`,`set_press`,`working_press`,`design_press`,`working_temp`,`tag_no`,`id_no`,`vavle_model`,`vavle_type`,`manufacturer`,`license_no`,`diameter_nominal`,`seat_size`,`press_nominal`,`press_class_range_min`,`press_class_range_max`,`manu_date`,`manu_no`,`tightness_press`,`parity`) VALUES
('安全阀','压力容器','蒸汽',1.60,1.30,2.00,'180℃','WH-01','RQ2100001','A48Y-16C DN50','弹簧全启式安全阀','上海阀门厂股份有限公司','TS2710001-2024',50,32,1.60,1.30,1.80,'2024-01-15','CC240001',1.44,'离线校验'),
('安全阀','压力容器','压缩空气',1.27,1.00,1.60,'常温','WH-02','RQ2100002','A47H-16C DN40','弹簧微启式安全阀','浙江超达阀门股份有限公司','TS2710002-2024',40,26,1.60,1.00,1.50,'2024-03-15','CC240002',1.14,'离线校验'),
('安全阀','锅炉','蒸汽',2.20,1.90,2.50,'220℃','WH-03','RQ2100003','A42Y-25C DN32','全启式安全阀','苏州纽威阀门股份有限公司','TS2710003-2024',32,20,2.50,1.90,2.40,'2023-09-15','CC240003',1.98,'离线校验'),
('安全阀','压力管道','天然气',0.80,0.50,1.00,'常温','WH-04','RQ2100004','DA22Y-25 DN25','先导式安全阀','上海上螺阀门有限公司','TS2710004-2024',25,40,4.00,0.50,1.00,'2024-06-15','CC240004',0.72,'在线校验');

-- 委托单1：已通过（生成工单）
INSERT INTO `commission`
(`code`,`cust_id`,`cust_name`,`address`,`client`,`client_tel`,`applicant`,`cust_address`,`contact`,`contact_tel`,`exam_name`,`plan_receiver_time`,`deliverer`,`delivery_time`,`status`,`precheck`,`paid`,`create_by`,`create_time`) VALUES
('WT2026301',2,'武汉经发粮食物流产业投资有限公司','武汉市黄陂区临空产业园2号','陈明华','13970000002','武汉经发粮食物流产业投资有限公司','武汉市黄陂区临空产业园2号','陈明华','13970000002','武汉翼华技术设备有限公司','2026-01-10 09:30','陈明华','2026-01-10 09:30 09:30','approved',1,1,'周宽','2026-01-08 10:10:00');

INSERT INTO `commission_valve`
(`commission_id`,`equip_type`,`protected_equip_type`,`working_media`,`set_press`,`working_press`,`design_press`,`working_temp`,`tag_no`,`id_no`,`vavle_model`,`vavle_type`,`manufacturer`,`license_no`,`diameter_nominal`,`seat_size`,`press_nominal`,`press_class_range_min`,`press_class_range_max`,`manu_date`,`manu_no`,`tightness_press`,`parity`) VALUES
(1,'安全阀','压力容器','蒸汽',1.60,1.30,2.00,'180℃','JR-01','RQ2100011','A48Y-16C DN50','弹簧全启式安全阀','上海阀门厂股份有限公司','TS2710011-2024',50,32,1.60,1.30,1.80,'2024-01-15','CC240011',1.44,'离线校验'),
(1,'安全阀','压力容器','压缩空气',1.27,1.00,1.60,'常温','JR-02','RQ2100012','A47H-16C DN40','弹簧微启式安全阀','浙江超达阀门股份有限公司','TS2710012-2024',40,26,1.60,1.00,1.50,'2024-03-15','CC240012',1.14,'离线校验');

INSERT INTO `workorder`
(`commission_id`,`code`,`order_code`,`cust_name`,`client`,`client_tel`,`status`,`update_time`,`create_time`,`equip_type`,`protected_equip_type`,`working_media`,`set_press`,`working_press`,`design_press`,`working_temp`,`tag_no`,`id_no`,`vavle_model`,`vavle_type`,`manufacturer`,`license_no`,`diameter_nominal`,`seat_size`,`press_nominal`,`press_class_range_min`,`press_class_range_max`,`manu_date`,`manu_no`,`tightness_press`,`parity`) VALUES
(1,'WT2026301','JY20260001','武汉经发粮食物流产业投资有限公司','陈明华','13970000002','wait','2026-01-09 14:30:00','2026-01-08 10:10:00','安全阀','压力容器','蒸汽',1.60,1.30,2.00,'180℃','JR-01','RQ2100011','A48Y-16C DN50','弹簧全启式安全阀','上海阀门厂股份有限公司','TS2710011-2024',50,32,1.60,1.30,1.80,'2024-01-15','CC240011',1.44,'离线校验'),
(1,'WT2026301','JY20260002','武汉经发粮食物流产业投资有限公司','陈明华','13970000002','inspection','2026-01-09 14:40:00','2026-01-08 10:10:00','安全阀','压力容器','压缩空气',1.27,1.00,1.60,'常温','JR-02','RQ2100012','A47H-16C DN40','弹簧微启式安全阀','浙江超达阀门股份有限公司','TS2710012-2024',40,26,1.60,1.00,1.50,'2024-03-15','CC240012',1.14,'离线校验');

-- 委托单2：待审核（无工单）
INSERT INTO `commission`
(`code`,`cust_id`,`cust_name`,`address`,`client`,`client_tel`,`applicant`,`cust_address`,`contact`,`contact_tel`,`exam_name`,`plan_receiver_time`,`deliverer`,`delivery_time`,`status`,`create_by`,`create_time`) VALUES
('WT2026302',3,'武汉欣达康源装备有限公司','武汉市蔡甸区工业园3号','张丽','13970000003','武汉欣达康源装备有限公司','武汉市蔡甸区工业园3号','张丽','13970000003','武汉翼华技术设备有限公司','2026-01-20 09:30','张丽','2026-01-20 09:30','pending','周宽','2026-01-12 11:00:00');

INSERT INTO `commission_valve`
(`commission_id`,`equip_type`,`protected_equip_type`,`working_media`,`set_press`,`working_press`,`design_press`,`working_temp`,`tag_no`,`id_no`,`vavle_model`,`vavle_type`,`manufacturer`,`license_no`,`diameter_nominal`,`seat_size`,`press_nominal`,`press_class_range_min`,`press_class_range_max`,`manu_date`,`manu_no`,`tightness_press`,`parity`) VALUES
(2,'安全阀','锅炉','蒸汽',2.20,1.90,2.50,'220℃','XC-01','RQ2100021','A42Y-25C DN32','全启式安全阀','苏州纽威阀门股份有限公司','TS2710021-2024',32,20,2.50,1.90,2.40,'2023-09-15','CC240021',1.98,'离线校验');

-- 委托单3：已退回（工单在校验退回）
INSERT INTO `commission`
(`code`,`cust_id`,`cust_name`,`address`,`client`,`client_tel`,`applicant`,`cust_address`,`contact`,`contact_tel`,`exam_name`,`plan_receiver_time`,`deliverer`,`delivery_time`,`status`,`reject_reason`,`create_by`,`create_time`) VALUES
('WT2026303',6,'武汉环投千子山环境产业有限公司','武汉市江夏区6号','钱进','13970000006','武汉环投千子山环境产业有限公司','武汉市江夏区6号','钱进','13970000006','武汉翼华技术设备有限公司','2026-01-18 09:30','钱进','2026-01-18 09:30','rejected','委托单信息填写不完整，请补充设备代码后重新提交','周宽','2026-01-11 15:20:00');

INSERT INTO `commission_valve`
(`commission_id`,`equip_type`,`protected_equip_type`,`working_media`,`set_press`,`working_press`,`design_press`,`working_temp`,`tag_no`,`id_no`,`vavle_model`,`vavle_type`,`manufacturer`,`license_no`,`diameter_nominal`,`seat_size`,`press_nominal`,`press_class_range_min`,`press_class_range_max`,`manu_date`,`manu_no`,`tightness_press`,`parity`) VALUES
(3,'安全阀','压力容器','水',0.40,0.30,0.80,'常温','QZ-01','RQ2100031','A47H-10C DN80','弹簧微启式安全阀','上海阀门厂股份有限公司','TS2710031-2024',80,60,1.00,0.30,0.60,'2023-05-15','CC240031',0.36,'离线校验');

INSERT INTO `workorder`
(`commission_id`,`code`,`order_code`,`cust_name`,`client`,`client_tel`,`status`,`reject_reason`,`update_time`,`create_time`,`equip_type`,`protected_equip_type`,`working_media`,`set_press`,`working_press`,`design_press`,`working_temp`,`tag_no`,`id_no`,`vavle_model`,`vavle_type`,`manufacturer`,`license_no`,`diameter_nominal`,`seat_size`,`press_nominal`,`press_class_range_min`,`press_class_range_max`,`manu_date`,`manu_no`,`tightness_press`,`parity`) VALUES
(3,'WT2026303','JY20260003','武汉环投千子山环境产业有限公司','钱进','13970000006','rejected','整定压力参数与铭牌不符，请核实后重新送检','2026-01-12 09:00:00','2026-01-11 15:20:00','安全阀','压力容器','水',0.40,0.30,0.80,'常温','QZ-01','RQ2100031','A47H-10C DN80','弹簧微启式安全阀','上海阀门厂股份有限公司','TS2710031-2024',80,60,1.00,0.30,0.60,'2023-05-15','CC240031',0.36,'离线校验');

-- 已完成工单示例（含检验结果，供待打印/已完成页演示）
INSERT INTO `workorder`
(`commission_id`,`code`,`order_code`,`cust_name`,`client`,`client_tel`,`status`,`inspector`,`inspector_time`,`update_time`,`create_time`,`equip_type`,`protected_equip_type`,`working_media`,`set_press`,`working_press`,`design_press`,`working_temp`,`tag_no`,`id_no`,`vavle_model`,`vavle_type`,`manufacturer`,`license_no`,`diameter_nominal`,`seat_size`,`press_nominal`,`press_class_range_min`,`press_class_range_max`,`manu_date`,`manu_no`,`tightness_press`,`parity`) VALUES
(1,'WT2026301','JY20260004','武汉翼华技术设备有限公司','刘建国','13970000001','done','周宽','2026-01-10 16:00:00','2026-01-10 17:00:00','2026-01-08 10:10:00','安全阀','锅炉','蒸汽',1.10,0.80,1.60,'常温','YH-01','RQ2100041','A48Y-16C DN50','弹簧全启式安全阀','上海阀门厂股份有限公司','TS2710041-2024',50,32,1.60,0.80,1.30,'2024-02-15','CC240041',0.99,'离线校验');

INSERT INTO `workorder_result`
(`workorder_id`,`order_code`,`tightness_press`,`set_press`,`test_media`,`test_temprature`,`parity`,`visual_examination`,`disassembly_inspection`,`note`,`pressure_gauge_num`,`pressure_gauge_lifespan`,`pressure_gauge_accuracy`,`pressure_gauge_range`,`device_model`,`device_sn`,`testing_pressure1`,`testing_pressure2`,`testing_pressure3`,`test_status`) VALUES
(1,'JY20260004',0.99,1.10,'氮气','常温','离线校验','合格','合格','无','YB-10001','2027-06-30','0.4级','0-2.5','SFA-150','JC88001',1.10,1.10,1.11,'合格');

-- =============================================================
-- 角色权限 / 菜单访问设置
-- =============================================================

-- 角色表
CREATE TABLE IF NOT EXISTS `sys_role` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(50) NOT NULL DEFAULT '' COMMENT '角色标识',
  `label` VARCHAR(50) NOT NULL DEFAULT '' COMMENT '角色名称',
  `remark` VARCHAR(255) DEFAULT '' COMMENT '备注',
  `permissions` TEXT COMMENT '权限标识，逗号分隔，*为全部权限',
  `built_in` TINYINT NOT NULL DEFAULT 0 COMMENT '1内置角色不可删除',
  `is_del` TINYINT NOT NULL DEFAULT 0,
  `create_time` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='角色';

-- 菜单表
CREATE TABLE IF NOT EXISTS `sys_menu` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `parent_id` INT NOT NULL DEFAULT 0 COMMENT '父级菜单，0=顶级',
  `title` VARCHAR(50) NOT NULL DEFAULT '' COMMENT '菜单名称',
  `path` VARCHAR(100) NOT NULL DEFAULT '' COMMENT '路由路径',
  `permission` VARCHAR(100) DEFAULT '' COMMENT '权限标识',
  `icon` VARCHAR(50) DEFAULT '' COMMENT '图标名',
  `type` VARCHAR(10) NOT NULL DEFAULT 'menu' COMMENT 'dir=目录 menu=菜单',
  `sort` INT NOT NULL DEFAULT 1 COMMENT '排序',
  `visible` TINYINT NOT NULL DEFAULT 1 COMMENT '1显示 0隐藏',
  `is_del` TINYINT NOT NULL DEFAULT 0,
  `create_time` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='菜单访问配置';

-- 角色种子数据（与系统账号身份对应）：管理员/录入人员/检验人员/审核员/打印人员/录入审核
-- permissions 同时包含「菜单权限」与「按钮权限」（模块:页面:按钮），支持 xxx:* 前缀通配
INSERT INTO `sys_role` (`name`,`label`,`remark`,`permissions`,`built_in`) VALUES
('admin', '管理员', '拥有系统全部权限', '*', 1),
('input', '录入人员', '送检信息录入与签名相关权限',
 'dashboard,commission,commission:add,commission:add:submit,commission:add:valve,commission:list,commission:list:view,commission:list:add,commission:list:edit,commission:list:sign,commission:list:precheck', 0),
('inspector', '检验人员', '业务操作权限（可检验/打印，无审核、删除与系统写权限）',
 'dashboard,commission,commission:add,commission:add:submit,commission:add:valve,commission:list,commission:list:view,commission:list:print,commission:list:pending,workorder,workorder:view,workorder:reject,workorder:reject:resubmit,workorder:wait,workorder:wait:submit,workorder:inspection,workorder:inspection:result,workorder:audit,workorder:audit:pass,workorder:audit:reject,workorder:approval,workorder:approval:pass,workorder:approval:reject,workorder:print,workorder:print:print,workorder:print:export,workorder:all,workorder:all:print,workorder:all:export,system,system:user', 0),
('auditor', '审核员', '委托单审核相关权限',
 'dashboard,commission,commission:list,commission:list:view,commission:list:approve,commission:list:reject,workorder,workorder:all', 0),
('printer', '打印人员', '标签与报告打印相关权限',
 'dashboard,workorder,workorder:print,workorder:print:print,workorder:print:export,workorder:all,workorder:all:print,workorder:all:export', 0),
('input_audit', '录入审核', '录入与审核权限合集',
 'dashboard,commission,commission:add,commission:add:submit,commission:add:valve,commission:list,commission:list:view,commission:list:add,commission:list:edit,commission:list:approve,commission:list:reject,workorder,workorder:wait,workorder:inspection', 0);

-- 账号关联角色：admin -> 管理员(1)，whyh_006 -> 检验人员(2)
UPDATE `sys_user` SET `role_id` = 1 WHERE `account` = 'admin';
UPDATE `sys_user` SET `role_id` = 2 WHERE `account` = 'whyh_006';

-- 站点表（站点管理）
CREATE TABLE IF NOT EXISTS `site` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `parent_id` INT NOT NULL DEFAULT 0 COMMENT '上级站点id，0=无',
  `name` VARCHAR(150) NOT NULL COMMENT '站点名称',
  `manager` VARCHAR(50) DEFAULT '' COMMENT '负责人',
  `tel` VARCHAR(20) DEFAULT '' COMMENT '联系电话',
  `address` VARCHAR(255) DEFAULT '' COMMENT '地址',
  `status` TINYINT DEFAULT 1 COMMENT '1开启 0关闭',
  `is_del` TINYINT DEFAULT 0,
  `create_time` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='站点';

INSERT INTO `site` (`parent_id`,`name`,`manager`,`tel`,`address`,`status`) VALUES
(0, '武汉翼华技术有限公司', '周志钢', '13697323638', '湖北省武汉市青山区冶金大道中国一冶高新技术产业户', 1);

-- 模版表（模版管理）
CREATE TABLE IF NOT EXISTS `tpl` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `site_id` INT NOT NULL DEFAULT 0 COMMENT '站点id',
  `type` VARCHAR(50) DEFAULT '' COMMENT '类型：校验报告/校验记录/台账/委托单/收费单/委托单-未确认/报告封面/脱脂报告/脱脂记录',
  `name` VARCHAR(150) NOT NULL COMMENT '模板名称',
  `file_name` VARCHAR(255) DEFAULT '' COMMENT '模板文件名',
  `file_path` VARCHAR(255) DEFAULT '' COMMENT '模板文件相对路径',
  `status` TINYINT DEFAULT 1 COMMENT '1开启 0关闭',
  `is_del` TINYINT DEFAULT 0,
  `create_time` DATETIME DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='模版';

INSERT INTO `tpl` (`site_id`,`type`,`name`,`file_name`,`file_path`,`status`) VALUES
(1, '校验报告', '校验报告', '校验报告模板.docx', 'uploads/tpl/tpl_check_report.docx', 1),
(1, '校验记录', '校验记录20260918', '校验记录模板.docx', 'uploads/tpl/tpl_check_record.docx', 1),
(1, '台账', '安全阀台账', '安全阀台账模板.xlsx', 'uploads/tpl/tpl_ledger.xlsx', 1),
(1, '委托单', '委托单', '委托单模板.xlsx', 'uploads/tpl/tpl_commission.xlsx', 1);

-- 菜单种子数据（与前端动态路由树一致）
INSERT INTO `sys_menu` (`parent_id`,`title`,`path`,`permission`,`icon`,`type`,`sort`,`visible`) VALUES
(0, '工作台',   '/dashboard',        'dashboard',      'SpeedometerOutline',   'menu', 1, 1),
(0, '委托单管理', '/commission',       'commission',     'DocumentTextOutline',  'dir',  2, 1),
(0, '检验管理',  '/workorder',        'workorder',      'CheckboxOutline',      'dir',  3, 1),
(0, '安全阀管理', '/valve',            'valve',          'CubeOutline',          'dir',  4, 1),
(0, '客户管理',  '/customer',         'customer',       'PeopleOutline',        'menu', 5, 1),
(0, '系统管理',  '/system',           'system',         'SettingsOutline',      'dir',  6, 1),
(2, '现场送检',  '/commission/add',   'commission:add', 'CreateOutline',        'menu', 1, 1),
(2, '送检列表',  '/commission/list',  'commission:list','ListOutline',          'menu', 2, 1),
(2, '收费列表',  '/commission/charge','commission:charge','CashOutline',        'menu', 3, 1),
(3, '校验退回',  '/workorder/reject',     'workorder:reject',     '', 'menu', 1, 1),
(3, '待检验',    '/workorder/wait',       'workorder:wait',       '', 'menu', 2, 1),
(3, '检验中',    '/workorder/inspection', 'workorder:inspection', '', 'menu', 3, 1),
(3, '待审核',    '/workorder/audit',      'workorder:audit',      '', 'menu', 4, 1),
(3, '待审批',    '/workorder/approval',   'workorder:approval',   '', 'menu', 5, 1),
(3, '待打印',    '/workorder/print',      'workorder:print',      '', 'menu', 6, 1),
(3, '已完成',    '/workorder/all',        'workorder:all',        '', 'menu', 7, 1),
(4, '站点管理',    '/system/site', 'system:site', 'ServerOutline',          'menu', 0, 1),
(4, '模版管理',    '/system/tpl',  'system:tpl',  'DocumentOutline',        'menu', 1, 1),
(4, '系统账号',    '/system/user', 'system:user', 'PersonOutline',          'menu', 2, 1),
(4, '权限设置',    '/system/role', 'system:role', 'ShieldCheckmarkOutline', 'menu', 3, 1),
(4, '菜单访问设置', '/system/menu', 'system:menu', 'ListOutline',           'menu', 4, 1),
(17, '新增安全阀', '/valve/add',  'valve:add',  'AddCircleOutline',       'menu', 1, 1),
(17, '安全阀列表', '/valve/list', 'valve:list', 'GridOutline',            'menu', 2, 1);
