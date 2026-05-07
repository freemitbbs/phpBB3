<?php

namespace freemitbbs\cardgamesauth\controller;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class auth
{
	private const TOKEN_HASH_NAME = 'freemitbbs_cardgamesauth_token';
	private const JSON_FLAGS = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT;
	private const TESTER_GROUP_NAME = 'CARD_GAME_TESTERS';
	private const SENTRY_DEFAULT_CDN_URL = 'https://browser.sentry-cdn.com/10.45.0/bundle.min.js';

	protected \phpbb\auth\auth $auth;
	protected \phpbb\cache\driver\driver_interface $cache;
	protected \phpbb\config\config $config;
	protected \phpbb\controller\helper $helper;
	protected \phpbb\db\driver\driver_interface $db;
	protected \phpbb\language\language $language;
	protected \phpbb\request\request_interface $request;
	protected \phpbb\template\template $template;
	protected \phpbb\user $user;
	protected \freemitbbs\cardgamesauth\service\token_issuer $token_issuer;

	public function __construct(
		\phpbb\auth\auth $auth,
		\phpbb\cache\driver\driver_interface $cache,
		\phpbb\config\config $config,
		\phpbb\controller\helper $helper,
		\phpbb\db\driver\driver_interface $db,
		\phpbb\language\language $language,
		\phpbb\request\request_interface $request,
		\phpbb\template\template $template,
		\phpbb\user $user,
		\freemitbbs\cardgamesauth\service\token_issuer $token_issuer
	)
	{
		$this->auth = $auth;
		$this->cache = $cache;
		$this->config = $config;
		$this->helper = $helper;
		$this->db = $db;
		$this->language = $language;
		$this->request = $request;
		$this->template = $template;
		$this->user = $user;
		$this->token_issuer = $token_issuer;
	}

	public function launch(): Response
	{
		$this->boot_language();

		if ($this->is_guest())
		{
			login_box($this->helper->route('freemitbbs_cardgamesauth_launch'), $this->language->lang('CARDGAMES_LOGIN_REQUIRED'));
		}

		$can_play = $this->can_play();
		$client_url = $this->client_url();

		$this->template->assign_vars([
			'S_CARDGAMES_CAN_PLAY' => $can_play,
			'S_CARDGAMES_TESTING_MODE' => $this->is_testing_mode(),
			'S_CARDGAMES_MANAGE' => $this->can_manage(),
			'U_CARDGAMES_CLIENT' => $client_url,
			'U_CARDGAMES_ADMIN' => $this->helper->route('freemitbbs_cardgamesauth_admin'),
			'CARDGAMES_BOOTSTRAP_JSON' => $this->encode_json([
				'tokenUrl' => $this->helper->route('freemitbbs_cardgamesauth_token', [], false),
				'configUrl' => $this->helper->route('freemitbbs_cardgamesauth_config', [], false),
				'tokenHash' => generate_link_hash(self::TOKEN_HASH_NAME),
				'wsUrl' => $this->ws_url(),
				'assetBaseUrl' => $this->asset_base_url(),
				'audioBaseUrl' => $this->audio_base_url(),
				'cardStyle' => 'cardsclassic',
				'sentry' => $this->sentry_config($can_play),
			]),
		]);

		if ($can_play && $client_url !== '' && (bool) ((int) ($this->config['cardgamesauth_launch_redirect'] ?? 0)))
		{
			redirect($client_url);
		}

		return $this->helper->render('@freemitbbs_cardgamesauth/cardgames_launch.html', $this->language->lang('CARDGAMES_TITLE'));
	}

