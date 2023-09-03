<?php

/*
 * DiscordAccount, PocketMine-MP Plugin.
 *
 * Licensed under the Open Software License version 3.0 (OSL-3.0)
 * Copyright (C) 2022-present JaxkDev
 *
 * Discord :: JaxkDev
 * Email   :: JaxkDev@gmail.com
 */

namespace JaxkDev\DiscordAccount;

use function array_key_exists;
use function forward_static_call;
use function is_int;
use function is_string;
use function strlen;

final class ConfigUtils{

    const VERSION = 2;

    // Map all versions to a static function.
    private const _PATCH_MAP = [
        1 => "patch_1",
    ];

    static public function update(array &$config): void{
        for($i = (int)$config["version"]; $i < self::VERSION; $i += 1){
            $config = forward_static_call([self::class, self::_PATCH_MAP[$i]], $config);
        }
    }

    static public function patch_1(array $config): array{
        $config["version"] = 2;
        if(!isset($config["discord"])){
            $config["discord"] = [
                "command" => "/mclink",
            ];
        }
        if(!isset($config["discord"]["command"])){
            $config["discord"]["command"] = $config["discord"]["link_command"] ?? "/mclink";
        }
        unset($config["discord"]["link_command"], $config["discord"]["unlink_command"]);
        return $config;
    }

    /**
     * Verifies the config's keys and values, returning any keys and a relevant message.
     * @return string[]
     */
    static public function verify(array $config): array{
        $result = [];

        if(!array_key_exists("version", $config) || $config["version"] === null){
            $result[] = "No 'version' field found.";
        }else{
            if(!is_int($config["version"]) || $config["version"] <= 0 || $config["version"] > self::VERSION){
                $result[] = "Invalid 'version' ({$config["version"]}), you were warned not to touch it...";
            }
        }

        if(!array_key_exists("discord", $config) || $config["discord"] === null){
            $result[] = "No 'discord' field found.";
        }else{
            if(!array_key_exists("command", $config["discord"]) || $config["discord"]["command"] === null){
                $result[] = "No 'discord.command' field found.";
            }else{
                //Check for a unique prefix? (not a-Z)
                if(!is_string($config["discord"]["command"]) || strlen($config["discord"]["command"]) < 3){
                    $result[] = "Invalid 'discord.command' ({$config["discord"]["command"]}).";
                }
            }
        }

        if(!array_key_exists("code", $config) || $config["code"] === null){
            $result[] = "No 'code' field found.";
        }else{
            if(!array_key_exists("characters", $config["code"]) || $config["code"]["characters"] === null){
                $result[] = "No 'code.characters' field found.";
            }else{
                if(!is_string($config["code"]["characters"]) || strlen($config["code"]["characters"]) <= 10){
                    $result[] = "Invalid 'code.characters' ({$config["code"]["characters"]}), should be > 10 characters.";
                }
            }

            if(!array_key_exists("size", $config["code"]) || $config["code"]["size"] === null){
                $result[] = "No 'code.size' field found.";
            }else{
                if(!is_int($config["code"]["size"]) || $config["code"]["size"] < 4 || $config["code"]["size"] > 16){
                    $result[] = "Invalid 'code.size' ({$config["code"]["size"]}), minimum 4 and maximum 16.";
                }
            }

            if(!array_key_exists("timeout", $config["code"]) || $config["code"]["timeout"] === null){
                $result[] = "No 'code.timeout' field found.";
            }else{
                if(!is_int($config["code"]["timeout"]) || $config["code"]["timeout"] < 1 || $config["code"]["timeout"] > 1440){
                    $result[] = "Invalid 'code.timeout' ({$config["code"]["timeout"]}), minimum 1 and maximum 1440.";
                }
            }
        }

        //Leave database section as libasynql will use/verify it not us.

        return $result;
    }
}