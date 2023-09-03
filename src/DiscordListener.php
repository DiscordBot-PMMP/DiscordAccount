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

use JaxkDev\DiscordBot\Models\Emoji;
use JaxkDev\DiscordBot\Models\Interactions\Interaction;
use JaxkDev\DiscordBot\Models\Interactions\InteractionType;
use JaxkDev\DiscordBot\Models\Interactions\MessageComponentData;
use JaxkDev\DiscordBot\Models\Interactions\ModalSubmitData;
use JaxkDev\DiscordBot\Models\Messages\Component\ActionRow;
use JaxkDev\DiscordBot\Models\Messages\Component\Button;
use JaxkDev\DiscordBot\Models\Messages\Component\ButtonStyle;
use JaxkDev\DiscordBot\Models\Messages\Component\TextInput;
use JaxkDev\DiscordBot\Models\Messages\Component\TextInputStyle;
use JaxkDev\DiscordBot\Models\Messages\Embed\Embed;
use JaxkDev\DiscordBot\Models\Messages\Embed\Field;
use JaxkDev\DiscordBot\Models\Messages\Embed\Footer;
use JaxkDev\DiscordBot\Models\Messages\MessageType;
use JaxkDev\DiscordBot\Plugin\Api;
use JaxkDev\DiscordBot\Plugin\ApiRejection;
use JaxkDev\DiscordBot\Plugin\Events\DiscordClosed;
use JaxkDev\DiscordBot\Plugin\Events\InteractionReceived;
use JaxkDev\DiscordBot\Plugin\Events\MessageSent;
use pocketmine\event\Listener;
use poggit\libasynql\SqlError;

final class DiscordListener implements Listener{

    private Main $plugin;
    private Api $api;

    public function __construct(Main $plugin){
        $this->plugin = $plugin;
        $this->api = $this->plugin->getDiscord()->getApi();
    }

    //TODO commands once added to DiscordBot

    public function onDiscordClosed(DiscordClosed $event): void{
        $this->plugin->getLogger()->error("Discord closed, disabling plugin.");
        $this->plugin->getServer()->getPluginManager()->disablePlugin($this->plugin);
    }

    public function onDiscordMessage(MessageSent $event): void{
        $message = $event->getMessage();
        if($message->getAuthorId() === null || $message->getContent() === null || $message->getType() !== MessageType::DEFAULT){
            return;
        }
        $args = explode(" ", $message->getContent());
        $command = array_shift($args);
        if(strtolower($command) === $this->plugin->getConfig()->getNested("discord.command", "/mclink")){
            $this->plugin->getDatabase()->executeSelect("links.get_dcid", ["dcid" => $message->getAuthorId()], function(array $rows) use ($message){
                if(sizeof($rows) !== 0){
                    $username = $rows[0]["username"];
                    $uuid = $rows[0]["uuid"];
                    $timestamp = (new \DateTime($rows[0]["created_on"]))->getTimestamp();
                    $this->sendMainMenu($message->getChannelId(), $message->getId(), $username, $uuid, $timestamp);
                }else{
                    $this->sendMainMenu($message->getChannelId(), $message->getId());
                }
            }, function(SqlError $error) use ($message){
                $this->plugin->getLogger()->error("Failed to check dc account link status: " . $error->getErrorMessage());
                $this->sendMainMenu($message->getChannelId(), $message->getId());
            });
        }



        /*
            $reply = Message::class($message->getChannelId(), $message->getId());
            if($message->getAuthorId() === $message->getChannelId()){
                $reply->setContent("The `$command` command can only be run in my DM's.");
                $this->plugin->getDiscord()->getApi()->addReaction($message->get$message->getChannelId(), $message->getId()??"Never Null", "❌")->otherwise(function(ApiRejection $rejection){
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
        }*/
    }

