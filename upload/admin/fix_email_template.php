<?php
/**
 * Script to manually fix email template in database
 * Access via: http://localhost/~max/oc3.uniqsport.ru/admin/fix_email_template.php
 */

// Bootstrap OpenCart
require_once(dirname(__FILE__) . '/../config.php');
require_once(DIR_SYSTEM . 'startup.php');

// Registry
$registry = new Registry();

// Database
$db = new DB(DB_DRIVER, DB_HOSTNAME, DB_USERNAME, DB_PASSWORD, DB_DATABASE, DB_PORT);
$registry->set('db', $db);

echo "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>Fix Email Template</title></head><body>";
echo "<h1>Fix Email Template</h1>";

// The correct template with HTML entities
$correct_template = '<div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;">
	<div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); padding: 30px; text-align: center; border-radius: 8px 8px 0 0;">
		<h1 style="color: white; margin: 0; font-size: 28px;">&#127881; Поздравляем!</h1>
	</div>

	<div style="background: #f9fafb; padding: 30px; border-radius: 0 0 8px 8px;">
		<p style="font-size: 16px; color: #374151;">Здравствуйте, <strong>{customer_firstname}</strong>!</p>

		<p style="font-size: 16px; color: #374151; line-height: 1.6;">
			Мы рады сообщить, что ваш уровень лояльности был повышен!
		</p>

		<div style="background: white; border-left: 4px solid #667eea; padding: 20px; margin: 20px 0; border-radius: 4px;">
			<p style="margin: 0 0 10px 0; color: #6b7280; font-size: 14px;">Ваш предыдущий уровень:</p>
			<p style="margin: 0 0 15px 0; font-size: 18px; color: #9ca3af;"><s>{old_group_name}</s></p>

			<p style="margin: 0 0 10px 0; color: #6b7280; font-size: 14px;">Ваш новый уровень:</p>
			<p style="margin: 0; font-size: 24px; color: #667eea; font-weight: bold;">{new_group_name}</p>
		</div>

		<h3 style="color: #374151; margin-top: 30px;">Ваши новые преимущества:</h3>
		<ul style="color: #374151; line-height: 1.8; font-size: 15px;">
			<li><strong>Повышенный процент бонусов:</strong> {new_bonus_percent}% от суммы покупки</li>
			<li><strong>Приоритетная поддержка</strong></li>
			<li><strong>Эксклюзивные предложения</strong> для вашего уровня</li>
		</ul>

		<div style="background: #eff6ff; border: 1px solid #bfdbfe; border-radius: 6px; padding: 15px; margin: 20px 0;">
			<p style="margin: 0; color: #1e40af; font-size: 14px;">
				<strong>Ваша статистика:</strong><br>
				&#128176; Сумма покупок: <strong>{total_spent} &#8381;</strong><br>
				&#127873; Текущий баланс бонусов: <strong>{current_balance} &#8381;</strong><br>
				&#128197; Дата повышения: {date_upgraded}
			</p>
		</div>

		<p style="font-size: 16px; color: #374151; line-height: 1.6;">
			Спасибо за то, что выбираете нас! Продолжайте делать покупки и получайте еще больше бонусов.
		</p>

		<div style="text-align: center; margin: 30px 0;">
			<a href="{account_url}" style="display: inline-block; padding: 14px 28px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; text-decoration: none; border-radius: 6px; font-weight: bold; font-size: 16px;">
				Перейти в личный кабинет
			</a>
		</div>

		<div style="text-align: center; margin-top: 30px; padding-top: 20px; border-top: 1px solid #e5e7eb;">
			<p style="color: #6b7280; font-size: 14px; margin: 0;">
				С уважением,<br>
				<strong>{store_name}</strong>
			</p>
		</div>
	</div>
</div>';

echo "<h2>Step 1: Check Current Value</h2>";
$query = $db->query("SELECT * FROM `" . DB_PREFIX . "setting` WHERE `key` = 'module_bonus_manager_email_loyalty_upgrade_body'");

if ($query->num_rows) {
    echo "<p style='color: green;'>✓ Record exists</p>";
    echo "<p><strong>Current length:</strong> " . strlen($query->row['value']) . " bytes</p>";
    echo "<p><strong>Serialized:</strong> " . $query->row['serialized'] . "</p>";
} else {
    echo "<p style='color: red;'>✗ Record does NOT exist</p>";
}

echo "<h2>Step 2: Delete Old Record</h2>";
$db->query("DELETE FROM `" . DB_PREFIX . "setting` WHERE `key` = 'module_bonus_manager_email_loyalty_upgrade_body'");
echo "<p style='color: green;'>✓ Old record deleted</p>";

echo "<h2>Step 3: Insert Correct Template</h2>";
$db->query("INSERT INTO `" . DB_PREFIX . "setting`
    SET store_id = '0',
    `code` = 'module_bonus_manager',
    `key` = 'module_bonus_manager_email_loyalty_upgrade_body',
    `value` = '" . $db->escape($correct_template) . "',
    serialized = '0'");

echo "<p style='color: green;'>✓ New template inserted</p>";
echo "<p><strong>Template length:</strong> " . strlen($correct_template) . " bytes</p>";

echo "<h2>Step 4: Verify</h2>";
$verify = $db->query("SELECT * FROM `" . DB_PREFIX . "setting` WHERE `key` = 'module_bonus_manager_email_loyalty_upgrade_body'");

if ($verify->num_rows) {
    $saved_value = $verify->row['value'];
    echo "<p style='color: green;'>✓ Template saved successfully</p>";
    echo "<p><strong>Saved length:</strong> " . strlen($saved_value) . " bytes</p>";

    if (strlen($saved_value) == strlen($correct_template)) {
        echo "<p style='color: green; font-weight: bold;'>✓✓✓ SUCCESS! Lengths match!</p>";
    } else {
        echo "<p style='color: orange;'>⚠ Warning: Length mismatch</p>";
        echo "<p>Expected: " . strlen($correct_template) . " bytes</p>";
        echo "<p>Got: " . strlen($saved_value) . " bytes</p>";
    }

    echo "<h3>First 500 characters:</h3>";
    echo "<pre style='background: #f0f0f0; padding: 10px; border: 1px solid #ccc;'>";
    echo htmlspecialchars(substr($saved_value, 0, 500));
    echo "</pre>";
} else {
    echo "<p style='color: red;'>✗ Failed to save template</p>";
}

echo "<hr>";
echo "<p><a href='" . str_replace('admin/fix_email_template.php', 'admin/index.php?route=extension/module/bonus_manager&user_token=', $_SERVER['REQUEST_URI']) . "' style='padding: 10px 20px; background: #28a745; color: white; text-decoration: none; border-radius: 4px; display: inline-block;'>Go to Bonus Manager Settings</a></p>";

echo "</body></html>";
