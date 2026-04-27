<?php

namespace bastien59960\reactions\migrations;

class release_1_0_2 extends \phpbb\db\migration\migration
{
    public static function depends_on()
    {
        return ['\bastien59960\reactions\migrations\release_1_0_1'];
    }

    public function update_schema()
    {
        return [
            'add_index' => [
                $this->table_prefix . 'post_reactions' => [
                    'post_reactions_view' => ['post_id', 'reaction_time', 'user_id'],
                ],
            ],
        ];
    }

    public function revert_schema()
    {
        return [
            'drop_keys' => [
                $this->table_prefix . 'post_reactions' => [
                    'post_reactions_view',
                ],
            ],
        ];
    }

    public function update_data()
    {
        return [
            ['config.update', ['bastien59960_reactions_version', '1.0.2']],
        ];
    }
}
