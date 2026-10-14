-- Mangalam Jewellers — database schema (MySQL 5.7+ / MariaDB 10.3+, utf8mb4).
-- Run by install.php (or import through phpMyAdmin, then load database/seed.json with the installer).

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

DROP TABLE IF EXISTS enquiry_replies, enquiries, message_templates, appointments, subscribers, hotspots, product_images, offers, products,
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
  view3d         TEXT         NULL,                      -- the 3D view & customiser (JSON; see view3d_config() in app/lib/content.php)
  spin           TEXT         NULL,                      -- the 360° view: its photographed frames and how it turns (JSON; see spin_config())
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

-- Category offers: the Offers page (/offers.html), and the ribbon on the category's own page
CREATE TABLE offers (
  id              INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  category_id     INT UNSIGNED NULL,                     -- the category it is for; NULL = all jewellery
  kicker          VARCHAR(80)  NOT NULL DEFAULT '',      -- small line above the title, e.g. "Wedding season"
  title           VARCHAR(120) NOT NULL,                 -- *words* = gold italics
  deal_type       ENUM('upto','flat') NOT NULL DEFAULT 'upto',  -- "Up to 25% off" or "Flat 10% off"
  percent         TINYINT UNSIGNED NOT NULL DEFAULT 10,
  deal_on         VARCHAR(60)  NOT NULL DEFAULT '',      -- what the discount is on ("making charges"); '' = the price
  text            VARCHAR(400) NOT NULL DEFAULT '',
  terms           VARCHAR(200) NOT NULL DEFAULT '',      -- small print
  cta             VARCHAR(40)  NOT NULL DEFAULT '',      -- the button ('' = "Shop <category>")
  images          TEXT         NULL,                     -- JSON list of photographs for the gallery; the first is the cover
  with_products   TINYINT(1)   NOT NULL DEFAULT 1,       -- the gallery also shows the category's pieces
  starts_on       DATE         NULL,
  ends_on         DATE         NULL,                     -- runs to the end of this day
  is_live         TINYINT(1)   NOT NULL DEFAULT 1,
  sort_order      INT          NOT NULL DEFAULT 0,
  created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_offers_live (is_live, sort_order),
  CONSTRAINT fk_offers_category FOREIGN KEY (category_id) REFERENCES categories (id) ON DELETE SET NULL
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

-- A conversation with a customer: the enquiry they sent (or the first message the team sent them), then every
-- message either way in enquiry_replies
CREATE TABLE enquiries (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  token       CHAR(32)     NULL UNIQUE,                  -- the customer's private conversation page (conversation.html?t=…)
  name        VARCHAR(120) NOT NULL,
  email       VARCHAR(190) NOT NULL,
  phone       VARCHAR(40)  NOT NULL DEFAULT '',
  source      ENUM('product','contact','email','admin') NOT NULL DEFAULT 'contact',  -- email = written straight to the mailbox; admin = started by the team
  product_id  INT UNSIGNED NULL,
  topic       VARCHAR(80)  NOT NULL DEFAULT '',
  subject     VARCHAR(200) NOT NULL DEFAULT '',          -- the email subject ('' = made from the piece or the topic)
  message     TEXT         NOT NULL,                     -- what the customer wrote ('' when the team started the conversation)
  message_id  VARCHAR(255) NOT NULL DEFAULT '',          -- the email it arrived as (source = email)
  status      ENUM('new','replied','closed','archived') NOT NULL DEFAULT 'new',  -- new = waiting for the team, also after the customer writes again
  last_at     DATETIME     NULL,                         -- the latest message either way; the inbox is sorted by it
  created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_enquiries_status (status, created_at),
  KEY idx_enquiries_email (email),
  CONSTRAINT fk_enquiries_product FOREIGN KEY (product_id) REFERENCES products (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Every message after the first, either way and on any channel (the table's name is from when it held only replies)
CREATE TABLE enquiry_replies (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  enquiry_id  INT UNSIGNED NOT NULL,
  direction   ENUM('out','in') NOT NULL DEFAULT 'out',   -- out = from the team, in = from the customer
  channel     ENUM('email','whatsapp','website','phone','note') NOT NULL DEFAULT 'email',  -- note = seen only by the team
  is_auto     TINYINT(1)   NOT NULL DEFAULT 0,           -- sent by the website itself (Email templates › Automatic emails)
  author      VARCHAR(120) NOT NULL,
  subject     VARCHAR(200) NOT NULL DEFAULT '',
  message     TEXT         NOT NULL,
  emailed     TINYINT(1)   NOT NULL DEFAULT 0,
  message_id  VARCHAR(255) NOT NULL DEFAULT '',          -- the email's Message-ID: replies are matched to it, and arrivals read only once
  seen_at     DATETIME     NULL,                         -- the customer opened their conversation page after it
  created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  KEY idx_replies_enquiry (enquiry_id, created_at),
  KEY idx_replies_message_id (message_id(64)),
  CONSTRAINT fk_replies_enquiry FOREIGN KEY (enquiry_id) REFERENCES enquiries (id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Email templates: the replies the team chooses from, and the emails the website sends by itself (auto_key)
CREATE TABLE message_templates (
  id          INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  name        VARCHAR(80)  NOT NULL,
  auto_key    VARCHAR(40)  NULL UNIQUE,                  -- enquiry_received, appointment_received, appointment_confirmed; NULL = a reply
  subject     VARCHAR(200) NOT NULL DEFAULT '',          -- '' = the conversation's own subject (appointment emails need one)
  heading     VARCHAR(160) NOT NULL DEFAULT '',          -- the large line at the top of the email; *words* = gold italics
  body        TEXT         NOT NULL,                     -- {first_name}, {product} … (TEMPLATE_FIELDS in app/lib/conversations.php)
  button      VARCHAR(40)  NOT NULL DEFAULT '',          -- a button under the text ('' = none)
  button_link VARCHAR(20)  NOT NULL DEFAULT 'conversation',  -- where it goes (TEMPLATE_LINKS)
  is_on       TINYINT(1)   NOT NULL DEFAULT 1,           -- automatic emails can be switched off
  sort_order  INT          NOT NULL DEFAULT 0,
  updated_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
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
