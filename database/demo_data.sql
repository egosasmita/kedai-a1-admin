-- Data awal demo untuk Kedai A1 (MySQL / MariaDB).
-- Jalankan setelah seluruh migration Laravel selesai.
-- Akun demo menggunakan password yang sama: KedaiDemo2026!
-- Hash di bawah dibuat dengan algoritma bcrypt Laravel.

START TRANSACTION;

SET @demo_password_hash = '$2y$12$pLGYa9p9JdKK9wq0boexSufcmSFmPxQ4C593yaYsCC5sRr6iSnb3G';

INSERT INTO users (name, email, password, is_admin, created_at, updated_at)
VALUES
    ('Admin Demo Kedai A1', 'admin.demo@kedaia1.test', @demo_password_hash, 1, NOW(), NOW()),
    ('Ayu Lestari', 'ayu.demo@kedaia1.test', @demo_password_hash, 0, NOW(), NOW()),
    ('Bima Saputra', 'bima.demo@kedaia1.test', @demo_password_hash, 0, NOW(), NOW()),
    ('Citra Maharani', 'citra.demo@kedaia1.test', @demo_password_hash, 0, NOW(), NOW()),
    ('Doni Pratama', 'doni.demo@kedaia1.test', @demo_password_hash, 0, NOW(), NOW())
ON DUPLICATE KEY UPDATE
    name = VALUES(name),
    password = VALUES(password),
    is_admin = VALUES(is_admin),
    updated_at = NOW();

SET @demo_admin_id = (SELECT id FROM users WHERE email = 'admin.demo@kedaia1.test');

INSERT INTO settings (shop_name, tagline, address, phone, instagram, open_hour, close_hour, created_at, updated_at)
SELECT 'Kedai A1', 'Kopi dan cerita baik setiap hari.', 'Nganjuk, Jawa Timur',
       '081234567890', '@kedaia1', '08:00', '22:00', NOW(), NOW()
WHERE NOT EXISTS (SELECT 1 FROM settings WHERE shop_name = 'Kedai A1');

INSERT INTO categories (name, description, created_at, updated_at)
VALUES
    ('KOPI', 'Minuman berbasis kopi.', NOW(), NOW()),
    ('MAKANAN', 'Menu makanan utama.', NOW(), NOW()),
    ('SNACK', 'Camilan dan makanan ringan.', NOW(), NOW()),
    ('NON KOPI', 'Minuman tanpa kopi.', NOW(), NOW())
ON DUPLICATE KEY UPDATE
    description = VALUES(description),
    updated_at = NOW();

INSERT INTO menus
    (category_id, name, slug, description, price, image_url, is_available, is_featured, created_at, updated_at)
SELECT c.id, seed.name, seed.slug, seed.description, seed.price, NULL, 1, 0, NOW(), NOW()
FROM (
    SELECT 'Americano' AS name, 'americano' AS slug, 'Kopi hitam dengan rasa seimbang.' AS description, 13000 AS price, 'KOPI' AS category
    UNION ALL SELECT 'Matcha Milk', 'matcha-milk', 'Matcha dengan susu segar.', 15000, 'KOPI'
    UNION ALL SELECT 'Hazelnut Latte', 'hazelnut-latte', 'Espresso, susu, dan sirup hazelnut.', 15000, 'KOPI'
    UNION ALL SELECT 'Nasi Katsu Mentai', 'nasi-katsu-mentai', 'Nasi dengan chicken katsu dan saus mentai.', 13000, 'MAKANAN'
    UNION ALL SELECT 'Mie Goreng Spesial', 'mie-goreng-spesial', 'Mi goreng spesial dengan telur.', 12000, 'MAKANAN'
    UNION ALL SELECT 'Pisang Choco', 'pisang-choco', 'Pisang dengan cokelat.', 10000, 'SNACK'
    UNION ALL SELECT 'Cireng', 'cireng', 'Cireng goreng renyah.', 10000, 'SNACK'
    UNION ALL SELECT 'French Fries', 'french-fries', 'Kentang goreng.', 12000, 'SNACK'
    UNION ALL SELECT 'Matcha', 'matcha', 'Minuman matcha.', 15000, 'NON KOPI'
    UNION ALL SELECT 'Mineral', 'mineral', 'Air mineral botol.', 5000, 'NON KOPI'
) AS seed
JOIN categories AS c ON c.name = seed.category
ON DUPLICATE KEY UPDATE
    category_id = VALUES(category_id),
    name = VALUES(name),
    description = VALUES(description),
    price = VALUES(price),
    is_available = VALUES(is_available),
    updated_at = NOW();

