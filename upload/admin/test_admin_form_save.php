<?php
/**
 * Test script to mimic admin form save and debug truncation
 * Access via: http://localhost/~max/oc3.uniqsport.ru/admin/test_admin_form_save.php
 */

// Bootstrap OpenCart
require_once(dirname(__FILE__) . '/../config.php');
require_once(DIR_SYSTEM . 'startup.php');

// Registry
$registry = new Registry();

// Config
$config = new Config();
$registry->set('config', $config);

// Database
$db = new DB(DB_DRIVER, DB_HOSTNAME, DB_USERNAME, DB_PASSWORD, DB_DATABASE, DB_PORT);
$registry->set('db', $db);

echo "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>Admin Form Save Test</title></head><body>";
echo "<h1>Admin Form Save Test</h1>";

$test_template = '<div style="font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto;">
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

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
	echo "<h2>Testing OpenCart Request Class Behavior</h2>";

	// Simulate what OpenCart does
	$_POST['test_template'] = $test_template;

	// Create Request object (this will run htmlspecialchars on all POST data)
	$request = new Request();

	echo "<h3>1. Original Template</h3>";
	echo "<p><strong>Size:</strong> " . strlen($test_template) . " bytes</p>";
	echo "<pre>" . htmlspecialchars(substr($test_template, 0, 300)) . "...</pre>";

	echo "<h3>2. After Request Class Processing</h3>";
	$processed_value = $request->post['test_template'];
	echo "<p><strong>Size:</strong> " . strlen($processed_value) . " bytes</p>";
	echo "<pre>" . htmlspecialchars(substr($processed_value, 0, 300)) . "...</pre>";

	echo "<h3>3. After html_entity_decode() (our fix)</h3>";
	$decoded_value = html_entity_decode($processed_value, ENT_QUOTES, 'UTF-8');
	echo "<p><strong>Size:</strong> " . strlen($decoded_value) . " bytes</p>";
	echo "<pre>" . htmlspecialchars(substr($decoded_value, 0, 300)) . "...</pre>";

	echo "<h3>4. Comparison</h3>";
	if ($decoded_value === $test_template) {
		echo "<p style='color: green; font-weight: bold;'>✓✓✓ Perfect match! The encoding/decoding cycle works correctly.</p>";
	} else {
		echo "<p style='color: red; font-weight: bold;'>✗ Mismatch detected!</p>";
		echo "<p>Original length: " . strlen($test_template) . "</p>";
		echo "<p>Final length: " . strlen($decoded_value) . "</p>";
	}

	echo "<h3>5. Test Database Save</h3>";
	// Delete old test record
	$db->query("DELETE FROM `" . DB_PREFIX . "setting` WHERE `key` = 'test_email_template'");

	// Save using the same method as the admin controller
	$save_value = html_entity_decode($processed_value, ENT_QUOTES, 'UTF-8');
	$db->query("INSERT INTO `" . DB_PREFIX . "setting`
		SET store_id = '0',
		`code` = 'test_module',
		`key` = 'test_email_template',
		`value` = '" . $db->escape($save_value) . "',
		serialized = '0'");

	echo "<p style='color: green;'>✓ Saved to database</p>";

	// Read back
	$query = $db->query("SELECT `value` FROM `" . DB_PREFIX . "setting` WHERE `key` = 'test_email_template'");
	if ($query->num_rows) {
		$saved_value = $query->row['value'];
		echo "<p><strong>Retrieved size:</strong> " . strlen($saved_value) . " bytes</p>";

		if ($saved_value === $test_template) {
			echo "<p style='color: green; font-weight: bold;'>✓✓✓ SUCCESS! Template saved and retrieved correctly!</p>";
		} else {
			echo "<p style='color: red; font-weight: bold;'>✗ Data mismatch after save/retrieve</p>";
			echo "<p>Expected: " . strlen($test_template) . " bytes</p>";
			echo "<p>Got: " . strlen($saved_value) . " bytes</p>";
		}

		echo "<h3>First 500 characters of saved value:</h3>";
		echo "<pre>" . htmlspecialchars(substr($saved_value, 0, 500)) . "</pre>";
	}

	// Cleanup
	$db->query("DELETE FROM `" . DB_PREFIX . "setting` WHERE `key` = 'test_email_template'");
	echo "<p><em>Test record cleaned up</em></p>";

} else {
	echo "<h2>Instructions</h2>";
	echo "<p>This script tests the complete save/retrieve cycle using OpenCart's Request class.</p>";
	echo "<p><strong>Template size:</strong> " . strlen($test_template) . " bytes</p>";
	echo "<form method='POST'>";
	echo "<button type='submit' style='padding: 10px 20px; background: #28a745; color: white; border: none; border-radius: 4px; cursor: pointer; font-size: 16px;'>Run Test</button>";
	echo "</form>";

	echo "<h3>What this test does:</h3>";
	echo "<ol>";
	echo "<li>Simulates POST submission with the full template</li>";
	echo "<li>Processes it through OpenCart's Request class (htmlspecialchars)</li>";
	echo "<li>Applies html_entity_decode() (our fix)</li>";
	echo "<li>Saves to database using db->escape()</li>";
	echo "<li>Retrieves from database</li>";
	echo "<li>Verifies the data matches the original</li>";
	echo "</ol>";
}

echo "</body></html>";
?>
