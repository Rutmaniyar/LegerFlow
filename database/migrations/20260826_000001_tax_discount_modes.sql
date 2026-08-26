ALTER TABLE taxes
  MODIFY COLUMN rate DECIMAL(8,2) NOT NULL DEFAULT 0;

ALTER TABLE quote_items
  ADD COLUMN discount_type ENUM('percent','amount') NOT NULL DEFAULT 'percent' AFTER unit_price,
  CHANGE COLUMN discount_rate discount_value DECIMAL(14,2) NOT NULL DEFAULT 0,
  MODIFY COLUMN tax_rate DECIMAL(8,2) NOT NULL DEFAULT 0;

ALTER TABLE invoice_items
  ADD COLUMN discount_type ENUM('percent','amount') NOT NULL DEFAULT 'percent' AFTER unit_price,
  CHANGE COLUMN discount_rate discount_value DECIMAL(14,2) NOT NULL DEFAULT 0,
  MODIFY COLUMN tax_rate DECIMAL(8,2) NOT NULL DEFAULT 0;

ALTER TABLE recurring_invoice_items
  ADD COLUMN discount_type ENUM('percent','amount') NOT NULL DEFAULT 'percent' AFTER unit_price,
  CHANGE COLUMN discount_rate discount_value DECIMAL(14,2) NOT NULL DEFAULT 0,
  MODIFY COLUMN tax_rate DECIMAL(8,2) NOT NULL DEFAULT 0;
