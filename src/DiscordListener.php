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

use JaxkDev\DiscordBot\Models\Messages\Reply;
use JaxkDev\DiscordBot\Plugin\ApiRejection;
use JaxkDev\DiscordBot\Plugin\Events\MessageSent;
use pocketmine\event\Listener;
use poggit\libasynql\SqlError;

final class DiscordListener implements Listener{

    private Main $plugin;

    public function __construct(Main $plugin){
        $this->plugin = $plugin;
    }

    public function onDiscordMessage(MessageSent $event): void{
        $message = $event->getMessage();
        $args = explode(" ", $message->getContent());
        $command = array_shift($args);
        if(strtolower($command) === $this->plugin->getConfig()->getNested("discord.link_command", "/mclink")){
            $reply = new Reply($message->getChannelId(), $message->getId());
            if($message->getServerId() !== null){
                $reply->setContent("The `$command` command can only be run in my DM's.");
                $this->plugin->getDiscord()->getApi()->addReaction($message->getChannelId(), $message->getId()??"Never Null", "❌")->otherwise(function(ApiRejection $rejection){
                    $this->plugin->getLogger()->error($rejection->getMessage());
                });
                $this->plugin->getDiscord()->getApi()->sendMessage($reply)->otherwise(function(ApiRejection $rejection){
                    $this->plugin->getLogger()->error($rejection->getMessage());
                });
                return;
            }
            //DirectMessage
            if($message->getAuthorId() === null){
                $this->plugin->getLogger()->debug("Failed to get author on message send event.");
                return;
            }
            $reply->setChannelId($message->getAuthorId());
            if(sizeof($args) !== 1){
                //Invalid usage.
                $reply->setContent("Usage: `$command <code>`");
                //$this->plugin->getDiscord()->getApi()->addReaction($message->getAuthorId(), $message->getId(), "❌"); https://github.com/DiscordBot-PMMP/DiscordBot/issues/73
                $this->plugin->getDiscord()->getApi()->sendMessage($reply)->otherwise(function(ApiRejection $rejection){
                    $this->plugin->getLogger()->error($rejection->getMessage());
                });
            }else{
                //Code provided.
                $code = $args[0];
                $this->plugin->getDatabase()->executeSelect("codes.get", ["code" => $code], function(array $rows) use($code, $message, $reply){
                    if(sizeof($rows) === 0){
                        $reply->setContent("The code you provided is invalid/expired.");
                        $this->plugin->getDiscord()->getApi()->sendMessage($reply)->otherwise(function(ApiRejection $rejection){
                            $this->plugin->getLogger()->error($rejection->getMessage());
                        });
                        //$this->plugin->getDiscord()->getApi()->addReaction($message->getAuthorId(), $message->getId(), "❌");
                    }else{
                        $expires = $rows[0]["expiry"];
                        if(time() > $expires){
                            $reply->setContent("The code you provided is invalid/expired.");
                            $this->plugin->getDiscord()->getApi()->sendMessage($reply)->otherwise(function(ApiRejection $rejection){
                                $this->plugin->getLogger()->error($rejection->getMessage());
                            });
                            //$this->plugin->getDiscord()->getApi()->addReaction($message->getAuthorId(), $message->getId(), "❌");
                            $this->plugin->getDatabase()->executeChange("codes.delete", ["code" => $code], function() use($code){
                                $this->plugin->getLogger()->debug("Deleted expired code: $code");
                            }, function(SqlError $error) use($code){
                                $this->plugin->getLogger()->error("Failed to delete expired code: $code");
                                $this->plugin->getLogger()->error($error->getMessage());
                            });
                        }else{
                            $this->plugin->getDatabase()->executeInsert("links.insert", ["dcid" => $message->getAuthorId(), "uuid" => $rows[0]["uuid"]], function() use ($rows, $code, $reply){
                                $reply->setContent("Your account has been linked to `USERNAME (".$rows[0]["uuid"].")`.");
                                $this->plugin->getDiscord()->getApi()->sendMessage($reply)->otherwise(function(ApiRejection $rejection){
                                    $this->plugin->getLogger()->error($rejection->getMessage());
                                });
                                //$this->plugin->getDiscord()->getApi()->addReaction($message->getAuthorId(), $message->getId(), "✅");
                                $this->plugin->getDatabase()->executeGeneric("codes.delete", ["code" => $code], function(){
                                }, function(SqlError $error){
                                    $this->plugin->getLogger()->error($error->getMessage());
                                });
                            }, function(SqlError $error) use ($reply){
                                $reply->setContent("An error occurred while linking your account.");
                                $this->plugin->getDiscord()->getApi()->sendMessage($reply)->otherwise(function(ApiRejection $rejection){
                                    $this->plugin->getLogger()->error($rejection->getMessage());
                                });
                                //$this->plugin->getDiscord()->getApi()->addReaction($message->getAuthorId(), $message->getId(), "❌");
                                $this->plugin->getLogger()->error("Failed to link account: ".$error);
                            });
                        }
                    }
                }, function(SqlError $error) use($reply){
                    $reply->setContent("An error occurred while checking your code.");
                    $this->plugin->getDiscord()->getApi()->sendMessage($reply)->otherwise(function(ApiRejection $rejection){
                        $this->plugin->getLogger()->error($rejection->getMessage());
                    });
                    //$this->plugin->getDiscord()->getApi()->addReaction($message->getAuthorId(), $message->getId(), "❌");
                    $this->plugin->getLogger()->error("Failed to check code: ".$error);
                });
            }
        }
    }
}