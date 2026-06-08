<?php
/**
*
* This file is part of the phpBB Forum Software package.
*
* @copyright (c) phpBB Limited <https://www.phpbb.com>
* @license GNU General Public License, version 2 (GPL-2.0)
*
* For full copyright and license information, please see
* the docs/CREDITS.txt file.
*
*/

/**
* @ignore
*/
if (!defined('IN_PHPBB'))
{
	exit;
}

/**
* ucp_login_link
* Allows users of external accounts link those accounts to their phpBB accounts
* during an attempted login.
*/
class ucp_login_link
{
	/**
	* @var	string
	*/
	public $u_action;

	/**
	* Generates the ucp_login_link page and handles login link process
	*
	* @param	int		$id
	* @param	string	$mode
	*/
	function main($id, $mode)
	{
		global $config, $db, $phpbb_container, $request, $template, $user, $phpbb_dispatcher;
		global $phpbb_root_path, $phpEx;

		// Initialize necessary variables
		$login_error = null;
		$login_link_error = null;
		$oauth_username = '';
		$fatal_error = false;
		$oauth_user_data = array();

		// Build the data array
		$data = $this->get_login_link_data_array();

		// Ensure the person was sent here with login_link data
		if (empty($data))
		{
			$login_link_error = $user->lang['LOGIN_LINK_NO_DATA_PROVIDED'];
			$fatal_error = true;
		}

		// Use the auth_provider requested even if different from configured
		/* @var $provider_collection \phpbb\auth\provider_collection */
		$provider_collection = $phpbb_container->get('auth.provider_collection');
		$auth_provider = $provider_collection->get_provider($request->variable('auth_provider', ''));

		// Set the link_method to login_link
		$data['link_method'] = 'login_link';

		// Have the authentication provider check that all necessary data is available
		$result = $auth_provider->login_link_has_necessary_data($data);
		if ($result !== null)
		{
			$login_link_error = $user->lang[$result];
			$fatal_error = true;
		}

		if (!$fatal_error)
		{
			if (!method_exists($auth_provider, 'get_login_link_user_data'))
			{
				$login_link_error = $user->lang['LOGIN_LINK_MISSING_DATA'];
				$fatal_error = true;
			}
			else
			{
				try
				{
					$oauth_user_data = $auth_provider->get_login_link_user_data($data);
				}
				catch (\RuntimeException $e)
				{
					$login_link_error = isset($user->lang[$e->getMessage()]) ? $user->lang[$e->getMessage()] : $e->getMessage();
					$fatal_error = true;
				}
			}
		}

		if (!$fatal_error && (empty($oauth_user_data['email']) || empty($oauth_user_data['email_verified'])))
		{
			$login_link_error = $user->lang['OAUTH_REGISTER_EMAIL_UNAVAILABLE'];
			$fatal_error = true;
		}

		if (!$fatal_error)
		{
			$existing_user = $this->find_user_by_email($oauth_user_data['email']);

			if ($existing_user)
			{
				if ((int) $existing_user['user_type'] !== USER_NORMAL && (int) $existing_user['user_type'] !== USER_FOUNDER)
				{
					$login_link_error = $user->lang['ACTIVE_ERROR'];
					$fatal_error = true;
				}
				else
				{
					$data['user_id'] = (int) $existing_user['user_id'];
					$result = $auth_provider->link_account($data);

					if ($result)
					{
						$login_link_error = $user->lang[$result];
						$fatal_error = true;
					}
					else
					{
						$user->session_create((int) $existing_user['user_id'], false, false, true);
						$this->perform_redirect();
					}
				}
			}
		}

		add_form_key('ucp_oauth_register');

		if (!$fatal_error && $request->is_set_post('register'))
		{
			$oauth_username = $request->variable('username', '', true, \phpbb\request\request_interface::POST);
			$validation_data = array(
				'username'	=> $oauth_username,
				'email'		=> $oauth_user_data['email'],
			);
			$error = validate_data($validation_data, array(
				'username'	=> array(
					array('string', false, $config['min_name_chars'], $config['max_name_chars']),
					array('username', ''),
				),
				'email'		=> array(
					array('string', false, 6, 60),
					array('user_email'),
				),
			));

			if (!check_form_key('ucp_oauth_register'))
			{
				$error[] = $user->lang['FORM_INVALID'];
			}

			$error = array_map(array($user, 'lang'), $error);

			if ($config['check_dnsbl'])
			{
				if (($dnsbl = $user->check_dnsbl('register')) !== false)
				{
					$error[] = sprintf($user->lang['IP_BLACKLISTED'], $user->ip, $dnsbl[1]);
				}
			}

			if (!count($error))
			{
				$sql = 'SELECT group_id
					FROM ' . GROUPS_TABLE . "
					WHERE group_name = 'REGISTERED'
						AND group_type = " . GROUP_SPECIAL;
				$result = $db->sql_query($sql);
				$row = $db->sql_fetchrow($result);
				$db->sql_freeresult($result);

				if (!$row)
				{
					trigger_error('NO_GROUP');
				}

				/* @var $passwords_manager \phpbb\passwords\manager */
				$passwords_manager = $phpbb_container->get('passwords.manager');

				$user_row = array(
					'username'		=> $oauth_username,
					'user_password'	=> $passwords_manager->hash(gen_rand_string(32)),
					'user_email'	=> $oauth_user_data['email'],
					'group_id'		=> (int) $row['group_id'],
					'user_timezone'	=> $config['board_timezone'],
					'user_lang'		=> $user->lang_name,
					'user_type'		=> USER_NORMAL,
					'user_ip'		=> $user->ip,
					'user_regdate'	=> time(),
				);

				if ($config['new_member_post_limit'])
				{
					$user_row['user_new'] = 1;
				}

				$user_id = user_add($user_row);

				if ((bool) $user_id === false)
				{
					trigger_error('NO_USER', E_USER_ERROR);
				}

				$data['user_id'] = (int) $user_id;
				$result = $auth_provider->link_account($data);

				if ($result)
				{
					$login_link_error = $user->lang[$result];
				}
				else
				{
					$user->session_create((int) $user_id, false, false, true);
					$this->perform_redirect();
				}
			}
			else
			{
				$login_link_error = implode('<br />', $error);
			}
		}

		$register_query = array(
			'mode'			=> 'login_link',
			'auth_provider'	=> $request->variable('auth_provider', ''),
		);

		foreach ($data as $key => $value)
		{
			if ($key !== 'link_method')
			{
				$register_query['login_link_' . $key] = $value;
			}
		}

		$tpl_ary = array(
			// Common template elements
			'LOGIN_LINK_ERROR'					=> $login_link_error,
			'S_CAN_OAUTH_REGISTER'				=> !$fatal_error,
			'S_HIDDEN_FIELDS'					=> $this->get_hidden_fields($data),

			// Registration elements
			'OAUTH_USERNAME'					=> $oauth_username,
			'OAUTH_USERNAME_EXPLAIN'			=> $user->lang($config['allow_name_chars'] . '_EXPLAIN', $user->lang('CHARACTERS_XY', (int) $config['min_name_chars']), $user->lang('CHARACTERS_XY', (int) $config['max_name_chars'])),
			'REGISTER_ACTION'					=> append_sid("{$phpbb_root_path}ucp.$phpEx", $register_query),

			// Kept for the event contract.
			'LOGIN_ERROR'						=> $login_error,
			'LOGIN_USERNAME'					=> '',
			'PASSWORD_CREDENTIAL'				=> 'login_password',
			'USERNAME_CREDENTIAL'				=> 'login_username',
		);

		/**
		* Event to perform additional actions before ucp_login_link is displayed
		*
		* @event core.ucp_login_link_template_after
		* @var	array							data				Login link data
		* @var	\phpbb\auth\provider_interface	auth_provider		Auth provider
		* @var	string							login_link_error	Login link error
		* @var	string							login_error			Login error
		* @var	string							login_username		Login username
		* @var	array							tpl_ary				Template variables
		* @since 3.2.4-RC1
		*/
		$login_username = '';
		$vars = array('data', 'auth_provider', 'login_link_error', 'login_error', 'login_username', 'tpl_ary');
		extract($phpbb_dispatcher->trigger_event('core.ucp_login_link_template_after', compact($vars)));

		$template->assign_vars($tpl_ary);

		$this->tpl_name = 'ucp_login_link';
		$this->page_title = 'UCP_LOGIN_LINK';
	}