INSERT INTO employee_profiles
    (user_id, employee_number, position, hired_at, base_salary, employment_status, notes, created_at, updated_at)
SELECT u.id, seed.employee_number, seed.position, '2025-01-06',
       seed.base_salary, 'active', 'Profil karyawan demo.', NOW(), NOW()
FROM (
    SELECT 'ayu.demo@kedaia1.test' AS email, 'A1-DEMO-001' AS employee_number, 'Barista' AS position, 3500000 AS base_salary
    UNION ALL SELECT 'bima.demo@kedaia1.test', 'A1-DEMO-002', 'Kasir', 3200000
    UNION ALL SELECT 'citra.demo@kedaia1.test', 'A1-DEMO-003', 'Koki', 3300000
    UNION ALL SELECT 'doni.demo@kedaia1.test', 'A1-DEMO-004', 'Pelayan', 3000000
) AS seed
JOIN users AS u ON u.email = seed.email
ON DUPLICATE KEY UPDATE
    employee_number = VALUES(employee_number),
    position = VALUES(position),
    base_salary = VALUES(base_salary),
    employment_status = VALUES(employment_status),
    updated_at = NOW();

INSERT INTO shifts (name, starts_at, ends_at, is_active, sort_order, created_at, updated_at)
VALUES
    ('Shift Pagi', '07:00:00', '15:00:00', 1, 1, NOW(), NOW()),
    ('Shift Siang', '11:00:00', '19:00:00', 1, 2, NOW(), NOW()),
    ('Shift Sore', '15:00:00', '23:00:00', 1, 3, NOW(), NOW())
ON DUPLICATE KEY UPDATE
    starts_at = VALUES(starts_at),
    ends_at = VALUES(ends_at),
    is_active = VALUES(is_active),
    sort_order = VALUES(sort_order),
    updated_at = NOW();

INSERT INTO stock_items
    (sku, name, unit, quantity, minimum_quantity, unit_cost, is_active, notes, created_at, updated_at)
VALUES
    ('SQL-DEMO-BEAN', 'Biji kopi house blend', 'kg', 8, 2, 180000, 1, 'Stok demo.', NOW(), NOW()),
    ('SQL-DEMO-MILK', 'Susu segar', 'liter', 12, 4, 22000, 1, 'Stok demo.', NOW(), NOW()),
    ('SQL-DEMO-MATCHA', 'Bubuk matcha', 'kg', 1.5, 0.5, 250000, 1, 'Stok demo.', NOW(), NOW()),
    ('SQL-DEMO-CUP', 'Gelas take away', 'pcs', 100, 25, 1200, 1, 'Stok demo.', NOW(), NOW()),
    ('SQL-DEMO-SUGAR', 'Gula aren', 'kg', 0.5, 1, 35000, 1, 'Stok demo - di bawah batas minimum.', NOW(), NOW()),
    ('SQL-DEMO-RICE', 'Beras', 'kg', 10, 3, 16000, 1, 'Stok demo.', NOW(), NOW()),
    ('SQL-DEMO-KATSU', 'Chicken katsu', 'pcs', 30, 8, 7500, 1, 'Stok demo.', NOW(), NOW()),
    ('SQL-DEMO-MENTAI', 'Saus mentai', 'liter', 2, 0.5, 85000, 1, 'Stok demo.', NOW(), NOW()),
    ('SQL-DEMO-NOODLE', 'Mi telur', 'pcs', 40, 10, 2500, 1, 'Stok demo.', NOW(), NOW()),
    ('SQL-DEMO-EGG', 'Telur ayam', 'pcs', 30, 10, 2500, 1, 'Stok demo.', NOW(), NOW()),
    ('SQL-DEMO-OIL', 'Minyak goreng', 'liter', 5, 2, 18000, 1, 'Stok demo.', NOW(), NOW()),
    ('SQL-DEMO-BANANA', 'Pisang', 'kg', 4, 1, 22000, 1, 'Stok demo.', NOW(), NOW()),
    ('SQL-DEMO-CHOCOLATE', 'Cokelat', 'kg', 2, 0.5, 95000, 1, 'Stok demo.', NOW(), NOW()),
    ('SQL-DEMO-CIRENG', 'Cireng frozen', 'pcs', 50, 15, 1000, 1, 'Stok demo.', NOW(), NOW()),
    ('SQL-DEMO-FRIES', 'Kentang frozen', 'kg', 5, 2, 30000, 1, 'Stok demo.', NOW(), NOW()),
    ('SQL-DEMO-WATER', 'Air mineral botol', 'pcs', 48, 12, 2500, 1, 'Stok demo.', NOW(), NOW())
