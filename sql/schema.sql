CREATE DATABASE IF NOT EXISTS hotel CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE hotel;

DROP TABLE IF EXISTS bookings;
DROP TABLE IF EXISTS rooms;
DROP TABLE IF EXISTS categories;

CREATE TABLE categories (
    id   TINYINT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    name VARCHAR(50) NOT NULL UNIQUE
) ENGINE=InnoDB;

CREATE TABLE rooms (
    id          INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    category_id TINYINT UNSIGNED NOT NULL,
    number      VARCHAR(10) NOT NULL UNIQUE,
    price       INT UNSIGNED NOT NULL COMMENT 'руб./сутки',
    capacity    TINYINT UNSIGNED NOT NULL,
    area        SMALLINT UNSIGNED NOT NULL COMMENT 'м2',
    features    VARCHAR(500) NOT NULL COMMENT 'через ;',
    image       VARCHAR(100) NOT NULL,
    FOREIGN KEY (category_id) REFERENCES categories(id)
) ENGINE=InnoDB;

CREATE TABLE bookings (
    id         INT UNSIGNED PRIMARY KEY AUTO_INCREMENT,
    room_id    INT UNSIGNED NOT NULL,
    first_name VARCHAR(50)  NOT NULL,
    last_name  VARCHAR(50)  NOT NULL,
    phone      VARCHAR(20)  NOT NULL,
    email      VARCHAR(254) NOT NULL,
    check_in   DATE NOT NULL,
    check_out  DATE NOT NULL,
    status     ENUM('new','approved','rejected') NOT NULL DEFAULT 'new',
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (room_id) REFERENCES rooms(id) ON DELETE CASCADE,
    INDEX idx_room_status (room_id, status, check_in, check_out),
    CONSTRAINT chk_dates CHECK (check_out > check_in)
) ENGINE=InnoDB;

INSERT INTO categories (name) VALUES ('Стандартный'), ('Студия'), ('Люкс');

INSERT INTO rooms (category_id, number, price, capacity, area, features, image) VALUES
(1,'101',3000,2,18,'Полный санузел;Двуспальная кровать;Телевизор','standart.png'),
(1,'102',3400,2,20,'Полный санузел;Двуспальная кровать;Телевизор','standart.png'),
(1,'103',3800,2,22,'Полный санузел;Двуспальная кровать;Телевизор','standart.png'),
(2,'201',4600,2,28,'Кухонный уголок;Полный санузел;Двуспальная кровать;Телевизор;Холодильник','studio.jpg'),
(2,'202',5400,3,32,'Кухонный уголок;Полный санузел;Двуспальная кровать;Телевизор;Холодильник','studio.jpg'),
(2,'203',6200,3,35,'Кухонный уголок;Полный санузел;Двуспальная кровать;Телевизор;Холодильник','studio.jpg'),
(3,'301',8500,3,45,'Двухкомнатный;Полный санузел;Двуспальная кровать;Телевизор;Холодильник;Кондиционер','lux.png'),
(3,'302',11500,4,60,'Двухкомнатный;Полный санузел;Двуспальная кровать;Телевизор;Холодильник;Кондиционер','lux.png'),
(3,'303',15000,4,75,'Двухкомнатный;Полный санузел;Двуспальная кровать;Телевизор;Холодильник;Кондиционер','lux.png');
