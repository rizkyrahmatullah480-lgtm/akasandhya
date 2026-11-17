CREATE DATABASE IF NOT EXISTS akasandhya;
USE rohmatul;

CREATE TABLE tamu (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nama VARCHAR(100) NOT NULL,
    no_wa VARCHAR(20) NOT NULL,
    kategori VARCHAR(50),
    status_undangan ENUM('belum', 'terkirim', 'gagal') DEFAULT 'belum',
    status_kehadiran ENUM('belum_konfirmasi', 'hadir', 'tidak_hadir') DEFAULT 'belum_konfirmasi',
    ucapan TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE admin (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL
);

INSERT INTO admin (username, password) VALUES ('admin', 'admin123');
