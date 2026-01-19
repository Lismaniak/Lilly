CREATE DATABASE IF NOT EXISTS lilly_sandbox CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

GRANT ALL PRIVILEGES ON lilly_sandbox.* TO 'lilly'@'%';

FLUSH PRIVILEGES;