	/**
	* Finds a board user by email, respecting phpBB's Gmail alias comparison.
	*
	* @param	string	$email	Email address
	* @return	array|false
	*/
	protected function find_user_by_email($email)
	{
		global $db;

		$email = strtolower($email);
		$email_comparison = phpbb_email_address_for_ban_comparison($email);

		if (phpbb_email_address_uses_gmail_dot_aliasing($email))
		{
			$sql = 'SELECT user_id, username, user_email, user_ip, user_type
				FROM ' . USERS_TABLE . '
				WHERE user_email ' . $db->sql_like_expression($db->get_any_char() . '@gmail.com') . '
					OR user_email ' . $db->sql_like_expression($db->get_any_char() . '@googlemail.com');
		}
		else
		{
			$sql = 'SELECT user_id, username, user_email, user_ip, user_type
				FROM ' . USERS_TABLE . "
				WHERE user_email = '" . $db->sql_escape($email) . "'";
		}

		$result = $db->sql_query($sql);
		while ($row = $db->sql_fetchrow($result))
		{
			if (!phpbb_email_address_uses_gmail_dot_aliasing($email) || $email_comparison === phpbb_email_address_for_ban_comparison($row['user_email']))
			{
				$db->sql_freeresult($result);
				return $row;
			}
		}
		$db->sql_freeresult($result);

		return false;
	}