ON DUPLICATE KEY UPDATE
    name = VALUES(name),
    unit = VALUES(unit),
    quantity = VALUES(quantity),
    minimum_quantity = VALUES(minimum_quantity),
    unit_cost = VALUES(unit_cost),
    is_active = VALUES(is_active),
    notes = VALUES(notes),
    updated_at = NOW();

INSERT INTO stock_transactions
    (stock_item_id, recorded_by, type, quantity, unit_cost, quantity_before, quantity_after, reference, notes, occurred_at, created_at, updated_at)
SELECT si.id, @demo_admin_id, 'in', si.quantity, si.unit_cost, 0, si.quantity,
       CONCAT('SQL-DEMO-INITIAL-', si.sku), 'Stok awal demo.', '2026-10-01 08:00:00', NOW(), NOW()
FROM stock_items AS si
WHERE si.sku LIKE 'SQL-DEMO-%'
  AND NOT EXISTS (
      SELECT 1 FROM stock_transactions AS st
      WHERE st.stock_item_id = si.id AND st.reference = CONCAT('SQL-DEMO-INITIAL-', si.sku)
  );

INSERT INTO menu_ingredients
    (menu_id, stock_item_id, quantity_per_serving, notes, created_at, updated_at)
SELECT m.id, si.id, recipe.quantity_per_serving, 'Takaran demo per porsi.', NOW(), NOW()
FROM (
    SELECT 'americano' AS menu_slug, 'SQL-DEMO-BEAN' AS sku, 0.018 AS quantity_per_serving
    UNION ALL SELECT 'americano', 'SQL-DEMO-CUP', 1
    UNION ALL SELECT 'matcha-milk', 'SQL-DEMO-MATCHA', 0.02
    UNION ALL SELECT 'matcha-milk', 'SQL-DEMO-MILK', 0.2
    UNION ALL SELECT 'matcha-milk', 'SQL-DEMO-CUP', 1
    UNION ALL SELECT 'hazelnut-latte', 'SQL-DEMO-BEAN', 0.018
    UNION ALL SELECT 'hazelnut-latte', 'SQL-DEMO-MILK', 0.2
    UNION ALL SELECT 'hazelnut-latte', 'SQL-DEMO-SUGAR', 0.02
    UNION ALL SELECT 'nasi-katsu-mentai', 'SQL-DEMO-RICE', 0.15
    UNION ALL SELECT 'nasi-katsu-mentai', 'SQL-DEMO-KATSU', 1
    UNION ALL SELECT 'nasi-katsu-mentai', 'SQL-DEMO-MENTAI', 0.03
    UNION ALL SELECT 'mie-goreng-spesial', 'SQL-DEMO-NOODLE', 1
    UNION ALL SELECT 'mie-goreng-spesial', 'SQL-DEMO-EGG', 1
    UNION ALL SELECT 'mie-goreng-spesial', 'SQL-DEMO-OIL', 0.02
    UNION ALL SELECT 'pisang-choco', 'SQL-DEMO-BANANA', 0.15
    UNION ALL SELECT 'pisang-choco', 'SQL-DEMO-CHOCOLATE', 0.03
    UNION ALL SELECT 'cireng', 'SQL-DEMO-CIRENG', 5
    UNION ALL SELECT 'cireng', 'SQL-DEMO-OIL', 0.02
    UNION ALL SELECT 'french-fries', 'SQL-DEMO-FRIES', 0.15
    UNION ALL SELECT 'french-fries', 'SQL-DEMO-OIL', 0.02
    UNION ALL SELECT 'matcha', 'SQL-DEMO-MATCHA', 0.02
    UNION ALL SELECT 'matcha', 'SQL-DEMO-CUP', 1
    UNION ALL SELECT 'mineral', 'SQL-DEMO-WATER', 1
) AS recipe
JOIN menus AS m ON m.slug = recipe.menu_slug
JOIN stock_items AS si ON si.sku = recipe.sku
ON DUPLICATE KEY UPDATE
    quantity_per_serving = VALUES(quantity_per_serving),
    notes = VALUES(notes),
    updated_at = NOW();

