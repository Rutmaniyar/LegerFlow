ALTER TABLE payments
  ADD COLUMN type ENUM('payment','refund') NOT NULL DEFAULT 'payment' AFTER amount;
