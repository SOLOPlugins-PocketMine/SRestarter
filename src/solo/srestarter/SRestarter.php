<?php

namespace solo\srestarter;

use pocketmine\plugin\PluginBase;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\scheduler\PluginTask;
use pocketmine\utils\Config;
use pocketmine\utils\Utils;

class SRestarter extends PluginBase{

	public function onEnable(){
		@mkdir($this->getDataFolder());
		$this->saveResource("setting.yml");

		$this->config = new Config($this->getDataFolder() . "setting.yml", Config::YAML);

		$this->getServer()->getScheduler()->scheduleRepeatingTask(new class($this) extends PluginTask{
			public function onRun($currentTick){
				$this->owner->check();
			}
		}, 60);
	}

	public function check(){
		$rebootElappsedMinute = $this->config->getNested("reboot-schedule.server-elappsed-minute", PHP_INT_MAX);
		$serverElappsedMinute = (microtime(true) - \pocketmine\START_TIME) / 60;
		if($serverElappsedMinute > $rebootElappsedMinute){
			$this->restart();
			return true;
		}

		$rebootDateTimes = $this->config->getNested("reboot-schedule.date-time", []);
		foreach(is_array($rebootDateTimes) ? $rebootDateTimes : [$rebootDateTimes] as $rebootDateTime){
			if(abs(time() - strtotime($rebootDateTime)) < 20){
				$this->restart();
				return true;
			}
		}

		$rebootMemory = $this->config->getNested("reboot-schedule.over-memory", PHP_INT_MAX);
		$currentMemory = (Utils::getMemoryUsage(true)[1] / 1024) / 1024;
		if($rebootMemory < $currentMemory){
			$this->restart();
			return true;
		}
		return false;
	}

	public function restart() : bool{
		static $triggered = false;
		if($triggered){
			return false;
		}

		$preBroadcast = $this->config->getNested("reboot-message.pre-broadcast", "");
		$preBroadcast = is_array($preBroadcast) ? explode("\n", $preBroadcast) : $preBroadcast;

		$this->getServer()->broadcastMessage($preBroadcast);

		$this->getServer()->getScheduler()->scheduleDelayedTask(new class($this) extends PluginTask{
			public function onRun(int $currentTick){
				$kickMessage = $this->owner->getConfig()->getNested("reboot-message.kick-message", "");
				$kickMessage = is_array($kickMessage) ? explode("\n", $kickMessage) : $kickMessage;

				foreach($this->owner->getServer()->getOnlinePlayers() as $player){
					$player->save();
					if(!empty($kickMessage)){
						$player->kick($kickMessage, false);
					}
				}
				foreach($this->owner->getServer()->getLevels() as $level){
					$level->save(true);
				}
				$server->shutdown();
			}
		}, 200);
		return true;
	}

	public function onCommand(CommandSender $sender, Command $command, string $label, array $args) : bool{
		$this->restart();
		return true;
	}
}
