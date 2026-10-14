-- Mangalam Jewellers — category offers and the Offers page, 10 October 2026
-- Adds the offers table and the Offers page's heading, introduction and banner — all managed in the admin
-- under Marketing › Offers page. Upload the changed code first, then run
-- this file (phpMyAdmin › Import, or: mysql database < this-file). Safe to run more than once.
-- No offers are added: the Offers menu link and the category ribbons appear once the team publishes the
-- first offer. (Opening the admin after the upload makes the same changes by itself.)

SET NAMES utf8mb4;

CREATE TABLE IF NOT EXISTS offers (
  id              INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  category_id     INT UNSIGNED NULL,
  kicker          VARCHAR(80)  NOT NULL DEFAULT '',
  title           VARCHAR(120) NOT NULL,
  deal_type       ENUM('upto','flat') NOT NULL DEFAULT 'upto',
  percent         TINYINT UNSIGNED NOT NULL DEFAULT 10,
  deal_on         VARCHAR(60)  NOT NULL DEFAULT '',
  text            VARCHAR(400) NOT NULL DEFAULT '',
  terms           VARCHAR(200) NOT NULL DEFAULT '',
  cta             VARCHAR(40)  NOT NULL DEFAULT '',
  images          TEXT         NULL,
  with_products   TINYINT(1)   NOT NULL DEFAULT 1,
  starts_on       DATE         NULL,
  ends_on         DATE         NULL,
  is_live         TINYINT(1)   NOT NULL DEFAULT 1,
  sort_order      INT          NOT NULL DEFAULT 0,
  created_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at      DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY idx_offers_live (is_live, sort_order),
  CONSTRAINT fk_offers_category FOREIGN KEY (category_id) REFERENCES categories (id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT INTO pages (page_key, name, kind, heading, lead, banner, seo_title, seo_desc, sort_order)
SELECT 'offers', 'Offers', 'Page', 'Offers of | the *season*',
  'Seasonal savings on our handcrafted gold, diamond and bridal jewellery, chosen category by category. Every piece is BIS hallmarked.',
  'assets/images/campaign/banner-all.jpg', 'Jewellery Offers — Mangalam Jewellers',
  'This season''s offers on rings, necklaces, earrings, bangles and bridal jewellery at Mangalam Jewellers, Surat. Every piece BIS hallmarked.',
  (SELECT COALESCE(MAX(p.sort_order), 0) + 1 FROM pages p)
FROM DUAL WHERE NOT EXISTS (SELECT 1 FROM pages WHERE page_key = 'offers');