	/**
	* Builds the hidden fields string from the data array.
	*
	* @param	array	$data	This function only includes data in the array
	*							that has a key that begins with 'login_link_'
	* @return	string	A string of hidden fields that can be included in the
	*					template
	*/
	protected function get_hidden_fields($data)
	{
		$fields = array();

		foreach ($data as $key => $value)
		{
			$fields['login_link_' . $key] = $value;
		}

		return build_hidden_fields($fields);
	}

	/**
	* Builds the login_link data array
	*
	* @return	array	All login_link data. This is all GET data whose names
	*					begin with 'login_link_'
	*/
	protected function get_login_link_data_array()
	{
		global $request;

		$var_names = $request->variable_names(\phpbb\request\request_interface::GET);
		$login_link_data = array();
		$string_start_length = strlen('login_link_');

		foreach ($var_names as $var_name)
		{
			if (strpos($var_name, 'login_link_') === 0)
			{
				$key_name = substr($var_name, $string_start_length);
				$login_link_data[$key_name] = $request->variable($var_name, '', false, \phpbb\request\request_interface::GET);
			}
		}

		return $login_link_data;
	}

	/**
	* Processes the result array from the login process
	* @param	array	$result	The login result array
	* @return	string|null	If there was an error in the process, a string is
	*						returned. If the login was successful, then null is
	*						returned.
	*/
	protected function process_login_result($result)
	{
		global $config, $template, $user, $phpbb_container;

		$login_error = null;

		if ($result['status'] != LOGIN_SUCCESS)
		{
			// Handle all errors first
			if ($result['status'] == LOGIN_BREAK)
			{
				trigger_error($result['error_msg']);
			}

			switch ($result['status'])
			{
				case LOGIN_ERROR_ATTEMPTS:

					$captcha = $phpbb_container->get('captcha.factory')->get_instance($config['captcha_plugin']);
					$captcha->init(CONFIRM_LOGIN);

					$template->assign_vars(array(
						'CAPTCHA_TEMPLATE'			=> $captcha->get_template(),
					));

					$login_error = $user->lang[$result['error_msg']];
				break;

				case LOGIN_ERROR_PASSWORD_CONVERT:
					$login_error = sprintf(
						$user->lang[$result['error_msg']],
						($config['email_enable']) ? '<a href="' . append_sid("{$phpbb_root_path}ucp.$phpEx", 'mode=sendpassword') . '">' : '',
						($config['email_enable']) ? '</a>' : '',
						($config['board_contact']) ? '<a href="mailto:' . htmlspecialchars($config['board_contact'], ENT_COMPAT) . '">' : '',
						($config['board_contact']) ? '</a>' : ''
					);
				break;

				// Username, password, etc...
				default:
					$login_error = $user->lang[$result['error_msg']];

					// Assign admin contact to some error messages
					if ($result['error_msg'] == 'LOGIN_ERROR_USERNAME' || $result['error_msg'] == 'LOGIN_ERROR_PASSWORD')
					{
						$login_error = (!$config['board_contact']) ? sprintf($user->lang[$result['error_msg']], '', '') : sprintf($user->lang[$result['error_msg']], '<a href="mailto:' . htmlspecialchars($config['board_contact'], ENT_COMPAT) . '">', '</a>');
					}

				break;
			}
		}

		return $login_error;
	}

	/**
	* Performs a post login redirect
	*/
	protected function perform_redirect()
	{
		global $phpbb_root_path, $phpEx;
		$url = append_sid($phpbb_root_path . 'index.' . $phpEx);
		redirect($url);
	}
}
