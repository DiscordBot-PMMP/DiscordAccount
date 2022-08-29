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

use JaxkDev\DiscordBot\Models\Messages\Message;
use JaxkDev\DiscordBot\Plugin\ApiRejection;
use JaxkDev\DiscordBot\Plugin\Events\MessageSent;
use pocketmine\event\Listener;

final class DiscordListener implements Listener{

    private Main $plugin;

    public function __construct(Main $plugin){
        $this->plugin = $plugin;
    }

    public function onDiscordMessage(MessageSent $event): void{
        $message = $event->getMessage();
        $args = explode(" ", $message->getContent());
        $command = array_shift($args);
        if(strtolower($command) === $this->plugin->getConfig()->getNested("discord.command", "/mclink")){
            $reply = new Message($message->getChannelId());
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
                $reply->setContent("<@{$message->getAuthorId()}>, Usage: `$command <code>`");
                //$this->plugin->getDiscord()->getApi()->addReaction($message->getAuthorId(), $message->getId(), "❌"); https://github.com/DiscordBot-PMMP/DiscordBot/issues/73
                $this->plugin->getDiscord()->getApi()->sendMessage($reply)->otherwise(function(ApiRejection $rejection){
                    $this->plugin->getLogger()->error($rejection->getMessage());
                });
            }else{
                //Code provided.
                //TODO, verify code.
            }
        }
    }
}