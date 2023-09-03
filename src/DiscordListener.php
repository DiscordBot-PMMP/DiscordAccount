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
use JaxkDev\DiscordBot\Models\Interactions\ApplicationCommandData;
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
use JaxkDev\DiscordBot\Models\Messages\Message;
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
                $this->sendMainMenu($message->getChannelId(), $message->getId(), $message->getAuthorId(),
                    $rows[0]["username"] ?? null, $rows[0]["uuid"] ?? null, $rows[0]["created_on"] !== null ? (new \DateTime($rows[0]["created_on"]))->getTimestamp() : null);
            }, function(SqlError $error) use ($message){
                $this->plugin->getLogger()->error("Failed to check discord account link status for main menu: " . $error->getErrorMessage());
                $this->sendMainMenu($message->getChannelId(), $message->getId(), $message->getAuthorId());
            });
        }




            /*
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
                }*/
    }

    public function onInteraction(InteractionReceived $event): void{
        $interaction = $event->getInteraction();
        if($interaction->getData() instanceof ApplicationCommandData){
            //todo commands.
            return;
        }
        if($interaction->getData() === null || !str_starts_with(($id = $interaction->getData()->getCustomId()), "discordaccount")){
            //Nothing to do with our plugin.
            return;
        }
        if($interaction->getType() === InteractionType::MESSAGE_COMPONENT){
            if(!str_ends_with($id, $interaction->getUserId() ?? "§")){
                //A different user to one main menu response is to, is interacting with a message we sent.
                //TODO little hack for user-specific menus, not required once commands are registered via DiscordBot (3.1?)
                $this->api->interactionRespondWithMessage($interaction, "Please use YOUR main menu to interact with YOUR account.", null, null, null, null, true)->otherwise(function(ApiRejection $rejection){
                    $this->plugin->getLogger()->error("Failed to send not your menu response: " . $rejection->getMessage());
                });
                return;
            }
            if(str_starts_with($id, "discordaccount_link")){
                $this->linkAccountInitial($interaction);
            }elseif(str_starts_with($id, "discordaccount_unlink")){
                $this->unlinkAccount($interaction);
            }
        }elseif($interaction->getType() === InteractionType::MODAL_SUBMIT){
            if($id === "discordaccount_link_code"){
                $this->linkAccountCode($interaction);
            }
        }
    }

    protected function linkAccountCode(Interaction $interaction): void{
        if(!$interaction->getData() instanceof ModalSubmitData){
            $this->plugin->getLogger()->error("Invalid link code interaction data.");
            return;
        }
        /** @var TextInput|null $data */
        $data = $interaction->getData()->getComponents()[0] ?? null;
        if((!$data instanceof TextInput) || $data->getCustomId() !== "discordaccount_code" || $data->getValue() === null){
            $this->plugin->getLogger()->error("Invalid link code interaction data.");
            return;
        }
        $code = $data->getValue();

        $this->plugin->getDatabase()->executeSelect("codes.get", ["code" => $code], function(array $rows) use ($code, $interaction){
            if(sizeof($rows) === 0){
                $this->api->interactionRespondWithMessage($interaction, null, [
                    new Embed("❌ Failed to Link Account", "The code you provided is invalid/expired.", null, time(), null, new Footer("DiscordAccount v" . $this->plugin->getDescription()->getVersion())),
                ], null, null, null, true)->otherwise(function(ApiRejection $rejection){
                    $this->plugin->getLogger()->error("Failed to send code invalid error response: " . $rejection->getMessage());
                });
            }else{
                $expires = $rows[0]["expiry"];
                if(time() > $expires){
                    $this->api->interactionRespondWithMessage($interaction, null, [
                        new Embed("❌ Failed to Link Account", "The code you provided is invalid/expired.", null, time(), null, new Footer("DiscordAccount v" . $this->plugin->getDescription()->getVersion())),
                    ], null, null, null, true)->otherwise(function(ApiRejection $rejection){
                        $this->plugin->getLogger()->error("Failed to send code expired error response: " . $rejection->getMessage());
                    });
                    $this->plugin->getDatabase()->executeChange("codes.delete", ["code" => $code], function() use ($code){
                        $this->plugin->getLogger()->debug("Deleted expired code: $code");
                    }, function(SqlError $error) use ($code){
                        $this->plugin->getLogger()->error("Failed to delete expired code: $code");
                        $this->plugin->getLogger()->error($error->getMessage());
                    });
                }else{
                    //valid code provided.
                    $this->plugin->getDatabase()->executeInsert("links.insert", ["dcid" => $interaction->getUserId()??"§", "uuid" => $rows[0]["uuid"]], function() use ($code, $interaction){
                        $this->plugin->getDatabase()->executeSelect("links.get_dcid", ["dcid" => $interaction->getUserId()??"§"], function(array $rows) use ($interaction){
                            $this->api->interactionRespondWithMessage($interaction, null, [
                                new Embed("✅ Linked Account", $interaction->getMessage() === null ? "(_Failed to update main menu with details_)" : null, null, time(), null, new Footer("DiscordAccount v" . $this->plugin->getDescription()->getVersion())),
                            ], null, null, null, true)->otherwise(function(ApiRejection $rejection){
                                $this->plugin->getLogger()->error("Failed to send final link success response: " . $rejection->getMessage());
                            });
                            if($interaction->getMessage() !== null && $interaction->getUserId() !== null){
                                $this->updateMainMenu($interaction->getMessage(), $interaction->getUserId(), $rows[0]["username"], $rows[0]["uuid"], (new \DateTime($rows[0]["created_on"]))->getTimestamp());
                            }
                        }, function(SqlError $error) use($interaction){
                            $this->plugin->getLogger()->error("Failed to get linked account details, but still linked.");
                            $this->plugin->getLogger()->error($error->getMessage());
                            $this->api->interactionRespondWithMessage($interaction, null, [
                                new Embed("✅ Linked Account", "(_Failed to update main menu with details_)", null, time(), null, new Footer("DiscordAccount v" . $this->plugin->getDescription()->getVersion())),
                            ], null, null, null, true)->otherwise(function(ApiRejection $rejection){
                                $this->plugin->getLogger()->error("Failed to send final link success-2 response: " . $rejection->getMessage());
                            });
                        });
                        $this->plugin->getDatabase()->executeGeneric("codes.delete", ["code" => $code], null, function(SqlError $error){
                            $this->plugin->getLogger()->error("Failed to delete used code: " . $error->getMessage());
                        });
                    }, function(SqlError $error) use ($interaction){
                        $this->api->interactionRespondWithMessage($interaction, null, [
                            new Embed("❌ Failed to Link Account", "An error occurred while linking your account.", null, time(), null, new Footer("DiscordAccount v" . $this->plugin->getDescription()->getVersion())),
                        ], null, null, null, true)->otherwise(function(ApiRejection $rejection){
                            $this->plugin->getLogger()->error("Failed to link account: " . $rejection->getMessage());
                        });
                        $this->plugin->getLogger()->error("Failed to link account: " . $error);
                    });
                }
            }
        }, function(SqlError $error) use ($interaction){
            $this->api->interactionRespondWithMessage($interaction, null, [
                new Embed("❌ Failed to Link Account", "An error occurred while checking your account.", null, time(), null, new Footer("DiscordAccount v" . $this->plugin->getDescription()->getVersion())),
            ], null, null, null, true)->otherwise(function(ApiRejection $rejection){
                $this->plugin->getLogger()->error("Failed to send link error response: " . $rejection->getMessage());
            });
            $this->plugin->getLogger()->error("Failed to get/check code: ".$error);
        });
    }

    protected function linkAccountInitial(Interaction $interaction): void{
        $this->plugin->getDatabase()->executeSelect("links.get_dcid", ["dcid" => $interaction->getUserId() ?? ""], function(array $rows) use ($interaction){
            if(sizeof($rows) !== 0){
                $this->api->interactionRespondWithMessage($interaction, "You are already linked to a Minecraft account `".$rows[0]["username"]." (".$rows[0]["uuid"].")`.\nUse the unlink button to remove this Minecraft account.")->otherwise(function(ApiRejection $rejection){
                    $this->plugin->getLogger()->error("Failed to send already linked response: " . $rejection->getMessage());
                });
                if($interaction->getMessage() !== null && $interaction->getUserId() !== null){
                    $this->updateMainMenu($interaction->getMessage(), $interaction->getUserId(), $rows[0]["username"], $rows[0]["uuid"], (new \DateTime($rows[0]["created_on"]))->getTimestamp());
                }
            }else{
                /** @var int $size */
                $size = $this->plugin->getConfig()->getNested("code.size", 4);
                //Send popup text input to enter code privately.
                $this->api->interactionRespondWithModal($interaction, "Link Minecraft Account", "discordaccount_link_code", [
                    new ActionRow([
                        new TextInput("discordaccount_code", TextInputStyle::SHORT, "Code", $size, $size, true, null, "Unique link code from minecraft")
                    ])
                ])->otherwise(function(ApiRejection $rejection){
                    $this->plugin->getLogger()->error("Failed to send initial link response: " . $rejection->getMessage());
                });
            }
        }, function(SqlError $error) use ($interaction){
            $this->plugin->getLogger()->error("Failed to check dc account link status: " . $error->getErrorMessage());
            $this->api->interactionRespondWithMessage($interaction, null, [
                new Embed("❌ Failed to Link Account", "An error occurred while checking your account.", null, time(), null, new Footer("DiscordAccount v" . $this->plugin->getDescription()->getVersion())),
            ], null, null, null, true)->otherwise(function(ApiRejection $rejection){
                $this->plugin->getLogger()->error("Failed to send link response: " . $rejection->getMessage());
            });
        });
    }

    protected function unlinkAccount(Interaction $interaction): void{
        $this->plugin->getDatabase()->executeChange("links.delete_dcid", ["dcid" => $interaction->getUserId() ?? ""], function(int $changed) use($interaction){
            $this->api->interactionRespondWithMessage($interaction, null, [
                new Embed($changed >= 1 ? "✅ Unlinked Account" : "❌ No account to unlink.", null, null, time(), null, new Footer("DiscordAccount v".$this->plugin->getDescription()->getVersion())),
            ], null, null, null, true)->otherwise(function(ApiRejection $rejection){
                $this->plugin->getLogger()->error($rejection->getMessage());
            });
            if($interaction->getMessage() !== null && $interaction->getUserId() !== null){
                $this->updateMainMenu($interaction->getMessage(), $interaction->getUserId());
            }
        }, function(SqlError $error) use($interaction){
            $this->api->interactionRespondWithMessage($interaction, null, [
                new Embed("❌ Failed to Unlink Account", "An error occurred while unlinking your account.", null, time(), null, new Footer("DiscordAccount v" . $this->plugin->getDescription()->getVersion())),
            ], null, null, null, true)->otherwise(function(ApiRejection $rejection){
                $this->plugin->getLogger()->error("Failed to send link response: " . $rejection->getMessage());
            });
            $this->plugin->getLogger()->error("Failed to unlink account: " . $error->getErrorMessage());
        });
    }

    protected function updateMainMenu(Message $message, string $author_id, ?string $username = null, ?string $uuid = null, ?int $timestamp = null): void{
        $message->setComponents([
            new ActionRow([
                new Button(ButtonStyle::SUCCESS, "Link", Emoji::fromUnicode("🔗"), "discordaccount_link_" . $author_id, null, $username !== null),
                new Button(ButtonStyle::DANGER, "Unlink", Emoji::fromUnicode("📵"), "discordaccount_unlink_" . $author_id, null, $username === null)
            ])
        ]);
        $message->setEmbeds([
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
                ($username === null || $uuid === null) ? [] : [
                    new Field("Username", $username, true),
                    new Field("UUID", $uuid, true),
                    new Field("Linked On", date("d/m/Y H:i:s", $timestamp), false),
                ]
            )
        ]);
        $this->api->editMessage($message)->otherwise(function(ApiRejection $rejection){
            $this->plugin->getLogger()->error("Failed to update main menu: " . $rejection->getMessage());
        });
    }

    protected function sendMainMenu(string $channel_id, string $reference_id, string $user_id, ?string $username = null, ?string $uuid = null, ?int $timestamp = null): void{
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
                ($username === null || $uuid === null) ? [] : [
                    new Field("Username", $username, true),
                    new Field("UUID", $uuid, true),
                    new Field("Linked On", date("d/m/Y H:i:s", $timestamp), false),
                ]
            )
        ], null, [
            new ActionRow([
                new Button(ButtonStyle::SUCCESS, "Link", Emoji::fromUnicode("🔗"), "discordaccount_link_" . $user_id, null, $username !== null),
                new Button(ButtonStyle::DANGER, "Unlink", Emoji::fromUnicode("📵"), "discordaccount_unlink_" . $user_id, null, $username === null)
            ])
        ])->otherwise(function(ApiRejection $rejection){
            $this->plugin->getLogger()->error("Failed to send command response: " . $rejection->getMessage());
        });
    }
}