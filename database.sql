-- =============================================
-- Информационная система учета МТБ колледжа
-- MySQL-совместимый дамп - ВЕРСИЯ 3.0
-- Полная демонстрационная версия с историей изменений
-- =============================================

DROP DATABASE IF EXISTS `college_mtb`;
CREATE DATABASE `college_mtb` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `college_mtb`;

-- =============================================
-- ТАБЛИЦА: users (Пользователи системы)
-- =============================================
CREATE TABLE `users` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `username` VARCHAR(50) NOT NULL,
  `password_hash` VARCHAR(255) NOT NULL,
  `full_name` VARCHAR(100) NOT NULL,
  `email` VARCHAR(100) DEFAULT NULL,
  `role` ENUM('admin','user','viewer') NOT NULL DEFAULT 'user',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `last_login` DATETIME DEFAULT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  PRIMARY KEY (`id`),
  UNIQUE KEY `ux_users_username` (`username`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =============================================
-- ТАБЛИЦА: categories (Категории оборудования)
-- =============================================
CREATE TABLE `categories` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(100) NOT NULL,
  `code` VARCHAR(20) DEFAULT NULL,
  `description` TEXT DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `ux_categories_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =============================================
-- ТАБЛИЦА: condition_status (Статусы состояния)
-- =============================================
CREATE TABLE `condition_status` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(50) NOT NULL,
  `description` VARCHAR(200) DEFAULT NULL,
  `color_class` VARCHAR(50) DEFAULT 'gray',
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =============================================
-- ТАБЛИЦА: employees (Сотрудники)
-- =============================================
CREATE TABLE `employees` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `full_name` VARCHAR(100) NOT NULL,
  `position` VARCHAR(100) NOT NULL,
  `department` VARCHAR(100) DEFAULT NULL,
  `phone` VARCHAR(20) DEFAULT NULL,
  `email` VARCHAR(100) DEFAULT NULL,
  `hire_date` DATE DEFAULT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =============================================
-- ТАБЛИЦА: premises (Помещения)
-- =============================================
CREATE TABLE `premises` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `room_number` VARCHAR(20) NOT NULL,
  `building` VARCHAR(50) NOT NULL,
  `floor` VARCHAR(10) DEFAULT NULL,
  `room_type` VARCHAR(50) DEFAULT NULL,
  `area` DECIMAL(10,2) DEFAULT NULL,
  `capacity` INT DEFAULT NULL,
  `responsible_id` INT DEFAULT NULL,
  `status` ENUM('активное','ремонт','закрыто') NOT NULL DEFAULT 'активное',
  `description` TEXT DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  CONSTRAINT `fk_premises_responsible` FOREIGN KEY (`responsible_id`) REFERENCES `employees`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =============================================
-- ТАБЛИЦА: equipment (Оборудование)
-- =============================================
CREATE TABLE `equipment` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `inventory_number` VARCHAR(50) NOT NULL,
  `name` VARCHAR(200) NOT NULL,
  `category_id` INT DEFAULT NULL,
  `premise_id` INT DEFAULT NULL,
  `responsible_id` INT DEFAULT NULL,
  `purchase_date` DATE DEFAULT NULL,
  `purchase_price` DECIMAL(12,2) DEFAULT NULL,
  `current_value` DECIMAL(12,2) DEFAULT NULL,
  `condition_id` INT DEFAULT NULL,
  `manufacturer` VARCHAR(100) DEFAULT NULL,
  `model` VARCHAR(100) DEFAULT NULL,
  `serial_number` VARCHAR(100) DEFAULT NULL,
  `description` TEXT DEFAULT NULL,
  `warranty_until` DATE DEFAULT NULL,
  `commissioning_date` DATE DEFAULT NULL,
  `decommissioning_date` DATE DEFAULT NULL,
  `decommissioning_reason` TEXT DEFAULT NULL,
  `is_active` TINYINT(1) NOT NULL DEFAULT 1,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `ux_equipment_inventory` (`inventory_number`),
  KEY `idx_equipment_category` (`category_id`),
  KEY `idx_equipment_premise` (`premise_id`),
  KEY `idx_equipment_responsible` (`responsible_id`),
  CONSTRAINT `fk_equipment_category` FOREIGN KEY (`category_id`) REFERENCES `categories`(`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_equipment_premise` FOREIGN KEY (`premise_id`) REFERENCES `premises`(`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_equipment_responsible` FOREIGN KEY (`responsible_id`) REFERENCES `employees`(`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_equipment_condition` FOREIGN KEY (`condition_id`) REFERENCES `condition_status`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =============================================
-- ТАБЛИЦА: equipment_history (История изменений оборудования)
-- =============================================
CREATE TABLE `equipment_history` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `equipment_id` INT NOT NULL,
  `change_type` ENUM(
    'создание',
    'перемещение',
    'смена_ответственного',
    'смена_состояния',
    'изменение_цены',
    'редактирование',
    'списание',
    'восстановление',
    'инвентаризация'
  ) NOT NULL,
  `change_date` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `old_value` TEXT DEFAULT NULL,
  `new_value` TEXT DEFAULT NULL,
  `old_premise_id` INT DEFAULT NULL,
  `new_premise_id` INT DEFAULT NULL,
  `old_responsible_id` INT DEFAULT NULL,
  `new_responsible_id` INT DEFAULT NULL,
  `old_condition_id` INT DEFAULT NULL,
  `new_condition_id` INT DEFAULT NULL,
  `old_price` DECIMAL(12,2) DEFAULT NULL,
  `new_price` DECIMAL(12,2) DEFAULT NULL,
  `reason` VARCHAR(500) DEFAULT NULL,
  `performed_by` INT DEFAULT NULL,
  `notes` TEXT DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_history_equipment` (`equipment_id`),
  KEY `idx_history_date` (`change_date`),
  KEY `idx_history_type` (`change_type`),
  CONSTRAINT `fk_history_equipment` FOREIGN KEY (`equipment_id`) REFERENCES `equipment`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_history_old_premise` FOREIGN KEY (`old_premise_id`) REFERENCES `premises`(`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_history_new_premise` FOREIGN KEY (`new_premise_id`) REFERENCES `premises`(`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_history_old_responsible` FOREIGN KEY (`old_responsible_id`) REFERENCES `employees`(`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_history_new_responsible` FOREIGN KEY (`new_responsible_id`) REFERENCES `employees`(`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_history_performed_by` FOREIGN KEY (`performed_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =============================================
-- ТАБЛИЦА: inventories (Инвентаризации)
-- =============================================
CREATE TABLE `inventories` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `inventory_number` VARCHAR(50) NOT NULL,
  `start_date` DATE NOT NULL,
  `end_date` DATE DEFAULT NULL,
  `status` ENUM('запланирована','в процессе','завершена','отменена') NOT NULL DEFAULT 'запланирована',
  `responsible_id` INT DEFAULT NULL,
  `notes` TEXT DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `ux_inventories_number` (`inventory_number`),
  CONSTRAINT `fk_inventories_responsible` FOREIGN KEY (`responsible_id`) REFERENCES `employees`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =============================================
-- ТАБЛИЦА: inventory_items (Позиции инвентаризации)
-- =============================================
CREATE TABLE `inventory_items` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `inventory_id` INT NOT NULL,
  `equipment_id` INT NOT NULL,
  `expected_location_id` INT DEFAULT NULL,
  `actual_location_id` INT DEFAULT NULL,
  `expected_condition_id` INT DEFAULT NULL,
  `actual_condition_id` INT DEFAULT NULL,
  `status` ENUM('не проверено','совпадает','расхождение','не найдено') NOT NULL DEFAULT 'не проверено',
  `notes` TEXT DEFAULT NULL,
  `checked_at` DATETIME DEFAULT NULL,
  `checked_by` INT DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_inv_items_inventory` (`inventory_id`),
  KEY `idx_inv_items_equipment` (`equipment_id`),
  CONSTRAINT `fk_inv_items_inventory` FOREIGN KEY (`inventory_id`) REFERENCES `inventories`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_inv_items_equipment` FOREIGN KEY (`equipment_id`) REFERENCES `equipment`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =============================================
-- ТАБЛИЦА: write_offs (Акты списания)
-- =============================================
CREATE TABLE `write_offs` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `equipment_id` INT NOT NULL,
  `write_off_date` DATE NOT NULL,
  `reason` TEXT NOT NULL,
  `document_number` VARCHAR(50) DEFAULT NULL,
  `residual_value` DECIMAL(12,2) DEFAULT 0.00,
  `approved_by` INT DEFAULT NULL,
  `notes` TEXT DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_writeoffs_equipment` (`equipment_id`),
  CONSTRAINT `fk_writeoffs_equipment` FOREIGN KEY (`equipment_id`) REFERENCES `equipment`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_writeoffs_approved` FOREIGN KEY (`approved_by`) REFERENCES `employees`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =============================================
-- НАЧАЛЬНЫЕ ДАННЫЕ
-- =============================================

-- Пользователи
INSERT INTO `users` (`username`, `password_hash`, `full_name`, `email`, `role`) VALUES
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Администратор системы', 'admin@college.ru', 'admin'),
('ivanov', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Иванов И.И.', 'ivanov@college.ru', 'user'),
('petrov', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Петров П.П.', 'petrov@college.ru', 'user');

-- Категории (только офисная техника)
INSERT INTO `categories` (`id`, `name`, `code`, `description`) VALUES
(1, 'Офисная техника', 'OT', 'Принтеры, сканеры, МФУ, копиры и другая офисная техника'),
(2, 'Офисная техника', 'OT2', 'Телефоны, факсы, шредеры');

-- Статусы состояния
INSERT INTO `condition_status` (`id`, `name`, `description`, `color_class`) VALUES
(1, 'Отличное', 'Новое или как новое оборудование', 'green'),
(2, 'Хорошее', 'Рабочее состояние, незначительный износ', 'blue'),
(3, 'Удовлетворительное', 'Работает, но есть заметный износ', 'yellow'),
(4, 'Требует ремонта', 'Необходим ремонт или техобслуживание', 'orange'),
(5, 'Списано', 'Оборудование списано', 'red');

-- Сотрудники
INSERT INTO `employees` (`id`, `full_name`, `position`, `department`, `phone`, `email`, `hire_date`) VALUES
(1, 'Сидорова Анна Петровна', 'Заведующий хозяйственной частью', 'АХО', '+7(495)123-45-01', 'sidorova@college.ru', '2018-03-15'),
(2, 'Козлов Дмитрий Сергеевич', 'Системный администратор', 'ИТ-отдел', '+7(495)123-45-02', 'kozlov@college.ru', '2019-09-01'),
(3, 'Морозова Елена Викторовна', 'Секретарь', 'Приёмная', '+7(495)123-45-03', 'morozova@college.ru', '2020-01-10'),
(4, 'Новиков Алексей Иванович', 'Преподаватель информатики', 'Кафедра ИТ', '+7(495)123-45-04', 'novikov@college.ru', '2017-08-20'),
(5, 'Белова Ольга Николаевна', 'Бухгалтер', 'Бухгалтерия', '+7(495)123-45-05', 'belova@college.ru', '2016-05-12');

-- Помещения
INSERT INTO `premises` (`id`, `room_number`, `building`, `floor`, `room_type`, `area`, `capacity`, `responsible_id`, `status`) VALUES
(1, '101', 'Главный корпус', '1', 'Приёмная', 25.00, 5, 3, 'активное'),
(2, '102', 'Главный корпус', '1', 'Бухгалтерия', 30.00, 4, 5, 'активное'),
(3, '201', 'Главный корпус', '2', 'Серверная', 15.00, 2, 2, 'активное'),
(4, '202', 'Главный корпус', '2', 'Компьютерный класс', 60.00, 25, 4, 'активное'),
(5, '301', 'Главный корпус', '3', 'Кабинет АХО', 20.00, 3, 1, 'активное'),
(6, '103', 'Главный корпус', '1', 'Канцелярия', 18.00, 3, 3, 'активное');

-- Оборудование (офисная техника)
INSERT INTO `equipment` (`id`, `inventory_number`, `name`, `category_id`, `premise_id`, `responsible_id`, `purchase_date`, `purchase_price`, `current_value`, `condition_id`, `manufacturer`, `model`, `serial_number`, `description`, `warranty_until`, `commissioning_date`) VALUES
(1, 'ОТ-2021-001', 'МФУ лазерное', 1, 1, 3, '2021-03-15', 45000.00, 32000.00, 2, 'HP', 'LaserJet Pro M428fdn', 'VNC3X12345', 'Многофункциональное устройство: печать, копирование, сканирование, факс', '2024-03-15', '2021-03-20'),
(2, 'ОТ-2021-002', 'Принтер лазерный ч/б', 1, 2, 5, '2021-05-10', 18000.00, 12000.00, 2, 'Brother', 'HL-L2340DW', 'U64051C1N123456', 'Лазерный принтер для бухгалтерии', '2024-05-10', '2021-05-15'),
(3, 'ОТ-2022-001', 'МФУ цветное струйное', 1, 4, 4, '2022-01-20', 35000.00, 28000.00, 1, 'Epson', 'L6190', 'X5WY123456', 'Цветное МФУ для компьютерного класса', '2025-01-20', '2022-01-25'),
(4, 'ОТ-2020-001', 'Принтер матричный', 1, 2, 5, '2020-02-10', 25000.00, 8000.00, 3, 'Epson', 'LX-350', 'MSNY012345', 'Для печати на бланках строгой отчётности', '2023-02-10', '2020-02-15'),
(5, 'ОТ-2023-001', 'МФУ лазерное цветное', 1, 5, 1, '2023-06-01', 85000.00, 78000.00, 1, 'Canon', 'i-SENSYS MF746Cx', 'LBP123456789', 'Цветное МФУ для АХО', '2026-06-01', '2023-06-05'),
(6, 'ОТ-2021-003', 'Сканер планшетный', 1, 1, 3, '2021-04-05', 12000.00, 8500.00, 2, 'Canon', 'CanoScan LiDE 400', 'CAND123456', 'Планшетный сканер А4', '2024-04-05', '2021-04-10'),
(7, 'ОТ-2022-002', 'Сканер потоковый', 1, 2, 5, '2022-03-15', 45000.00, 38000.00, 1, 'Fujitsu', 'fi-7160', 'FI7123456', 'Высокоскоростной сканер для документооборота', '2025-03-15', '2022-03-20'),
(8, 'ОТ-2019-001', 'Копировальный аппарат', 1, 6, 3, '2019-11-20', 120000.00, 45000.00, 3, 'Xerox', 'VersaLink B405', 'XRX987654321', 'Копир для канцелярии', '2022-11-20', '2019-11-25'),
(9, 'ОТ-2022-003', 'Шредер офисный', 1, 2, 5, '2022-07-10', 15000.00, 12000.00, 1, 'Fellowes', 'Powershred 99Ci', 'FEL456789', 'Уничтожитель документов уровень секретности P-4', '2025-07-10', '2022-07-15'),
(10, 'ОТ-2020-002', 'Шредер персональный', 1, 5, 1, '2020-09-05', 8000.00, 4000.00, 2, 'HSM', 'shredstar X5', 'HSM789012', 'Персональный шредер', '2023-09-05', '2020-09-10'),
(11, 'ОТ-2021-004', 'Телефон IP', 1, 1, 3, '2021-02-01', 8500.00, 6000.00, 2, 'Grandstream', 'GXP1625', 'GXP123456', 'IP-телефон для приёмной', '2024-02-01', '2021-02-05'),
(12, 'ОТ-2021-005', 'Телефон IP', 1, 2, 5, '2021-02-01', 8500.00, 6000.00, 2, 'Grandstream', 'GXP1625', 'GXP123457', 'IP-телефон для бухгалтерии', '2024-02-01', '2021-02-05'),
(13, 'ОТ-2023-002', 'Телефон IP с видео', 1, 5, 1, '2023-04-15', 25000.00, 23000.00, 1, 'Yealink', 'T58W', 'YEA789012', 'IP-телефон с видеосвязью для АХО', '2026-04-15', '2023-04-20'),
(14, 'ОТ-2022-004', 'Ламинатор офисный', 1, 6, 3, '2022-05-20', 12000.00, 9500.00, 1, 'Fellowes', 'Saturn 3i A3', 'FEL321654', 'Ламинатор А3 для канцелярии', '2025-05-20', '2022-05-25'),
(15, 'ОТ-2018-001', 'Принтер лазерный устаревший', 1, NULL, NULL, '2018-06-10', 22000.00, 0.00, 5, 'HP', 'LaserJet P2055d', 'VNB4567890', 'Списан по износу', '2021-06-10', '2018-06-15');

-- Обновляем списанное оборудование
UPDATE `equipment` SET `is_active` = 0, `decommissioning_date` = '2024-01-15', `decommissioning_reason` = 'Физический износ, нецелесообразность ремонта' WHERE `id` = 15;

-- =============================================
-- ИСТОРИЯ ИЗМЕНЕНИЙ (демонстрационные данные)
-- =============================================

-- Создание оборудования
INSERT INTO `equipment_history` (`equipment_id`, `change_type`, `change_date`, `new_value`, `new_premise_id`, `new_responsible_id`, `new_condition_id`, `new_price`, `reason`, `notes`) VALUES
(1, 'создание', '2021-03-20 09:00:00', 'МФУ лазерное HP LaserJet Pro M428fdn', 1, 3, 1, 45000.00, 'Закупка по контракту №15-2021', 'Инв. номер: ОТ-2021-001'),
(2, 'создание', '2021-05-15 10:30:00', 'Принтер лазерный ч/б Brother HL-L2340DW', 2, 5, 1, 18000.00, 'Закупка по контракту №22-2021', 'Инв. номер: ОТ-2021-002'),
(3, 'создание', '2022-01-25 11:00:00', 'МФУ цветное струйное Epson L6190', 4, 4, 1, 35000.00, 'Закупка по контракту №03-2022', 'Инв. номер: ОТ-2022-001'),
(4, 'создание', '2020-02-15 09:30:00', 'Принтер матричный Epson LX-350', 2, 5, 1, 25000.00, 'Закупка для бухгалтерии', 'Инв. номер: ОТ-2020-001'),
(5, 'создание', '2023-06-05 14:00:00', 'МФУ лазерное цветное Canon i-SENSYS MF746Cx', 5, 1, 1, 85000.00, 'Закупка по контракту №45-2023', 'Инв. номер: ОТ-2023-001'),
(6, 'создание', '2021-04-10 10:00:00', 'Сканер планшетный Canon CanoScan LiDE 400', 1, 3, 1, 12000.00, 'Закупка по контракту №15-2021', 'Инв. номер: ОТ-2021-003'),
(7, 'создание', '2022-03-20 09:00:00', 'Сканер потоковый Fujitsu fi-7160', 2, 5, 1, 45000.00, 'Закупка для электронного документооборота', 'Инв. номер: ОТ-2022-002'),
(8, 'создание', '2019-11-25 11:30:00', 'Копировальный аппарат Xerox VersaLink B405', 6, 3, 1, 120000.00, 'Закупка для канцелярии', 'Инв. номер: ОТ-2019-001'),
(9, 'создание', '2022-07-15 10:00:00', 'Шредер офисный Fellowes Powershred 99Ci', 2, 5, 1, 15000.00, 'Закупка для защиты персональных данных', 'Инв. номер: ОТ-2022-003'),
(10, 'создание', '2020-09-10 14:30:00', 'Шредер персональный HSM shredstar X5', 5, 1, 1, 8000.00, 'Закупка для АХО', 'Инв. номер: ОТ-2020-002');

-- Изменения цен (амортизация и переоценка)
INSERT INTO `equipment_history` (`equipment_id`, `change_type`, `change_date`, `old_price`, `new_price`, `reason`, `notes`) VALUES
(1, 'изменение_цены', '2022-01-01 00:00:00', 45000.00, 40000.00, 'Ежегодная амортизация', 'Норма амортизации 11%'),
(1, 'изменение_цены', '2023-01-01 00:00:00', 40000.00, 36000.00, 'Ежегодная амортизация', 'Норма амортизации 10%'),
(1, 'изменение_цены', '2024-01-01 00:00:00', 36000.00, 32000.00, 'Ежегодная амортизация', 'Норма амортизации 11%'),
(2, 'изменение_цены', '2022-06-01 00:00:00', 18000.00, 15000.00, 'Ежегодная амортизация', 'Норма амортизации 17%'),
(2, 'изменение_цены', '2023-06-01 00:00:00', 15000.00, 12000.00, 'Ежегодная амортизация', 'Норма амортизации 20%'),
(3, 'изменение_цены', '2023-02-01 00:00:00', 35000.00, 32000.00, 'Ежегодная амортизация', 'Норма амортизации 9%'),
(3, 'изменение_цены', '2024-02-01 00:00:00', 32000.00, 28000.00, 'Ежегодная амортизация', 'Норма амортизации 12%'),
(4, 'изменение_цены', '2021-03-01 00:00:00', 25000.00, 18000.00, 'Ежегодная амортизация', 'Норма амортизации 28%'),
(4, 'изменение_цены', '2022-03-01 00:00:00', 18000.00, 12000.00, 'Ежегодная амортизация', 'Норма амортизации 33%'),
(4, 'изменение_цены', '2023-03-01 00:00:00', 12000.00, 8000.00, 'Ежегодная амортизация', 'Норма амортизации 33%'),
(8, 'изменение_цены', '2020-12-01 00:00:00', 120000.00, 95000.00, 'Ежегодная амортизация', 'Норма амортизации 21%'),
(8, 'изменение_цены', '2021-12-01 00:00:00', 95000.00, 70000.00, 'Ежегодная амортизация', 'Норма амортизации 26%'),
(8, 'изменение_цены', '2022-12-01 00:00:00', 70000.00, 55000.00, 'Ежегодная амортизация', 'Норма амортизации 21%'),
(8, 'изменение_цены', '2023-12-01 00:00:00', 55000.00, 45000.00, 'Ежегодная амортизация', 'Норма амортизации 18%');

-- Перемещения оборудования
INSERT INTO `equipment_history` (`equipment_id`, `change_type`, `change_date`, `old_premise_id`, `new_premise_id`, `reason`, `notes`) VALUES
(4, 'перемещение', '2021-08-15 14:00:00', 6, 2, 'Перемещение в бухгалтерию', 'По заявке №45 от бухгалтерии'),
(8, 'перемещение', '2022-04-10 10:30:00', 1, 6, 'Перемещение в канцелярию', 'Освобождение места в приёмной');

-- Смена ответственных
INSERT INTO `equipment_history` (`equipment_id`, `change_type`, `change_date`, `old_responsible_id`, `new_responsible_id`, `reason`, `notes`) VALUES
(1, 'смена_ответственного', '2022-06-01 09:00:00', 1, 3, 'Смена МОЛ', 'В связи с кадровыми изменениями'),
(8, 'смена_ответственного', '2022-04-10 10:30:00', 1, 3, 'Смена МОЛ', 'При перемещении в канцелярию');

-- Изменения состояния
INSERT INTO `equipment_history` (`equipment_id`, `change_type`, `change_date`, `old_condition_id`, `new_condition_id`, `reason`, `notes`) VALUES
(1, 'смена_состояния', '2023-03-15 11:00:00', 1, 2, 'Плановое техобслуживание', 'Выявлен незначительный износ'),
(4, 'смена_состояния', '2022-08-20 15:00:00', 2, 3, 'Результат инвентаризации', 'Требуется замена картриджа'),
(8, 'смена_состояния', '2023-06-10 09:30:00', 2, 3, 'Плановое техобслуживание', 'Износ барабана, рекомендована замена'),
(10, 'смена_состояния', '2023-11-15 14:00:00', 1, 2, 'Ежегодная проверка', 'Нормальный износ');

-- Списание оборудования
INSERT INTO `equipment_history` (`equipment_id`, `change_type`, `change_date`, `old_condition_id`, `new_condition_id`, `old_price`, `new_price`, `reason`, `notes`) VALUES
(15, 'списание', '2024-01-15 10:00:00', 4, 5, 5000.00, 0.00, 'Физический износ, нецелесообразность ремонта', 'Акт списания №5 от 15.01.2024');

-- Запись в таблицу списаний
INSERT INTO `write_offs` (`equipment_id`, `write_off_date`, `reason`, `document_number`, `residual_value`, `approved_by`, `notes`) VALUES
(15, '2024-01-15', 'Физический износ барабана и печатающей головки. Стоимость ремонта превышает остаточную стоимость оборудования.', 'Акт №5-2024', 0.00, 1, 'Утилизация в соответствии с экологическими нормами');

-- =============================================
-- ИНВЕНТАРИЗАЦИЯ (демонстрационные данные)
-- =============================================

-- Завершённая инвентаризация
INSERT INTO `inventories` (`id`, `inventory_number`, `start_date`, `end_date`, `status`, `responsible_id`, `notes`) VALUES
(1, 'ИНВ-2024-001', '2024-01-10', '2024-01-12', 'завершена', 1, 'Плановая годовая инвентаризация офисной техники');

-- Позиции завершённой инвентаризации
INSERT INTO `inventory_items` (`inventory_id`, `equipment_id`, `expected_location_id`, `actual_location_id`, `expected_condition_id`, `actual_condition_id`, `status`, `notes`, `checked_at`) VALUES
(1, 1, 1, 1, 2, 2, 'совпадает', NULL, '2024-01-10 10:15:00'),
(1, 2, 2, 2, 2, 2, 'совпадает', NULL, '2024-01-10 10:30:00'),
(1, 3, 4, 4, 1, 1, 'совпадает', NULL, '2024-01-10 11:00:00'),
(1, 4, 2, 2, 3, 3, 'совпадает', 'Рекомендуется замена картриджа', '2024-01-10 10:45:00'),
(1, 5, 5, 5, 1, 1, 'совпадает', NULL, '2024-01-11 09:00:00'),
(1, 6, 1, 1, 2, 2, 'совпадает', NULL, '2024-01-10 10:20:00'),
(1, 7, 2, 2, 1, 1, 'совпадает', NULL, '2024-01-10 10:35:00'),
(1, 8, 6, 6, 3, 3, 'совпадает', 'Износ барабана 70%', '2024-01-10 11:30:00'),
(1, 9, 2, 2, 1, 1, 'совпадает', NULL, '2024-01-10 10:40:00'),
(1, 10, 5, 5, 2, 2, 'совпадает', NULL, '2024-01-11 09:15:00'),
(1, 11, 1, 1, 2, 2, 'совпадает', NULL, '2024-01-10 10:25:00'),
(1, 12, 2, 2, 2, 2, 'совпадает', NULL, '2024-01-10 10:50:00'),
(1, 13, 5, 5, 1, 1, 'совпадает', NULL, '2024-01-11 09:30:00'),
(1, 14, 6, 6, 1, 1, 'совпадает', NULL, '2024-01-10 11:45:00');

-- Текущая инвентаризация (в процессе) с разными статусами
INSERT INTO `inventories` (`id`, `inventory_number`, `start_date`, `end_date`, `status`, `responsible_id`, `notes`) VALUES
(2, 'ИНВ-2025-001', '2025-02-01', NULL, 'в процессе', 2, 'Внеплановая инвентаризация после ремонта в корпусе');

-- Позиции текущей инвентаризации (частично проверено, с расхождениями)
INSERT INTO `inventory_items` (`inventory_id`, `equipment_id`, `expected_location_id`, `actual_location_id`, `expected_condition_id`, `actual_condition_id`, `status`, `notes`, `checked_at`) VALUES
(2, 1, 1, 1, 2, 2, 'совпадает', NULL, '2025-02-01 10:00:00'),
(2, 2, 2, 2, 2, 2, 'совпадает', NULL, '2025-02-01 10:15:00'),
(2, 3, 4, 4, 1, 2, 'расхождение', 'Состояние хуже ожидаемого - следы использования', '2025-02-01 10:30:00'),
(2, 4, 2, 6, 3, 3, 'расхождение', 'Находится в канцелярии вместо бухгалтерии', '2025-02-01 10:45:00'),
(2, 5, 5, 5, 1, 1, 'совпадает', NULL, '2025-02-01 11:00:00'),
(2, 6, 1, 1, 2, 2, 'совпадает', NULL, '2025-02-01 10:05:00'),
(2, 7, 2, 2, 1, 1, 'совпадает', NULL, '2025-02-01 10:20:00'),
(2, 8, 6, NULL, 3, NULL, 'не найдено', 'Копир не обнаружен на месте, проводится розыск', '2025-02-01 11:30:00'),
(2, 9, 2, 2, 1, 1, 'совпадает', NULL, '2025-02-01 10:25:00'),
(2, 10, 5, 5, 2, 2, 'совпадает', NULL, '2025-02-01 11:05:00'),
(2, 11, 1, 1, 2, 2, 'не проверено', NULL, NULL),
(2, 12, 2, 2, 2, 2, 'не проверено', NULL, NULL),
(2, 13, 5, 5, 1, 1, 'не проверено', NULL, NULL),
(2, 14, 6, 6, 1, 1, 'не проверено', NULL, NULL);

-- =============================================
-- ПРЕДСТАВЛЕНИЕ: Помещения с количеством оборудования
-- =============================================
CREATE OR REPLACE VIEW `vw_premises_with_equipment` AS
SELECT 
    p.id, p.room_number, p.building, p.floor, p.room_type, 
    p.area, p.capacity, p.status, p.responsible_id,
    e.full_name AS responsible_name,
    (SELECT COUNT(*) FROM equipment eq WHERE eq.premise_id = p.id AND eq.is_active = 1) AS equipment_count
FROM premises p
LEFT JOIN employees e ON p.responsible_id = e.id;

-- =============================================
-- ПРЕДСТАВЛЕНИЕ: Полная информация об оборудовании
-- =============================================
CREATE OR REPLACE VIEW `vw_equipment_full` AS
SELECT 
    e.id, e.inventory_number, e.name, e.category_id, e.premise_id, e.responsible_id,
    e.purchase_date, e.purchase_price, e.current_value, e.condition_id,
    e.manufacturer, e.model, e.serial_number, e.description,
    e.warranty_until, e.commissioning_date, e.decommissioning_date, e.decommissioning_reason,
    e.is_active, e.created_at, e.updated_at,
    c.name AS category_name,
    CONCAT(p.building, ', ', p.room_number) AS location,
    cs.name AS condition_name,
    emp.full_name AS responsible_name
FROM equipment e
LEFT JOIN categories c ON e.category_id = c.id
LEFT JOIN premises p ON e.premise_id = p.id
LEFT JOIN condition_status cs ON e.condition_id = cs.id
LEFT JOIN employees emp ON e.responsible_id = emp.id;

-- =============================================
-- КОНЕЦ ФАЙЛА
-- =============================================
