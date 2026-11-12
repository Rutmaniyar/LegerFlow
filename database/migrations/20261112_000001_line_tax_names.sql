-- MySQL commits each ALTER independently. A retry must also keep any existing snapshots.
SET @add_tax_name = IF(EXISTS (
  SELECT 1 FROM information_schema.columns
  WHERE table_schema = DATABASE() AND table_name = 'invoice_items' AND column_name = 'tax_name'
), 'DO 0', 'ALTER TABLE invoice_items ADD COLUMN tax_name VARCHAR(190) NULL AFTER tax_rate');
PREPARE add_tax_name FROM @add_tax_name;
EXECUTE add_tax_name;
DEALLOCATE PREPARE add_tax_name;

SET @add_tax_name = IF(EXISTS (
  SELECT 1 FROM information_schema.columns
  WHERE table_schema = DATABASE() AND table_name = 'quote_items' AND column_name = 'tax_name'
), 'DO 0', 'ALTER TABLE quote_items ADD COLUMN tax_name VARCHAR(190) NULL AFTER tax_rate');
PREPARE add_tax_name FROM @add_tax_name;
EXECUTE add_tax_name;
DEALLOCATE PREPARE add_tax_name;

SET @add_tax_name = IF(EXISTS (
  SELECT 1 FROM information_schema.columns
  WHERE table_schema = DATABASE() AND table_name = 'recurring_invoice_items' AND column_name = 'tax_name'
), 'DO 0', 'ALTER TABLE recurring_invoice_items ADD COLUMN tax_name VARCHAR(190) NULL AFTER tax_rate');
PREPARE add_tax_name FROM @add_tax_name;
EXECUTE add_tax_name;
DEALLOCATE PREPARE add_tax_name;
