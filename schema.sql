-- StageDoor v1 schema
-- Run: mysql -u root -p < schema.sql

CREATE DATABASE IF NOT EXISTS stagedoor CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE stagedoor;

-- Creators and buyers. v1 checkout doesn't require buyer accounts,
-- but the table exists so creator accounts (and later, buyer login) have a home.
CREATE TABLE users (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    name            VARCHAR(100) NOT NULL,
    email           VARCHAR(150) NOT NULL UNIQUE,
    password_hash   VARCHAR(255) NOT NULL,
    role            ENUM('creator', 'buyer') NOT NULL DEFAULT 'buyer',
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE events (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    creator_id      INT NOT NULL,
    title           VARCHAR(200) NOT NULL,
    description     TEXT,
    event_date      DATETIME NOT NULL,
    venue           VARCHAR(200) NOT NULL,
    image_url       VARCHAR(500),
    status          ENUM('draft', 'published', 'cancelled') NOT NULL DEFAULT 'draft',
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (creator_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_event_date (event_date),
    INDEX idx_status (status)
);

CREATE TABLE ticket_types (
    id                  INT AUTO_INCREMENT PRIMARY KEY,
    event_id            INT NOT NULL,
    name                VARCHAR(100) NOT NULL,
    price               DECIMAL(10,2) NOT NULL,
    quantity_available  INT NOT NULL,
    quantity_sold       INT NOT NULL DEFAULT 0,
    FOREIGN KEY (event_id) REFERENCES events(id) ON DELETE CASCADE,
    INDEX idx_event (event_id)
);

CREATE TABLE orders (
    id                      INT AUTO_INCREMENT PRIMARY KEY,
    event_id                INT NOT NULL,
    buyer_name              VARCHAR(150) NOT NULL,
    buyer_email             VARCHAR(150) NOT NULL,
    buyer_phone             VARCHAR(30),
    total_amount            DECIMAL(10,2) NOT NULL,
    platform_fee            DECIMAL(10,2) NOT NULL DEFAULT 0,
    status                  ENUM('pending', 'paid', 'failed', 'expired', 'cancelled') NOT NULL DEFAULT 'pending',
    mpesa_checkout_request_id VARCHAR(60),
    created_at              TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (event_id) REFERENCES events(id),
    INDEX idx_buyer_email (buyer_email),
    INDEX idx_checkout_request (mpesa_checkout_request_id)
);

CREATE TABLE order_items (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    order_id        INT NOT NULL,
    ticket_type_id  INT NOT NULL,
    quantity        INT NOT NULL,
    unit_price      DECIMAL(10,2) NOT NULL,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY (ticket_type_id) REFERENCES ticket_types(id)
);

-- One row per physical ticket (not per order item) so each can carry
-- its own scannable code and check-in state.
CREATE TABLE tickets (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    order_item_id   INT NOT NULL,
    unique_code     VARCHAR(50) NOT NULL UNIQUE,
    checked_in      BOOLEAN NOT NULL DEFAULT FALSE,
    created_at      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (order_item_id) REFERENCES order_items(id) ON DELETE CASCADE
);

-- Records money actually sent to a creator, per event. What a creator is OWED
-- is calculated on the fly (paid orders' total_amount - platform_fee, per event);
-- what they've been PAID is the sum of rows here. The difference is what's
-- still outstanding. Only the platform admin creates rows here — creators
-- can view but never write to this table.
CREATE TABLE payouts (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    creator_id      INT NOT NULL,
    event_id        INT NOT NULL,
    amount          DECIMAL(10,2) NOT NULL,
    notes           VARCHAR(255),
    paid_at         TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (creator_id) REFERENCES users(id),
    FOREIGN KEY (event_id) REFERENCES events(id),
    INDEX idx_creator (creator_id),
    INDEX idx_event (event_id)
);

-- Seed data so index.php / event.php have something to show immediately.
INSERT INTO users (name, email, password_hash, role) VALUES
('Wachira Sessions', 'wachira@example.com', '$2y$10$examplehashvalueforseeddata1234567890', 'creator');

INSERT INTO events (creator_id, title, description, event_date, venue, status) VALUES
(1, 'Neon Hours — The Wachira Sessions', 'A night of live sets in Westlands.', '2026-10-18 19:00:00', 'The Alchemist, Westlands', 'published');

INSERT INTO ticket_types (event_id, name, price, quantity_available) VALUES
(1, 'General admission', 1500.00, 240),
(1, 'VIP + lounge access', 4000.00, 18),
(1, 'Front row + meet & greet', 7500.00, 4);
