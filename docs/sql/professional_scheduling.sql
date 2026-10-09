-- Professional Scheduling Schema
-- Date: 2026-03-11
-- Notes:
-- - Scoped to the existing multi-tenant model via restaurant_id
-- - Built for a single-provider professional scheduling product
-- - Uses VARCHAR columns instead of ENUM for typed/status fields

CREATE TABLE IF NOT EXISTS professional_profiles (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    restaurant_id INT UNSIGNED NOT NULL,
    owner_user_id INT UNSIGNED NOT NULL,
    business_name VARCHAR(255) NOT NULL,
    display_name VARCHAR(255) NOT NULL,
    business_phone VARCHAR(20) NULL,
    business_email VARCHAR(255) NULL,
    timezone VARCHAR(64) NOT NULL DEFAULT 'America/New_York',
    booking_slug VARCHAR(150) NOT NULL,
    slot_interval_minutes SMALLINT UNSIGNED NOT NULL DEFAULT 30,
    default_buffer_before_minutes SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    default_buffer_after_minutes SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    minimum_booking_notice_hours SMALLINT UNSIGNED NOT NULL DEFAULT 2,
    maximum_booking_horizon_days SMALLINT UNSIGNED NOT NULL DEFAULT 90,
    default_location_type VARCHAR(30) NOT NULL DEFAULT 'in_person',
    default_location_label VARCHAR(255) NULL,
    booking_instructions TEXT NULL,
    cancellation_policy TEXT NULL,
    cancellation_notice_hours SMALLINT UNSIGNED NOT NULL DEFAULT 24,
    is_public_booking_enabled TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_prof_profiles_restaurant (restaurant_id),
    UNIQUE KEY uk_prof_profiles_owner_user (owner_user_id),
    UNIQUE KEY uk_prof_profiles_booking_slug (booking_slug),
    CONSTRAINT fk_prof_profiles_restaurant FOREIGN KEY (restaurant_id)
        REFERENCES restaurants(id) ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_prof_profiles_owner_user FOREIGN KEY (owner_user_id)
        REFERENCES users(id) ON UPDATE CASCADE ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS professional_services (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    restaurant_id INT UNSIGNED NOT NULL,
    name VARCHAR(255) NOT NULL,
    description TEXT NULL,
    duration_minutes SMALLINT UNSIGNED NOT NULL,
    buffer_before_minutes SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    buffer_after_minutes SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    price DECIMAL(10,2) NULL,
    currency_code VARCHAR(10) NOT NULL DEFAULT 'USD',
    location_type VARCHAR(30) NULL,
    location_label VARCHAR(255) NULL,
    color VARCHAR(20) NULL,
    sort_order INT UNSIGNED NOT NULL DEFAULT 0,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    is_public_bookable TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_prof_services_restaurant_active (restaurant_id, is_active, sort_order),
    INDEX idx_prof_services_restaurant_public (restaurant_id, is_public_bookable, is_active),
    CONSTRAINT fk_prof_services_restaurant FOREIGN KEY (restaurant_id)
        REFERENCES restaurants(id) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS professional_availability_rules (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    restaurant_id INT UNSIGNED NOT NULL,
    weekday TINYINT UNSIGNED NOT NULL COMMENT '0=Sunday through 6=Saturday',
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    location_type VARCHAR(30) NULL,
    location_label VARCHAR(255) NULL,
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_prof_availability_restaurant_day (restaurant_id, weekday, is_active),
    CONSTRAINT fk_prof_availability_restaurant FOREIGN KEY (restaurant_id)
        REFERENCES restaurants(id) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS professional_time_off (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    restaurant_id INT UNSIGNED NOT NULL,
    starts_at DATETIME NOT NULL,
    ends_at DATETIME NOT NULL,
    reason VARCHAR(255) NULL,
    notes TEXT NULL,
    is_all_day TINYINT(1) NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_prof_time_off_restaurant_range (restaurant_id, starts_at, ends_at),
    CONSTRAINT fk_prof_time_off_restaurant FOREIGN KEY (restaurant_id)
        REFERENCES restaurants(id) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS professional_clients (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    restaurant_id INT UNSIGNED NOT NULL,
    first_name VARCHAR(120) NOT NULL,
    last_name VARCHAR(120) NOT NULL,
    email VARCHAR(255) NULL,
    phone VARCHAR(20) NULL,
    birth_date DATE NULL,
    notes TEXT NULL,
    internal_notes TEXT NULL,
    service_address_line1 VARCHAR(255) NULL,
    service_city VARCHAR(100) NULL,
    service_state VARCHAR(50) NULL,
    service_postal_code VARCHAR(20) NULL,
    last_service_date DATE NULL,
    preferred_contact_method VARCHAR(20) NULL,
    marketing_opt_in TINYINT(1) NOT NULL DEFAULT 0,
    last_appointment_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_prof_clients_restaurant_name (restaurant_id, last_name, first_name),
    INDEX idx_prof_clients_restaurant_email (restaurant_id, email),
    INDEX idx_prof_clients_restaurant_phone (restaurant_id, phone),
    CONSTRAINT fk_prof_clients_restaurant FOREIGN KEY (restaurant_id)
        REFERENCES restaurants(id) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS professional_appointments (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    restaurant_id INT UNSIGNED NOT NULL,
    professional_user_id INT UNSIGNED NOT NULL,
    client_id INT UNSIGNED NOT NULL,
    service_id INT UNSIGNED NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'confirmed' COMMENT 'pending, confirmed, completed, cancelled, no_show',
    source VARCHAR(30) NOT NULL DEFAULT 'staff' COMMENT 'staff, public_booking, imported, api',
    appointment_date DATE NOT NULL,
    start_at DATETIME NOT NULL,
    end_at DATETIME NOT NULL,
    service_name VARCHAR(255) NOT NULL,
    duration_minutes SMALLINT UNSIGNED NOT NULL,
    buffer_before_minutes SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    buffer_after_minutes SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    price DECIMAL(10,2) NULL,
    currency_code VARCHAR(10) NOT NULL DEFAULT 'USD',
    location_type VARCHAR(30) NULL,
    location_label VARCHAR(255) NULL,
    confirmation_code VARCHAR(32) NULL,
    client_notes TEXT NULL,
    internal_notes TEXT NULL,
    cancelled_at DATETIME NULL,
    completed_at DATETIME NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_prof_appointments_confirmation_code (confirmation_code),
    INDEX idx_prof_appointments_restaurant_start (restaurant_id, start_at),
    INDEX idx_prof_appointments_restaurant_status_date (restaurant_id, status, appointment_date, start_at),
    INDEX idx_prof_appointments_provider_range (professional_user_id, start_at, end_at),
    INDEX idx_prof_appointments_client (client_id, start_at),
    CONSTRAINT fk_prof_appointments_restaurant FOREIGN KEY (restaurant_id)
        REFERENCES restaurants(id) ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_prof_appointments_professional_user FOREIGN KEY (professional_user_id)
        REFERENCES users(id) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_prof_appointments_client FOREIGN KEY (client_id)
        REFERENCES professional_clients(id) ON UPDATE CASCADE ON DELETE RESTRICT,
    CONSTRAINT fk_prof_appointments_service FOREIGN KEY (service_id)
        REFERENCES professional_services(id) ON UPDATE CASCADE ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
