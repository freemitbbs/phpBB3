<?php

namespace freemitbbs\cardgamesauth\event;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class listener implements EventSubscriberInterface
{
	private const TESTER_GROUP_NAME = 'CARD_GAME_TESTERS';
	private const ACTIVE_PLAYER_COUNT_CACHE_KEY = '_freemitbbs_cardgamesauth_active_player_count';
	private const ACTIVE_PLAYER_COUNT_CACHE_TTL = 10;
	private const ACTIVE_PLAYER_WINDOW_SECONDS = 300;
	private const ACTIVE_PLAYER_RUNTIME_TIMEOUT_MS = 1500;
	private const ROBOT_USER_ID_BASE = 900000000;

	protected \phpbb\auth\auth $auth;
	protected \phpbb\cache\driver\driver_interface $cache;
	protected \phpbb\config\config $config;
	protected \phpbb\controller\helper $helper;
	protected \phpbb\db\driver\driver_interface $db;
	protected \phpbb\language\language $language;
	protected \phpbb\template\template $template;
	protected \phpbb\user $user;
	protected string $sessions_table;
	protected string $room_members_table;

	public function __construct(
		\phpbb\auth\auth $auth,
		\phpbb\cache\driver\driver_interface $cache,
		\phpbb\config\config $config,
		\phpbb\controller\helper $helper,
		\phpbb\db\driver\driver_interface $db,
		\phpbb\language\language $language,
		\phpbb\template\template $template,
		\phpbb\user $user,
		string $sessions_table,
		string $room_members_table
	)
	{
		$this->auth = $auth;
		$this->cache = $cache;
		$this->config = $config;
		$this->helper = $helper;
		$this->db = $db;
		$this->language = $language;
		$this->template = $template;
		$this->user = $user;
		$this->sessions_table = $sessions_table;
		$this->room_members_table = $room_members_table;
	}

	public static function getSubscribedEvents()
	{
		return [
			'core.user_setup' => 'load_language',
			'core.page_header' => 'assign_header_links',
			'core.permissions' => 'add_permissions',
		];
	}

	public function load_language(): void
	{
		$this->language->add_lang('common', 'freemitbbs/cardgamesauth');
	}

	public function assign_header_links(): void
	{
		$enabled = (bool) ((int) ($this->config['cardgamesauth_enabled'] ?? 1));
		$show_nav = (bool) ((int) ($this->config['cardgamesauth_nav_enabled'] ?? 1));
		$testing_mode = $this->is_testing_mode();
		$is_tester = $testing_mode ? $this->is_tester() : false;
		$user_id = (int) ($this->user->data['user_id'] ?? ANONYMOUS);
		$user_type = (int) ($this->user->data['user_type'] ?? USER_IGNORE);
		$can_play = $enabled
			&& $user_id !== ANONYMOUS
			&& empty($this->user->data['is_bot'])
			&& $user_type !== USER_IGNORE
			&& $user_type !== USER_INACTIVE
			&& $this->auth->acl_get('u_cardgames_play')
			&& (!$testing_mode || $is_tester);
		$show_cardgames_nav = $enabled && $show_nav && (!$testing_mode || $is_tester);
		$active_player_count = $show_cardgames_nav ? $this->active_player_count() : 0;
		$nav_label = $this->language->lang('CARDGAMES_NAV');
		if ($active_player_count > 0)
		{
			$nav_label = $this->language->lang('CARDGAMES_NAV_COUNTED', $nav_label, $active_player_count);
		}

		$this->template->assign_vars([
			'S_CARDGAMES_NAV' => $show_cardgames_nav,
			'S_CARDGAMES_CAN_PLAY' => $can_play,
			'U_CARDGAMES_LAUNCH' => $this->route($can_play ? 'freemitbbs_cardgamesauth_client' : 'freemitbbs_cardgamesauth_launch'),
			'CARDGAMES_NAV_LABEL' => $nav_label,
			'CARDGAMES_NAV_COUNT' => $active_player_count,
			'S_CARDGAMES_NAV_COUNT' => $active_player_count > 0,
		]);
	}

	protected function active_player_count(): int
	{
		$cached = $this->cache->get(self::ACTIVE_PLAYER_COUNT_CACHE_KEY);
		if (is_array($cached) && array_key_exists('count', $cached))
		{
			return max(0, (int) $cached['count']);
		}

		try
		{
			$count = $this->runtime_active_player_count();
		}
		catch (\Throwable $e)
		{
			$count = null;
		}

		if ($count === null)
		{
			$count = $this->db_active_player_count();
		}

		$count = max(0, (int) $count);
		$this->cache->put(self::ACTIVE_PLAYER_COUNT_CACHE_KEY, ['count' => $count], self::ACTIVE_PLAYER_COUNT_CACHE_TTL);

		return $count;
	}

