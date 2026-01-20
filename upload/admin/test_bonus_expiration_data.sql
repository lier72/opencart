-- Test Data for Bonus Expiration System
-- This script creates test scenarios for testing the bonus_expiration_cron.php
-- Run this in your database to populate test data

-- ============================================================================
-- INSTRUCTIONS:
-- 1. Replace CUSTOMER_ID with an actual customer_id from your ocus_customer table
-- 2. Run this script: mysql -u root a1627-unqs-oc3 < test_bonus_expiration_data.sql
-- 3. Run the cron: php admin/bonus_expiration_cron.php
-- 4. Check logs: cat storage/logs/bonus_expiration.log
-- ============================================================================

-- Get a sample customer (replace 1 with actual customer_id)
SET @test_customer_id = 1059;

-- Clean up any existing test data (optional)
-- DELETE FROM ocus_customer_reward WHERE description LIKE '%TEST%';

-- ============================================================================
-- SCENARIO 1: Bonuses expiring in 90 days (should trigger 90-day warning)
-- ============================================================================
INSERT INTO ocus_customer_reward
(customer_id, order_id, description, points, date_added, date_expires)
VALUES
(@test_customer_id, 100001, 'TEST: Order #100001 (expires in 90 days)', 500, NOW(), DATE_ADD(NOW(), INTERVAL 90 DAY));

-- ============================================================================
-- SCENARIO 2: Bonuses expiring in 30 days (should trigger 30-day warning)
-- ============================================================================
INSERT INTO ocus_customer_reward
(customer_id, order_id, description, points, date_added, date_expires)
VALUES
(@test_customer_id, 100002, 'TEST: Order #100002 (expires in 30 days)', 750, DATE_SUB(NOW(), INTERVAL 60 DAY), DATE_ADD(NOW(), INTERVAL 30 DAY));

-- ============================================================================
-- SCENARIO 3: Bonuses expiring in 7 days (should trigger 7-day warning)
-- ============================================================================
INSERT INTO ocus_customer_reward
(customer_id, order_id, description, points, date_added, date_expires)
VALUES
(@test_customer_id, 100003, 'TEST: Order #100003 (expires in 7 days)', 1000, DATE_SUB(NOW(), INTERVAL 358 DAY), DATE_ADD(NOW(), INTERVAL 7 DAY));

-- ============================================================================
-- SCENARIO 4: Bonuses expiring tomorrow (should trigger 7-day warning if configured)
-- ============================================================================
INSERT INTO ocus_customer_reward
(customer_id, order_id, description, points, date_added, date_expires)
VALUES
(@test_customer_id, 100004, 'TEST: Order #100004 (expires tomorrow)', 300, DATE_SUB(NOW(), INTERVAL 364 DAY), DATE_ADD(NOW(), INTERVAL 1 DAY));

-- ============================================================================
-- SCENARIO 5: Already expired bonuses (should be marked as expired)
-- ============================================================================
INSERT INTO ocus_customer_reward
(customer_id, order_id, description, points, date_added, date_expires)
VALUES
(@test_customer_id, 100005, 'TEST: Order #100005 (already expired)', 800, DATE_SUB(NOW(), INTERVAL 400 DAY), DATE_SUB(NOW(), INTERVAL 5 DAY));

-- ============================================================================
-- SCENARIO 6: Bonuses with spent points (negative)
-- This simulates customer spending some of their bonuses
-- ============================================================================
INSERT INTO ocus_customer_reward
(customer_id, order_id, description, points, date_added, date_expires)
VALUES
(@test_customer_id, 100006, 'TEST: Order #100006 - Spent bonuses', -200, NOW(), NULL);

-- ============================================================================
-- SCENARIO 7: Never-expiring bonuses (date_expires = NULL)
-- ============================================================================
INSERT INTO ocus_customer_reward
(customer_id, order_id, description, points, date_added, date_expires)
VALUES
(@test_customer_id, 100007, 'TEST: Order #100007 (never expires)', 1500, DATE_SUB(NOW(), INTERVAL 500 DAY), NULL);

