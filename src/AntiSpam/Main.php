<?php

declare(strict_types=1);

namespace AntiSpam;

use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerChatEvent;
use pocketmine\player\Player;
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

        if($player->isOp() || $player->hasPermission("antispam.bypass")){
            return;
        }

        $cooldown = (int)$this->getConfig()->get("cooldown", 3);
        $name = strtolower($player->getName());

        if(isset($this->lastMessage[$name])){
            $remaining = ($this->lastMessage[$name] + $cooldown) - time();

            if($remaining > 0){
                $message = str_replace(
                    "{time}",
                    (string)$remaining,
                    $this->getConfig()->getNested("messages.spam")
                );

                $player->sendMessage(TextFormat::colorize($message));
                $event->cancel();
                return;
            }
        }

        $this->lastMessage[$name] = time();

        $message = $event->getMessage();
        $lower = strtolower($message);

        foreach($this->getConfig()->get("banned-words", []) as $word){
            if(str_contains($lower, strtolower($word))){
                $player->sendMessage(
                    TextFormat::colorize(
                        $this->getConfig()->getNested("messages.blocked-word")
                    )
                );

                $event->cancel();
                return;
            }
        }

        foreach($this->getConfig()->get("filtered-words", []) as $word){
            $pattern = "/" . preg_quote($word, "/") . "/i";

            $message = preg_replace(
                $pattern,
                str_repeat("*", strlen($word)),
                $message
            );
        }

        $event->setMessage($message);
    }
}
