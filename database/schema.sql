-- Mangalam Jewellers — database schema (MySQL 5.7+ / MariaDB 10.3+, utf8mb4).
-- Run by install.php (or import through phpMyAdmin, then load database/seed.json with the installer).

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS enquiry_replies, enquiries, appointments, subscribers, hotspots, product_images, products,
  categories, collections, articles, testimonials, pages, media, role_permissions, users, settings;

SET FOREIGN_KEY_CHECKS = 1;

-- ---------- Catalogue ----------

CREATE TABLE categories (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  slug        VARCHAR(80)  NOT NULL UNIQUE,              -- the page is /<slug>.html
  name        VARCHAR(80)  NOT NULL,
  heading     VARCHAR(160) NOT NULL DEFAULT '',          -- *words* = gold italics, | = new line
  intro       TEXT         NULL,
  image       VARCHAR(255) NOT NULL DEFAULT '',          -- menu, chip and arch image (3:4)
  banner      VARCHAR(255) NOT NULL DEFAULT '',          -- page banner
  seo_title   VARCHAR(160) NOT NULL DEFAULT '',
  seo_desc    VARCHAR(255) NOT NULL DEFAULT '',
  in_menu     TINYINT(1)   NOT NULL DEFAULT 1,
  sort_order  INT          NOT NULL DEFAULT 0,
  created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE products (
  id             INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  slug           VARCHAR(160) NOT NULL UNIQUE,           -- product.html?slug=<slug>
  name           VARCHAR(160) NOT NULL,
  category_id    INT UNSIGNED NULL,
  price          INT UNSIGNED NOT NULL DEFAULT 0,        -- rupees
  offer_price    INT UNSIGNED NULL,
  metal          VARCHAR(40)  NOT NULL DEFAULT 'Gold',
  purity         VARCHAR(8)   NOT NULL DEFAULT '22K',
  stone          VARCHAR(40)  NOT NULL DEFAULT 'None',
  style          VARCHAR(40)  NOT NULL DEFAULT 'Classic',
  line           VARCHAR(80)  NOT NULL DEFAULT 'Mangalam Signature',  -- the collection line above the name
  summary        TEXT         NULL,                      -- short description under the price
  details        MEDIUMTEXT   NULL,                      -- full description (HTML from the editor)
  gross_weight   DECIMAL(9,3) NULL,
  gold_weight    DECIMAL(9,3) NULL,
  stone_weight   DECIMAL(9,3) NULL,
  making_charges DECIMAL(6,2) NULL,
  huid           VARCHAR(6)   NULL,
  image_position VARCHAR(40)  NOT NULL DEFAULT 'center',
  is_new         TINYINT(1)   NOT NULL DEFAULT 0,
  featured_order INT          NOT NULL DEFAULT 0,        -- 0 = not featured; otherwise its place in the Signature tab
  show_price     TINYINT(1)   NOT NULL DEFAULT 1,
  allow_enquiry  TINYINT(1)   NOT NULL DEFAULT 1,
  status         ENUM('published','draft','hidden') NOT NULL DEFAULT 'draft',
  publish_on     DATE         NULL,                      -- a published piece appears from this day
  seo_title      VARCHAR(160) NOT NULL DEFAULT '',
  seo_desc       VARCHAR(255) NOT NULL DEFAULT '',
  sort_order     INT          NOT NULL DEFAULT 0,
  created_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_products_category (category_id),
  KEY idx_products_status (status),
  CONSTRAINT fk_products_category FOREIGN KEY (category_id) REFERENCES categories (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE product_images (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  product_id  INT UNSIGNED NOT NULL,
  path        VARCHAR(255) NOT NULL,                     -- e.g. assets/images/products/ring-cocktail.jpg (a -sm copy sits beside it)
  sort_order  INT          NOT NULL DEFAULT 0,           -- 1 = main photo, 2 = shown on hover
  KEY idx_product_images_product (product_id, sort_order),
  CONSTRAINT fk_product_images_product FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE collections (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  title       VARCHAR(80)  NOT NULL,
  kicker      VARCHAR(160) NOT NULL DEFAULT '',
  image       VARCHAR(255) NOT NULL DEFAULT '',
  image_pos   VARCHAR(40)  NOT NULL DEFAULT '50% 50%',
  rule_field  ENUM('category','metal','style') NOT NULL DEFAULT 'category',
  rule_value  VARCHAR(80)  NOT NULL DEFAULT '',
  on_home     TINYINT(1)   NOT NULL DEFAULT 1,
  sort_order  INT          NOT NULL DEFAULT 0,
  created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE hotspots (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  product_id  INT UNSIGNED NOT NULL,
  x           DECIMAL(5,2) NOT NULL,                     -- percent across the hero photograph
  y           DECIMAL(5,2) NOT NULL,                     -- percent down
  flip        TINYINT(1)   NOT NULL DEFAULT 0,           -- card opens to the left
  sort_order  INT          NOT NULL DEFAULT 0,
  CONSTRAINT fk_hotspots_product FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- Website content ----------

CREATE TABLE pages (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  page_key    VARCHAR(40)  NOT NULL UNIQUE,              -- index, about, contact … (the file name without .html)
  name        VARCHAR(80)  NOT NULL,
  kind        ENUM('Page','Template') NOT NULL DEFAULT 'Page',
  heading     VARCHAR(200) NOT NULL DEFAULT '',          -- *words* = gold italics, | = new line
  lead        TEXT         NULL,
  banner      VARCHAR(255) NOT NULL DEFAULT '',
  seo_title   VARCHAR(160) NOT NULL DEFAULT '',
  seo_desc    VARCHAR(255) NOT NULL DEFAULT '',
  sort_order  INT          NOT NULL DEFAULT 0,
  updated_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE articles (
  id             INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  slug           VARCHAR(160) NOT NULL UNIQUE,           -- article.html?slug=<slug>
  title          VARCHAR(200) NOT NULL,
  category       VARCHAR(40)  NOT NULL DEFAULT 'Guide',  -- the topic
  excerpt        VARCHAR(255) NOT NULL DEFAULT '',
  body           MEDIUMTEXT   NULL,                      -- HTML from the editor
  image          VARCHAR(255) NOT NULL DEFAULT '',
  image_position VARCHAR(40)  NOT NULL DEFAULT 'center',
  read_time      SMALLINT     NOT NULL DEFAULT 5,        -- minutes
  author         VARCHAR(80)  NOT NULL DEFAULT '',
  status         ENUM('published','draft','scheduled') NOT NULL DEFAULT 'draft',
  published_on   DATE         NULL,
  on_home        TINYINT(1)   NOT NULL DEFAULT 1,
  seo_title      VARCHAR(160) NOT NULL DEFAULT '',
  seo_desc       VARCHAR(255) NOT NULL DEFAULT '',
  created_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_articles_status (status, published_on)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE testimonials (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  quote       VARCHAR(400) NOT NULL,
  who         VARCHAR(80)  NOT NULL,
  occasion    VARCHAR(120) NOT NULL DEFAULT '',
  consent     TINYINT(1)   NOT NULL DEFAULT 1,
  on_home     TINYINT(1)   NOT NULL DEFAULT 1,
  sort_order  INT          NOT NULL DEFAULT 0,
  created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE media (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  path        VARCHAR(255) NOT NULL UNIQUE,              -- relative to the website root
  folder      ENUM('campaign','products','brand','other') NOT NULL DEFAULT 'other',
  name        VARCHAR(160) NOT NULL,
  alt         VARCHAR(255) NOT NULL DEFAULT '',
  width       INT UNSIGNED NULL,
  height      INT UNSIGNED NULL,
  bytes       INT UNSIGNED NOT NULL DEFAULT 0,
  created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Everything else the website shows (contact details, hours, offer, announcements, homepage hero,
-- sections and bridal panels, notification and motion switches). JSON values for lists.
CREATE TABLE settings (
  name        VARCHAR(60)  NOT NULL PRIMARY KEY,
  value       MEDIUMTEXT   NULL,
  updated_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- Customers ----------

CREATE TABLE enquiries (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  name        VARCHAR(120) NOT NULL,
  email       VARCHAR(190) NOT NULL,
  phone       VARCHAR(40)  NOT NULL DEFAULT '',
  source      ENUM('product','contact') NOT NULL DEFAULT 'contact',
  product_id  INT UNSIGNED NULL,
  topic       VARCHAR(80)  NOT NULL DEFAULT '',
  message     TEXT         NOT NULL,
  status      ENUM('new','replied','closed','archived') NOT NULL DEFAULT 'new',
  created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_enquiries_status (status, created_at),
  CONSTRAINT fk_enquiries_product FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE enquiry_replies (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  enquiry_id  INT UNSIGNED NOT NULL,
  author      VARCHAR(120) NOT NULL,
  message     TEXT         NOT NULL,
  emailed     TINYINT(1)   NOT NULL DEFAULT 0,
  created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  CONSTRAINT fk_replies_enquiry FOREIGN KEY (enquiry_id) REFERENCES enquiries (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE appointments (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  name        VARCHAR(120) NOT NULL,
  phone       VARCHAR(40)  NOT NULL DEFAULT '',
  email       VARCHAR(190) NOT NULL DEFAULT '',
  date        DATE         NOT NULL,
  time        VARCHAR(10)  NOT NULL DEFAULT '',          -- e.g. 11:30 AM ('' when the visitor has not chosen one)
  interest    VARCHAR(80)  NOT NULL DEFAULT '',
  consultant  VARCHAR(120) NOT NULL DEFAULT '',
  status      ENUM('pending','confirmed','completed','cancelled') NOT NULL DEFAULT 'pending',
  notes       TEXT         NULL,
  source      ENUM('website','admin') NOT NULL DEFAULT 'admin',
  created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_appointments_date (date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE subscribers (
  id               INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  email            VARCHAR(190) NOT NULL UNIQUE,
  source           VARCHAR(40)  NOT NULL DEFAULT 'Website footer',
  status           ENUM('subscribed','unsubscribed') NOT NULL DEFAULT 'subscribed',
  created_at       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  unsubscribed_at  DATETIME     NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ---------- Team ----------

CREATE TABLE users (
  id               INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  name             VARCHAR(120) NOT NULL,
  email            VARCHAR(190) NOT NULL UNIQUE,
  password_hash    VARCHAR(255) NULL,                    -- empty until an invited person chooses a password
  role             ENUM('Owner','Manager','Editor','Sales') NOT NULL DEFAULT 'Editor',
  status           ENUM('active','invited') NOT NULL DEFAULT 'invited',
  token_hash       CHAR(64)     NULL,                    -- invitation or password-reset link (sha256 of the token)
  token_expires    DATETIME     NULL,
  last_login_at    DATETIME     NULL,
  notices_seen_at  DATETIME     NULL,
  created_at       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at       DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE role_permissions (
  role        ENUM('Manager','Editor','Sales') NOT NULL, -- the Owner can always do everything
  permission  VARCHAR(40)  NOT NULL,
  allowed     TINYINT(1)   NOT NULL DEFAULT 0,
  PRIMARY KEY (role, permission)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
