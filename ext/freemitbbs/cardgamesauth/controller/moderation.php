<?php

namespace freemitbbs\cardgamesauth\controller;

use Symfony\Component\HttpFoundation\Response;

class moderation
{
	private const FORM_KEY = 'freemitbbs/cardgamesauth_moderation';
	private const JSON_FLAGS = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT;
	private const CONTROL_ACTIONS = [
		'disconnect_user' => [
			'lang' => 'CARDGAMES_CONTROL_DISCONNECT_USER',
			'user' => true,
			'room' => false,
			'session' => false,
		],
		'reset_room' => [
			'lang' => 'CARDGAMES_CONTROL_RESET_ROOM',
			'user' => false,
			'room' => true,
			'session' => false,
		],
		'cancel_game' => [
			'lang' => 'CARDGAMES_CONTROL_CANCEL_GAME',
			'user' => false,
			'room' => true,
			'session' => false,
		],
		'reload_user_status' => [
			'lang' => 'CARDGAMES_CONTROL_RELOAD_USER_STATUS',
			'user' => true,
			'room' => false,
			'session' => false,
		],
	];

	protected \phpbb\auth\auth $auth;
	protected \phpbb\config\config $config;
	protected \phpbb\controller\helper $helper;
	protected \phpbb\db\driver\driver_interface $db;
	protected \phpbb\language\language $language;
	protected \phpbb\request\request_interface $request;
	protected \phpbb\template\template $template;
	protected \phpbb\user $user;
	protected string $room_configs_table;
	protected string $sessions_table;
	protected string $moderation_audit_table;

	public function __construct(
		\phpbb\auth\auth $auth,
		\phpbb\config\config $config,
		\phpbb\controller\helper $helper,
		\phpbb\db\driver\driver_interface $db,
		\phpbb\language\language $language,
		\phpbb\request\request_interface $request,
		\phpbb\template\template $template,
		\phpbb\user $user,
		string $room_configs_table,
		string $sessions_table,
		string $moderation_audit_table
	)
	{
		$this->auth = $auth;
		$this->config = $config;
		$this->helper = $helper;
		$this->db = $db;
		$this->language = $language;
		$this->request = $request;
		$this->template = $template;
		$this->user = $user;
		$this->room_configs_table = $room_configs_table;
		$this->sessions_table = $sessions_table;
		$this->moderation_audit_table = $moderation_audit_table;
	}

	public function admin(): Response
	{
		$this->boot_language();

		if ($this->is_guest())
		{
			login_box($this->helper->route('freemitbbs_cardgamesauth_admin'), $this->language->lang('CARDGAMES_LOGIN_REQUIRED'));
		}
		if (!$this->can_manage())
		{
			trigger_error('NOT_AUTHORISED');
		}

		add_form_key(self::FORM_KEY);
		if ($this->request->is_set_post('submit'))
		{
			$this->handle_submit();
		}

		$this->assign_template_vars();

		return $this->helper->render('@freemitbbs_cardgamesauth/cardgames_admin.html', $this->language->lang('CARDGAMES_ADMIN_TITLE'));
	}

	protected function handle_submit(): void
	{
		if (!check_form_key(self::FORM_KEY))
		{
			trigger_error($this->language->lang('FORM_INVALID') . $this->return_link(), E_USER_WARNING);
			return;
		}

		$input = $this->control_input();
		try
		{
			$result = $this->execute_control($input);
		}
		catch (\RuntimeException $e)
		{
			trigger_error($this->language->lang('CARDGAMES_CONTROL_FAILED', $e->getMessage()) . $this->return_link(), E_USER_WARNING);
			return;
		}

		trigger_error($this->language->lang('CARDGAMES_CONTROL_DONE', $this->language->lang(self::CONTROL_ACTIONS[$result['action']]['lang'])) . $this->return_link());
	}

	protected function control_input(): array
	{
		$target_user_id = max(0, (int) $this->request->variable('target_user_id', 0));
		$target_username = trim((string) $this->request->variable('target_username', '', true));
		if ($target_user_id <= ANONYMOUS && $target_username !== '')
		{
			$target_user_id = $this->user_id_from_username($target_username);
		}

		return [
			'action' => (string) $this->request->variable('action', ''),
			'room_key' => trim((string) $this->request->variable('room_key', '', true)),
			'session_id' => max(0, (int) $this->request->variable('session_id', 0)),
			'target_user_id' => $target_user_id,
			'target_username' => $target_username,
			'reason' => trim((string) $this->request->variable('reason', '', true)),
		];
	}

