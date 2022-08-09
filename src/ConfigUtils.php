<?php
/*
 * DiscordAccount, PocketMine-MP Plugin.
 *
 * Licensed under the Open Software License version 3.0 (OSL-3.0)
 * Copyright (C) 2022-present JaxkDev
 *
 * Twitter :: @JaxkDev
 * Discord :: JaxkDev#2698
 * Email   :: JaxkDev@gmail.com
 */

namespace JaxkDev\DiscordAccount;

final class ConfigUtils{

    const VERSION = 1;

    // Map all versions to a static function.
    private const _PATCH_MAP = [];

    static public function update(array &$config): void{
        for($i = (int)$config["version"]; $i < self::VERSION; $i += 1){
            /** @phpstan-ignore-next-line */
            $config = forward_static_call([self::class, self::_PATCH_MAP[$i]], $config);
        }
    }

    /**
     * Verifies the config's keys and values, returning any keys and a relevant message.
     * @param array $config
     * @return string[]
     */
    static public function verify(array $config): array{
        //TODO.
        return [];
    }
}