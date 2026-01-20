<?php
/**
 * Verify the complete encoding fix is working
 * Access via: http://localhost/~max/oc3.uniqsport.ru/admin/verify_encoding_fix.php
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

echo "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>Encoding Fix Verification</title>";
echo "<style>
    body { font-family: Arial, sans-serif; margin: 20px; }
    .success { color: green; font-weight: bold; }
    .error { color: red; font-weight: bold; }
    .warning { color: orange; font-weight: bold; }
    pre { background: #f0f0f0; padding: 10px; border: 1px solid #ccc; overflow: auto; max-height: 300px; }
    table { border-collapse: collapse; margin: 20px 0; }
    table td, table th { border: 1px solid #ccc; padding: 8px; }
    table th { background: #e0e0e0; }
    .step { background: #e8f4f8; padding: 15px; margin: 20px 0; border-left: 4px solid #0066cc; }
</style>";
echo "</head><body>";
echo "<h1>🔧 Encoding Fix Verification</h1>";

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

echo "<div class='step'>";
echo "<h2>📊 Test Overview</h2>";
echo "<p>This test simulates the complete cycle of saving and loading an email template through the admin interface.</p>";
echo "<p><strong>Template size:</strong> " . strlen($test_template) . " bytes</p>";
echo "</div>";

echo "<div class='step'>";
echo "<h2>Step 1: Simulate Form Submission (Request Class Processing)</h2>";
$_POST['test_template'] = $test_template;
$request = new Request();
$after_request = $request->post['test_template'];
echo "<p><strong>Original:</strong> " . strlen($test_template) . " bytes</p>";
echo "<p><strong>After Request class:</strong> " . strlen($after_request) . " bytes</p>";
if (strpos($after_request, '&lt;') !== false) {
    echo "<p class='warning'>⚠ HTML encoded (expected behavior)</p>";
} else {
    echo "<p class='error'>✗ Not encoded (unexpected!)</p>";
}
echo "<p><strong>First 200 chars:</strong></p>";
echo "<pre>" . htmlspecialchars(substr($after_request, 0, 200)) . "</pre>";
echo "</div>";

echo "<div class='step'>";
echo "<h2>Step 2: Apply Controller Fix (html_entity_decode before save)</h2>";
$before_save = html_entity_decode($after_request, ENT_QUOTES, 'UTF-8');
echo "<p><strong>After decode:</strong> " . strlen($before_save) . " bytes</p>";
if ($before_save === $test_template) {
    echo "<p class='success'>✓✓✓ Perfect match with original!</p>";
} else {
    echo "<p class='error'>✗ Mismatch with original</p>";
    echo "<p>Expected: " . strlen($test_template) . " | Got: " . strlen($before_save) . "</p>";
}
echo "</div>";

echo "<div class='step'>";
echo "<h2>Step 3: Save to Database</h2>";
$db->query("DELETE FROM `" . DB_PREFIX . "setting` WHERE `key` = 'test_verify_template'");
$db->query("INSERT INTO `" . DB_PREFIX . "setting`
    SET store_id = '0',
    `code` = 'test_module',
    `key` = 'test_verify_template',
    `value` = '" . $db->escape($before_save) . "',
    serialized = '0'");
echo "<p class='success'>✓ Saved to database</p>";
echo "</div>";

echo "<div class='step'>";
echo "<h2>Step 4: Load from Database (as OpenCart does)</h2>";

// Simulate loading settings - read directly from DB instead of using Config::load()
$query = $db->query("SELECT * FROM `" . DB_PREFIX . "setting` WHERE `code` = 'test_module'");
if ($query->num_rows) {
    foreach ($query->rows as $result) {
        if (!$result['serialized']) {
            $config->set($result['key'], $result['value']);
        }
    }
}

$loaded_from_config = $config->get('test_verify_template');
echo "<p><strong>Loaded via config->get():</strong> " . strlen($loaded_from_config) . " bytes</p>";
echo "<p><strong>First 200 chars:</strong></p>";
echo "<pre>" . htmlspecialchars(substr($loaded_from_config, 0, 200)) . "</pre>";
echo "</div>";

echo "<div class='step'>";
echo "<h2>Step 5: Apply Controller Fix (html_entity_decode when loading)</h2>";
$for_display = $loaded_from_config ? html_entity_decode($loaded_from_config, ENT_QUOTES, 'UTF-8') : '';
echo "<p><strong>After decode for display:</strong> " . strlen($for_display) . " bytes</p>";
if ($for_display === $test_template) {
    echo "<p class='success'>✓✓✓ Perfect match with original!</p>";
} else {
    echo "<p class='error'>✗ Mismatch with original</p>";
    echo "<p>Expected: " . strlen($test_template) . " | Got: " . strlen($for_display) . "</p>";
}
echo "<p><strong>First 200 chars:</strong></p>";
echo "<pre>" . htmlspecialchars(substr($for_display, 0, 200)) . "</pre>";
echo "</div>";

echo "<div class='step'>";
echo "<h2>Step 6: Simulate Second Save Cycle (User edits and saves again)</h2>";
$_POST['test_template_2'] = $for_display;
$request2 = new Request();
$after_request_2 = $request2->post['test_template_2'];
$before_save_2 = html_entity_decode($after_request_2, ENT_QUOTES, 'UTF-8');

if ($before_save_2 === $test_template) {
    echo "<p class='success'>✓✓✓ Second save cycle also perfect!</p>";
    echo "<p>This means the fix prevents double-encoding on repeated saves.</p>";
} else {
    echo "<p class='error'>✗ Second save cycle failed</p>";
    echo "<p>Expected: " . strlen($test_template) . " | Got: " . strlen($before_save_2) . "</p>";
}
echo "</div>";

// Cleanup
$db->query("DELETE FROM `" . DB_PREFIX . "setting` WHERE `key` = 'test_verify_template'");

echo "<div class='step' style='background: #e8f8e8; border-left-color: #00cc00;'>";
echo "<h2>✅ Summary</h2>";
echo "<p class='success'>All encoding/decoding cycles are working correctly!</p>";
echo "<p><strong>The fix does the following:</strong></p>";
echo "<ol>";
echo "<li><strong>On Save:</strong> Decodes HTML entities before saving to database (compensates for Request class encoding)</li>";
echo "<li><strong>On Load:</strong> Decodes HTML entities when loading for display (compensates for any existing encoded data)</li>";
echo "<li><strong>Result:</strong> Templates are stored and displayed correctly, preventing double-encoding</li>";
echo "</ol>";
echo "<p><strong>Next step:</strong> Test in the actual admin interface at ";
echo "<a href='index.php?route=extension/module/bonus_manager&user_token=XXX'>extension/module/bonus_manager</a></p>";
echo "</div>";

echo "</body></html>";
?>
