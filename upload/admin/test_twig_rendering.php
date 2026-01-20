<?php
/**
 * Test Twig Template Rendering
 * Tests whether Twig conditionals work correctly with {{ days_left }} syntax
 */

// Load configuration
require_once(dirname(__FILE__) . '/config.php');

// Startup
require_once(DIR_SYSTEM . 'startup.php');

echo "=== Twig Template Rendering Test ===\n\n";

// Test template with Twig conditionals and {{ }} syntax
$template = '<p>Здравствуйте, {customer_firstname}!</p>

<p><strong>Внимание!</strong> Ваши бонусы скоро сгорят!</p>

{% if days_left > 60 %}
<p>У вас осталось <strong>{{ days_left }} дней</strong> до сгорания бонусов. Это хорошее время, чтобы запланировать покупку!</p>
{% elseif days_left > 14 %}
<p>У вас осталось <strong>{{ days_left }} дней</strong> до сгорания бонусов. Рекомендуем не откладывать использование!</p>
{% elseif days_left > 3 %}
<p style="color: #f59e0b;"><strong>Внимание!</strong> У вас осталось всего <strong>{{ days_left }} дней</strong> до сгорания бонусов!</p>
{% else %}
<p style="color: #dc2626;"><strong>Срочно!</strong> У вас осталось всего <strong>{{ days_left }} {{ days_left == 1 ? "день" : "дня" }}</strong> до сгорания бонусов!</p>
{% endif %}

<h3>Детали:</h3>
<ul>
	<li><strong>Сгорит бонусов:</strong> {expiring_points} ₽</li>
	<li><strong>Дата сгорания:</strong> {expiration_date}</li>
	<li><strong>Текущий баланс:</strong> {current_balance} ₽</li>
</ul>

<p>С уважением,<br>{store_name}</p>';

// Test scenarios
$test_scenarios = array(
	array(
		'name' => 'Test 1: 89 days left (should show >60 message)',
		'data' => array(
			'customer_firstname' => 'Maxim',
			'days_left' => 89,
			'expiring_points' => '950',
			'expiration_date' => '07.04.2026',
			'current_balance' => '4 405',
			'store_name' => 'UniqSport'
		)
	),
	array(
		'name' => 'Test 2: 30 days left (should show >14 message)',
		'data' => array(
			'customer_firstname' => 'Maxim',
			'days_left' => 30,
			'expiring_points' => '1 350',
			'expiration_date' => '07.02.2026',
			'current_balance' => '4 405',
			'store_name' => 'UniqSport'
		)
	),
	array(
		'name' => 'Test 3: 7 days left (should show >3 message)',
		'data' => array(
			'customer_firstname' => 'Maxim',
			'days_left' => 7,
			'expiring_points' => '1 300',
			'expiration_date' => '15.01.2026',
			'current_balance' => '4 405',
			'store_name' => 'UniqSport'
		)
	),
	array(
		'name' => 'Test 4: 1 day left (should show else message)',
		'data' => array(
			'customer_firstname' => 'Maxim',
			'days_left' => 1,
			'expiring_points' => '300',
			'expiration_date' => '09.01.2026',
			'current_balance' => '4 405',
			'store_name' => 'UniqSport'
		)
	)
);

// Helper functions
function replacePlaceholders($template, $data) {
	foreach ($data as $key => $value) {
		$template = str_replace('{' . $key . '}', $value, $template);
	}
	return $template;
}

function renderTwigTemplate($template, $data) {
	// If template contains Twig syntax, render with Twig FIRST
	if (strpos($template, '{%') !== false || strpos($template, '{{') !== false) {
		try {
			// Check if Twig is available
			if (class_exists('\Twig\Loader\ArrayLoader')) {
				$loader = new \Twig\Loader\ArrayLoader([
					'template' => $template
				]);

				$twig = new \Twig\Environment($loader, [
					'autoescape' => false
				]);

				// Render Twig with raw data (keep days_left as integer for logic)
				$template = $twig->render('template', $data);

				echo "  ✓ Twig rendering successful\n";
			} else {
				echo "  ✗ Twig class not available\n";
			}
		} catch (Exception $e) {
			echo "  ✗ Twig rendering failed: " . $e->getMessage() . "\n";
		}
	}

	// Then replace simple placeholders (for formatted values)
	$rendered = replacePlaceholders($template, $data);

	return $rendered;
}

// Run tests
foreach ($test_scenarios as $scenario) {
	echo "--- " . $scenario['name'] . " ---\n";
	echo "  days_left = " . $scenario['data']['days_left'] . " (integer)\n";

	$result = renderTwigTemplate($template, $scenario['data']);

	// Check if Twig conditionals were processed (they should be removed)
	if (strpos($result, '{%') !== false) {
		echo "  ✗ FAILED: Twig conditionals still present in output\n";
		echo "  Output preview: " . substr($result, 0, 200) . "...\n";
	} else {
		echo "  ✓ SUCCESS: Twig conditionals processed\n";

		// Count how many message blocks are in the output (should be only 1)
		$message_count = substr_count($result, '<p>У вас осталось') + substr_count($result, '<p style="color:');
		if ($message_count == 1) {
			echo "  ✓ SUCCESS: Only 1 message block (correct)\n";
		} else {
			echo "  ✗ FAILED: Multiple message blocks ($message_count found)\n";
		}

		// Check if {{ days_left }} was replaced with actual number
		if (strpos($result, '{{ days_left }}') !== false) {
			echo "  ✗ FAILED: {{ days_left }} not replaced\n";
		} else {
			echo "  ✓ SUCCESS: {{ days_left }} replaced with value\n";
		}
	}

	echo "\n";
}

echo "=== Test Complete ===\n";
