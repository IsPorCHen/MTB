-- =============================================
-- Информационная система учета МТБ колледжа
-- MySQL-совместимый дамп - ВЕРСИЯ 2.0
-- Добавлена полная история изменений и расширенный функционал
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

-- Внешний ключ для ответственного за помещение
ALTER TABLE `premises` ADD CONSTRAINT `fk_premises_responsible` 
  FOREIGN KEY (`responsible_id`) REFERENCES `employees`(`id`) ON DELETE SET NULL;

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
-- Центральная таблица для отслеживания ВСЕХ изменений
-- =============================================
CREATE TABLE `equipment_history` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `equipment_id` INT NOT NULL,
  `change_type` ENUM(
    'создание',
    'перемещение',
    'смена_ответственного',
    'смена_состояния',
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
-- ТАБЛИЦА: movements (Перемещения оборудования - для обратной совместимости)
-- =============================================
CREATE TABLE `movements` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `equipment_id` INT NOT NULL,
  `from_premise_id` INT DEFAULT NULL,
  `to_premise_id` INT DEFAULT NULL,
  `movement_date` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `reason` VARCHAR(500) DEFAULT NULL,
  `responsible_id` INT DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_movements_equipment` (`equipment_id`),
  CONSTRAINT `fk_movements_equipment` FOREIGN KEY (`equipment_id`) REFERENCES `equipment`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_movements_from_premise` FOREIGN KEY (`from_premise_id`) REFERENCES `premises`(`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_movements_to_premise` FOREIGN KEY (`to_premise_id`) REFERENCES `premises`(`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_movements_responsible` FOREIGN KEY (`responsible_id`) REFERENCES `employees`(`id`) ON DELETE SET NULL
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
  `equipment_id` INT DEFAULT NULL,
  `expected_location_id` INT DEFAULT NULL,
  `actual_location_id` INT DEFAULT NULL,
  `expected_condition_id` INT DEFAULT NULL,
  `actual_condition_id` INT DEFAULT NULL,
  `status` ENUM('не проверено','совпадает','расхождение','не найдено') NOT NULL DEFAULT 'не проверено',
  `notes` VARCHAR(500) DEFAULT NULL,
  `checked_at` DATETIME DEFAULT NULL,
  `checked_by` INT DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_inventory_items_inventory` (`inventory_id`),
  CONSTRAINT `fk_inventory_items_inventory` FOREIGN KEY (`inventory_id`) REFERENCES `inventories`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_inventory_items_equipment` FOREIGN KEY (`equipment_id`) REFERENCES `equipment`(`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_inventory_items_expected_loc` FOREIGN KEY (`expected_location_id`) REFERENCES `premises`(`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_inventory_items_actual_loc` FOREIGN KEY (`actual_location_id`) REFERENCES `premises`(`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_inventory_items_expected_cond` FOREIGN KEY (`expected_condition_id`) REFERENCES `condition_status`(`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_inventory_items_actual_cond` FOREIGN KEY (`actual_condition_id`) REFERENCES `condition_status`(`id`) ON DELETE SET NULL,
  CONSTRAINT `fk_inventory_items_checked_by` FOREIGN KEY (`checked_by`) REFERENCES `users`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =============================================
-- ТАБЛИЦА: write_offs (Списание оборудования)
-- =============================================
CREATE TABLE `write_offs` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `equipment_id` INT NOT NULL,
  `write_off_date` DATE NOT NULL,
  `reason` VARCHAR(500) NOT NULL,
  `document_number` VARCHAR(100) DEFAULT NULL,
  `document_date` DATE DEFAULT NULL,
  `residual_value` DECIMAL(12,2) DEFAULT NULL,
  `approved_by` INT DEFAULT NULL,
  `notes` TEXT DEFAULT NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_writeoffs_equipment` (`equipment_id`),
  CONSTRAINT `fk_writeoffs_equipment` FOREIGN KEY (`equipment_id`) REFERENCES `equipment`(`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_writeoffs_approved_by` FOREIGN KEY (`approved_by`) REFERENCES `employees`(`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- =============================================
-- ДЕМО-ДАННЫЕ
-- =============================================

-- Пользователи (пароль: 123456)
INSERT INTO users (username, password_hash, full_name, email, role) VALUES
('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Администратор Системы', 'admin@college.ru', 'admin'),
('user1', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Иванов Иван Иванович', 'ivanov@college.ru', 'user');

-- Категории
INSERT INTO categories (name, code, description) VALUES
('Компьютерная техника', 'COMP', 'Компьютеры, ноутбуки, серверы'),
('Офисная техника', 'OFFICE', 'Принтеры, сканеры, МФУ'),
('Мебель', 'FURN', 'Столы, стулья, шкафы'),
('Лабораторное оборудование', 'LAB', 'Измерительные приборы, стенды'),
('Проекционное оборудование', 'PROJ', 'Проекторы, экраны'),
('Аудио-видео техника', 'AV', 'Колонки, микрофоны, камеры'),
('Спортивное оборудование', 'SPORT', 'Тренажеры, мячи, маты'),
('Учебное оборудование', 'EDU', 'Учебные пособия, макеты, стенды');

-- Статусы состояния
INSERT INTO condition_status (name, description, color_class) VALUES
('Отличное', 'Новое или почти новое оборудование', 'green'),
('Хорошее', 'Рабочее состояние, незначительный износ', 'blue'),
('Удовлетворительное', 'Работает, но требует внимания', 'yellow'),
('Требует ремонта', 'Неисправно или сильно изношено', 'orange'),
('Списано', 'Не подлежит использованию', 'red');

-- Сотрудники
INSERT INTO employees (full_name, position, department, phone, email, hire_date, is_active) VALUES
('Иванов Иван Иванович', 'Заведующий хозяйством', 'Административный отдел', '+7-999-111-2233', 'ivanov@college.ru', '2015-09-01', 1),
('Петрова Мария Сергеевна', 'Заведующая лабораторией', 'Техническое отделение', '+7-999-222-3344', 'petrova@college.ru', '2018-02-15', 1),
('Сидоров Петр Алексеевич', 'Системный администратор', 'IT-отдел', '+7-999-333-4455', 'sidorov@college.ru', '2020-08-01', 1),
('Козлова Анна Дмитриевна', 'Библиотекарь', 'Библиотека', '+7-999-444-5566', 'kozlova@college.ru', '2017-03-20', 1),
('Морозов Дмитрий Николаевич', 'Преподаватель информатики', 'Отделение информационных технологий', '+7-999-555-6677', 'morozov@college.ru', '2019-09-01', 1);

-- Помещения (с ответственными)
INSERT INTO premises (room_number, building, floor, room_type, area, capacity, status, responsible_id, description) VALUES
('101', 'Корпус А', '1', 'аудитория', 50.00, 30, 'активное', 1, 'Аудитория для лекционных занятий'),
('102', 'Корпус А', '1', 'лаборатория', 65.00, 15, 'активное', 2, 'Лаборатория физики'),
('201', 'Корпус А', '2', 'аудитория', 48.00, 25, 'активное', 1, 'Аудитория для семинаров'),
('202', 'Корпус А', '2', 'компьютерный класс', 60.00, 20, 'активное', 3, 'Компьютерный класс №1'),
('203', 'Корпус А', '2', 'компьютерный класс', 55.00, 18, 'активное', 3, 'Компьютерный класс №2'),
('Склад-1', 'Корпус Б', '1', 'склад', 80.00, NULL, 'активное', 1, 'Основной склад оборудования'),
('Спортзал', 'Корпус Б', '1', 'спортзал', 200.00, 50, 'активное', 1, 'Спортивный зал'),
('Библиотека', 'Корпус А', '1', 'библиотека', 120.00, 40, 'активное', 4, 'Главная библиотека колледжа');

-- Оборудование
INSERT INTO equipment (inventory_number, name, category_id, premise_id, responsible_id, purchase_date, purchase_price, current_value, condition_id, manufacturer, model, serial_number, commissioning_date) VALUES
('INV-2023-001', 'Компьютер преподавателя', 1, 1, 1, '2023-03-15', 45000.00, 45000.00, 2, 'ASUS', 'VivoBook X515', 'SN-VB-001', '2023-03-20'),
('INV-2023-002', 'Проектор мультимедийный', 5, 1, 1, '2023-04-20', 35000.00, 35000.00, 2, 'Epson', 'EB-X41', 'SN-EP-002', '2023-04-25'),
('INV-2022-015', 'МФУ лазерное', 2, 2, 2, '2022-09-10', 25000.00, 22000.00, 2, 'HP', 'LaserJet Pro M428', 'SN-HP-015', '2022-09-15'),
('INV-2024-101', 'Компьютер студенческий #1', 1, 4, 3, '2024-01-15', 38000.00, 38000.00, 1, 'Dell', 'OptiPlex 3080', 'SN-DL-101', '2024-01-20'),
('INV-2024-102', 'Компьютер студенческий #2', 1, 4, 3, '2024-01-15', 38000.00, 38000.00, 1, 'Dell', 'OptiPlex 3080', 'SN-DL-102', '2024-01-20'),
('INV-2024-103', 'Компьютер студенческий #3', 1, 4, 3, '2024-01-15', 38000.00, 38000.00, 1, 'Dell', 'OptiPlex 3080', 'SN-DL-103', '2024-01-20'),
('INV-2024-104', 'Компьютер студенческий #4', 1, 5, 3, '2024-01-15', 38000.00, 38000.00, 1, 'Dell', 'OptiPlex 3080', 'SN-DL-104', '2024-01-20'),
('INV-2024-105', 'Компьютер студенческий #5', 1, 5, 3, '2024-01-15', 38000.00, 38000.00, 1, 'Dell', 'OptiPlex 3080', 'SN-DL-105', '2024-01-20'),
('INV-2021-050', 'Стол ученический двухместный', 3, 1, 1, '2021-08-20', 4500.00, 3500.00, 2, 'МебельПром', 'СТ-2М', NULL, '2021-08-25'),
('INV-2021-051', 'Стул ученический', 3, 1, 1, '2021-08-20', 1200.00, 800.00, 3, 'МебельПром', 'СТУ-1', NULL, '2021-08-25'),
('INV-2023-200', 'Осциллограф цифровой', 4, 2, 2, '2023-05-10', 85000.00, 82000.00, 2, 'Rigol', 'DS1054Z', 'SN-RG-200', '2023-05-15'),
('INV-2020-300', 'Интерактивная доска', 5, 4, 3, '2020-08-15', 120000.00, 90000.00, 2, 'SMART', 'Board 885', 'SN-SM-300', '2020-08-20'),
('INV-2019-400', 'Принтер лазерный', 2, 8, 4, '2019-03-10', 15000.00, 8000.00, 3, 'Canon', 'LBP6030', 'SN-CN-400', '2019-03-15');

-- История оборудования (примеры)
INSERT INTO equipment_history (equipment_id, change_type, change_date, old_value, new_value, reason, performed_by, notes) VALUES
(1, 'создание', '2023-03-15 10:00:00', NULL, 'Компьютер преподавателя', 'Закупка нового оборудования', 1, 'Первичная постановка на учет'),
(1, 'перемещение', '2023-03-20 14:30:00', NULL, 'Корпус А, 101', 1, 'Установка на рабочее место', 1, 'Перемещено со склада'),
(4, 'создание', '2024-01-15 09:00:00', NULL, 'Компьютер студенческий #1', 'Обновление компьютерного класса', 1, 'Поставка новых ПК'),
(4, 'смена_состояния', '2024-06-01 11:00:00', 'Отличное', 'Хорошее', 1, 'Плановая проверка', 1, 'Незначительный износ после эксплуатации');

-- Обновляем историю с правильными связями
UPDATE equipment_history SET new_premise_id = 1 WHERE equipment_id = 1 AND change_type = 'перемещение';
UPDATE equipment_history SET old_condition_id = 1, new_condition_id = 2 WHERE equipment_id = 4 AND change_type = 'смена_состояния';

-- Инвентаризации
INSERT INTO inventories (inventory_number, start_date, end_date, status, responsible_id, notes) VALUES
('ИНВ-2024-001', '2024-01-15', '2024-01-25', 'завершена', 1, 'Плановая инвентаризация начала года'),
('ИНВ-2024-002', '2024-06-01', NULL, 'в процессе', 1, 'Инвентаризация компьютерных классов');

-- =============================================
-- ПРЕДСТАВЛЕНИЯ (VIEWS)
-- =============================================

CREATE VIEW `vw_equipment_details` AS
SELECT 
    e.id,
    e.inventory_number,
    e.name,
    e.category_id,
    e.premise_id,
    e.responsible_id,
    e.condition_id,
    c.name AS category,
    CONCAT(p.building, ', ', p.room_number) AS location,
    cs.name AS `condition`,
    e.current_value AS price,
    e.purchase_price,
    e.manufacturer,
    e.model,
    e.serial_number,
    e.purchase_date,
    e.warranty_until,
    e.commissioning_date,
    e.is_active,
    emp.full_name AS responsible,
    e.description,
    e.created_at,
    e.updated_at
FROM equipment e
LEFT JOIN categories c ON e.category_id = c.id
LEFT JOIN premises p ON e.premise_id = p.id
LEFT JOIN condition_status cs ON e.condition_id = cs.id
LEFT JOIN employees emp ON e.responsible_id = emp.id;

CREATE VIEW `vw_premises_with_equipment` AS
SELECT 
    p.id,
    p.room_number,
    p.building,
    p.floor,
    p.room_type,
    p.area,
    p.capacity,
    p.status,
    p.description,
    p.responsible_id,
    emp.full_name AS responsible_name,
    COUNT(e.id) AS equipment_count,
    COALESCE(SUM(e.current_value), 0) AS total_value
FROM premises p
LEFT JOIN equipment e ON p.id = e.premise_id AND e.is_active = 1
LEFT JOIN employees emp ON p.responsible_id = emp.id
GROUP BY p.id, p.room_number, p.building, p.floor, p.room_type, p.area, p.capacity, p.status, p.description, p.responsible_id, emp.full_name;

CREATE VIEW `vw_employees_with_equipment` AS
SELECT 
    emp.id,
    emp.full_name,
    emp.position,
    emp.department,
    emp.phone,
    emp.email,
    emp.hire_date,
    emp.is_active,
    COUNT(DISTINCT e.id) AS equipment_count,
    COUNT(DISTINCT p.id) AS premises_count,
    COALESCE(SUM(e.current_value), 0) AS total_equipment_value
FROM employees emp
LEFT JOIN equipment e ON emp.id = e.responsible_id AND e.is_active = 1
LEFT JOIN premises p ON emp.id = p.responsible_id
GROUP BY emp.id, emp.full_name, emp.position, emp.department, emp.phone, emp.email, emp.hire_date, emp.is_active;

CREATE VIEW `vw_equipment_history_details` AS
SELECT 
    h.id,
    h.equipment_id,
    h.change_type,
    h.change_date,
    h.old_value,
    h.new_value,
    h.reason,
    h.notes,
    e.inventory_number,
    e.name AS equipment_name,
    op.room_number AS old_premise_number,
    op.building AS old_premise_building,
    np.room_number AS new_premise_number,
    np.building AS new_premise_building,
    oe.full_name AS old_responsible_name,
    ne.full_name AS new_responsible_name,
    oc.name AS old_condition_name,
    nc.name AS new_condition_name,
    u.full_name AS performed_by_name
FROM equipment_history h
LEFT JOIN equipment e ON h.equipment_id = e.id
LEFT JOIN premises op ON h.old_premise_id = op.id
LEFT JOIN premises np ON h.new_premise_id = np.id
LEFT JOIN employees oe ON h.old_responsible_id = oe.id
LEFT JOIN employees ne ON h.new_responsible_id = ne.id
LEFT JOIN condition_status oc ON h.old_condition_id = oc.id
LEFT JOIN condition_status nc ON h.new_condition_id = nc.id
LEFT JOIN users u ON h.performed_by = u.id
ORDER BY h.change_date DESC;

SELECT 'База данных v2.0 создана успешно!' AS msg;
SELECT 'Пользователи: admin/123456, user1/123456' AS info;
