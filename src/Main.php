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

use JaxkDev\DiscordBot\Plugin\Main as DiscordBot;
use Phar;
use pocketmine\plugin\DisablePluginException;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\VersionString;
use poggit\libasynql\libasynql;

class Main extends PluginBase{

    private DiscordBot $discord;

    public function onLoad(): void{
        $this->checkPrerequisites();
        $this->checkConfig();
    }

    public function onEnable(): void{
        if($this->getServer()->getPluginManager()->getPlugin("DiscordBot")?->isEnabled() !== true){
            $this->disable("DiscordBot is not enabled! Dependency must be enabled for this plugin to operate.");
        }
    }

    private function checkPrerequisites(): void{
        //Phar
        if(Phar::running() === ""){
            $this->disable("Plugin running from source, please use a release phar.");
        }

        //Virions
        if(!class_exists(libasynql::class)){
            $this->disable("Missing libasynql virion, please use a release phar.");
        }

        //Dependencies
        $discordBot = $this->getServer()->getPluginManager()->getPlugin("DiscordBot");
        if($discordBot === null){
            return; //Will never happen.
        }
        if($discordBot->getDescription()->getWebsite() !== "https://github.com/DiscordBot-PMMP/DiscordBot"){
            $this->disable("Incompatible dependency 'DiscordBot' detected, see https://github.com/DiscordBot-PMMP/DiscordBot/releases for the correct plugin.");
        }
        $ver = new VersionString($discordBot->getDescription()->getVersion());
        if($ver->getMajor() !== 2){
            $this->disable("Incompatible dependency 'DiscordBot' detected, v2.x.y is required however v{$ver->getFullVersion(true)}) is installed, see https://github.com/DiscordBot-PMMP/DiscordBot/releases for downloads.");
        }
        if($ver->getSuffix() !== ""){
            $this->disable("Incompatible dependency 'DiscordBot' detected, A stable release is required however v{$ver->getFullVersion(true)}) is installed, see https://github.com/DiscordBot-PMMP/DiscordBot/releases for stable downloads.");
        }
        if(!$discordBot instanceof DiscordBot){
            $this->disable("Incompatible dependency 'DiscordBot' detected.");
        }
        $this->discord = $discordBot;
    }

    private function checkConfig(): void{
        $this->getLogger()->debug("Loading and checking configuration...");

        /** @var array<string, mixed> $config */
        $config = $this->getConfig()->getAll();
        if($config === [] or !is_int($config["version"]??"")){
            $this->disable("Failed to parse config.yml");
        }
        $this->getLogger()->debug("Config loaded, version: ".$config["version"]);

        if(intval($config["version"]) !== ConfigUtils::VERSION){
            $old = $config["version"];
            $this->getLogger()->info("Updating your config from v".$old." to v".ConfigUtils::VERSION);
            ConfigUtils::update($config);
            rename($this->getDataFolder()."config.yml", $this->getDataFolder()."config.yml.v".$old);
            $this->getConfig()->setAll($config);
            $this->getConfig()->save();
            $this->getLogger()->notice("Config updated, old config was saved to '{$this->getDataFolder()}config.yml.v".$old."'");
        }

        $this->getLogger()->debug("Verifying config...");
        $result_raw = ConfigUtils::verify($config);
        if(sizeof($result_raw) !== 0){
            $result = "There were some problems with your config.yml, see below:\n";
            foreach($result_raw as $value){
                $result .= "$value\n";
            }
            $this->getLogger()->error(rtrim($result));
            $this->disable("Config.yml has problems.");
        }
        $this->getLogger()->debug("Config verified.");
    }

    /**
     * @throw DisablePluginException
     * @return never-returns
     */
    private function disable(string $message): void{
        $this->getLogger()->critical($message);
        throw new DisablePluginException($message); //message isn't always shown to user so send critical message.
    }

    public function getDiscord(): DiscordBot{
        return $this->discord;
    }
}
