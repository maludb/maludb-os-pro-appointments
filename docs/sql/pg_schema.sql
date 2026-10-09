-- PostgreSQL Schema for ZozoCal Restaurant Reservation System
-- Converted from MySQL/MariaDB - 2026-05-17
-- Database: zozocal

-- Trigger function for updated_at columns (replaces MySQL ON UPDATE CURRENT_TIMESTAMP)
CREATE OR REPLACE FUNCTION update_updated_at()
RETURNS TRIGGER AS $$
BEGIN
    NEW.updated_at = CURRENT_TIMESTAMP;
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

-- ============================================================
-- 1. restaurants
-- ============================================================
CREATE TABLE restaurants (
    id SERIAL PRIMARY KEY,
    name VARCHAR(200) NOT NULL,
    slug VARCHAR(100) NOT NULL,
    phone VARCHAR(20) DEFAULT NULL,
    email VARCHAR(255) DEFAULT NULL,
    address_line1 VARCHAR(255) DEFAULT NULL,
    address_line2 VARCHAR(255) DEFAULT NULL,
    city VARCHAR(100) DEFAULT NULL,
    state VARCHAR(50) DEFAULT NULL,
    postal_code VARCHAR(20) DEFAULT NULL,
    website VARCHAR(255) DEFAULT NULL,
    timezone VARCHAR(50) NOT NULL DEFAULT 'America/Chicago',
    location_type VARCHAR(20) NOT NULL DEFAULT 'professional',
    is_active SMALLINT NOT NULL DEFAULT 1,
    status VARCHAR(10) NOT NULL DEFAULT 'in-setup',
    affiliate_id INTEGER NOT NULL DEFAULT 0,
    billing_user INTEGER NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    prepay_balance DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    CONSTRAINT uk_slug UNIQUE (slug)
);

CREATE INDEX idx_restaurants_active ON restaurants (is_active);

-- ============================================================
-- 2. users
-- ============================================================
CREATE TABLE users (
    id SERIAL PRIMARY KEY,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    company_name VARCHAR(120) DEFAULT NULL,
    email VARCHAR(255) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    google_id VARCHAR(255) DEFAULT NULL,
    auth_provider VARCHAR(20) NOT NULL DEFAULT 'local',
    user_type VARCHAR(20) NOT NULL DEFAULT 'user',
    user_mode VARCHAR(20) DEFAULT NULL,
    product_type VARCHAR(25) NOT NULL DEFAULT 'restaurant',
    is_platform_admin SMALLINT NOT NULL DEFAULT 0,
    role VARCHAR(20) DEFAULT 'user',
    phone VARCHAR(20) DEFAULT NULL,
    is_active SMALLINT NOT NULL DEFAULT 1,
    is_affiliate SMALLINT NOT NULL DEFAULT 0,
    referred_by INTEGER NOT NULL DEFAULT 0,
    referral_code VARCHAR(5) DEFAULT NULL,
    last_login_at TIMESTAMP NULL DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT uk_email UNIQUE (email),
    CONSTRAINT idx_google_id UNIQUE (google_id)
);

CREATE INDEX idx_users_active ON users (is_active);

-- ============================================================
-- 3. products
-- ============================================================
CREATE TABLE products (
    id SERIAL PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    slug VARCHAR(100) NOT NULL,
    description TEXT DEFAULT NULL,
    price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    affiliate_price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    billing_interval VARCHAR(50) NOT NULL DEFAULT 'monthly',
    is_active SMALLINT NOT NULL DEFAULT 1,
    sort_order INTEGER NOT NULL DEFAULT 0,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT slug UNIQUE (slug),
    CONSTRAINT chk_products_billing_interval CHECK (billing_interval IN ('monthly','yearly','one_time'))
);

-- ============================================================
-- 4. nav_permissions
-- ============================================================
CREATE TABLE nav_permissions (
    id SERIAL PRIMARY KEY,
    nav_item_id VARCHAR(100) NOT NULL,
    label VARCHAR(100) DEFAULT NULL,
    user_role VARCHAR(50) DEFAULT NULL,
    restaurant_role VARCHAR(50) DEFAULT NULL,
    location_type VARCHAR(50) DEFAULT NULL,
    product_type VARCHAR(50) DEFAULT NULL,
    is_active SMALLINT NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT uk_nav_perm UNIQUE (nav_item_id, user_role, restaurant_role, location_type, product_type)
);

-- ============================================================
-- 5. prompt_templates
-- ============================================================
CREATE TABLE prompt_templates (
    id SERIAL PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description VARCHAR(255) DEFAULT NULL,
    agent_prompt TEXT NOT NULL,
    begin_message TEXT DEFAULT NULL,
    is_active SMALLINT NOT NULL DEFAULT 1,
    sort_order INTEGER NOT NULL DEFAULT 0,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP
);

-- ============================================================
-- 6. landing_page_routes
-- ============================================================
CREATE TABLE landing_page_routes (
    id SERIAL PRIMARY KEY,
    user_id INTEGER NOT NULL DEFAULT 0,
    affiliate_id INTEGER NOT NULL DEFAULT 0,
    host VARCHAR(255) NOT NULL,
    canonical_url VARCHAR(255) NOT NULL,
    page_include VARCHAR(255) NOT NULL,
    description VARCHAR(255) DEFAULT NULL,
    is_active SMALLINT NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT uk_landing_host UNIQUE (host)
);

CREATE INDEX idx_landing_active_host ON landing_page_routes (is_active, host);

-- ============================================================
-- 7. sms_agents
-- ============================================================
CREATE TABLE sms_agents (
    id SERIAL PRIMARY KEY,
    to_number VARCHAR(20) NOT NULL,
    agent_name VARCHAR(100) NOT NULL,
    system_prompt TEXT NOT NULL,
    webhook_url VARCHAR(500) NOT NULL,
    webhook_auth_token VARCHAR(255) DEFAULT NULL,
    llm_model VARCHAR(100) NOT NULL DEFAULT 'claude-sonnet-4-20250514',
    max_tokens INTEGER NOT NULL DEFAULT 1024,
    temperature DECIMAL(3,2) NOT NULL DEFAULT 0.70,
    is_active SMALLINT NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT sms_agents_to_number UNIQUE (to_number)
);

CREATE INDEX idx_sms_agents_to_number ON sms_agents (to_number);

-- ============================================================
-- 8. affiliates
-- ============================================================
CREATE TABLE affiliates (
    id SERIAL PRIMARY KEY,
    user_id INTEGER NOT NULL,
    company_name VARCHAR(255) DEFAULT NULL,
    company_type VARCHAR(50) DEFAULT NULL,
    tax_id VARCHAR(20) DEFAULT NULL,
    business_address VARCHAR(255) DEFAULT NULL,
    business_city VARCHAR(100) DEFAULT NULL,
    business_state VARCHAR(2) DEFAULT NULL,
    business_zip VARCHAR(10) DEFAULT NULL,
    contact_name VARCHAR(255) DEFAULT NULL,
    contact_phone VARCHAR(20) DEFAULT NULL,
    contact_email VARCHAR(255) DEFAULT NULL,
    affiliate_code VARCHAR(50) NOT NULL,
    commission_rate DECIMAL(5,2) NOT NULL DEFAULT 10.00,
    status VARCHAR(50) NOT NULL DEFAULT 'active',
    payout_email VARCHAR(255) DEFAULT NULL,
    payout_method VARCHAR(50) DEFAULT 'paypal',
    notes TEXT DEFAULT NULL,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT affiliates_user_id UNIQUE (user_id),
    CONSTRAINT affiliates_affiliate_code UNIQUE (affiliate_code),
    CONSTRAINT chk_affiliates_company_type CHECK (company_type IN ('c-corp','s-corp','llc','partnership','sole-proprietor','non-profit','other')),
    CONSTRAINT chk_affiliates_status CHECK (status IN ('active','inactive','suspended')),
    CONSTRAINT chk_affiliates_payout_method CHECK (payout_method IN ('paypal','bank_transfer','check')),
    FOREIGN KEY (user_id) REFERENCES users (id)
);

-- ============================================================
-- 9. user_restaurants
-- ============================================================
CREATE TABLE user_restaurants (
    id SERIAL PRIMARY KEY,
    user_id INTEGER NOT NULL,
    restaurant_id INTEGER NOT NULL,
    role VARCHAR(10) NOT NULL DEFAULT 'admin',
    is_active SMALLINT NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT uk_user_restaurant UNIQUE (user_id, restaurant_id),
    CONSTRAINT fk_ur_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_ur_restaurant FOREIGN KEY (restaurant_id) REFERENCES restaurants (id) ON DELETE CASCADE ON UPDATE CASCADE
);

CREATE INDEX idx_user_restaurants_restaurant ON user_restaurants (restaurant_id);
CREATE INDEX idx_user_restaurants_role ON user_restaurants (role);

-- ============================================================
-- 10. settings
-- ============================================================
CREATE TABLE settings (
    id SERIAL PRIMARY KEY,
    restaurant_id INTEGER NOT NULL,
    setting_key VARCHAR(100) NOT NULL,
    setting_value TEXT DEFAULT NULL,
    category VARCHAR(50) NOT NULL DEFAULT 'general',
    description VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT uk_restaurant_setting UNIQUE (restaurant_id, setting_key),
    CONSTRAINT fk_settings_restaurant FOREIGN KEY (restaurant_id) REFERENCES restaurants (id) ON DELETE CASCADE ON UPDATE CASCADE
);