	protected function runtime_active_player_count(): ?int
	{
		if (!$this->runtime_configured())
		{
			return null;
		}

		$status_response = $this->runtime_request('GET', '/card-games/runtime/status');
		$status = $status_response['body']['status'] ?? $status_response['body'];
		if (!is_array($status))
		{
			return null;
		}

		$connections = $status['connections'] ?? null;
		if (is_array($connections) && is_array($connections['users'] ?? null))
		{
			return $this->count_user_entries($connections['users']);
		}

		$total = $this->first_int_value($status, [
			'activePlayerCount',
			'active_player_count',
			'playerCount',
			'player_count',
			'userCount',
			'user_count',
		]);
		if ($total !== null)
		{
			return $total;
		}

		$count = 0;
		$has_count_source = false;
		foreach (['lobbyUsers', 'users'] as $key)
		{
			if (is_array($status[$key] ?? null))
			{
				$count += $this->count_user_entries($status[$key]);
				$has_count_source = true;
				break;
			}
		}
		if (is_array($status['lobby']['users'] ?? null))
		{
			$count += $this->count_user_entries($status['lobby']['users']);
			$has_count_source = true;
		}
		if (is_array($status['presence']['users'] ?? null))
		{
			$count += $this->count_user_entries($status['presence']['users']);
			$has_count_source = true;
		}

		$rooms = $status['rooms'] ?? [];
		if (is_array($rooms))
		{
			foreach ($rooms as $room)
			{
				if (!is_array($room))
				{
					continue;
				}

				$room_count = 0;
				$room_robot_count = 0;
				$has_room_detail = false;
				foreach (['seats', 'observers', 'members', 'users'] as $key)
				{
					if (is_array($room[$key] ?? null))
					{
						$room_count += $this->count_user_entries($room[$key]);
						$room_robot_count += $this->count_robot_entries($room[$key]);
						$has_room_detail = true;
					}
				}

				$human_member_count = $this->first_int_value($room, ['humanMemberCount', 'human_member_count']);
				if ($human_member_count !== null)
				{
					$count += $human_member_count;
					$has_count_source = true;
					continue;
				}

				$member_count = $this->first_int_value($room, ['memberCount', 'member_count']);
				if ($member_count !== null)
				{
					$count += max($room_count, $member_count - $room_robot_count);
					$has_count_source = true;
					continue;
				}

				if ($has_room_detail)
				{
					$count += $room_count;
					$has_count_source = true;
				}
			}
		}

		return $has_count_source ? $count : null;
	}

	protected function count_user_entries(array $entries): int
	{
		$user_ids = [];
		$anonymous_count = 0;
		foreach ($entries as $entry)
		{
			if (!is_array($entry))
			{
				continue;
			}
			if (array_key_exists('connected', $entry) && !$entry['connected'])
			{
				continue;
			}

			$user = is_array($entry['user'] ?? null) ? $entry['user'] : $entry;
			if (array_key_exists('connected', $user) && !$user['connected'])
			{
				continue;
			}
			if ($this->is_robot_user($user) || $this->is_robot_user($entry))
			{
				continue;
			}

			$user_id = $this->first_int_value($user, ['userId', 'user_id', 'id']);
			if ($user_id !== null && $user_id > ANONYMOUS && $user_id < self::ROBOT_USER_ID_BASE)
			{
				$user_ids[$user_id] = true;
				continue;
			}

			if (($user['displayName'] ?? $user['username'] ?? $entry['displayName'] ?? $entry['username'] ?? '') !== '')
			{
				$anonymous_count++;
			}
		}

		return count($user_ids) + $anonymous_count;
	}

	protected function count_robot_entries(array $entries): int
	{
		$robot_ids = [];
		$anonymous_count = 0;
		foreach ($entries as $entry)
		{
			if (!is_array($entry))
			{
				continue;
			}

			$user = is_array($entry['user'] ?? null) ? $entry['user'] : $entry;
			if (!$this->is_robot_user($user) && !$this->is_robot_user($entry))
			{
				continue;
			}

			$user_id = $this->first_int_value($user, ['userId', 'user_id', 'id']);
			if ($user_id !== null)
			{
				$robot_ids[$user_id] = true;
				continue;
			}

			$anonymous_count++;
		}

		return count($robot_ids) + $anonymous_count;
	}