-- ============================================================================
-- SCENARIO 8: Multiple bonuses expiring on same day (should group into one email)
-- ============================================================================
INSERT INTO ocus_customer_reward
(customer_id, order_id, description, points, date_added, date_expires)
VALUES
(@test_customer_id, 100008, 'TEST: Order #100008 (expires in 30 days)', 250, DATE_SUB(NOW(), INTERVAL 335 DAY), DATE_ADD(NOW(), INTERVAL 30 DAY)),
(@test_customer_id, 100009, 'TEST: Order #100009 (expires in 30 days)', 350, DATE_SUB(NOW(), INTERVAL 335 DAY), DATE_ADD(NOW(), INTERVAL 30 DAY));

-- ============================================================================
-- SCENARIO 9: Old bonuses (2+ years old) for consolidation testing
-- ============================================================================
INSERT INTO ocus_customer_reward
(customer_id, order_id, description, points, date_added, date_expires)
VALUES
(@test_customer_id, 100010, 'TEST: Old order #100010 (3 years ago)', 2000, DATE_SUB(NOW(), INTERVAL 1095 DAY), DATE_SUB(NOW(), INTERVAL 365 DAY)),
(@test_customer_id, 100011, 'TEST: Old order #100011 - Spent', -500, DATE_SUB(NOW(), INTERVAL 1000 DAY), NULL),
(@test_customer_id, 100012, 'TEST: Old order #100012 - Spent', -700, DATE_SUB(NOW(), INTERVAL 900 DAY), NULL);

-- ============================================================================
-- SCENARIO 10: Bonuses expiring in 89 days (edge case - should trigger 90-day warning)
-- Tests the BETWEEN X-1 AND X+1 tolerance
-- ============================================================================
INSERT INTO ocus_customer_reward
(customer_id, order_id, description, points, date_added, date_expires)
VALUES
(@test_customer_id, 100013, 'TEST: Order #100013 (expires in 89 days)', 450, NOW(), DATE_ADD(NOW(), INTERVAL 89 DAY));

-- ============================================================================
-- VERIFY TEST DATA
-- ============================================================================
SELECT
    customer_reward_id,
    customer_id,
    order_id,
    description,
    points,
    DATE_FORMAT(date_added, '%Y-%m-%d') as added_date,
    DATE_FORMAT(date_expires, '%Y-%m-%d') as expires_date,
    CASE
        WHEN date_expires IS NULL THEN 'Never'
        WHEN date_expires <= NOW() THEN 'EXPIRED'
        ELSE CONCAT(DATEDIFF(date_expires, NOW()), ' days')
    END as days_until_expiration,
    CASE
        WHEN date_expires IS NULL OR date_expires > NOW() THEN 'ACTIVE'
        ELSE 'EXPIRED'
    END as status
FROM ocus_customer_reward
WHERE description LIKE '%TEST%'
ORDER BY date_expires ASC;

-- ============================================================================
-- CHECK CURRENT BALANCE (should exclude expired bonuses)
-- ============================================================================
SELECT
    @test_customer_id as customer_id,
    SUM(points) as total_balance,
    SUM(CASE WHEN date_expires IS NULL OR date_expires > NOW() THEN points ELSE 0 END) as active_balance,
    SUM(CASE WHEN date_expires IS NOT NULL AND date_expires <= NOW() THEN points ELSE 0 END) as expired_balance
FROM ocus_customer_reward
WHERE customer_id = @test_customer_id
AND description LIKE '%TEST%';

-- ============================================================================
-- EXPECTED RESULTS AFTER RUNNING CRON (with warning periods: 90,30,7)
-- ============================================================================
--
-- Expiration Warnings:
-- - 90-day warning: Orders #100001, #100013 (950 points total)
-- - 30-day warning: Orders #100002, #100008, #100009 (1350 points total)
-- - 7-day warning: Orders #100003, #100004 (1300 points total)
--
-- Expired Bonuses:
-- - Order #100005 should be marked with "(Expired)" in description
--
-- Balance Calculation:
-- Total awarded: 500+750+1000+300+800+1500+250+350+2000+450 = 7900
-- Total spent: -200-500-700 = -1400
-- Expired: 800 (order #100005)
-- Active balance: 7900 - 1400 - 800 = 5700
--
-- ============================================================================

-- ============================================================================
-- CLEANUP (run this to remove test data after testing)
-- ============================================================================
-- DELETE FROM ocus_customer_reward WHERE description LIKE '%TEST%';