    public function onInteraction(InteractionReceived $event): void{
        $interaction = $event->getInteraction();
        if($interaction->getType() === InteractionType::MESSAGE_COMPONENT){
            /** @var MessageComponentData $data */
            $data = $interaction->getData();
            switch($data->getCustomId()){
                case "discordaccount_link":
                    $this->linkAccountInitial($interaction);
                    break;
                case "discordaccount_unlink":
                    $this->unlinkAccount($interaction);
                    break;
            }
        }elseif($interaction->getType() === InteractionType::MODAL_SUBMIT){
            /** @var ModalSubmitData $data */
            $data = $interaction->getData();
            if($data->getCustomId() === "discordaccount_link_code"){
                $this->linkAccountCode($interaction);
            }
        }
    }

    protected function linkAccountCode(Interaction $interaction): void{
        /** @var TextInput|null $data */
        $data = $interaction->getData()->getComponents()[0] ?? null;
        if((!$data instanceof TextInput) || $data->getCustomId() !== "discordaccount_code" || $data->getValue() === null){
            $this->plugin->getLogger()->error("Invalid link code interaction data.");
            return;
        }
        $code = $data->getValue();
        var_dump($code);
        //TODO Link.
        $this->api->interactionRespondWithMessage($interaction, null, [
            new Embed("✅ Linked Account", null, null, time(), null, new Footer("DiscordAccount v" . $this->plugin->getDescription()->getVersion())),
        ], null, null, null, true)->otherwise(function(ApiRejection $rejection){
            $this->plugin->getLogger()->error("Failed to send link response: " . $rejection->getMessage());
        });
    }

    protected function linkAccountInitial(Interaction $interaction): void{
        //Send popup text input to enter code privately.
        $this->api->interactionRespondWithModal($interaction, "Link Minecraft Account", "discordaccount_link_code", [
            new ActionRow([
                new TextInput("discordaccount_code", TextInputStyle::SHORT, "Code", $this->plugin->getConfig()->getNested("code.size", 4), $this->plugin->getConfig()->getNested("code.size", 16), true, null, "Unique link code from minecraft")
            ])
        ])->otherwise(function(ApiRejection $rejection){
            $this->plugin->getLogger()->error("Failed to send link response: " . $rejection->getMessage());
        });
    }

    protected function unlinkAccount(Interaction $interaction): void{
        //TODO Unlink.
        $this->api->interactionRespondWithMessage($interaction, null, [
            new Embed("✅ Unlinked Account", null, null, time(), null, new Footer("DiscordAccount v" . $this->plugin->getDescription()->getVersion())),
        ], null, null, null, true)->otherwise(function(ApiRejection $rejection){
            $this->plugin->getLogger()->error("Failed to send unlink response: " . $rejection->getMessage());
        });
    }

    protected function sendMainMenu(string $channel_id, string $reference_id, ?string $username = null, ?string $uuid = null, ?int $timestamp = null): void{
        $this->api->sendMessage(null, $channel_id, null, $reference_id, [
            new Embed(
                "Minecraft Account",
                $username === null ? "You are not linked to a Minecraft account." : "Linked Minecraft account details:",
                null,
                time(),
                null,
                new Footer("DiscordAccount v" . $this->plugin->getDescription()->getVersion()),
                null, //new Image("https://vignette.wikia.nocookie.net/minecraft/images/3/3b/MinecraftApp.png"),
                null,
                null,
                null,
                null, //new Author("Minecraft Account", null, "https://vignette.wikia.nocookie.net/minecraft/images/3/3b/MinecraftApp.png"),
                $username === null ? [] : [
                    new Field("Username", $username, true),
                    new Field("UUID", $uuid, true),
                    new Field("Linked On", date("d/m/Y H:i:s", $timestamp), false),
                ]
            )
        ], null, [
            new ActionRow([
                new Button(ButtonStyle::SUCCESS, "Link", Emoji::fromUnicode("🔗"), "discordaccount_link", null, $username !== null),
                new Button(ButtonStyle::DANGER, "Unlink", Emoji::fromUnicode("📵"), "discordaccount_unlink", null, $username === null)
            ])
        ])->otherwise(function(ApiRejection $rejection){
            $this->plugin->getLogger()->error("Failed to send command response: " . $rejection->getMessage());
        });
    }
}