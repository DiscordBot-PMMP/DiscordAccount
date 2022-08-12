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

final class Utils{
    public static function generateCode(int $length, string $chars = "abcdefghjkmnpqrstuvwxyzABCDEFGHJKMNPQRSTUVWXYZ23456789"): string{
        mt_srand(intval(microtime(true)*1000000));
        $pass = '' ;
        for($i = 0; $i < $length; $i += 1){
            $num = mt_rand() % strlen($chars);
            $pass .= $chars[$num];
        }
        return $pass;
    }
}