	protected function is_robot_user(array $user): bool
	{
		foreach (['bot', 'isBot', 'is_bot', 'robot', 'isRobot', 'is_robot'] as $key)
		{
			if (!empty($user[$key]))
			{
				return true;
			}
		}

		$user_id = $this->first_int_value($user, ['userId', 'user_id', 'id']);
		if ($user_id !== null && $user_id >= self::ROBOT_USER_ID_BASE)
		{
			return true;
		}

		$username = (string) ($user['username'] ?? $user['usernameClean'] ?? $user['username_clean'] ?? '');

		return str_starts_with($username, 'robot_');
	}

	protected function first_int_value(array $data, array $keys): ?int
	{
		foreach ($keys as $key)
		{
			if (array_key_exists($key, $data) && is_numeric($data[$key]))
			{
				return max(0, (int) $data[$key]);
			}
		}

		return null;
	}

	protected function db_active_player_count(): int
	{
		$sql = 'SELECT COUNT(DISTINCT m.user_id) AS active_count
			FROM ' . $this->room_members_table . ' m
			INNER JOIN ' . $this->sessions_table . ' s
				ON s.id = m.session_id
			WHERE m.user_id > ' . ANONYMOUS . '
				AND m.user_id < ' . self::ROBOT_USER_ID_BASE . '
				AND m.connected = 1
				AND m.left_at = 0
				AND m.last_seen_at >= ' . (time() - self::ACTIVE_PLAYER_WINDOW_SECONDS) . '
				AND s.finished_at = 0
				AND ' . $this->db->sql_in_set('s.status', ['waiting', 'starting', 'playing']);
		$result = $this->db->sql_query($sql);
		$count = (int) $this->db->sql_fetchfield('active_count');
		$this->db->sql_freeresult($result);

		return max(0, $count);
	}

	protected function runtime_request(string $method, string $path): array
	{
		$method = strtoupper($method);
		$url = $this->runtime_base_url() . $path;
		$body = '';
		$response = $this->http_request($method, $url, $body, $this->runtime_headers($method, $url, $body));
		if ($response['status'] < 200 || $response['status'] >= 300)
		{
			throw new \RuntimeException('runtime_status_' . $response['status']);
		}

		$data = json_decode($response['body'], true);
		if (!is_array($data))
		{
			throw new \RuntimeException('runtime_invalid_json');
		}

		return [
			'status' => $response['status'],
			'body' => $data,
		];
	}

	protected function http_request(string $method, string $url, string $body, array $headers): array
	{
		$timeout_ms = $this->runtime_timeout_ms();
		$timeout_seconds = (int) ceil($timeout_ms / 1000);
		if (function_exists('curl_init'))
		{
			$curl = curl_init($url);
			curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $method);
			curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
			curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
			if (defined('CURLOPT_TIMEOUT_MS'))
			{
				curl_setopt($curl, CURLOPT_TIMEOUT_MS, $timeout_ms);
			}
			else
			{
				curl_setopt($curl, CURLOPT_TIMEOUT, $timeout_seconds);
			}

			$response_body = curl_exec($curl);
			$status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
			$error = curl_error($curl);
			curl_close($curl);
			if ($response_body === false)
			{
				throw new \RuntimeException('runtime_unreachable_' . $error);
			}

			return [
				'status' => $status,
				'body' => (string) $response_body,
			];
		}

		$options = [
			'http' => [
				'method' => $method,
				'header' => implode("\r\n", $headers),
				'timeout' => $timeout_seconds,
				'ignore_errors' => true,
			],
		];

		$response_body = @file_get_contents($url, false, stream_context_create($options));
		if ($response_body === false)
		{
			throw new \RuntimeException('runtime_unreachable');
		}

		$status = 0;
		foreach (($http_response_header ?? []) as $header)
		{
			if (preg_match('#^HTTP/\S+\s+(\d+)#', $header, $match))
			{
				$status = (int) $match[1];
				break;
			}
		}

