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
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\player\Player;
use pocketmine\plugin\DisablePluginException;
use pocketmine\plugin\PluginBase;
use pocketmine\scheduler\ClosureTask;
use pocketmine\utils\TextFormat;
use pocketmine\utils\VersionString;
use poggit\libasynql\DataConnector;
use poggit\libasynql\libasynql;
use poggit\libasynql\SqlError;

class Main extends PluginBase{

    private DataConnector $database;
    private DiscordBot $discord;

    public function onLoad(): void{
        $this->checkPrerequisites();
        $this->checkConfig();
    }

    public function onEnable(): void{
        if($this->discord->isEnabled() !== true){
            $this->disable("DiscordBot is not enabled! Dependency must be enabled for this plugin to operate.");
        }
        $this->database = libasynql::create($this, $this->getConfig()->get("database"), [
            "sqlite" => "sql/sqlite.sql",
            "mysql" => "sql/mysql.sql"
        ]);
        foreach(["minecraft.init", "links.init", "codes.init"] as $stmt){
            $this->database->executeGeneric($stmt, [], null, function(SqlError $error) use($stmt): void{
                $this->disable("Failed to execute sql statement ($stmt) - " . $error->getErrorMessage());
            });
        }
        $this->getServer()->getPluginManager()->registerEvents(new DiscordListener($this), $this);
        $this->getScheduler()->scheduleRepeatingTask(new ClosureTask(function(): void{
            $this->database->executeChange("codes.clean", ["now" => time()], function(int $deleted): void{
                $this->getLogger()->debug("Cleaned $deleted expired codes.");
            }, function(SqlError $error){
                $this->getLogger()->error("Failed to clean codes table - " . $error->getErrorMessage());
            });
        }), 72000 * 6); //Every 6 hours just to keep the table clean for larger servers.
    }