INSERT IGNORE INTO attendances
    (user_id, shift_id, attendance_date, check_in_at, check_out_at, status, late_minutes, notes, created_at, updated_at)
SELECT u.id, sh.id, a.attendance_date, a.check_in_at, a.check_out_at, a.status, a.late_minutes,
       'Absensi demo - bukan catatan kehadiran aktual.', NOW(), NOW()
FROM (
    SELECT 'ayu.demo@kedaia1.test' AS email, '2026-10-01' AS attendance_date, '2026-10-01 06:55:00' AS check_in_at, '2026-10-01 15:05:00' AS check_out_at, 'present' AS status, 0 AS late_minutes
    UNION ALL SELECT 'ayu.demo@kedaia1.test', '2026-10-02', '2026-10-02 07:12:00', '2026-10-02 15:03:00', 'late', 12
    UNION ALL SELECT 'ayu.demo@kedaia1.test', '2026-10-05', '2026-10-05 06:58:00', '2026-10-05 15:04:00', 'present', 0
    UNION ALL SELECT 'ayu.demo@kedaia1.test', '2026-10-06', '2026-10-06 06:57:00', '2026-10-06 15:06:00', 'present', 0
    UNION ALL SELECT 'ayu.demo@kedaia1.test', '2026-10-07', '2026-10-07 06:59:00', '2026-10-07 15:02:00', 'present', 0
    UNION ALL SELECT 'bima.demo@kedaia1.test', '2026-10-01', '2026-10-01 10:55:00', '2026-10-01 19:05:00', 'present', 0
    UNION ALL SELECT 'bima.demo@kedaia1.test', '2026-10-02', '2026-10-02 11:10:00', '2026-10-02 19:03:00', 'late', 10
    UNION ALL SELECT 'bima.demo@kedaia1.test', '2026-10-05', '2026-10-05 10:58:00', '2026-10-05 19:04:00', 'present', 0
    UNION ALL SELECT 'bima.demo@kedaia1.test', '2026-10-06', '2026-10-06 10:57:00', '2026-10-06 19:06:00', 'present', 0
    UNION ALL SELECT 'bima.demo@kedaia1.test', '2026-10-07', '2026-10-07 10:59:00', '2026-10-07 19:02:00', 'present', 0
    UNION ALL SELECT 'citra.demo@kedaia1.test', '2026-10-01', '2026-10-01 06:55:00', '2026-10-01 15:05:00', 'present', 0
    UNION ALL SELECT 'citra.demo@kedaia1.test', '2026-10-02', '2026-10-02 07:15:00', '2026-10-02 15:03:00', 'late', 15
    UNION ALL SELECT 'citra.demo@kedaia1.test', '2026-10-05', '2026-10-05 06:58:00', '2026-10-05 15:04:00', 'present', 0
    UNION ALL SELECT 'citra.demo@kedaia1.test', '2026-10-06', '2026-10-06 06:57:00', '2026-10-06 15:06:00', 'present', 0
    UNION ALL SELECT 'citra.demo@kedaia1.test', '2026-10-07', '2026-10-07 06:59:00', '2026-10-07 15:02:00', 'present', 0
    UNION ALL SELECT 'doni.demo@kedaia1.test', '2026-10-01', '2026-10-01 14:55:00', '2026-10-01 23:05:00', 'present', 0
    UNION ALL SELECT 'doni.demo@kedaia1.test', '2026-10-02', '2026-10-02 15:12:00', '2026-10-02 23:03:00', 'late', 12
    UNION ALL SELECT 'doni.demo@kedaia1.test', '2026-10-05', '2026-10-05 14:58:00', '2026-10-05 23:04:00', 'present', 0
    UNION ALL SELECT 'doni.demo@kedaia1.test', '2026-10-06', '2026-10-06 14:57:00', '2026-10-06 23:06:00', 'present', 0
    UNION ALL SELECT 'doni.demo@kedaia1.test', '2026-10-07', '2026-10-07 14:59:00', '2026-10-07 23:02:00', 'present', 0
) AS a
JOIN users AS u ON u.email = a.email
JOIN shifts AS sh ON sh.name = CASE
    WHEN a.email = 'bima.demo@kedaia1.test' THEN 'Shift Siang'
    WHEN a.email = 'doni.demo@kedaia1.test' THEN 'Shift Sore'
    ELSE 'Shift Pagi'
