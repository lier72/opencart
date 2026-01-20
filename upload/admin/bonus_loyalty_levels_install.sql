-- ============================================================================
-- BONUS LOYALTY LEVELS - DATABASE INSTALLATION
-- ============================================================================
-- This script creates the loyalty program information page and sets default
-- loyalty level thresholds in the settings table
--
-- USAGE:
-- mysql -u root a1627-unqs-oc3 < admin/bonus_loyalty_levels_install.sql
-- ============================================================================

-- No separate table needed - all settings stored in ocus_setting table
-- Settings will be:
-- module_bonus_manager_loyalty_status = 1 (enable/disable auto-upgrades)
-- module_bonus_manager_loyalty_levels = JSON array of {"customer_group_id": X, "min_total_spent": Y}

-- Add information page for loyalty program
-- First, get the next available information_id
SET @next_id = (SELECT COALESCE(MAX(information_id), 0) + 1 FROM ocus_information);

INSERT INTO `ocus_information` (`information_id`, `bottom`, `sort_order`, `status`)
VALUES (@next_id, 0, 10, 1);

-- Add English description
INSERT INTO `ocus_information_description` (`information_id`, `language_id`, `title`, `description`, `meta_title`, `meta_description`, `meta_keyword`)
VALUES (
  @next_id,
  3, -- English language_id (adjust if different)
  'Loyalty Program Levels',
  '<h2>Welcome to Our Loyalty Program!</h2>

<p>Our loyalty program rewards you for every purchase with bonus points and automatic upgrades to better pricing tiers.</p>

<h3>How It Works</h3>

<ul>
  <li><strong>Earn Bonus Points:</strong> Get up to 10% back on every purchase as bonus points</li>
  <li><strong>Use Points for Payment:</strong> Pay up to 30% of your next order with bonus points</li>
  <li><strong>Automatic Upgrades:</strong> As you shop more, you automatically move to higher loyalty levels with better prices</li>
  <li><strong>Exclusive Offers:</strong> Members get access to special deals and early product launches</li>
</ul>

<h3>Loyalty Levels</h3>

<table class="table table-bordered">
  <thead>
    <tr>
      <th>Level</th>
      <th>Total Purchases Required</th>
      <th>Benefits</th>
    </tr>
  </thead>
  <tbody>
    <tr>
      <td><strong>Default Customer</strong></td>
      <td>0 ₽</td>
      <td>Standard pricing, 10% bonus points</td>
    </tr>
    <tr>
      <td><strong>Sportsmen</strong></td>
      <td>50,000 ₽</td>
      <td>Better pricing tier, priority support</td>
    </tr>
    <tr>
      <td><strong>Friend -15%</strong></td>
      <td>100,000 ₽</td>
      <td>15% better prices, VIP support</td>
    </tr>
  </tbody>
</table>

<p><em>Your customer group will be automatically upgraded when you reach the threshold. Prices update immediately!</em></p>

<h3>Bonus Points Rules</h3>

<ul>
  <li>Points are awarded when your order is <strong>completed</strong></li>
  <li>Points are valid for <strong>365 days</strong> from the date earned</li>
  <li>You will receive email notifications when points are about to expire</li>
  <li>Points cannot be transferred between accounts</li>
</ul>

<h3>Get Started</h3>

<p>Simply <a href="index.php?route=account/register">register for a free account</a> and start earning points with your first purchase!</p>',
  'Loyalty Program Levels',
  'Learn about our loyalty program levels, bonus points, and automatic upgrades to better pricing tiers',
  'loyalty program, bonus points, customer levels, rewards'
);

