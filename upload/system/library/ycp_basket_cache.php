<?php

/**
 * Filesystem snapshot used by the zero-SQL YCP health-check fast path.
 *
 * A small manifest points to a versioned snapshot directory. Each product
 * group has its own JSON file, so a health check reads only the requested
 * products instead of decoding the full catalog.
 */
final class YcpBasketCache {
	const GENERATION_RETENTION_SECONDS = 259200;

	public static function read(array $payload, $cache_dir = null) {
		if (empty($payload['items']) || !is_array($payload['items'])) {
			return null;
		}

		$manifest = self::readManifest($cache_dir);

		if ($manifest === null) {
			return null;
		}

		$directory = self::getDirectory($cache_dir);
		$offers = [];
		$loaded_groups = [];

		foreach ($payload['items'] as $requested_item) {
			if (!is_array($requested_item) || !array_key_exists('id', $requested_item)) {
				return null;
			}

			$group_id = self::getGroupId((string)$requested_item['id']);

			if ($group_id === null || isset($loaded_groups[$group_id])) {
				continue;
			}

			$loaded_groups[$group_id] = true;

			if (!isset($manifest['groups'][$group_id])) {
				continue;
			}

			$file = $directory . $manifest['generation'] . DIRECTORY_SEPARATOR
				. $manifest['groups'][$group_id];
			$content = is_file($file) ? file_get_contents($file) : false;
			$group_offers = $content === false ? null : json_decode($content, true);

			if (!is_array($group_offers)) {
				return null;
			}
			$offers += $group_offers;
		}

		return self::select($payload, $offers);
	}

	public static function select(array $payload, array $offers) {
		if (empty($payload['items']) || !is_array($payload['items'])) {
			return null;
		}

		$groups = self::buildGroups($offers);
		$items = [];

		foreach ($payload['items'] as $requested_item) {
			if (!is_array($requested_item) || !array_key_exists('id', $requested_item)) {
				return null;
			}

			$id = (string)$requested_item['id'];

			if (isset($offers[$id]) && is_array($offers[$id])) {
				$items[] = self::expandOffer($id, $offers, $groups);
			}
		}

		return ['items' => $items];
	}

	public static function writeOffers(array $offers, $cache_dir = null) {
		$directory = self::getDirectory($cache_dir);

		if (!$directory || (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory))) {
			return false;
		}

		$generation = 'snapshot-' . gmdate('YmdHis') . '-' . getmypid() . '-' . substr(sha1(uniqid('', true)), 0, 8);
		$snapshot_directory = $directory . $generation . DIRECTORY_SEPARATOR;

		if (!mkdir($snapshot_directory, 0775, true)) {
			return false;
		}

		$grouped_offers = [];
		foreach ($offers as $id => $offer) {
			$group_id = isset($offer['_group'])
				? (string)$offer['_group'] : self::getGroupId((string)$id);

			if ($group_id !== null) {
				$grouped_offers[$group_id][(string)$id] = $offer;
			}
		}

		$group_files = [];
		foreach ($grouped_offers as $group_id => $group_offers) {
			$filename = hash('sha256', $group_id) . '.json';
			$json = json_encode($group_offers, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

			if ($json === false || file_put_contents($snapshot_directory . $filename, $json, LOCK_EX) === false) {
				return false;
			}

			chmod($snapshot_directory . $filename, 0644);
			$group_files[$group_id] = $filename;
		}

		$manifest = json_encode([
			'generated_at' => gmdate('c'),
			'generation' => $generation,
			'offer_count' => count($offers),
			'groups' => $group_files
		], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

		if ($manifest === false || !self::atomicWrite($directory . 'catalog.json', $manifest)) {
			return false;
		}

		self::removeOldGenerations($directory, $generation);
		return true;
	}

	public static function acquireRefreshLock($cache_dir = null) {
		$directory = self::getDirectory($cache_dir);

		if (!$directory || (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory))) {
			return false;
		}

		$handle = fopen($directory . 'refresh.lock', 'c');

		if ($handle === false || !flock($handle, LOCK_EX)) {
			if (is_resource($handle)) {
				fclose($handle);
			}
			return false;
		}

		return $handle;
	}

	public static function releaseRefreshLock($handle) {
		if (is_resource($handle)) {
			flock($handle, LOCK_UN);
			fclose($handle);
		}
	}

	private static function readManifest($cache_dir) {
		$directory = self::getDirectory($cache_dir);
		$file = $directory ? $directory . 'catalog.json' : null;

		if (!$file || !is_file($file)) {
			return null;
		}

		$content = file_get_contents($file);
		$data = $content === false ? null : json_decode($content, true);

		if (!is_array($data) || empty($data['generation']) || !isset($data['groups'])
			|| !is_array($data['groups']) || !isset($data['offer_count'], $data['generated_at'])) {
			return null;
		}

		if (basename($data['generation']) !== $data['generation']) {
			return null;
		}

		return $data;
	}

	private static function atomicWrite($file, $content) {
		$temporary = tempnam(dirname($file), 'ycp_');

		if ($temporary === false) {
			return false;
		}

		if (file_put_contents($temporary, $content, LOCK_EX) === false) {
			unlink($temporary);
			return false;
		}

		chmod($temporary, 0644);

		if (!rename($temporary, $file)) {
			unlink($temporary);
			return false;
		}

		return true;
	}

	private static function getGroupId($offer_id) {
		if (!preg_match('/^(\d+)(?:-|$)/', $offer_id, $matches)) {
			return null;
		}

		return $matches[1];
	}

	private static function buildGroups(array $offers) {
		$groups = [];

		foreach ($offers as $id => $offer) {
			if (is_array($offer) && isset($offer['_group'])) {
				$groups[(string)$offer['_group']][] = (string)$id;
			}
		}

		return $groups;
	}

	private static function expandOffer($id, array $offers, array $groups) {
		$item = $offers[$id];
		$group_id = isset($item['_group']) ? (string)$item['_group'] : null;
		unset($item['_group']);
		$item['variations'] = [];

		if ($group_id === null || empty($groups[$group_id])) {
			return $item;
		}

		foreach ($groups[$group_id] as $sibling_id) {
			if ((string)$sibling_id === (string)$id || !isset($offers[$sibling_id])) {
				continue;
			}

			$sibling = $offers[$sibling_id];
			unset($sibling['_group'], $sibling['variations']);
			$item['variations'][] = $sibling;
		}

		return $item;
	}

	private static function removeOldGenerations($directory, $current_generation) {
		$cutoff = time() - self::GENERATION_RETENTION_SECONDS;

		foreach (glob($directory . 'snapshot-*', GLOB_ONLYDIR) ?: [] as $old_directory) {
			if (basename($old_directory) === $current_generation || filemtime($old_directory) >= $cutoff) {
				continue;
			}

			foreach (glob($old_directory . DIRECTORY_SEPARATOR . '*.json') ?: [] as $file) {
				unlink($file);
			}
			rmdir($old_directory);
		}
	}

	private static function getDirectory($cache_dir) {
		if ($cache_dir === null) {
			if (!defined('DIR_CACHE')) {
				return null;
			}

			$cache_dir = DIR_CACHE . 'ycp_basket/';
		}

		return rtrim($cache_dir, '/\\') . DIRECTORY_SEPARATOR;
	}
}
