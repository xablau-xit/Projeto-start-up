<?php

declare(strict_types=1);

const APP_NAME = 'RID';
const APP_TAGLINE = 'Registro Inteligente de Serviços';
const DB_PATH = __DIR__ . '/../storage/rid.sqlite';
const UPLOAD_DIR = __DIR__ . '/../storage/uploads/';
const MAX_UPLOAD_SIZE = 5 * 1024 * 1024; // 5MB
const ALLOWED_IMAGE_TYPES = ['image/jpeg', 'image/png', 'image/webp'];

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
