<?php

declare(strict_types=1);

namespace AntiSpam;

use pocketmine\event\Listener;
use pocketmine\event\player\PlayerChatEvent;
use pocketmine\player\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\TextFormat;

class Main extends PluginBase implements Listener{

    /** @var array<string, int> */
    private array $lastMessage = [];

    public function onEnable() : void{
        $this->saveDefaultConfig();

        $this->getServer()->getPluginManager()->registerEvents($this, $this);

        $this->getLogger()->info("AntiSpam enabled!");
    }

    public function onChat(PlayerChatEvent $event) : void{
        $player = $event->getPlayer();

        $message = $event->getMessage();
        $lowerMessage = strtolower($message);

        /*
         * COOLDOWN CHECK
         */
        if(!$player->hasPermission("antispam.bypass")){

            if($this->getConfig()->getNested("cooldown.enabled", true)){

                $cooldown = (int)$this->getConfig()->getNested("cooldown.seconds", 3);

                $name = strtolower($player->getName());

                if(isset($this->lastMessage[$name])){

                    $remaining = ($this->lastMessage[$name] + $cooldown) - time();

                    if($remaining > 0){

                        $player->sendMessage(TextFormat::colorize(
                            str_replace(
                                "{time}",
                                (string)$remaining,
                                $this->getConfig()->getNested(
                                    "messages.spam",
                                    "&cPlease wait {time}s before chatting again."
                                )
                            )
                        ));

                        $event->cancel();
                        return;
                    }
                }

                $this->lastMessage[$name] = time();
            }
        }

        /*
         * CAPS PROTECTION
         */
        if(
            !$player->hasPermission("antispam.caplock") &&
            $this->getConfig()->getNested("caps.enabled", true)
        ){

            $minLength = (int)$this->getConfig()->getNested("caps.min-length", 6);

            if(strlen($message) >= $minLength){

                $letters = preg_replace('/[^a-zA-Z]/', '', $message);

                if(strlen($letters) > 0){

                    preg_match_all('/[A-Z]/', $letters, $matches);

                    $uppercaseCount = count($matches[0]);

                    $percentage = ($uppercaseCount / strlen($letters)) * 100;

                    $maxPercentage = (float)$this->getConfig()->getNested(
                        "caps.percentage",
                        70
                    );

                    if($percentage >= $maxPercentage){

                        $player->sendMessage(TextFormat::colorize(
                            $this->getConfig()->getNested(
                                "messages.caps-spam",
                                "&cPlease do not use excessive capital letters."
                            )
                        ));

                        $this->notifyStaff(
                            "staff-caps",
                            $player,
                            $message
                        );

                        $event->cancel();
                        return;
                    }
                }
            }
        }

        /*
         * BANNED WORDS
         */
        foreach($this->getConfig()->get("banned-words", []) as $word){

            if(str_contains($lowerMessage, strtolower((string)$word))){

                $player->sendMessage(TextFormat::colorize(
                    $this->getConfig()->getNested(
                        "messages.blocked-word",
                        "&cThat message contains a prohibited word."
                    )
                ));

                $this->notifyStaff(
                    "staff-banned",
                    $player,
                    $message
                );

                $event->cancel();
                return;
            }
        }

        /*
         * FILTERED WORDS
         */
        $filteredTriggered = false;

        foreach($this->getConfig()->get("filtered-words", []) as $word){

            $word = (string)$word;

            if(str_contains($lowerMessage, strtolower($word))){
                $filteredTriggered = true;
            }

            $message = preg_replace(
                "/" . preg_quote($word, "/") . "/i",
                str_repeat("*", strlen($word)),
                $message
            );
        }

        if($filteredTriggered){

            $player->sendMessage(TextFormat::colorize(
                $this->getConfig()->getNested(
                    "messages.filtered-word",
                    "&eWatch your language."
                )
            ));

            $this->notifyStaff(
                "staff-filtered",
                $player,
                $event->getMessage()
            );
        }

        $event->setMessage($message);
    }

    private function notifyStaff(
        string $messageKey,
        Player $offender,
        string $message
    ) : void{

        $staffMessage = $this->getConfig()->getNested(
            "messages." . $messageKey,
            "&c[AntiSpam] {player}: {message}"
        );

        $staffMessage = str_replace(
            ["{player}", "{message}"],
            [$offender->getName(), $message],
            $staffMessage
        );

        $staffMessage = TextFormat::colorize($staffMessage);

        foreach($this->getServer()->getOnlinePlayers() as $online){

            if($online->hasPermission("antispam.see")){
                $online->sendMessage($staffMessage);
            }
        }
    }
}
