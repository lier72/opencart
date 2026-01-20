<?php
/**
 * Debug POST data to see what's actually being received
 * Access via: http://localhost/~max/oc3.uniqsport.ru/admin/debug_post_data.php
 */

echo "<!DOCTYPE html><html><head><meta charset='UTF-8'><title>POST Data Debug</title></head><body>";
echo "<h1>POST Data Debugging</h1>";

echo "<h2>PHP Configuration</h2>";
echo "<table border='1' cellpadding='5'>";
echo "<tr><td>post_max_size</td><td>" . ini_get('post_max_size') . "</td></tr>";
echo "<tr><td>upload_max_filesize</td><td>" . ini_get('upload_max_filesize') . "</td></tr>";
echo "<tr><td>max_input_vars</td><td>" . ini_get('max_input_vars') . "</td></tr>";
echo "<tr><td>memory_limit</td><td>" . ini_get('memory_limit') . "</td></tr>";
echo "</table>";

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    echo "<h2>Raw POST Data (php://input)</h2>";
    $raw_post = file_get_contents('php://input');
    echo "<p><strong>Total size:</strong> " . strlen($raw_post) . " bytes</p>";
    echo "<pre>" . htmlspecialchars(substr($raw_post, 0, 2000)) . "</pre>";

    echo "<h2>\$_POST Array</h2>";
    echo "<p><strong>Number of variables:</strong> " . count($_POST) . "</p>";

    if (isset($_POST['test_field'])) {
        echo "<p><strong>test_field size:</strong> " . strlen($_POST['test_field']) . " bytes</p>";
        echo "<p><strong>test_field first 500 chars:</strong></p>";
        echo "<pre>" . htmlspecialchars(substr($_POST['test_field'], 0, 500)) . "</pre>";
        echo "<p><strong>test_field last 500 chars:</strong></p>";
        echo "<pre>" . htmlspecialchars(substr($_POST['test_field'], -500)) . "</pre>";

        // Check if it's truncated
        if (strlen($_POST['test_field']) < 1800) {
            echo "<p style='color: red; font-weight: bold;'>⚠ DATA TRUNCATED! Expected ~1800 bytes, got " . strlen($_POST['test_field']) . " bytes</p>";
        } else {
            echo "<p style='color: green; font-weight: bold;'>✓ Full data received!</p>";
        }
    }
} else {
    echo "<p>Submit the form below to test POST data handling:</p>";

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

    echo "<form method='POST' action=''>";
    echo "<p><strong>Template size:</strong> " . strlen($test_template) . " bytes</p>";
    echo "<textarea name='test_field' rows='20' style='width: 100%; font-family: monospace;'>" . htmlspecialchars($test_template) . "</textarea>";
    echo "<p><button type='submit' style='padding: 10px 20px; background: #007bff; color: white; border: none; border-radius: 4px; cursor: pointer;'>Submit Test</button></p>";
    echo "</form>";
}

echo "</body></html>";
?>