CREATE INDEX idx_settings_category ON settings (restaurant_id, category);

-- ============================================================
-- 11. sections
-- ============================================================
CREATE TABLE sections (
    id SERIAL PRIMARY KEY,
    restaurant_id INTEGER NOT NULL,
    name VARCHAR(100) NOT NULL,
    description VARCHAR(255) DEFAULT NULL,
    display_order INTEGER NOT NULL DEFAULT 0,
    is_active SMALLINT NOT NULL DEFAULT 1,
    open_time TIME DEFAULT NULL,
    close_time TIME DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT uk_restaurant_name UNIQUE (restaurant_id, name),
    CONSTRAINT fk_sections_restaurant FOREIGN KEY (restaurant_id) REFERENCES restaurants (id) ON DELETE CASCADE ON UPDATE CASCADE
);

CREATE INDEX idx_sections_active_order ON sections (restaurant_id, is_active, display_order);

-- ============================================================
-- 12. tables (quoted - reserved word)
-- ============================================================
CREATE TABLE "tables" (
    id SERIAL PRIMARY KEY,
    restaurant_id INTEGER NOT NULL,
    table_name VARCHAR(50) NOT NULL,
    section_id INTEGER NOT NULL,
    min_seats SMALLINT NOT NULL DEFAULT 1,
    max_seats SMALLINT NOT NULL,
    status VARCHAR(50) NOT NULL DEFAULT 'available',
    sort_order INTEGER NOT NULL DEFAULT 0,
    is_active SMALLINT NOT NULL DEFAULT 1,
    notes VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT uk_restaurant_table UNIQUE (restaurant_id, table_name),
    CONSTRAINT chk_tables_status CHECK (status IN ('available','occupied','reserved','blocked')),
    CONSTRAINT fk_tables_restaurant FOREIGN KEY (restaurant_id) REFERENCES restaurants (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_tables_section FOREIGN KEY (section_id) REFERENCES sections (id) ON UPDATE CASCADE
);

CREATE INDEX idx_tables_section ON "tables" (section_id);
CREATE INDEX idx_tables_status ON "tables" (restaurant_id, status);
CREATE INDEX idx_tables_seats ON "tables" (restaurant_id, min_seats, max_seats);
CREATE INDEX idx_tables_active_section ON "tables" (restaurant_id, is_active, section_id, sort_order);

-- ============================================================
-- 13. combinable_tables
-- ============================================================
CREATE TABLE combinable_tables (
    id SERIAL PRIMARY KEY,
    table_id INTEGER NOT NULL,
    combines_with_table_id INTEGER NOT NULL,
    combined_max_seats SMALLINT NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT uk_combination UNIQUE (table_id, combines_with_table_id),
    CONSTRAINT fk_combinable_table FOREIGN KEY (table_id) REFERENCES "tables" (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_combinable_with FOREIGN KEY (combines_with_table_id) REFERENCES "tables" (id) ON DELETE CASCADE ON UPDATE CASCADE
);

-- ============================================================
-- 14. guests
-- ============================================================
CREATE TABLE guests (
    id SERIAL PRIMARY KEY,
    restaurant_id INTEGER NOT NULL,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    email VARCHAR(255) DEFAULT NULL,
    phone VARCHAR(20) DEFAULT NULL,
    address_line1 VARCHAR(255) DEFAULT NULL,
    address_line2 VARCHAR(255) DEFAULT NULL,
    city VARCHAR(100) DEFAULT NULL,
    state VARCHAR(50) DEFAULT NULL,
    postal_code VARCHAR(20) DEFAULT NULL,
    tags VARCHAR(500) DEFAULT NULL,
    dietary_restrictions VARCHAR(500) DEFAULT NULL,
    allergies VARCHAR(500) DEFAULT NULL,
    seating_preference VARCHAR(100) DEFAULT NULL,
    favorite_server VARCHAR(100) DEFAULT NULL,
    notes TEXT DEFAULT NULL,
    visit_count INTEGER NOT NULL DEFAULT 0,
    noshow_count INTEGER NOT NULL DEFAULT 0,
    last_visit_at DATE DEFAULT NULL,
    is_active SMALLINT NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    do_not_call SMALLINT NOT NULL DEFAULT 0,
    do_not_email SMALLINT NOT NULL DEFAULT 0,
    do_not_text SMALLINT NOT NULL DEFAULT 0,
    CONSTRAINT fk_guests_restaurant FOREIGN KEY (restaurant_id) REFERENCES restaurants (id) ON DELETE CASCADE ON UPDATE CASCADE
);

CREATE INDEX idx_guests_restaurant ON guests (restaurant_id);
CREATE INDEX idx_guests_email ON guests (restaurant_id, email);
CREATE INDEX idx_guests_phone ON guests (restaurant_id, phone);
CREATE INDEX idx_guests_name ON guests (restaurant_id, last_name, first_name);
CREATE INDEX idx_guests_tags ON guests (tags);
CREATE INDEX idx_guests_last_visit ON guests (last_visit_at);

-- ============================================================
-- 15. events
-- ============================================================
CREATE TABLE events (
    id SERIAL PRIMARY KEY,
    restaurant_id INTEGER NOT NULL,
    name VARCHAR(200) NOT NULL,
    description TEXT DEFAULT NULL,
    event_date DATE NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME DEFAULT NULL,
    max_capacity INTEGER NOT NULL DEFAULT 50,
    price_per_person DECIMAL(8,2) DEFAULT NULL,
    status VARCHAR(50) NOT NULL DEFAULT 'draft',
    is_public SMALLINT NOT NULL DEFAULT 1,
    notes TEXT DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT chk_events_status CHECK (status IN ('draft','published','cancelled','completed')),
    CONSTRAINT fk_events_restaurant FOREIGN KEY (restaurant_id) REFERENCES restaurants (id) ON DELETE CASCADE ON UPDATE CASCADE
);

CREATE INDEX idx_events_restaurant ON events (restaurant_id);
CREATE INDEX idx_events_date ON events (restaurant_id, event_date);
CREATE INDEX idx_events_status ON events (restaurant_id, status);

-- ============================================================
-- 16. reservations
-- ============================================================
CREATE TABLE reservations (
    id SERIAL PRIMARY KEY,
    restaurant_id INTEGER NOT NULL,
    guest_id INTEGER NOT NULL,
    table_id INTEGER DEFAULT NULL,
    reservation_date DATE NOT NULL,
    reservation_time TIME NOT NULL,
    party_size SMALLINT NOT NULL,
    status VARCHAR(50) NOT NULL DEFAULT 'pending',
    meal_status VARCHAR(50) DEFAULT NULL,
    ordering_at TIMESTAMP NULL DEFAULT NULL,
    eating_at TIMESTAMP NULL DEFAULT NULL,
    check_dropped_at TIMESTAMP NULL DEFAULT NULL,
    paid_at TIMESTAMP NULL DEFAULT NULL,
    confirmation_code VARCHAR(20) NOT NULL,
    source VARCHAR(50) NOT NULL DEFAULT 'staff',
    special_requests TEXT DEFAULT NULL,
    turn_time_minutes SMALLINT DEFAULT NULL,
    cancellation_reason VARCHAR(255) DEFAULT NULL,
    cancelled_at TIMESTAMP NULL DEFAULT NULL,
    seated_at TIMESTAMP NULL DEFAULT NULL,
    checked_in_at TIMESTAMP NULL DEFAULT NULL,
    completed_at TIMESTAMP NULL DEFAULT NULL,
    internal_notes TEXT DEFAULT NULL,
    created_by INTEGER DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    event_id INTEGER DEFAULT NULL,
    CONSTRAINT uk_confirmation_code UNIQUE (confirmation_code),
    CONSTRAINT chk_reservations_status CHECK (status IN ('pending','confirmed','seated','completed','no_show','cancelled')),
    CONSTRAINT chk_reservations_meal_status CHECK (meal_status IN ('seated','ordering','eating','check_dropped','paid')),
    CONSTRAINT chk_reservations_source CHECK (source IN ('online','phone','walk_in','staff')),
    CONSTRAINT fk_reservations_created_by FOREIGN KEY (created_by) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_reservations_event FOREIGN KEY (event_id) REFERENCES events (id) ON DELETE SET NULL,
    CONSTRAINT fk_reservations_guest FOREIGN KEY (guest_id) REFERENCES guests (id) ON UPDATE CASCADE,
    CONSTRAINT fk_reservations_restaurant FOREIGN KEY (restaurant_id) REFERENCES restaurants (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_reservations_table FOREIGN KEY (table_id) REFERENCES "tables" (id) ON DELETE SET NULL ON UPDATE CASCADE
);

CREATE INDEX idx_reservations_restaurant_date ON reservations (restaurant_id, reservation_date, reservation_time);
CREATE INDEX idx_reservations_date_status ON reservations (restaurant_id, reservation_date, status);
CREATE INDEX idx_reservations_guest ON reservations (guest_id);
CREATE INDEX idx_reservations_table_date ON reservations (table_id, reservation_date);
CREATE INDEX idx_reservations_status ON reservations (restaurant_id, status);
CREATE INDEX idx_reservations_source ON reservations (restaurant_id, source);
CREATE INDEX idx_reservations_created_at ON reservations (restaurant_id, created_at);
CREATE INDEX idx_reservations_created_by ON reservations (created_by);
CREATE INDEX idx_reservations_event ON reservations (event_id);

-- ============================================================
-- 17. reservation_tables
-- ============================================================
CREATE TABLE reservation_tables (
    id SERIAL PRIMARY KEY,
    reservation_id INTEGER NOT NULL,
    table_id INTEGER NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT uk_reservation_table UNIQUE (reservation_id, table_id),
    CONSTRAINT fk_restables_reservation FOREIGN KEY (reservation_id) REFERENCES reservations (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_restables_table FOREIGN KEY (table_id) REFERENCES "tables" (id) ON DELETE CASCADE ON UPDATE CASCADE
);

-- ============================================================
-- 18. waitlist
-- ============================================================
CREATE TABLE waitlist (
    id SERIAL PRIMARY KEY,
    restaurant_id INTEGER NOT NULL,
    guest_id INTEGER DEFAULT NULL,
    guest_name VARCHAR(200) DEFAULT NULL,
    party_size SMALLINT NOT NULL,
    phone VARCHAR(20) DEFAULT NULL,
    estimated_wait_minutes SMALLINT DEFAULT NULL,
    status VARCHAR(50) NOT NULL DEFAULT 'waiting',
    queue_position INTEGER NOT NULL DEFAULT 0,
    seating_preference VARCHAR(100) DEFAULT NULL,
    notes VARCHAR(500) DEFAULT NULL,
    notified_at TIMESTAMP NULL DEFAULT NULL,
    notify_count SMALLINT NOT NULL DEFAULT 0,
    seated_at TIMESTAMP NULL DEFAULT NULL,
    reservation_id INTEGER DEFAULT NULL,
    added_by INTEGER DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT chk_waitlist_status CHECK (status IN ('waiting','notified','seated','left','no_response')),
    CONSTRAINT fk_waitlist_added_by FOREIGN KEY (added_by) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_waitlist_guest FOREIGN KEY (guest_id) REFERENCES guests (id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_waitlist_reservation FOREIGN KEY (reservation_id) REFERENCES reservations (id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_waitlist_restaurant FOREIGN KEY (restaurant_id) REFERENCES restaurants (id) ON DELETE CASCADE ON UPDATE CASCADE
);

CREATE INDEX idx_waitlist_restaurant_status ON waitlist (restaurant_id, status);
CREATE INDEX idx_waitlist_queue ON waitlist (restaurant_id, status, queue_position);
CREATE INDEX idx_waitlist_date ON waitlist (restaurant_id, created_at);

-- ============================================================
-- 19. operating_hours
-- ============================================================
CREATE TABLE operating_hours (
    id SERIAL PRIMARY KEY,
    restaurant_id INTEGER NOT NULL,
    day_of_week SMALLINT NOT NULL,
    service_name VARCHAR(50) NOT NULL,
    open_time TIME NOT NULL,
    close_time TIME NOT NULL,
    first_seating TIME NOT NULL,
    last_seating TIME NOT NULL,
    is_active SMALLINT NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT uk_restaurant_day_service UNIQUE (restaurant_id, day_of_week, service_name),
    CONSTRAINT fk_hours_restaurant FOREIGN KEY (restaurant_id) REFERENCES restaurants (id) ON DELETE CASCADE ON UPDATE CASCADE
);

CREATE INDEX idx_operating_hours_restaurant_day ON operating_hours (restaurant_id, day_of_week);

-- ============================================================
-- 20. special_dates
-- ============================================================
CREATE TABLE special_dates (
    id SERIAL PRIMARY KEY,
    restaurant_id INTEGER NOT NULL,
    special_date DATE NOT NULL,
    date_type VARCHAR(50) NOT NULL,
    label VARCHAR(100) NOT NULL,
    is_closed SMALLINT NOT NULL DEFAULT 0,
    custom_open_time TIME DEFAULT NULL,
    custom_close_time TIME DEFAULT NULL,
    custom_first_seating TIME DEFAULT NULL,
    custom_last_seating TIME DEFAULT NULL,
    max_covers INTEGER DEFAULT NULL,
    notes TEXT DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    outbound_voice_agent VARCHAR(80) DEFAULT NULL,
    CONSTRAINT uk_restaurant_date_type UNIQUE (restaurant_id, special_date, date_type),
    CONSTRAINT chk_special_dates_date_type CHECK (date_type IN ('holiday','blackout','event','modified_hours')),
    CONSTRAINT fk_special_restaurant FOREIGN KEY (restaurant_id) REFERENCES restaurants (id) ON DELETE CASCADE ON UPDATE CASCADE
);

CREATE INDEX idx_special_dates_restaurant_date ON special_dates (restaurant_id, special_date);

-- ============================================================
-- 21. turn_times
-- ============================================================
CREATE TABLE turn_times (
    id SERIAL PRIMARY KEY,
    restaurant_id INTEGER NOT NULL,
    min_party_size SMALLINT NOT NULL,
    max_party_size SMALLINT NOT NULL,
    service_period VARCHAR(50) NOT NULL DEFAULT 'all',
    duration_minutes SMALLINT NOT NULL,
    buffer_minutes SMALLINT NOT NULL DEFAULT 15,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT uk_restaurant_party_service UNIQUE (restaurant_id, min_party_size, max_party_size, service_period),
    CONSTRAINT fk_turntimes_restaurant FOREIGN KEY (restaurant_id) REFERENCES restaurants (id) ON DELETE CASCADE ON UPDATE CASCADE
);

-- ============================================================
-- 22. notifications_log
-- ============================================================
CREATE TABLE notifications_log (
    id SERIAL PRIMARY KEY,
    restaurant_id INTEGER NOT NULL,
    reservation_id INTEGER DEFAULT NULL,
    waitlist_id INTEGER DEFAULT NULL,
    guest_id INTEGER DEFAULT NULL,
    notification_type VARCHAR(50) NOT NULL,
    channel VARCHAR(50) NOT NULL,
    recipient VARCHAR(255) NOT NULL,
    subject VARCHAR(255) DEFAULT NULL,
    body TEXT DEFAULT NULL,
    status VARCHAR(50) NOT NULL DEFAULT 'pending',
    provider_response TEXT DEFAULT NULL,
    sent_at TIMESTAMP NULL DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT chk_notifications_log_notification_type CHECK (notification_type IN ('confirmation','reminder','modification','cancellation','waitlist_ready','noshow_followup','staff_alert','thank_you')),
    CONSTRAINT chk_notifications_log_channel CHECK (channel IN ('email','sms')),
    CONSTRAINT chk_notifications_log_status CHECK (status IN ('pending','sent','failed','bounced')),
    CONSTRAINT fk_notif_guest FOREIGN KEY (guest_id) REFERENCES guests (id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_notif_reservation FOREIGN KEY (reservation_id) REFERENCES reservations (id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_notif_restaurant FOREIGN KEY (restaurant_id) REFERENCES restaurants (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_notif_waitlist FOREIGN KEY (waitlist_id) REFERENCES waitlist (id) ON DELETE SET NULL ON UPDATE CASCADE
);

CREATE INDEX idx_notifications_log_restaurant ON notifications_log (restaurant_id);
CREATE INDEX idx_notifications_log_reservation ON notifications_log (reservation_id);
CREATE INDEX idx_notifications_log_guest ON notifications_log (guest_id);
CREATE INDEX idx_notifications_log_type_status ON notifications_log (restaurant_id, notification_type, status);
CREATE INDEX idx_notifications_log_sent_at ON notifications_log (restaurant_id, sent_at);

-- ============================================================
-- 23. activity_log
-- ============================================================
CREATE TABLE activity_log (
    id SERIAL PRIMARY KEY,
    restaurant_id INTEGER DEFAULT NULL,
    user_id INTEGER DEFAULT NULL,
    action VARCHAR(50) NOT NULL,
    entity_type VARCHAR(50) NOT NULL,
    entity_id INTEGER DEFAULT NULL,
    description VARCHAR(500) DEFAULT NULL,
    old_value TEXT DEFAULT NULL,
    new_value TEXT DEFAULT NULL,
    ip_address VARCHAR(45) DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_activity_restaurant FOREIGN KEY (restaurant_id) REFERENCES restaurants (id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_activity_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE SET NULL ON UPDATE CASCADE
);

CREATE INDEX idx_activity_log_restaurant ON activity_log (restaurant_id);
CREATE INDEX idx_activity_log_user ON activity_log (user_id);
CREATE INDEX idx_activity_log_entity ON activity_log (entity_type, entity_id);
CREATE INDEX idx_activity_log_action ON activity_log (action);
CREATE INDEX idx_activity_log_created ON activity_log (created_at);

-- ============================================================
-- 24. email_templates
-- ============================================================
CREATE TABLE email_templates (
    id SERIAL PRIMARY KEY,
    restaurant_id INTEGER NOT NULL,
    template_key VARCHAR(50) NOT NULL,
    subject VARCHAR(255) NOT NULL,
    body_html TEXT NOT NULL,
    body_text TEXT DEFAULT NULL,
    placeholders TEXT DEFAULT NULL,
    is_active SMALLINT NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT uk_restaurant_template UNIQUE (restaurant_id, template_key),
    CONSTRAINT fk_templates_restaurant FOREIGN KEY (restaurant_id) REFERENCES restaurants (id) ON DELETE CASCADE ON UPDATE CASCADE
);

-- ============================================================
-- 25. call_logs
-- ============================================================
CREATE TABLE call_logs (
    id SERIAL PRIMARY KEY,
    restaurant_id INTEGER DEFAULT NULL,
    guest_id INTEGER DEFAULT NULL,
    client_id INTEGER DEFAULT NULL,
    retell_call_id VARCHAR(100) DEFAULT NULL,
    direction VARCHAR(50) NOT NULL DEFAULT 'inbound',
    from_number VARCHAR(20) NOT NULL,
    to_number VARCHAR(20) NOT NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'ended',
    duration_ms INTEGER DEFAULT NULL,
    disconnection_reason VARCHAR(100) DEFAULT NULL,
    transcript TEXT DEFAULT NULL,
    recording_url VARCHAR(500) DEFAULT NULL,
    call_summary TEXT DEFAULT NULL,
    metadata JSONB DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    ended_at TIMESTAMP NULL DEFAULT NULL,
    CONSTRAINT chk_call_logs_direction CHECK (direction IN ('inbound','outbound')),
    CONSTRAINT fk_calllogs_guest FOREIGN KEY (guest_id) REFERENCES guests (id) ON DELETE SET NULL ON UPDATE CASCADE,
    CONSTRAINT fk_calllogs_restaurant FOREIGN KEY (restaurant_id) REFERENCES restaurants (id) ON DELETE SET NULL ON UPDATE CASCADE
);

CREATE INDEX idx_call_logs_restaurant ON call_logs (restaurant_id);
CREATE INDEX idx_call_logs_guest ON call_logs (guest_id);
CREATE INDEX idx_call_logs_retell_call ON call_logs (retell_call_id);
CREATE INDEX idx_call_logs_created ON call_logs (restaurant_id, created_at);
CREATE INDEX idx_call_logs_direction ON call_logs (restaurant_id, direction);
CREATE INDEX idx_call_logs_client ON call_logs (client_id);

-- ============================================================
-- 26. api_tokens
-- ============================================================
CREATE TABLE api_tokens (
    id SERIAL PRIMARY KEY,
    user_id INTEGER NOT NULL,
    restaurant_id INTEGER NOT NULL,
    token_hash VARCHAR(64) NOT NULL,
    device_name VARCHAR(100) DEFAULT NULL,
    expires_at TIMESTAMP NOT NULL,
    last_used_at TIMESTAMP NULL,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT uq_token_hash UNIQUE (token_hash)
);

CREATE INDEX idx_api_tokens_user ON api_tokens (user_id);
CREATE INDEX idx_api_tokens_expires ON api_tokens (expires_at);

-- ============================================================
-- 27. product_features
-- ============================================================
CREATE TABLE product_features (
    id SERIAL PRIMARY KEY,
    product_id INTEGER NOT NULL,
    feature_key VARCHAR(100) NOT NULL,
    feature_label VARCHAR(255) NOT NULL,
    feature_value VARCHAR(255) DEFAULT NULL,
    sort_order INTEGER NOT NULL DEFAULT 0,
    FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE CASCADE
);

CREATE INDEX idx_product_features_product_id ON product_features (product_id);

-- ============================================================
-- 28. subscriptions
-- ============================================================
CREATE TABLE subscriptions (
    id SERIAL PRIMARY KEY,
    restaurant_id INTEGER NOT NULL,
    product_id INTEGER NOT NULL,
    status VARCHAR(50) NOT NULL DEFAULT 'active',
    started_at DATE NOT NULL,
    expires_at DATE DEFAULT NULL,
    cancelled_at TIMESTAMP NULL DEFAULT NULL,
    notes TEXT DEFAULT NULL,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT chk_subscriptions_status CHECK (status IN ('active','past_due','cancelled','trialing')),
    FOREIGN KEY (restaurant_id) REFERENCES restaurants (id),
    FOREIGN KEY (product_id) REFERENCES products (id)
);

CREATE INDEX idx_subscriptions_restaurant_id ON subscriptions (restaurant_id);
CREATE INDEX idx_subscriptions_product_id ON subscriptions (product_id);

-- ============================================================
-- 29. affiliate_referrals
-- ============================================================
CREATE TABLE affiliate_referrals (
    id SERIAL PRIMARY KEY,
    affiliate_id INTEGER NOT NULL,
    restaurant_id INTEGER NOT NULL,
    status VARCHAR(50) NOT NULL DEFAULT 'pending',
    referred_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    activated_at TIMESTAMP NULL DEFAULT NULL,
    CONSTRAINT affiliate_referrals_affiliate_restaurant UNIQUE (affiliate_id, restaurant_id),
    CONSTRAINT chk_affiliate_referrals_status CHECK (status IN ('pending','active','cancelled')),
    FOREIGN KEY (affiliate_id) REFERENCES affiliates (id),
    FOREIGN KEY (restaurant_id) REFERENCES restaurants (id)
);

CREATE INDEX idx_affiliate_referrals_restaurant_id ON affiliate_referrals (restaurant_id);

-- ============================================================
-- 30. affiliate_commissions
-- ============================================================
CREATE TABLE affiliate_commissions (
    id SERIAL PRIMARY KEY,
    affiliate_id INTEGER NOT NULL,
    referral_id INTEGER NOT NULL,
    subscription_id INTEGER DEFAULT NULL,
    amount DECIMAL(10,2) NOT NULL,
    status VARCHAR(50) NOT NULL DEFAULT 'pending',
    period_start DATE DEFAULT NULL,
    period_end DATE DEFAULT NULL,
    paid_at TIMESTAMP NULL DEFAULT NULL,
    notes TEXT DEFAULT NULL,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT chk_affiliate_commissions_status CHECK (status IN ('pending','approved','paid','rejected')),
    FOREIGN KEY (affiliate_id) REFERENCES affiliates (id),
    FOREIGN KEY (referral_id) REFERENCES affiliate_referrals (id),
    FOREIGN KEY (subscription_id) REFERENCES subscriptions (id)
);

CREATE INDEX idx_affiliate_commissions_affiliate_id ON affiliate_commissions (affiliate_id);
CREATE INDEX idx_affiliate_commissions_referral_id ON affiliate_commissions (referral_id);
CREATE INDEX idx_affiliate_commissions_subscription_id ON affiliate_commissions (subscription_id);

-- ============================================================
-- 31. invoices
-- ============================================================
CREATE TABLE invoices (
    id SERIAL PRIMARY KEY,
    restaurant_id INTEGER NOT NULL,
    amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    status VARCHAR(50) NOT NULL DEFAULT 'unpaid',
    due_date DATE DEFAULT NULL,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT chk_invoices_status CHECK (status IN ('unpaid','overdue','paid'))
);

CREATE INDEX idx_invoices_restaurant_status ON invoices (restaurant_id, status);

-- ============================================================
-- 32. restaurant_phone_numbers
-- ============================================================
CREATE TABLE restaurant_phone_numbers (
    id SERIAL PRIMARY KEY,
    restaurant_id INTEGER NOT NULL,
    phone_number VARCHAR(20) NOT NULL,
    label VARCHAR(100) DEFAULT NULL,
    language_code VARCHAR(10) NOT NULL DEFAULT 'en',
    language_name VARCHAR(50) NOT NULL DEFAULT 'English',
    is_primary SMALLINT NOT NULL DEFAULT 0,
    is_active SMALLINT NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_rpn_restaurant ON restaurant_phone_numbers (restaurant_id);
CREATE INDEX idx_rpn_phone ON restaurant_phone_numbers (phone_number);

-- ============================================================
-- 33. restaurant_prompts
-- ============================================================
CREATE TABLE restaurant_prompts (
    id SERIAL PRIMARY KEY,
    restaurant_id INTEGER NOT NULL,
    agent_id VARCHAR(100) NOT NULL,
    retell_llm_id VARCHAR(64) DEFAULT NULL,
    voice_id VARCHAR(64) DEFAULT NULL,
    voice_model VARCHAR(64) DEFAULT NULL,
    model VARCHAR(64) DEFAULT 'gpt-4.1',
    agent_prompt TEXT DEFAULT NULL,
    begin_message TEXT DEFAULT NULL,
    transfer_number VARCHAR(20) DEFAULT NULL,
    agent_swap_id VARCHAR(64) DEFAULT NULL,
    webhook_url VARCHAR(512) DEFAULT NULL,
    inbound_webhook_url VARCHAR(512) DEFAULT NULL,
    mcp_url VARCHAR(512) DEFAULT NULL,
    mcp_headers TEXT DEFAULT NULL,
    is_synced SMALLINT NOT NULL DEFAULT 0,
    phone_number VARCHAR(20) DEFAULT NULL,
    language_code VARCHAR(10) NOT NULL DEFAULT 'en',
    language_name VARCHAR(50) NOT NULL DEFAULT 'English',
    is_primary_language SMALLINT NOT NULL DEFAULT 0,
    voice_agent_greeting TEXT DEFAULT NULL,
    description VARCHAR(255) DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT uk_restaurant_agent UNIQUE (restaurant_id, agent_id),
    CONSTRAINT fk_prompts_restaurant FOREIGN KEY (restaurant_id) REFERENCES restaurants (id) ON DELETE CASCADE ON UPDATE CASCADE
);

CREATE INDEX idx_restaurant_prompts_agent_id ON restaurant_prompts (agent_id);

-- ============================================================
-- 34. restaurant_prompt_details
-- ============================================================
CREATE TABLE restaurant_prompt_details (
    id SERIAL PRIMARY KEY,
    restaurant_prompt_id INTEGER NOT NULL,
    variable_name VARCHAR(100) NOT NULL,
    variable_value TEXT NOT NULL,
    is_active SMALLINT NOT NULL DEFAULT 1,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT uq_prompt_variable UNIQUE (restaurant_prompt_id, variable_name)
);

CREATE INDEX idx_restaurant_prompt_details_prompt_id ON restaurant_prompt_details (restaurant_prompt_id);

-- ============================================================
-- 35. voice_messages
-- ============================================================
CREATE TABLE voice_messages (
    id SERIAL PRIMARY KEY,
    restaurant_id INTEGER NOT NULL,
    call_log_id INTEGER DEFAULT NULL,
    source_prompt_id INTEGER DEFAULT NULL,
    target_prompt_id INTEGER DEFAULT NULL,
    from_language VARCHAR(10) NOT NULL,
    to_language VARCHAR(10) NOT NULL,
    message_text TEXT NOT NULL,
    recipient_phone VARCHAR(20) NOT NULL,
    recipient_type VARCHAR(50) NOT NULL DEFAULT 'business_staff',
    status VARCHAR(50) NOT NULL DEFAULT 'pending',
    delivery_call_id VARCHAR(100) DEFAULT NULL,
    delivery_call_log_id INTEGER DEFAULT NULL,
    error_message VARCHAR(500) DEFAULT NULL,
    retry_count SMALLINT NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    delivered_at TIMESTAMP NULL DEFAULT NULL,
    CONSTRAINT chk_voice_messages_recipient_type CHECK (recipient_type IN ('business_staff','customer')),
    CONSTRAINT chk_voice_messages_status CHECK (status IN ('pending','in_progress','delivered','failed'))
);

CREATE INDEX idx_vm_restaurant ON voice_messages (restaurant_id);
CREATE INDEX idx_vm_status ON voice_messages (status);
CREATE INDEX idx_vm_source ON voice_messages (call_log_id);

-- ============================================================
-- 36. professional_profiles
-- ============================================================
CREATE TABLE professional_profiles (
    id SERIAL PRIMARY KEY,
    restaurant_id INTEGER NOT NULL,
    owner_user_id INTEGER NOT NULL,
    business_name VARCHAR(255) NOT NULL,
    display_name VARCHAR(255) NOT NULL,
    business_phone VARCHAR(20) DEFAULT NULL,
    business_email VARCHAR(255) DEFAULT NULL,
    timezone VARCHAR(64) NOT NULL DEFAULT 'America/New_York',
    booking_slug VARCHAR(150) NOT NULL,
    slot_interval_minutes SMALLINT NOT NULL DEFAULT 30,
    default_buffer_before_minutes SMALLINT NOT NULL DEFAULT 0,
    default_buffer_after_minutes SMALLINT NOT NULL DEFAULT 0,
    minimum_booking_notice_hours SMALLINT NOT NULL DEFAULT 2,
    maximum_booking_horizon_days SMALLINT NOT NULL DEFAULT 90,
    default_location_type VARCHAR(30) NOT NULL DEFAULT 'in_person',
    default_location_label VARCHAR(255) DEFAULT NULL,
    booking_instructions TEXT DEFAULT NULL,
    cancellation_policy TEXT DEFAULT NULL,
    cancellation_notice_hours SMALLINT NOT NULL DEFAULT 24,
    is_public_booking_enabled SMALLINT NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT uk_prof_profiles_restaurant UNIQUE (restaurant_id),
    CONSTRAINT uk_prof_profiles_owner_user UNIQUE (owner_user_id),
    CONSTRAINT uk_prof_profiles_booking_slug UNIQUE (booking_slug),
    CONSTRAINT fk_prof_profiles_owner_user FOREIGN KEY (owner_user_id) REFERENCES users (id) ON UPDATE CASCADE,
    CONSTRAINT fk_prof_profiles_restaurant FOREIGN KEY (restaurant_id) REFERENCES restaurants (id) ON DELETE CASCADE ON UPDATE CASCADE
);

-- ============================================================
-- 37. professional_services
-- ============================================================
CREATE TABLE professional_services (
    id SERIAL PRIMARY KEY,
    restaurant_id INTEGER NOT NULL,
    name VARCHAR(255) NOT NULL,
    description TEXT DEFAULT NULL,
    duration_minutes SMALLINT NOT NULL,
    buffer_before_minutes SMALLINT NOT NULL DEFAULT 0,
    buffer_after_minutes SMALLINT NOT NULL DEFAULT 0,
    price DECIMAL(10,2) DEFAULT NULL,
    currency_code VARCHAR(10) NOT NULL DEFAULT 'USD',
    location_type VARCHAR(30) DEFAULT NULL,
    location_label VARCHAR(255) DEFAULT NULL,
    color VARCHAR(20) DEFAULT NULL,
    sort_order INTEGER NOT NULL DEFAULT 0,
    is_active SMALLINT NOT NULL DEFAULT 1,
    is_public_bookable SMALLINT NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_prof_services_restaurant FOREIGN KEY (restaurant_id) REFERENCES restaurants (id) ON DELETE CASCADE ON UPDATE CASCADE
);

CREATE INDEX idx_prof_services_restaurant_active ON professional_services (restaurant_id, is_active, sort_order);
CREATE INDEX idx_prof_services_restaurant_public ON professional_services (restaurant_id, is_public_bookable, is_active);

-- ============================================================
-- 38. professional_availability_rules
-- ============================================================
CREATE TABLE professional_availability_rules (
    id SERIAL PRIMARY KEY,
    restaurant_id INTEGER NOT NULL,
    weekday SMALLINT NOT NULL,
    start_time TIME NOT NULL,
    end_time TIME NOT NULL,
    location_type VARCHAR(30) DEFAULT NULL,
    location_label VARCHAR(255) DEFAULT NULL,
    is_active SMALLINT NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_prof_availability_restaurant FOREIGN KEY (restaurant_id) REFERENCES restaurants (id) ON DELETE CASCADE ON UPDATE CASCADE
);

CREATE INDEX idx_prof_availability_restaurant_day ON professional_availability_rules (restaurant_id, weekday, is_active);

-- ============================================================
-- 39. professional_time_off
-- ============================================================
CREATE TABLE professional_time_off (
    id SERIAL PRIMARY KEY,
    restaurant_id INTEGER NOT NULL,
    starts_at TIMESTAMP NOT NULL,
    ends_at TIMESTAMP NOT NULL,
    reason VARCHAR(255) DEFAULT NULL,
    notes TEXT DEFAULT NULL,
    is_all_day SMALLINT NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_prof_time_off_restaurant FOREIGN KEY (restaurant_id) REFERENCES restaurants (id) ON DELETE CASCADE ON UPDATE CASCADE
);

CREATE INDEX idx_prof_time_off_restaurant_range ON professional_time_off (restaurant_id, starts_at, ends_at);

-- ============================================================
-- 40. professional_clients
-- ============================================================
CREATE TABLE professional_clients (
    id SERIAL PRIMARY KEY,
    restaurant_id INTEGER NOT NULL,
    first_name VARCHAR(120) NOT NULL,
    last_name VARCHAR(120) NOT NULL,
    email VARCHAR(255) DEFAULT NULL,
    phone VARCHAR(20) DEFAULT NULL,
    birth_date DATE DEFAULT NULL,
    notes TEXT DEFAULT NULL,
    internal_notes TEXT DEFAULT NULL,
    service_address_line1 VARCHAR(255) DEFAULT NULL,
    service_city VARCHAR(100) DEFAULT NULL,
    service_state VARCHAR(50) DEFAULT NULL,
    service_postal_code VARCHAR(20) DEFAULT NULL,
    last_service_date DATE DEFAULT NULL,
    preferred_contact_method VARCHAR(20) DEFAULT NULL,
    marketing_opt_in SMALLINT NOT NULL DEFAULT 0,
    last_appointment_at TIMESTAMP DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_prof_clients_restaurant FOREIGN KEY (restaurant_id) REFERENCES restaurants (id) ON DELETE CASCADE ON UPDATE CASCADE
);

CREATE INDEX idx_prof_clients_restaurant_name ON professional_clients (restaurant_id, last_name, first_name);
CREATE INDEX idx_prof_clients_restaurant_email ON professional_clients (restaurant_id, email);
CREATE INDEX idx_prof_clients_restaurant_phone ON professional_clients (restaurant_id, phone);

-- ============================================================
-- 41. professional_appointments
-- ============================================================
CREATE TABLE professional_appointments (
    id SERIAL PRIMARY KEY,
    restaurant_id INTEGER NOT NULL,
    professional_user_id INTEGER NOT NULL,
    client_id INTEGER NOT NULL,
    service_id INTEGER DEFAULT NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'confirmed',
    source VARCHAR(30) NOT NULL DEFAULT 'staff',
    appointment_date DATE NOT NULL,
    start_at TIMESTAMP NOT NULL,
    end_at TIMESTAMP NOT NULL,
    service_name VARCHAR(255) NOT NULL,
    duration_minutes SMALLINT NOT NULL,
    buffer_before_minutes SMALLINT NOT NULL DEFAULT 0,
    buffer_after_minutes SMALLINT NOT NULL DEFAULT 0,
    price DECIMAL(10,2) DEFAULT NULL,
    currency_code VARCHAR(10) NOT NULL DEFAULT 'USD',
    location_type VARCHAR(30) DEFAULT NULL,
    location_label VARCHAR(255) DEFAULT NULL,
    confirmation_code VARCHAR(32) DEFAULT NULL,
    client_notes TEXT DEFAULT NULL,
    internal_notes TEXT DEFAULT NULL,
    cancelled_at TIMESTAMP DEFAULT NULL,
    completed_at TIMESTAMP DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    service_contact_name VARCHAR(140) DEFAULT NULL,
    service_address_1 VARCHAR(140) DEFAULT NULL,
    service_city VARCHAR(80) DEFAULT NULL,
    service_state VARCHAR(5) DEFAULT NULL,
    service_postal_code VARCHAR(5) DEFAULT NULL,
    service_phone VARCHAR(20) DEFAULT NULL,
    service_contact_method VARCHAR(3) DEFAULT NULL,
    CONSTRAINT uk_prof_appointments_confirmation_code UNIQUE (confirmation_code),
    CONSTRAINT fk_prof_appointments_client FOREIGN KEY (client_id) REFERENCES professional_clients (id) ON UPDATE CASCADE,
    CONSTRAINT fk_prof_appointments_professional_user FOREIGN KEY (professional_user_id) REFERENCES users (id) ON UPDATE CASCADE,
    CONSTRAINT fk_prof_appointments_restaurant FOREIGN KEY (restaurant_id) REFERENCES restaurants (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_prof_appointments_service FOREIGN KEY (service_id) REFERENCES professional_services (id) ON DELETE SET NULL ON UPDATE CASCADE
);

CREATE INDEX idx_prof_appointments_restaurant_start ON professional_appointments (restaurant_id, start_at);
CREATE INDEX idx_prof_appointments_restaurant_status_date ON professional_appointments (restaurant_id, status, appointment_date, start_at);
CREATE INDEX idx_prof_appointments_provider_range ON professional_appointments (professional_user_id, start_at, end_at);
CREATE INDEX idx_prof_appointments_client ON professional_appointments (client_id, start_at);
CREATE INDEX idx_prof_appointments_service ON professional_appointments (service_id);

-- ============================================================
-- 42. text_agent_prompts
-- ============================================================
CREATE TABLE text_agent_prompts (
    id SERIAL PRIMARY KEY,
    restaurant_id INTEGER NOT NULL,
    agent_id VARCHAR(255) NOT NULL,
    phone_number VARCHAR(20) DEFAULT NULL,
    description TEXT DEFAULT NULL,
    text_agent_greeting TEXT DEFAULT NULL,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT uk_text_agent UNIQUE (restaurant_id, agent_id),
    FOREIGN KEY (restaurant_id) REFERENCES restaurants (id) ON DELETE CASCADE
);

CREATE INDEX idx_text_agent_prompts_restaurant ON text_agent_prompts (restaurant_id);

-- ============================================================
-- 43. text_agent_prompt_details
-- ============================================================
CREATE TABLE text_agent_prompt_details (
    id SERIAL PRIMARY KEY,
    text_agent_prompt_id INTEGER NOT NULL,
    variable_name VARCHAR(255) NOT NULL,
    variable_value TEXT DEFAULT NULL,
    is_active SMALLINT DEFAULT 1,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (text_agent_prompt_id) REFERENCES text_agent_prompts (id) ON DELETE CASCADE
);

CREATE INDEX idx_text_agent_prompt_details_prompt ON text_agent_prompt_details (text_agent_prompt_id);

-- ============================================================
-- 44. sms_prompts
-- ============================================================
CREATE TABLE sms_prompts (
    id SERIAL PRIMARY KEY,
    restaurant_id INTEGER NOT NULL,
    text_agent_prompt_id INTEGER DEFAULT NULL,
    name VARCHAR(255) NOT NULL DEFAULT 'Default',
    system_prompt TEXT NOT NULL,
    model VARCHAR(100) NOT NULL DEFAULT 'gpt-4.1',
    temperature DECIMAL(3,2) NOT NULL DEFAULT 0.70,
    max_tokens INTEGER NOT NULL DEFAULT 1024,
    is_active SMALLINT NOT NULL DEFAULT 1,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (restaurant_id) REFERENCES restaurants (id) ON DELETE CASCADE,
    FOREIGN KEY (text_agent_prompt_id) REFERENCES text_agent_prompts (id) ON DELETE SET NULL
);

CREATE INDEX idx_sms_prompts_restaurant ON sms_prompts (restaurant_id);
CREATE INDEX idx_sms_prompts_text_agent ON sms_prompts (text_agent_prompt_id);

-- ============================================================
-- 45. sms_message_log
-- ============================================================
CREATE TABLE sms_message_log (
    id SERIAL PRIMARY KEY,
    restaurant_id INTEGER NOT NULL,
    client_id INTEGER DEFAULT NULL,
    prospect_id INTEGER DEFAULT NULL,
    phone_number_id INTEGER DEFAULT NULL,
    direction VARCHAR(50) NOT NULL DEFAULT 'inbound',
    from_number VARCHAR(20) NOT NULL,
    to_number VARCHAR(20) NOT NULL,
    body TEXT NOT NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'received',
    external_id VARCHAR(200) DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT chk_sms_message_log_direction CHECK (direction IN ('inbound','outbound'))
);

CREATE INDEX idx_sml_restaurant ON sms_message_log (restaurant_id);
CREATE INDEX idx_sml_phone ON sms_message_log (phone_number_id);
CREATE INDEX idx_sml_direction ON sms_message_log (direction);
CREATE INDEX idx_sml_created ON sms_message_log (created_at);
CREATE INDEX idx_sml_from ON sms_message_log (from_number);
CREATE INDEX idx_sml_to ON sms_message_log (to_number);
CREATE INDEX idx_sml_client ON sms_message_log (client_id);
CREATE INDEX idx_sml_prospect ON sms_message_log (prospect_id);

-- ============================================================
-- 46. sms_conversations
-- ============================================================
CREATE TABLE sms_conversations (
    id BIGSERIAL PRIMARY KEY,
    agent_id INTEGER NOT NULL,
    from_number VARCHAR(20) NOT NULL,
    to_number VARCHAR(20) NOT NULL,
    direction VARCHAR(50) NOT NULL,
    message_body TEXT NOT NULL,
    twilio_sid VARCHAR(50) DEFAULT NULL,
    tool_calls_json JSONB DEFAULT NULL,
    tokens_used INTEGER DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT chk_sms_conversations_direction CHECK (direction IN ('inbound','outbound')),
    FOREIGN KEY (agent_id) REFERENCES sms_agents (id)
);

CREATE INDEX idx_sms_conversations_conversation ON sms_conversations (agent_id, from_number, created_at);

-- ============================================================
-- 47. sms_agent_mcp_servers
-- ============================================================
CREATE TABLE sms_agent_mcp_servers (
    id SERIAL PRIMARY KEY,
    agent_id INTEGER NOT NULL,
    server_name VARCHAR(100) NOT NULL,
    server_url VARCHAR(500) NOT NULL,
    server_type VARCHAR(50) NOT NULL DEFAULT 'url',
    auth_token VARCHAR(255) DEFAULT NULL,
    is_active SMALLINT NOT NULL DEFAULT 1,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT chk_sms_agent_mcp_servers_server_type CHECK (server_type IN ('url','sse','stdio')),
    FOREIGN KEY (agent_id) REFERENCES sms_agents (id) ON DELETE CASCADE
);

CREATE INDEX idx_sms_agent_mcp_servers_agent_id ON sms_agent_mcp_servers (agent_id);

-- ============================================================
-- 48. sms_webhook_logs
-- ============================================================
CREATE TABLE sms_webhook_logs (
    id BIGSERIAL PRIMARY KEY,
    agent_id INTEGER NOT NULL,
    from_number VARCHAR(20) NOT NULL,
    request_payload JSONB DEFAULT NULL,
    response_payload JSONB DEFAULT NULL,
    http_status INTEGER DEFAULT NULL,
    duration_ms INTEGER DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (agent_id) REFERENCES sms_agents (id)
);

CREATE INDEX idx_sms_webhook_logs_agent_created ON sms_webhook_logs (agent_id, created_at);

-- ============================================================
-- 49. email_agent_prompts
-- ============================================================
CREATE TABLE email_agent_prompts (
    id SERIAL PRIMARY KEY,
    restaurant_id INTEGER NOT NULL,
    agent_id VARCHAR(255) NOT NULL,
    email_address VARCHAR(255) DEFAULT NULL,
    description TEXT DEFAULT NULL,
    email_agent_greeting TEXT DEFAULT NULL,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT uk_email_agent UNIQUE (restaurant_id, agent_id),
    FOREIGN KEY (restaurant_id) REFERENCES restaurants (id) ON DELETE CASCADE
);

CREATE INDEX idx_email_agent_prompts_restaurant ON email_agent_prompts (restaurant_id);

-- ============================================================
-- 50. email_agent_prompt_details
-- ============================================================
CREATE TABLE email_agent_prompt_details (
    id SERIAL PRIMARY KEY,
    email_agent_prompt_id INTEGER NOT NULL,
    variable_name VARCHAR(255) NOT NULL,
    variable_value TEXT DEFAULT NULL,
    is_active SMALLINT DEFAULT 1,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (email_agent_prompt_id) REFERENCES email_agent_prompts (id) ON DELETE CASCADE
);

CREATE INDEX idx_email_agent_prompt_details_prompt ON email_agent_prompt_details (email_agent_prompt_id);

-- ============================================================
-- 51. email_prompts
-- ============================================================
CREATE TABLE email_prompts (
    id SERIAL PRIMARY KEY,
    restaurant_id INTEGER NOT NULL,
    email_agent_prompt_id INTEGER DEFAULT NULL,
    name VARCHAR(255) NOT NULL DEFAULT 'Default',
    system_prompt TEXT NOT NULL,
    model VARCHAR(100) NOT NULL DEFAULT 'gpt-4.1',
    temperature DECIMAL(3,2) NOT NULL DEFAULT 0.70,
    max_tokens INTEGER NOT NULL DEFAULT 2048,
    is_active SMALLINT NOT NULL DEFAULT 1,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (restaurant_id) REFERENCES restaurants (id) ON DELETE CASCADE,
    FOREIGN KEY (email_agent_prompt_id) REFERENCES email_agent_prompts (id) ON DELETE SET NULL
);

CREATE INDEX idx_email_prompts_restaurant ON email_prompts (restaurant_id);
CREATE INDEX idx_email_prompts_email_agent ON email_prompts (email_agent_prompt_id);

-- ============================================================
-- 52. email_conversations
-- ============================================================
CREATE TABLE email_conversations (
    id SERIAL PRIMARY KEY,
    restaurant_id INTEGER NOT NULL,
    email_prompt_id INTEGER DEFAULT NULL,
    from_email VARCHAR(255) NOT NULL,
    to_email VARCHAR(255) NOT NULL,
    subject VARCHAR(500) DEFAULT NULL,
    status VARCHAR(50) NOT NULL DEFAULT 'active',
    last_message_at TIMESTAMP NULL DEFAULT NULL,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT chk_email_conversations_status CHECK (status IN ('active','closed')),
    FOREIGN KEY (restaurant_id) REFERENCES restaurants (id) ON DELETE CASCADE,
    FOREIGN KEY (email_prompt_id) REFERENCES email_prompts (id) ON DELETE SET NULL
);

CREATE INDEX idx_email_conversations_restaurant ON email_conversations (restaurant_id);
CREATE INDEX idx_email_conversations_from_email ON email_conversations (from_email);
CREATE INDEX idx_email_conversations_to_email ON email_conversations (to_email);
CREATE INDEX idx_email_conversations_lookup ON email_conversations (to_email, from_email, status);

-- ============================================================
-- 53. email_messages
-- ============================================================
CREATE TABLE email_messages (
    id SERIAL PRIMARY KEY,
    email_conversation_id INTEGER NOT NULL,
    role VARCHAR(50) NOT NULL,
    content TEXT DEFAULT NULL,
    tool_call_id VARCHAR(255) DEFAULT NULL,
    tool_name VARCHAR(255) DEFAULT NULL,
    tool_args TEXT DEFAULT NULL,
    tokens_used INTEGER DEFAULT NULL,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT chk_email_messages_role CHECK (role IN ('system','user','assistant','tool')),
    FOREIGN KEY (email_conversation_id) REFERENCES email_conversations (id) ON DELETE CASCADE
);

CREATE INDEX idx_email_messages_conversation ON email_messages (email_conversation_id);
CREATE INDEX idx_email_messages_role ON email_messages (role);

-- ============================================================
-- 54. email_message_log
-- ============================================================
CREATE TABLE email_message_log (
    id SERIAL PRIMARY KEY,
    restaurant_id INTEGER NOT NULL,
    client_id INTEGER DEFAULT NULL,
    direction VARCHAR(50) NOT NULL DEFAULT 'inbound',
    from_address VARCHAR(255) NOT NULL,
    to_address VARCHAR(255) NOT NULL,
    subject VARCHAR(500) DEFAULT NULL,
    body_text TEXT DEFAULT NULL,
    body_html TEXT DEFAULT NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'received',
    external_id VARCHAR(200) DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT chk_email_message_log_direction CHECK (direction IN ('inbound','outbound'))
);

CREATE INDEX idx_eml_restaurant ON email_message_log (restaurant_id);
CREATE INDEX idx_eml_direction ON email_message_log (direction);
CREATE INDEX idx_eml_created ON email_message_log (created_at);
CREATE INDEX idx_eml_from ON email_message_log (from_address);
CREATE INDEX idx_eml_to ON email_message_log (to_address);
CREATE INDEX idx_eml_client ON email_message_log (client_id);

-- ============================================================
-- 55. good_news
-- ============================================================
CREATE TABLE good_news (
    id SERIAL PRIMARY KEY,
    restaurant_id INTEGER NOT NULL,
    title VARCHAR(255) NOT NULL,
    summary TEXT DEFAULT NULL,
    source_name VARCHAR(255) DEFAULT NULL,
    source_url VARCHAR(500) DEFAULT NULL,
    image_url VARCHAR(500) DEFAULT NULL,
    category VARCHAR(100) DEFAULT NULL,
    published_date DATE DEFAULT NULL,
    is_active SMALLINT NOT NULL DEFAULT 1,
    fetched_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_good_news_restaurant FOREIGN KEY (restaurant_id) REFERENCES restaurants (id) ON DELETE CASCADE ON UPDATE CASCADE
);

CREATE INDEX idx_good_news_restaurant ON good_news (restaurant_id);
CREATE INDEX idx_good_news_active ON good_news (restaurant_id, is_active);
CREATE INDEX idx_good_news_fetched ON good_news (fetched_at);

-- ============================================================
-- 56. todos
-- ============================================================
CREATE TABLE todos (
    id SERIAL PRIMARY KEY,
    restaurant_id INTEGER NOT NULL,
    user_id INTEGER NOT NULL,
    title VARCHAR(255) NOT NULL,
    description TEXT DEFAULT NULL,
    due_date DATE DEFAULT NULL,
    priority VARCHAR(20) NOT NULL DEFAULT 'medium',
    status VARCHAR(20) NOT NULL DEFAULT 'pending',
    sort_order INTEGER NOT NULL DEFAULT 0,
    completed_at TIMESTAMP NULL DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_todos_restaurant FOREIGN KEY (restaurant_id) REFERENCES restaurants (id) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_todos_user FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE ON UPDATE CASCADE
);

CREATE INDEX idx_todos_restaurant_user ON todos (restaurant_id, user_id);
CREATE INDEX idx_todos_status ON todos (status);
CREATE INDEX idx_todos_due_date ON todos (due_date);

-- ============================================================
-- 57. prospects
-- ============================================================
CREATE TABLE prospects (
    id SERIAL PRIMARY KEY,
    affiliate_id INTEGER NOT NULL,
    restaurant_id INTEGER DEFAULT NULL,
    business_name VARCHAR(255) NOT NULL,
    business_type VARCHAR(100) DEFAULT NULL,
    address VARCHAR(255) DEFAULT NULL,
    city VARCHAR(100) DEFAULT NULL,
    state VARCHAR(2) DEFAULT NULL,
    zip VARCHAR(10) DEFAULT NULL,
    website VARCHAR(255) DEFAULT NULL,
    contact_name VARCHAR(255) DEFAULT NULL,
    contact_phone VARCHAR(20) DEFAULT NULL,
    contact_email VARCHAR(255) DEFAULT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'new',
    product_interest VARCHAR(25) NOT NULL DEFAULT 'restaurant',
    source VARCHAR(100) DEFAULT NULL,
    notes TEXT DEFAULT NULL,
    created_at TIMESTAMP NOT NULL,
    updated_at TIMESTAMP NOT NULL
);

CREATE INDEX idx_prospects_affiliate_id ON prospects (affiliate_id);
CREATE INDEX idx_prospects_status ON prospects (status);
CREATE INDEX idx_prospects_restaurant ON prospects (restaurant_id);

-- ============================================================
-- 58. prospect_activities
-- ============================================================
CREATE TABLE prospect_activities (
    id SERIAL PRIMARY KEY,
    prospect_id INTEGER NOT NULL,
    affiliate_id INTEGER NOT NULL,
    activity_type VARCHAR(50) NOT NULL DEFAULT 'note',
    subject VARCHAR(255) DEFAULT NULL,
    body TEXT DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE INDEX idx_prospect_activities_prospect_affiliate ON prospect_activities (prospect_id, affiliate_id);
CREATE INDEX idx_prospect_activities_created ON prospect_activities (created_at);

-- ============================================================
-- 59. prospect_products
-- ============================================================
CREATE TABLE prospect_products (
    id SERIAL PRIMARY KEY,
    prospect_id INTEGER NOT NULL,
    affiliate_id INTEGER NOT NULL,
    product_id INTEGER NOT NULL,
    recommended_price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    affiliate_price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    quoted_price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    status VARCHAR(50) NOT NULL DEFAULT 'pitched',
    notes TEXT DEFAULT NULL,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT uq_prospect_product UNIQUE (prospect_id, product_id)
);

CREATE INDEX idx_prospect_products_prospect ON prospect_products (prospect_id);
CREATE INDEX idx_prospect_products_affiliate ON prospect_products (affiliate_id);
CREATE INDEX idx_prospect_products_product ON prospect_products (product_id);

-- ============================================================
-- 60. account_options
-- ============================================================
CREATE TABLE account_options (
    id SERIAL PRIMARY KEY,
    restaurant_id INTEGER NOT NULL,
    option_key VARCHAR(100) NOT NULL,
    option_value VARCHAR(255) NOT NULL DEFAULT '1',
    enabled SMALLINT NOT NULL DEFAULT 1,
    notes TEXT DEFAULT NULL,
    created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT account_options_restaurant_option_key UNIQUE (restaurant_id, option_key),
    FOREIGN KEY (restaurant_id) REFERENCES restaurants (id)
);

-- ============================================================
-- 61. twilio_numbers
-- ============================================================
CREATE TABLE twilio_numbers (
    id SERIAL PRIMARY KEY,
    restaurant_id INTEGER DEFAULT NULL,
    guest_id INTEGER DEFAULT NULL,
    client_id INTEGER DEFAULT NULL,
    retell_call_id VARCHAR(100) DEFAULT NULL,
    direction VARCHAR(50) NOT NULL DEFAULT 'inbound',
    from_number VARCHAR(20) NOT NULL,
    to_number VARCHAR(20) NOT NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'ended',
    duration_ms INTEGER DEFAULT NULL,
    disconnection_reason VARCHAR(100) DEFAULT NULL,
    transcript TEXT DEFAULT NULL,
    recording_url VARCHAR(500) DEFAULT NULL,
    call_summary TEXT DEFAULT NULL,
    metadata JSONB DEFAULT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    ended_at TIMESTAMP NULL DEFAULT NULL,
    CONSTRAINT chk_twilio_numbers_direction CHECK (direction IN ('inbound','outbound'))
);

CREATE INDEX idx_twilio_numbers_restaurant ON twilio_numbers (restaurant_id);
CREATE INDEX idx_twilio_numbers_guest ON twilio_numbers (guest_id);
CREATE INDEX idx_twilio_numbers_retell_call ON twilio_numbers (retell_call_id);
CREATE INDEX idx_twilio_numbers_created ON twilio_numbers (restaurant_id, created_at);
CREATE INDEX idx_twilio_numbers_direction ON twilio_numbers (restaurant_id, direction);
CREATE INDEX idx_twilio_numbers_client ON twilio_numbers (client_id);


-- ============================================================
-- TRIGGERS: updated_at auto-update
-- ============================================================

CREATE TRIGGER trg_restaurants_updated_at
    BEFORE UPDATE ON restaurants
    FOR EACH ROW
    EXECUTE FUNCTION update_updated_at();

CREATE TRIGGER trg_users_updated_at
    BEFORE UPDATE ON users
    FOR EACH ROW
    EXECUTE FUNCTION update_updated_at();

CREATE TRIGGER trg_products_updated_at
    BEFORE UPDATE ON products
    FOR EACH ROW
    EXECUTE FUNCTION update_updated_at();

CREATE TRIGGER trg_prompt_templates_updated_at
    BEFORE UPDATE ON prompt_templates
    FOR EACH ROW
    EXECUTE FUNCTION update_updated_at();

CREATE TRIGGER trg_landing_page_routes_updated_at
    BEFORE UPDATE ON landing_page_routes
    FOR EACH ROW
    EXECUTE FUNCTION update_updated_at();

CREATE TRIGGER trg_sms_agents_updated_at
    BEFORE UPDATE ON sms_agents
    FOR EACH ROW
    EXECUTE FUNCTION update_updated_at();

CREATE TRIGGER trg_affiliates_updated_at
    BEFORE UPDATE ON affiliates
    FOR EACH ROW
    EXECUTE FUNCTION update_updated_at();

CREATE TRIGGER trg_user_restaurants_updated_at
    BEFORE UPDATE ON user_restaurants
    FOR EACH ROW
    EXECUTE FUNCTION update_updated_at();

CREATE TRIGGER trg_settings_updated_at
    BEFORE UPDATE ON settings
    FOR EACH ROW
    EXECUTE FUNCTION update_updated_at();

CREATE TRIGGER trg_sections_updated_at
    BEFORE UPDATE ON sections
    FOR EACH ROW
    EXECUTE FUNCTION update_updated_at();

CREATE TRIGGER trg_tables_updated_at
    BEFORE UPDATE ON "tables"
    FOR EACH ROW
    EXECUTE FUNCTION update_updated_at();

CREATE TRIGGER trg_guests_updated_at
    BEFORE UPDATE ON guests
    FOR EACH ROW
    EXECUTE FUNCTION update_updated_at();

CREATE TRIGGER trg_events_updated_at
    BEFORE UPDATE ON events
    FOR EACH ROW
    EXECUTE FUNCTION update_updated_at();

CREATE TRIGGER trg_reservations_updated_at
    BEFORE UPDATE ON reservations
    FOR EACH ROW
    EXECUTE FUNCTION update_updated_at();

CREATE TRIGGER trg_waitlist_updated_at
    BEFORE UPDATE ON waitlist
    FOR EACH ROW
    EXECUTE FUNCTION update_updated_at();

CREATE TRIGGER trg_operating_hours_updated_at
    BEFORE UPDATE ON operating_hours
    FOR EACH ROW
    EXECUTE FUNCTION update_updated_at();

CREATE TRIGGER trg_special_dates_updated_at
    BEFORE UPDATE ON special_dates
    FOR EACH ROW
    EXECUTE FUNCTION update_updated_at();

CREATE TRIGGER trg_turn_times_updated_at
    BEFORE UPDATE ON turn_times
    FOR EACH ROW
    EXECUTE FUNCTION update_updated_at();

CREATE TRIGGER trg_email_templates_updated_at
    BEFORE UPDATE ON email_templates
    FOR EACH ROW
    EXECUTE FUNCTION update_updated_at();

CREATE TRIGGER trg_restaurant_phone_numbers_updated_at
    BEFORE UPDATE ON restaurant_phone_numbers
    FOR EACH ROW
    EXECUTE FUNCTION update_updated_at();

CREATE TRIGGER trg_restaurant_prompts_updated_at
    BEFORE UPDATE ON restaurant_prompts
    FOR EACH ROW
    EXECUTE FUNCTION update_updated_at();

CREATE TRIGGER trg_restaurant_prompt_details_updated_at
    BEFORE UPDATE ON restaurant_prompt_details
    FOR EACH ROW
    EXECUTE FUNCTION update_updated_at();

CREATE TRIGGER trg_professional_profiles_updated_at
    BEFORE UPDATE ON professional_profiles
    FOR EACH ROW
    EXECUTE FUNCTION update_updated_at();

CREATE TRIGGER trg_professional_services_updated_at
    BEFORE UPDATE ON professional_services
    FOR EACH ROW
    EXECUTE FUNCTION update_updated_at();

CREATE TRIGGER trg_professional_availability_rules_updated_at
    BEFORE UPDATE ON professional_availability_rules
    FOR EACH ROW
    EXECUTE FUNCTION update_updated_at();

CREATE TRIGGER trg_professional_time_off_updated_at
    BEFORE UPDATE ON professional_time_off
    FOR EACH ROW
    EXECUTE FUNCTION update_updated_at();

CREATE TRIGGER trg_professional_clients_updated_at
    BEFORE UPDATE ON professional_clients
    FOR EACH ROW
    EXECUTE FUNCTION update_updated_at();

CREATE TRIGGER trg_professional_appointments_updated_at
    BEFORE UPDATE ON professional_appointments
    FOR EACH ROW
    EXECUTE FUNCTION update_updated_at();

CREATE TRIGGER trg_text_agent_prompts_updated_at
    BEFORE UPDATE ON text_agent_prompts
    FOR EACH ROW
    EXECUTE FUNCTION update_updated_at();

CREATE TRIGGER trg_sms_prompts_updated_at
    BEFORE UPDATE ON sms_prompts
    FOR EACH ROW
    EXECUTE FUNCTION update_updated_at();

CREATE TRIGGER trg_email_agent_prompts_updated_at
    BEFORE UPDATE ON email_agent_prompts
    FOR EACH ROW
    EXECUTE FUNCTION update_updated_at();

CREATE TRIGGER trg_email_prompts_updated_at
    BEFORE UPDATE ON email_prompts
    FOR EACH ROW
    EXECUTE FUNCTION update_updated_at();

CREATE TRIGGER trg_good_news_updated_at
    BEFORE UPDATE ON good_news
    FOR EACH ROW
    EXECUTE FUNCTION update_updated_at();

CREATE TRIGGER trg_todos_updated_at
    BEFORE UPDATE ON todos
    FOR EACH ROW
    EXECUTE FUNCTION update_updated_at();

CREATE TRIGGER trg_prospect_products_updated_at
    BEFORE UPDATE ON prospect_products
    FOR EACH ROW
    EXECUTE FUNCTION update_updated_at();

CREATE TRIGGER trg_invoices_updated_at
    BEFORE UPDATE ON invoices
    FOR EACH ROW
    EXECUTE FUNCTION update_updated_at();

CREATE TRIGGER trg_subscriptions_updated_at
    BEFORE UPDATE ON subscriptions
    FOR EACH ROW
    EXECUTE FUNCTION update_updated_at();