    public function onDisable(): void{
        if(isset($this->database)){
            $this->database->waitAll();
            $this->database->close();
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
            $this->disable("DiscordBot dependency not found."); //Will never happen.
        }
        if($discordBot->getDescription()->getWebsite() !== "https://github.com/DiscordBot-PMMP/DiscordBot"){
            $this->disable("Incompatible dependency 'DiscordBot' detected, see https://github.com/DiscordBot-PMMP/DiscordBot/releases for the correct plugin.");
        }

        $ver = new VersionString($discordBot->getDescription()->getVersion());
        if($ver->getMajor() !== 3 or ($ver->getMinor() === 0 and $ver->getPatch() < 2)){
            $this->disable("Incompatible dependency 'DiscordBot' detected, v3.0.2 or higher is required however v{$ver->getFullVersion(true)} is installed, see https://github.com/DiscordBot-PMMP/DiscordBot/releases for downloads.");
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
            /** @noinspection PhpUnhandledExceptionInspection */
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
        $this->getLogger()->critical($message); //message isn't always shown to user on enable so send critical message.
        throw new DisablePluginException($message);
    }

    public function onCommand(CommandSender $sender, Command $command, string $label, array $args): bool{
        if($command->getName() === "discordlink"){
            if(!$sender instanceof Player){
                $sender->sendMessage(TextFormat::RED . "This command can only be used in-game as a player.");
                return true;
            }
            if(!$this->getDiscord()->getApi()->isReady()){
                //Shouldn't really happen as time for player to join is same if not longer than discord start times, but just in case.
                $sender->sendMessage(TextFormat::RED . "Discord is not ready yet, please try again.\nIf this issue persists please contact a server administrator.");
                return true;
            }

            //Callbacks... :/
            $this->database->executeSelect("links.get_uuid", ["uuid" => $sender->getUniqueId()->toString()], function(array $rows) use ($sender): void{
                if(sizeof($rows) !== 0){
                    $sender->sendMessage(TextFormat::RED . "You are already linked to the discord account " . TextFormat::BOLD . TextFormat::GOLD . $rows[0]["dcid"] . TextFormat::RESET . TextFormat::RED . "\nUse /discordunlink to unlink from this discord account.");
                    return;
                }
                $cfg = $this->getConfig();
                /** @var int $length */
                $length = $cfg->getNested("code.length", 6);
                /** @var string $chars */
                $chars = $cfg->getNested("code.characters", "abcdefghjkmnpqrstuvwxyzABCDEFGHJKMNPQRSTUVWXYZ23456789");
                $code = Utils::generateCode($length, $chars);
                /** @var int $time */
                $time = $cfg->getNested("code.timeout", 15);
                /** @var string $cmd */
                $cmd = $cfg->getNested("discord.command", "/mclink");

                $this->database->executeInsert("minecraft.insert", ["uuid" => $sender->getUniqueId()->toString(), "username" => strtolower($sender->getName())], function(int $insertId) use ($code, $sender, $time, $cmd): void{
                    $this->getLogger()->debug("Minecraft user checked into database, id: $insertId");
                    $this->database->executeInsert("codes.insert", ["code" => $code, "uuid" => $sender->getUniqueId()->toString(), "expiry" => time() + ($time * 60)], function() use ($code, $sender, $time, $cmd): void{
                        $this->getLogger()->debug("New code generated for {$sender->getName()} ({$sender->getUniqueId()->toString()}) - $code");
                        $sender->sendMessage("Your code is: ".TextFormat::RED.TextFormat::BOLD.$code.TextFormat::RESET.", it will expire in $time minutes.\nSend `$cmd` in a discord channel to link account.");
                    }, function(SqlError $error) use ($sender): void{
                        $this->getLogger()->error("Failed to generate code for {$sender->getName()} ({$sender->getUniqueId()->toString()}): " . $error->getErrorMessage());
                        $sender->sendMessage(TextFormat::RED . "Failed to generate code, please try again later.\nIf this issue persists please contact a server administrator.");
                    });
                }, function(SqlError $error) use ($sender): void{
                    $this->getLogger()->error("Failed to check {$sender->getName()} ({$sender->getUniqueId()->toString()}) into database: " . $error->getErrorMessage());
                    $sender->sendMessage(TextFormat::RED . "Failed to generate code, please try again later.\nIf this issue persists please contact a server administrator.");
                });
            });
        }elseif($command->getName() === "discordunlink"){
            if(!$sender instanceof Player){
                $sender->sendMessage(TextFormat::RED . "This command can only be used in-game as a player.");
                return true;
            }
            $this->database->executeChange("links.delete_uuid", ["uuid" => $sender->getUniqueId()->toString()], function(int $affectedRows) use($sender): void{
                if($affectedRows === 0){
                    $sender->sendMessage(TextFormat::RED . "You are not linked to a discord account, use ".TextFormat::ITALIC . "/discordlink" . TextFormat::RESET . TextFormat::RED . " to link your account.");
                }else{
                    $sender->sendMessage("§aYour discord account has been unlinked.");
                }
            }, function(SqlError $error) use($sender): void{
                $this->getLogger()->error("Failed to unlink {$sender->getName()} ({$sender->getUniqueId()->toString()}) from discord: " . $error->getMessage());
                $sender->sendMessage(TextFormat::RED . "Failed to unlink your discord account, please try again later.\nIf this issue persists please contact a server administrator.");
            });
        }
        return true;
    }

    //---- API ----

    public function getDiscord(): DiscordBot{
        return $this->discord;
    }

    public function getDatabase(): DataConnector{
        return $this->database;
    }

    /**
     * @param string $minecraft_username
     * @param callable $callback function(?string $discord_userid): void{}
     * @return void
     */
    public function getDiscordByName(string $minecraft_username, Callable $callback): void{
        $this->database->executeSelect("links.get_username", ["username" => strtolower($minecraft_username)], function(array $rows) use($callback): void{
            $callback($rows[0]["dcid"]??null);
        }, function(SqlError $error) use($minecraft_username): void{
            $this->getLogger()->error("Failed to get discord id for $minecraft_username: " . $error->getMessage());
        });
    }

    /**
     * @param string $minecraft_uuid UUID in string format
     * @param callable $callback function(?string $discord_userid): void{}
     * @return void
     */
    public function getDiscordByUuid(string $minecraft_uuid, Callable $callback): void{
        $this->database->executeSelect("links.get_uuid", ["uuid" => $minecraft_uuid], function(array $rows) use ($callback): void{
            $callback($rows[0]["dcid"]??null);
        }, function(SqlError $error) use ($minecraft_uuid): void{
            $this->getLogger()->error("Failed to get discord id for $minecraft_uuid: ".$error->getMessage());
        });
    }

    /**
     * @param string $dcid Discord USER_ID
     * @param callable $callback function(?string $minecraft_username, ?string $minecraft_uuid): void{}
     * @return void
     */
    public function getMinecraftByDcid(string $dcid, Callable $callback): void{
        $this->database->executeSelect("links.get_dcid", ["dcid" => $dcid], function(array $rows) use($callback): void{
            $callback($rows[0]["username"]??null, $rows[0]["uuid"]??null);
        }, function(SqlError $error) use($dcid): void{
            $this->getLogger()->error("Failed to get minecraft data for $dcid: " . $error->getMessage());
        });
    }
}