	public function client(): Response
	{
		$this->boot_language();

		if ($this->is_guest())
		{
			login_box($this->helper->route('freemitbbs_cardgamesauth_client'), $this->language->lang('CARDGAMES_LOGIN_REQUIRED'));
		}

		if (!$this->can_play())
		{
			return $this->launch();
		}

		$sentry_config = $this->sentry_config(true);
		$this->template->assign_vars([
			'CARDGAMES_BOOTSTRAP_JSON' => $this->encode_json([
				'tokenUrl' => $this->helper->route('freemitbbs_cardgamesauth_token', [], false),
				'configUrl' => $this->helper->route('freemitbbs_cardgamesauth_config', [], false),
				'tokenHash' => generate_link_hash(self::TOKEN_HASH_NAME),
				'wsUrl' => $this->ws_url(),
				'assetBaseUrl' => $this->asset_base_url(),
				'audioBaseUrl' => $this->audio_base_url(),
				'cardStyle' => 'cardsclassic',
				'user' => $this->client_user_data(),
				'sentry' => $sentry_config,
			]),
			'S_CARDGAMES_SENTRY' => !empty($sentry_config['enabled']) && !empty($sentry_config['dsn']),
			'CARDGAMES_SENTRY_CDN_URL' => $this->sentry_cdn_url(),
		]);

		return $this->helper->render('@freemitbbs_cardgamesauth/cardgames_client.html', $this->language->lang('CARDGAMES_TITLE'));
	}

	public function config(): JsonResponse
	{
		$this->boot_language();
		$can_play = $this->can_play();

		$data = [
			'success' => true,
			'enabled' => $this->is_enabled(),
			'testingMode' => $this->is_testing_mode(),
			'isTester' => $this->is_tester(),
			'canPlay' => $can_play,
			'launchUrl' => $this->helper->route('freemitbbs_cardgamesauth_launch', [], false),
			'tokenUrl' => $this->helper->route('freemitbbs_cardgamesauth_token', [], false),
			'clientUrl' => $this->client_url(),
			'wsUrl' => $this->ws_url(),
			'assetBaseUrl' => $this->asset_base_url(),
			'audioBaseUrl' => $this->audio_base_url(),
			'cardStyle' => 'cardsclassic',
			'tokenHash' => $can_play ? generate_link_hash(self::TOKEN_HASH_NAME) : '',
			'user' => $can_play ? $this->client_user_data() : null,
			'sentry' => $this->sentry_config($can_play),
		];

		return $this->json($data);
	}

	public function token(): JsonResponse
	{
		$this->boot_language();

		if ($this->request->server('REQUEST_METHOD') !== 'POST')
		{
			return $this->json_error($this->language->lang('CARDGAMES_ERR_METHOD'), 405);
		}

		if (!$this->is_enabled())
		{
			return $this->json_error($this->language->lang('CARDGAMES_ERR_DISABLED'), 403);
		}

		if (!$this->can_play())
		{
			return $this->json_error($this->language->lang('CARDGAMES_ERR_PERMISSION'), 403);
		}

		if (!check_link_hash($this->request_hash(), self::TOKEN_HASH_NAME))
		{
			return $this->json_error($this->language->lang('CARDGAMES_ERR_FORM'), 400);
		}

		if (!$this->consume_rate_limit())
		{
			return $this->json_error($this->language->lang('CARDGAMES_ERR_RATE_LIMIT'), 429);
		}

		$issued = $this->token_issuer->issue($this->token_user_data());

		return $this->json([
			'success' => true,
			'token' => $issued['token'],
			'expiresAt' => $issued['expires_at'],
			'expiresIn' => $issued['expires_in'],
			'wsUrl' => $this->ws_url(),
			'user' => $this->client_user_data(),
		]);
	}

	protected function boot_language(): void
	{
		$this->language->add_lang('common', 'freemitbbs/cardgamesauth');
	}

	protected function can_play(): bool
	{
		if (!$this->is_enabled() || $this->is_guest())
		{
			return false;
		}

		$user_type = (int) ($this->user->data['user_type'] ?? USER_IGNORE);
		if (!empty($this->user->data['is_bot']) || $user_type === USER_IGNORE || $user_type === USER_INACTIVE)
		{
			return false;
		}

		return $this->auth->acl_get('u_cardgames_play')
			&& (!$this->is_testing_mode() || $this->is_tester());
	}