END;

INSERT INTO payroll_periods (name, starts_on, ends_on, status, processed_by, notes, created_at, updated_at)
VALUES ('Oktober 2026', '2026-10-01', '2026-10-31', 'calculated', @demo_admin_id, 'Periode penggajian demo - belum dibayar.', NOW(), NOW())
ON DUPLICATE KEY UPDATE
    name = VALUES(name),
    status = IF(status = 'paid', status, VALUES(status)),
    processed_by = VALUES(processed_by),
    notes = VALUES(notes),
    updated_at = NOW();

SET @demo_payroll_period_id = (
    SELECT id FROM payroll_periods
    WHERE starts_on = '2026-10-01' AND ends_on = '2026-10-31'
);

INSERT INTO payrolls
    (payroll_period_id, user_id, attendance_days, late_count, overtime_hours, base_salary,
     overtime_amount, allowances, bonus, deductions, net_pay, status, notes, created_at, updated_at)
SELECT @demo_payroll_period_id, u.id, attendance.attendance_days, attendance.late_count, 0,
       ep.base_salary, 0, 150000, 100000, 50000,
       ep.base_salary + 150000 + 100000 - 50000, 'review',
       'Gaji demo dihitung dari absensi Oktober - belum dibayar.', NOW(), NOW()
FROM users AS u
JOIN employee_profiles AS ep ON ep.user_id = u.id
JOIN (
    SELECT user_id,
           SUM(status IN ('present', 'late')) AS attendance_days,
           SUM(status = 'late') AS late_count
    FROM attendances
    WHERE attendance_date BETWEEN '2026-10-01' AND '2026-10-31'
    GROUP BY user_id
) AS attendance ON attendance.user_id = u.id
WHERE u.email IN (
    'ayu.demo@kedaia1.test',
    'bima.demo@kedaia1.test',
    'citra.demo@kedaia1.test',
    'doni.demo@kedaia1.test'
)
ON DUPLICATE KEY UPDATE
    attendance_days = VALUES(attendance_days),
    late_count = VALUES(late_count),
    base_salary = VALUES(base_salary),
    allowances = VALUES(allowances),
    bonus = VALUES(bonus),
    deductions = VALUES(deductions),
    net_pay = VALUES(net_pay),
    status = IF(status = 'paid', status, VALUES(status)),
    notes = VALUES(notes),
    updated_at = NOW();

INSERT INTO payroll_items (payroll_id, type, label, amount, notes, created_at, updated_at)
SELECT p.id, item.type, item.label, item.amount, 'Komponen gaji demo.', NOW(), NOW()
FROM payrolls AS p
JOIN users AS u ON u.id = p.user_id
JOIN (
    SELECT 'allowance' AS type, 'Tunjangan transport' AS label, 150000 AS amount
    UNION ALL SELECT 'bonus', 'Bonus kinerja', 100000
    UNION ALL SELECT 'deduction', 'Potongan keterlambatan', 50000
) AS item
WHERE p.payroll_period_id = @demo_payroll_period_id
  AND u.email IN (
      'ayu.demo@kedaia1.test',
      'bima.demo@kedaia1.test',
      'citra.demo@kedaia1.test',
      'doni.demo@kedaia1.test'
  )
  AND NOT EXISTS (
      SELECT 1 FROM payroll_items AS existing
      WHERE existing.payroll_id = p.id
        AND existing.type = item.type
        AND existing.label = item.label
  );

COMMIT;

-- Pemeriksaan hasil penggajian:
SELECT u.name, pp.name AS periode, p.attendance_days AS hari_hadir, p.late_count AS terlambat,
       p.base_salary AS gaji_pokok, p.allowances AS tunjangan, p.bonus AS bonus,
       p.deductions AS potongan, p.net_pay AS gaji_bersih, p.status
FROM payrolls AS p
JOIN users AS u ON u.id = p.user_id
JOIN payroll_periods AS pp ON pp.id = p.payroll_period_id
WHERE pp.starts_on = '2026-10-01'
  AND u.email IN (
      'ayu.demo@kedaia1.test',
      'bima.demo@kedaia1.test',
      'citra.demo@kedaia1.test',
      'doni.demo@kedaia1.test'
  )
ORDER BY u.name;
