<?php
// Настройки подключения к БД и администратора
const DB_HOST = 'localhost';
const DB_NAME = 'hotel';
const DB_USER = 'root';
const DB_PASS = '';

const ADMIN_LOGIN = 'hotel123';
const ADMIN_PASS  = 'adminHotel';

date_default_timezone_set('Europe/Moscow');
session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax']);
session_start();
