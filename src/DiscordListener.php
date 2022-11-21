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
            $this->plugin->getDatabase()->executeSelect("links.get_dcid", ["dcid" => $message->getAuthorId()], function(array $rows) use ($reply, $message, $args, $command){
                if(sizeof($rows) !== 0){
                    $reply->setContent("You are already linked to a Minecraft account `".$rows[0]["username"]." (".$rows[0]["uuid"].")`.\nUse `/mcunlink` to unlink from this Minecraft account.");
                    $this->plugin->getDiscord()->getApi()->sendMessage($reply)->otherwise(function(ApiRejection $rejection){
                        $this->plugin->getLogger()->error($rejection->getMessage());
                    });
                    return;
                }
                if(sizeof($args) !== 1){
                    //Invalid usage.
                    $reply->setContent("Usage: `$command <code>`, where `<code>` is the code you received in-game via `/discordlink`.");
                    //$this->plugin->getDiscord()->getApi()->addReaction($message->getAuthorId(), $message->getId(), "❌"); https://github.com/DiscordBot-PMMP/DiscordBot/issues/73
                    $this->plugin->getDiscord()->getApi()->sendMessage($reply)->otherwise(function(ApiRejection $rejection){
                        $this->plugin->getLogger()->error($rejection->getMessage());
                    });
                }else{
                    //Code provided.
                    $code = $args[0];
                    $this->plugin->getDatabase()->executeSelect("codes.get", ["code" => $code], function(array $rows) use ($code, $message, $reply){
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
                                $this->plugin->getDatabase()->executeChange("codes.delete", ["code" => $code], function() use ($code){
                                    $this->plugin->getLogger()->debug("Deleted expired code: $code");
                                }, function(SqlError $error) use ($code){
                                    $this->plugin->getLogger()->error("Failed to delete expired code: $code");
                                    $this->plugin->getLogger()->error($error->getMessage());
                                });
                            }else{
                                $this->plugin->getDatabase()->executeInsert("links.insert", ["dcid" => $message->getAuthorId(), "uuid" => $rows[0]["uuid"]], function() use ($message, $code, $reply){
                                    $this->plugin->getDatabase()->executeSelect("links.get_dcid", ["dcid" => $message->getAuthorId()], function(array $rows) use ($reply){
                                        $reply->setContent("You have successfully linked to your Minecraft account `".$rows[0]["username"]." (".$rows[0]["uuid"].")`.");
                                        $this->plugin->getDiscord()->getApi()->sendMessage($reply)->otherwise(function(ApiRejection $rejection){
                                            $this->plugin->getLogger()->error($rejection->getMessage());
                                        });
                                        //$this->plugin->getDiscord()->getApi()->addReaction($message->getAuthorId(), $message->getId(), "✅");
                                    }, function(SqlError $error) use($reply){
                                        $this->plugin->getLogger()->error("Failed to get linked account details, but still linked.");
                                        $this->plugin->getLogger()->error($error->getMessage());
                                        $reply->setContent("Your account has been linked. (_Failed to fetch minecraft details_)");
                                        $this->plugin->getDiscord()->getApi()->sendMessage($reply)->otherwise(function(ApiRejection $rejection){
                                            $this->plugin->getLogger()->error($rejection->getMessage());
                                        });
                                    });
                                    $this->plugin->getDatabase()->executeGeneric("codes.delete", ["code" => $code], function(){
                                    }, function(SqlError $error){
                                        $this->plugin->getLogger()->error("Failed to delete used code, " . $error->getMessage());
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
                    }, function(SqlError $error) use ($reply){
                        $reply->setContent("An error occurred while checking your code.");
                        $this->plugin->getDiscord()->getApi()->sendMessage($reply)->otherwise(function(ApiRejection $rejection){
                            $this->plugin->getLogger()->error($rejection->getMessage());
                        });
                        //$this->plugin->getDiscord()->getApi()->addReaction($message->getAuthorId(), $message->getId(), "❌");
                        $this->plugin->getLogger()->error("Failed to check code: ".$error);
                    });
                }
            }, function(SqlError $error) use ($reply){
                $reply->setContent("An error occurred while checking your account.");
                $this->plugin->getDiscord()->getApi()->sendMessage($reply)->otherwise(function(ApiRejection $rejection){
                    $this->plugin->getLogger()->error($rejection->getMessage());
                });
                //$this->plugin->getDiscord()->getApi()->addReaction($message->getAuthorId(), $message->getId(), "❌");
                $this->plugin->getLogger()->error("Failed to check dc account link status: ".$error);
            });
        }elseif(strtolower($command) === $this->plugin->getConfig()->getNested("discord.unlink_command", "/mcunlink")){
            if($message->getServerId() !== null){
                $author = explode(".", $message->getAuthorId()??"")[1];
                $channel = $message->getChannelId();
            }else{
                $author = $message->getAuthorId()??"";
                $channel = $author;
            }
            $reply = new Reply($channel, $message->getId());
            $this->plugin->getDatabase()->executeChange("links.delete_dcid", ["dcid" => $author], function(int $changed) use($reply){
                $reply->setContent($changed === 0 ? "Your account has not been linked to a minecraft account.\nUse `/discordlink` in minecraft to link." : "Your account has been unlinked.");
                $this->plugin->getDiscord()->getApi()->sendMessage($reply)->otherwise(function(ApiRejection $rejection){
                    $this->plugin->getLogger()->error($rejection->getMessage());
                });
            }, function(SqlError $error) use($reply){
                $reply->setContent("An error occurred while unlinking your account.");
                $this->plugin->getDiscord()->getApi()->sendMessage($reply)->otherwise(function(ApiRejection $rejection){
                    $this->plugin->getLogger()->error($rejection->getMessage());
                });
                $this->plugin->getLogger()->error("Failed to unlink account: ".$error);
            });
        }
    }
}