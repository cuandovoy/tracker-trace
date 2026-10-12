CREATE DATABASE IF NOT EXISTS truck_tracker_test;
CREATE USER IF NOT EXISTS 'truck_test'@'%' IDENTIFIED BY 'local-test-password';
GRANT ALL PRIVILEGES ON truck_tracker_test.* TO 'truck_test'@'%';