-- Add Russian description
INSERT INTO `ocus_information_description` (`information_id`, `language_id`, `title`, `description`, `meta_title`, `meta_description`, `meta_keyword`)
VALUES (
  @next_id,
  1, -- Russian language_id (adjust if different)
  'Уровни программы лояльности',
  '<h2>Добро пожаловать в нашу программу лояльности!</h2>

<p>Наша программа лояльности вознаграждает вас за каждую покупку бонусными баллами и автоматическими переходами на более выгодные ценовые уровни.</p>

<h3>Как это работает</h3>

<ul>
  <li><strong>Зарабатывайте бонусные баллы:</strong> Получайте до 10% от каждой покупки в виде бонусных баллов</li>
  <li><strong>Оплачивайте баллами:</strong> Оплачивайте до 30% следующего заказа бонусными баллами</li>
  <li><strong>Автоматические повышения:</strong> По мере роста ваших покупок вы автоматически переходите на более высокие уровни лояльности с лучшими ценами</li>
  <li><strong>Эксклюзивные предложения:</strong> Участники получают доступ к специальным акциям и ранним запускам продуктов</li>
</ul>

<h3>Уровни лояльности</h3>

<table class="table table-bordered">
  <thead>
    <tr>
      <th>Уровень</th>
      <th>Требуемая сумма покупок</th>
      <th>Преимущества</th>
    </tr>
  </thead>
  <tbody>
    <tr>
      <td><strong>Обычный покупатель</strong></td>
      <td>0 ₽</td>
      <td>Стандартные цены, 10% бонусных баллов</td>
    </tr>
    <tr>
      <td><strong>Спортсмен</strong></td>
      <td>50 000 ₽</td>
      <td>Улучшенные цены, приоритетная поддержка</td>
    </tr>
    <tr>
      <td><strong>Друг -15%</strong></td>
      <td>100 000 ₽</td>
      <td>Цены на 15% ниже, VIP поддержка</td>
    </tr>
  </tbody>
</table>

<p><em>Ваша группа покупателей будет автоматически повышена при достижении порога. Цены обновляются мгновенно!</em></p>

<h3>Правила бонусных баллов</h3>

<ul>
  <li>Баллы начисляются когда ваш заказ <strong>завершен</strong></li>
  <li>Баллы действительны <strong>365 дней</strong> с даты начисления</li>
  <li>Вы получите email уведомления когда баллы скоро сгорят</li>
  <li>Баллы не могут быть переданы между аккаунтами</li>
</ul>

<h3>Начните прямо сейчас</h3>

<p>Просто <a href="index.php?route=account/register">зарегистрируйте бесплатный аккаунт</a> и начните зарабатывать баллы с первой покупки!</p>',
  'Уровни программы лояльности',
  'Узнайте о уровнях нашей программы лояльности, бонусных баллах и автоматических переходах на более выгодные ценовые уровни',
  'программа лояльности, бонусные баллы, уровни клиентов, вознаграждения'
);

-- Add to information_to_store (make visible on all stores)
INSERT INTO `ocus_information_to_store` (`information_id`, `store_id`)
VALUES (@next_id, 0);

-- Add to information_to_layout (use default layout)
INSERT INTO `ocus_information_to_layout` (`information_id`, `store_id`, `layout_id`)
VALUES (@next_id, 0, 0);

-- Insert default loyalty level settings into ocus_setting
-- Enable loyalty auto-upgrade feature
INSERT INTO `ocus_setting` (`store_id`, `code`, `key`, `value`, `serialized`)
VALUES (0, 'module_bonus_manager', 'module_bonus_manager_loyalty_status', '1', 0)
ON DUPLICATE KEY UPDATE `value` = '1';

-- Default loyalty levels as JSON array
-- [{"customer_group_id": 1, "min_total_spent": 0}, {"customer_group_id": 2, "min_total_spent": 50000}, {"customer_group_id": 6, "min_total_spent": 100000}]
INSERT INTO `ocus_setting` (`store_id`, `code`, `key`, `value`, `serialized`)
VALUES (
  0,
  'module_bonus_manager',
  'module_bonus_manager_loyalty_levels',
  '[{\"customer_group_id\":1,\"min_total_spent\":0},{\"customer_group_id\":2,\"min_total_spent\":50000},{\"customer_group_id\":6,\"min_total_spent\":100000}]',
  0
)
ON DUPLICATE KEY UPDATE `value` = '[{\"customer_group_id\":1,\"min_total_spent\":0},{\"customer_group_id\":2,\"min_total_spent\":50000},{\"customer_group_id\":6,\"min_total_spent\":100000}]';

-- Information page ID for loyalty program (used in links)
INSERT INTO `ocus_setting` (`store_id`, `code`, `key`, `value`, `serialized`)
VALUES (0, 'module_bonus_manager', 'module_bonus_manager_loyalty_info_id', @next_id, 0)
ON DUPLICATE KEY UPDATE `value` = @next_id;

-- Drop old table if it exists from previous installation
DROP TABLE IF EXISTS `ocus_bonus_loyalty_level`;

-- Display installation results
SELECT 'Loyalty program settings added to ocus_setting table' AS status;
SELECT CONCAT('Information page created with ID: ', @next_id) AS info_page;
SELECT * FROM ocus_setting WHERE `key` LIKE 'module_bonus_manager_loyalty%';