	protected function execute_control(array $input): array
	{
		$action = (string) ($input['action'] ?? '');
		if (!isset(self::CONTROL_ACTIONS[$action]))
		{
			throw new \RuntimeException($this->language->lang('CARDGAMES_CONTROL_ERR_INVALID_ACTION'));
		}

		$definition = self::CONTROL_ACTIONS[$action];
		$room_key = substr((string) ($input['room_key'] ?? ''), 0, 64);
		$session_id = max(0, (int) ($input['session_id'] ?? 0));
		$target_user_id = max(0, (int) ($input['target_user_id'] ?? 0));

		if (!empty($definition['user']) && $target_user_id <= ANONYMOUS)
		{
			throw new \RuntimeException($this->language->lang('CARDGAMES_CONTROL_ERR_TARGET_USER'));
		}
		if (!empty($definition['room']) && $room_key === '')
		{
			throw new \RuntimeException($this->language->lang('CARDGAMES_CONTROL_ERR_ROOM'));
		}
		if (!empty($definition['session']) && $session_id <= 0)
		{
			throw new \RuntimeException($this->language->lang('CARDGAMES_CONTROL_ERR_SESSION'));
		}
		if ($action === 'cancel_game')
		{
			$session_id = $this->active_session_id_for_room($room_key);
			if ($session_id <= 0)
			{
				throw new \RuntimeException($this->language->lang('CARDGAMES_CONTROL_ERR_ACTIVE_SESSION'));
			}
		}

		$moderator_user_id = (int) ($this->user->data['user_id'] ?? 0);
		$requested_at = gmdate('c');
		$request_id = 'phpbb-' . time() . '-' . $this->nonce();
		$reason = trim((string) ($input['reason'] ?? ''));
		$payload = [
			'action' => $action,
			'requestId' => $request_id,
			'request_id' => $request_id,
			'roomKey' => $room_key,
			'room_key' => $room_key,
			'sessionId' => $session_id,
			'session_id' => $session_id,
			'targetUserId' => $target_user_id,
			'target_user_id' => $target_user_id,
			'actorUserId' => $moderator_user_id,
			'actor_user_id' => $moderator_user_id,
			'moderatorUserId' => $moderator_user_id,
			'moderator_user_id' => $moderator_user_id,
			'requestedAt' => $requested_at,
			'requested_at' => $requested_at,
		];
		if ($reason !== '')
		{
			$payload['reason'] = $reason;
		}

		try
		{
			$runtime_result = $this->call_runtime_hook($action, $payload, $room_key, $session_id, $target_user_id);
		}
		catch (\RuntimeException $e)
		{
			$this->record_audit($action, $room_key, $session_id, $target_user_id, $reason, [
				'input' => $payload,
				'success' => false,
				'error' => $e->getMessage(),
			]);
			throw $e;
		}

		$persistence_result = [];
		$this->record_audit($action, $room_key, $session_id, $target_user_id, $reason, [
			'input' => $payload,
			'success' => true,
			'runtime' => $runtime_result,
			'persistence' => $persistence_result,
		]);

		return [
			'action' => $action,
			'runtime' => $runtime_result,
			'persistence' => $persistence_result,
		];
	}

	protected function call_runtime_hook(string $action, array $payload, string $room_key, int $session_id, int $target_user_id): array
	{
		$base_url = $this->runtime_base_url();
		$secret = $this->runtime_service_secret();
		if (!$this->runtime_enabled() || $base_url === '' || $secret === '')
		{
			throw new \RuntimeException($this->language->lang('CARDGAMES_CONTROL_ERR_RUNTIME_NOT_CONFIGURED'));
		}

		$url = $base_url . $this->runtime_path($action, $room_key, $session_id, $target_user_id);
		$body = $this->encode_json($payload);
		$timestamp = (string) time();
		$nonce = $this->nonce();
		$body_hash = hash('sha256', $body);
		$path = (string) (parse_url($url, PHP_URL_PATH) ?: '/');
		$query = (string) (parse_url($url, PHP_URL_QUERY) ?: '');
		$signature_path = $path . ($query !== '' ? '?' . $query : '');
		$signature = hash_hmac('sha256', implode("\n", [
			'POST',
			$signature_path,
			$timestamp,
			$nonce,
			$body_hash,
		]), $secret);

		$headers = [
			'accept: application/json',
			'content-type: application/json',
			'x-cardgames-service: ' . $this->runtime_service_id(),
			'x-cardgames-timestamp: ' . $timestamp,
			'x-cardgames-nonce: ' . $nonce,
			'x-cardgames-content-sha256: ' . $body_hash,
			'x-cardgames-signature: sha256=' . $signature,
		];

		$response = $this->post_json($url, $body, $headers);
		if ($response['status'] < 200 || $response['status'] >= 300)
		{
			throw new \RuntimeException($this->language->lang('CARDGAMES_CONTROL_ERR_RUNTIME_STATUS', $response['status'], $response['body']));
		}

		$data = json_decode($response['body'], true);
		return [
			'status' => $response['status'],
			'body' => is_array($data) ? $data : new \stdClass(),
		];
	}