		return [
			'status' => $status,
			'body' => (string) $response_body,
		];
	}

	protected function runtime_headers(string $method, string $url, string $body): array
	{
		$timestamp = (string) time();
		$nonce = bin2hex(random_bytes(16));
		$body_hash = hash('sha256', $body);
		$path = (string) (parse_url($url, PHP_URL_PATH) ?: '/');
		$query = (string) (parse_url($url, PHP_URL_QUERY) ?: '');
		$signature_path = $path . ($query !== '' ? '?' . $query : '');
		$signature = hash_hmac('sha256', implode("\n", [
			$method,
			$signature_path,
			$timestamp,
			$nonce,
			$body_hash,
		]), $this->runtime_service_secret());

		return [
			'accept: application/json',
			'content-type: application/json',
			'x-cardgames-service: ' . $this->runtime_service_id(),
			'x-cardgames-timestamp: ' . $timestamp,
			'x-cardgames-nonce: ' . $nonce,
			'x-cardgames-content-sha256: ' . $body_hash,
			'x-cardgames-signature: sha256=' . $signature,
		];
	}

	protected function runtime_configured(): bool
	{
		return $this->runtime_enabled()
			&& $this->runtime_base_url() !== ''
			&& $this->runtime_service_secret() !== '';
	}

	protected function runtime_enabled(): bool
	{
		return (int) ($this->config['cardgames_node_runtime_enabled'] ?? 0) === 1;
	}

	protected function runtime_base_url(): string
	{
		return rtrim(trim((string) ($this->config['cardgames_node_runtime_base_url'] ?? '')), '/');
	}

	protected function runtime_service_id(): string
	{
		$service_id = trim((string) ($this->config['cardgames_node_runtime_service_id'] ?? 'phpbb-cardgamesauth'));

		return $service_id !== '' ? $service_id : 'phpbb-cardgamesauth';
	}

	protected function runtime_service_secret(): string
	{
		return trim((string) ($this->config['cardgames_node_runtime_service_secret'] ?? ''));
	}

	protected function runtime_timeout_ms(): int
	{
		$timeout_ms = (int) ($this->config['cardgames_node_runtime_timeout_ms'] ?? self::ACTIVE_PLAYER_RUNTIME_TIMEOUT_MS);

		return max(500, min(self::ACTIVE_PLAYER_RUNTIME_TIMEOUT_MS, $timeout_ms));
	}

	protected function route(string $route, array $params = [], bool $is_amp = true): string
	{
		return $this->force_app_front_controller($this->helper->route($route, $params, $is_amp));
	}

	protected function force_app_front_controller(string $url): string
	{
		if ($url === '' || preg_match('~(^|://[^/]+)(?:/[^?#]*)?/app\.php/card-games(?:[/?#]|$)~i', $url))
		{
			return $url;
		}

		if (str_starts_with($url, './card-games'))
		{
			return './app.php' . substr($url, 1);
		}
		if (str_starts_with($url, 'card-games'))
		{
			return 'app.php/' . $url;
		}

		$forced = preg_replace('~^((?:https?://[^/]+)?(?:/[^?#]*)?)/card-games(?=[/?#]|$)~i', '$1/app.php/card-games', $url, 1);

		return is_string($forced) ? $forced : $url;
	}

	protected function is_testing_mode(): bool
	{
		return (bool) ((int) ($this->config['cardgamesauth_testing_mode'] ?? 1));
	}

	protected function is_tester(): bool
	{
		$user_id = (int) ($this->user->data['user_id'] ?? ANONYMOUS);
		if ($user_id === ANONYMOUS)
		{
			return false;
		}

		$group_id = $this->tester_group_id();
		if ($group_id <= 0)
		{
			return false;
		}

		$sql = 'SELECT 1 AS is_tester
			FROM ' . USER_GROUP_TABLE . '
			WHERE group_id = ' . $group_id . '
				AND user_id = ' . $user_id . '
				AND user_pending = 0';
		$result = $this->db->sql_query_limit($sql, 1);
		$is_tester = (bool) $this->db->sql_fetchfield('is_tester');
		$this->db->sql_freeresult($result);

		return $is_tester;
	}

	protected function tester_group_id(): int
	{
		$group_id = (int) ($this->config['cardgamesauth_tester_group_id'] ?? 0);
		if ($group_id > 0)
		{
			return $group_id;
		}

		$sql = 'SELECT group_id
			FROM ' . GROUPS_TABLE . "
			WHERE group_name = '" . $this->db->sql_escape(self::TESTER_GROUP_NAME) . "'";
		$result = $this->db->sql_query_limit($sql, 1);
		$group_id = (int) $this->db->sql_fetchfield('group_id');
		$this->db->sql_freeresult($result);
		if ($group_id > 0)
		{
			$this->config->set('cardgamesauth_tester_group_id', (string) $group_id);
		}

		return $group_id;
	}

	public function add_permissions($event): void
	{
		$permissions = $event['permissions'];
		$permissions['u_cardgames_play'] = ['lang' => 'ACL_U_CARDGAMES_PLAY', 'cat' => 'misc'];
		$permissions['m_cardgames_manage'] = ['lang' => 'ACL_M_CARDGAMES_MANAGE', 'cat' => 'misc'];
		$permissions['m_cardgames_replay_export'] = ['lang' => 'ACL_M_CARDGAMES_REPLAY_EXPORT', 'cat' => 'misc'];
		$event['permissions'] = $permissions;
	}
}
