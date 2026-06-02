<?php

namespace bastien59960\reactions\migrations;

class release_1_0_3 extends \phpbb\db\migration\migration
{
    public static function depends_on()
    {
        return ['\bastien59960\reactions\migrations\release_1_0_2'];
    }

    public function update_data()
    {
        return [
            ['custom', [[$this, 'increase_post_emoji_size']]],
            ['config.update', ['bastien59960_reactions_version', '1.0.3']],
        ];
    }

    public function revert_data()
    {
        return [
            ['config.update', ['bastien59960_reactions_version', '1.0.2']],
        ];
    }

    public function increase_post_emoji_size(): void
    {
        $current_size = (int) ($this->config['bastien59960_reactions_post_emoji_size'] ?? 24);
        if ($current_size < 18)
        {
            $this->config->set('bastien59960_reactions_post_emoji_size', '18');
        }
    }
}