	protected function post_json(string $url, string $body, array $headers): array
	{
		$timeout_ms = $this->runtime_timeout_ms();
		$timeout_seconds = (int) ceil($timeout_ms / 1000);
		if (function_exists('curl_init'))
		{
			$curl = curl_init($url);
			curl_setopt($curl, CURLOPT_POST, true);
			curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
			curl_setopt($curl, CURLOPT_POSTFIELDS, $body);
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
				throw new \RuntimeException($this->language->lang('CARDGAMES_CONTROL_ERR_RUNTIME_UNREACHABLE', $error));
			}

			return [
				'status' => $status,
				'body' => (string) $response_body,
			];
		}

		$context = stream_context_create([
			'http' => [
				'method' => 'POST',
				'header' => implode("\r\n", $headers),
				'content' => $body,
				'timeout' => $timeout_seconds,
				'ignore_errors' => true,
			],
		]);
		$response_body = file_get_contents($url, false, $context);
		if ($response_body === false)
		{
			throw new \RuntimeException($this->language->lang('CARDGAMES_CONTROL_ERR_RUNTIME_UNREACHABLE', ''));
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

	protected function record_audit(string $action, string $room_key, int $session_id, int $target_user_id, string $reason, array $payload): void
	{
		$this->db->sql_query('INSERT INTO ' . $this->moderation_audit_table . ' ' . $this->db->sql_build_array('INSERT', [
			'room_key' => $room_key,
			'session_id' => $session_id,
			'moderator_user_id' => (int) ($this->user->data['user_id'] ?? 0),
			'target_user_id' => $target_user_id,
			'action' => $action,
			'reason' => substr($reason, 0, 255),
			'payload_json' => $this->encode_json($payload),
			'created_at' => time(),
		]));
	}

	protected function assign_template_vars(): void
	{
		$this->template->assign_vars([
			'U_ACTION' => $this->helper->route('freemitbbs_cardgamesauth_admin'),
			'U_REPLAY_EXPORT' => $this->helper->route('freemitbbs_cardgamesauth_replay_export'),
			'S_CARDGAMES_RUNTIME_CONFIGURED' => $this->runtime_configured(),
			'S_CARDGAMES_CAN_EXPORT_REPLAY' => $this->can_export_replay(),
		]);

		foreach (self::CONTROL_ACTIONS as $action => $definition)
		{
			$this->template->assign_block_vars('actions', [
				'VALUE' => $action,
				'LABEL' => $this->language->lang($definition['lang']),
			]);
		}

		foreach ($this->active_sessions() as $row)
		{
			$this->template->assign_block_vars('active_rooms', [
				'SESSION_ID' => (int) $row['session_id'],
				'ROOM_KEY' => (string) $row['room_key'],
				'ROOM_NAME' => (string) ($row['display_name'] ?: $row['room_key']),
				'GAME_TYPE' => (string) $row['game_type'],
				'STATUS' => (string) $row['status'],
				'OWNER' => $this->display_user($row),
				'STATE_VERSION' => (int) $row['state_version'],
				'UPDATED_AT' => $this->format_time((int) $row['updated_at']),
			]);
		}
	}

	protected function active_sessions(): array
	{
		$sql = 'SELECT s.id AS session_id, s.room_key, s.game_type, s.status, s.owner_user_id,
				s.state_version, s.updated_at, rc.display_name, u.username, u.user_colour
			FROM ' . $this->sessions_table . ' s
			LEFT JOIN ' . $this->room_configs_table . ' rc
				ON rc.room_key = s.room_key
			LEFT JOIN ' . USERS_TABLE . ' u
				ON u.user_id = s.owner_user_id
			WHERE s.finished_at = 0
				AND ' . $this->db->sql_in_set('s.status', ['starting', 'playing']) . '
			ORDER BY s.updated_at DESC, s.id DESC';
		$result = $this->db->sql_query_limit($sql, 50);
		$rows = [];
		while ($row = $this->db->sql_fetchrow($result))
		{
			$rows[] = $row;
		}
		$this->db->sql_freeresult($result);

		return $rows;
	}

	protected function user_id_from_username(string $username): int
	{
		$sql = 'SELECT user_id
			FROM ' . USERS_TABLE . "
			WHERE username_clean = '" . $this->db->sql_escape(utf8_clean_string($username)) . "'";
		$result = $this->db->sql_query_limit($sql, 1);
		$user_id = (int) $this->db->sql_fetchfield('user_id');
		$this->db->sql_freeresult($result);

		return $user_id;
	}

	protected function active_session_id_for_room(string $room_key): int
	{
		if ($room_key === '')
		{
			return 0;
		}

		$sql = 'SELECT id
			FROM ' . $this->sessions_table . "
			WHERE room_key = '" . $this->db->sql_escape($room_key) . "'
				AND finished_at = 0
				AND " . $this->db->sql_in_set('status', ['starting', 'playing']) . '
			ORDER BY updated_at DESC, id DESC';
		$result = $this->db->sql_query_limit($sql, 1);
		$session_id = (int) $this->db->sql_fetchfield('id');
		$this->db->sql_freeresult($result);

		return $session_id;
	}

	protected function display_user(array $row): string
	{
		$user_id = (int) ($row['owner_user_id'] ?? 0);
		if ($user_id <= ANONYMOUS)
		{
			return '';
		}

		return get_username_string('no_profile', $user_id, (string) ($row['username'] ?? ''), (string) ($row['user_colour'] ?? ''));
	}

	protected function can_manage(): bool
	{
		return $this->auth->acl_get('m_cardgames_manage') || $this->auth->acl_get('a_board') || $this->auth->acl_get('a_');
	}

	protected function can_export_replay(): bool
	{
		return $this->can_manage() || $this->auth->acl_get('m_cardgames_replay_export');
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
		$timeout_ms = (int) ($this->config['cardgames_node_runtime_timeout_ms'] ?? 10000);
		return max(1000, min(30000, $timeout_ms));
	}

	protected function runtime_path(string $action, string $room_key, int $session_id, int $target_user_id): string
	{
		switch ($action)
		{
			case 'disconnect_user':
				return '/card-games/runtime/users/' . $target_user_id . '/disconnect';

			case 'reload_user_status':
				return '/card-games/runtime/users/' . $target_user_id . '/refresh';

			case 'reset_room':
				return '/card-games/runtime/rooms/' . rawurlencode($room_key) . '/reset';

			case 'cancel_game':
				return '/card-games/runtime/sessions/' . $session_id . '/cancel';
		}

		throw new \RuntimeException($this->language->lang('CARDGAMES_CONTROL_ERR_INVALID_ACTION'));
	}

	protected function is_guest(): bool
	{
		return (int) ($this->user->data['user_id'] ?? ANONYMOUS) === ANONYMOUS;
	}

	protected function boot_language(): void
	{
		$this->language->add_lang('common', 'freemitbbs/cardgamesauth');
	}

	protected function return_link(): string
	{
		return '<br /><br />' . sprintf(
			$this->language->lang('RETURN_PAGE'),
			'<a href="' . $this->helper->route('freemitbbs_cardgamesauth_admin') . '">',
			'</a>'
		);
	}

	protected function encode_json(array $data): string
	{
		$json = json_encode($data, self::JSON_FLAGS);

		return $json === false ? '{}' : $json;
	}

	protected function nonce(): string
	{
		try
		{
			return bin2hex(random_bytes(16));
		}
		catch (\Exception $e)
		{
			return sha1(uniqid((string) mt_rand(), true));
		}
	}

	protected function format_time(int $timestamp): string
	{
		return $timestamp > 0 ? $this->user->format_date($timestamp) : '';
	}
}
