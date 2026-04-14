<?php
namespace bastien59960\reactions\controller;

use phpbb\db\driver\driver_interface;
use phpbb\template\template;
use phpbb\request\request;
use phpbb\user;

class ucp_reactions
{
    protected $u_action;
    /** @var driver_interface */
    protected $db;
    /** @var template */
    protected $template;
    /** @var request */
    protected $request;
    /** @var user */
    protected $user;
    /** @var string */
    protected $table_prefix;
    
    public function __construct(driver_interface $db, request $request, template $template, user $user, $table_prefix)
    {
        $this->db = $db;
        $this->request = $request;
        $this->template = $template;
        $this->user = $user;
        $this->table_prefix = $table_prefix;
    }
    
    public function set_page_url($u_action)
    {
        $this->u_action = $u_action;
    }

    public function handle($id, $mode)
    {
        $this->user->add_lang_ext('bastien59960/reactions', 'common');
        $this->user->add_lang_ext('bastien59960/reactions', 'ucp_reactions');

        add_form_key('bastien59960/reactions');

        // Logique de traitement du formulaire UCP
        if ($this->request->is_set_post('submit'))
        {
            if (!check_form_key('bastien59960/reactions'))
            {
                trigger_error('FORM_INVALID');
            }

            // Traitement de la sauvegarde
            $user_reactions_notify = $this->request->variable('user_reactions_notify', 0);
            $user_reactions_cron_email = $this->request->variable('user_reactions_cron_email', 0);
            
            // Sauvegarde en base
            $sql = 'UPDATE ' . USERS_TABLE . '
                SET user_reactions_notify = ' . (int) $user_reactions_notify . ',
                    user_reactions_cron_email = ' . (int) $user_reactions_cron_email . '
                WHERE user_id = ' . (int) $this->user->data['user_id'];
            $this->db->sql_query($sql);
            
            meta_refresh(3, $this->u_action);

            $message = $this->user->lang['UCP_REACTIONS_SAVED']
                . '<br /><br />'
                . $this->user->lang('RETURN_UCP', '<a href="' . $this->u_action . '">', '</a>');

            trigger_error($message);
        }

        // Affichage du template
        $this->template->assign_vars([
            'USER_REACTIONS_NOTIFY'      => $this->user->data['user_reactions_notify'],
            'USER_REACTIONS_CRON_EMAIL'  => $this->user->data['user_reactions_cron_email'],
            'U_ACTION'                   => $this->u_action,
        ]);
    }
}