	protected function is_enabled(): bool
	{
		return (bool) ((int) ($this->config['cardgamesauth_enabled'] ?? 1));
	}

	protected function can_manage(): bool
	{
		return $this->auth->acl_get('m_cardgames_manage') || $this->auth->acl_get('a_board') || $this->auth->acl_get('a_');
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

	protected function is_guest(): bool
	{
		return (int) ($this->user->data['user_id'] ?? ANONYMOUS) === ANONYMOUS;
	}

	protected function token_user_data(): array
	{
		$data = $this->client_user_data();
		$data['groups'] = $this->user_groups();
		$data['permissions'] = $this->user_permissions();

		return $data;
	}

	protected function client_user_data(): array
	{
		return [
			'user_id' => (int) ($this->user->data['user_id'] ?? 0),
			'username' => (string) ($this->user->data['username'] ?? ''),
			'username_clean' => (string) ($this->user->data['username_clean'] ?? ''),
			'display_name' => html_entity_decode(strip_tags(get_username_string('no_profile', (int) $this->user->data['user_id'], (string) $this->user->data['username'], (string) ($this->user->data['user_colour'] ?? ''))), ENT_QUOTES, 'UTF-8'),
			'avatar_url' => $this->avatar_url(),
		];
	}

	protected function user_groups(): array
	{
		$user_id = (int) ($this->user->data['user_id'] ?? 0);
		if ($user_id <= ANONYMOUS)
		{
			return [];
		}

		$sql = 'SELECT group_id
			FROM ' . USER_GROUP_TABLE . '
			WHERE user_id = ' . $user_id . '
				AND user_pending = 0
			ORDER BY group_id ASC';
		$result = $this->db->sql_query($sql);
		$groups = [];
		while ($row = $this->db->sql_fetchrow($result))
		{
			$groups[] = (string) ((int) $row['group_id']);
		}
		$this->db->sql_freeresult($result);

		return $groups;
	}

	protected function user_permissions(): array
	{
		$permissions = [];
		if ($this->auth->acl_get('u_cardgames_play'))
		{
			$permissions[] = 'u_cardgames_play';
		}
		if ($this->auth->acl_get('m_'))
		{
			$permissions[] = 'moderator';
		}
		if ($this->auth->acl_get('a_'))
		{
			$permissions[] = 'admin';
		}

		return $permissions;
	}

	protected function consume_rate_limit(): bool
	{
		$limit = max(1, min(120, (int) ($this->config['cardgamesauth_token_rate_limit'] ?? 20)));
		$window = max(10, min(3600, (int) ($this->config['cardgamesauth_token_rate_window'] ?? 60)));
		$now = time();
		$key = '_cardgamesauth_rate_' . (int) ($this->user->data['user_id'] ?? 0) . '_' . substr(hash('sha256', (string) ($this->user->session_id ?? '')), 0, 24);
		$record = $this->cache->get($key);

		if (!is_array($record) || (int) ($record['reset'] ?? 0) <= $now)
		{
			$this->cache->put($key, [
				'count' => 1,
				'reset' => $now + $window,
			], $window);

			return true;
		}

		if ((int) ($record['count'] ?? 0) >= $limit)
		{
			return false;
		}

		$record['count'] = (int) ($record['count'] ?? 0) + 1;
		$this->cache->put($key, $record, max(1, (int) $record['reset'] - $now));

		return true;
	}

	protected function avatar_url(): string
	{
		if (!function_exists('phpbb_get_user_avatar'))
		{
			return '';
		}

		$avatar_html = phpbb_get_user_avatar($this->user->data, 'USER_AVATAR', false, true);
		if ($avatar_html === '')
		{
			return '';
		}

		if (preg_match('#src="([^"]+)"#', $avatar_html, $match))
		{
			$url = html_entity_decode($match[1], ENT_QUOTES, 'UTF-8');
			if (preg_match('#^https?://#i', $url))
			{
				return $url;
			}

			return rtrim(generate_board_url(), '/') . '/' . ltrim($url, '/');
		}

		return '';
	}

	protected function client_url(): string
	{
		$configured = trim((string) ($this->config['cardgamesauth_client_url'] ?? ''));
		return $configured !== '' ? $configured : $this->helper->route('freemitbbs_cardgamesauth_client', [], false);
	}

	protected function ws_url(): string
	{
		$ws_url = trim((string) ($this->config['cardgamesauth_ws_url'] ?? ''));
		if ($ws_url !== '')
		{
			return $ws_url;
		}

		$board_url = generate_board_url();
		$scheme = str_starts_with($board_url, 'https://') ? 'wss://' : 'ws://';
		$host_path = preg_replace('#^https?://#', '', rtrim($board_url, '/'));

		return $scheme . $host_path . '/card-games/ws';
	}

	protected function asset_base_url(): string
	{
		return rtrim(generate_board_url(), '/') . '/ext/freemitbbs/cardgamesauth/styles/all/theme/images';
	}

	protected function audio_base_url(): string
	{
		return rtrim(generate_board_url(), '/') . '/ext/freemitbbs/cardgamesauth/styles/all/theme/audio';
	}

	protected function sentry_config(bool $include_user): array
	{
		$enabled = (bool) ((int) ($this->config['cardgamesauth_sentry_enabled'] ?? 0));
		$dsn = trim((string) ($this->config['cardgamesauth_sentry_dsn'] ?? ''));
		if (!$enabled || $dsn === '')
		{
			return [
				'enabled' => false,
			];
		}

		$config = [
			'enabled' => true,
			'dsn' => $dsn,
			'environment' => $this->sentry_environment(),
			'release' => trim((string) ($this->config['cardgamesauth_sentry_release'] ?? '')),
			'sampleRate' => $this->bounded_float_config('cardgamesauth_sentry_sample_rate', 1.0),
			'tracesSampleRate' => $this->bounded_float_config('cardgamesauth_sentry_traces_sample_rate', 0.0),
		];
		if ($include_user)
		{
			$config['user'] = [
				'user_id' => (int) ($this->user->data['user_id'] ?? 0),
			];
		}

		return $config;
	}

	protected function sentry_cdn_url(): string
	{
		$url = trim((string) ($this->config['cardgamesauth_sentry_cdn_url'] ?? ''));
		if (!preg_match('#^https://#i', $url))
		{
			return self::SENTRY_DEFAULT_CDN_URL;
		}

		return $url;
	}

	protected function sentry_environment(): string
	{
		$environment = trim((string) ($this->config['cardgamesauth_sentry_environment'] ?? 'production'));
		$environment = preg_replace('#[\s/]+#', '-', $environment) ?: 'production';
		return substr($environment, 0, 64);
	}

	protected function bounded_float_config(string $name, float $default): float
	{
		$value = (float) ($this->config[$name] ?? $default);
		if (!is_finite($value))
		{
			return $default;
		}

		return max(0.0, min(1.0, $value));
	}

	protected function request_hash(): string
	{
		$hash = $this->request->variable('hash', '');
		if ($hash !== '')
		{
			return $hash;
		}

		return (string) $this->request->server('HTTP_X_CARDGAMES_HASH', '');
	}

	protected function json(array $data, int $status = 200): JsonResponse
	{
		return new JsonResponse($data, $status, [
			'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
			'Pragma' => 'no-cache',
		]);
	}

	protected function encode_json(array $data): string
	{
		$json = json_encode($data, self::JSON_FLAGS);

		return $json === false ? '{}' : $json;
	}

	protected function json_error(string $message, int $status = 400): JsonResponse
	{
		return $this->json([
			'success' => false,
			'error' => $message,
		], $status);
	}
}
