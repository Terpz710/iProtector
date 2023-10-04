<?php

declare(strict_types = 1);

namespace LDX\iProtector;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\entity\Entity;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerBucketEvent;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\item\FlintSteel;
use pocketmine\math\Vector3;
use pocketmine\player\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\Server;
use pocketmine\utils\TextFormat;
use pocketmine\world\Position;

final class Main extends PluginBase implements Listener{

	/** @var bool[][] */
	private array $worldFlags = [];
	/** @var Area[] */
	public array $areas = [];

	private bool $god = false;
	private bool $edit = false;
	private bool $touch = false;

	/** @var bool[] */
	private array $selectingFirst = [];
	/** @var bool[] */
	private array $selectingSecond = [];

	/** @var Vector3[] */
	private array $firstPosition = [];
	/** @var Vector3[] */
	private array $secondPosition = [];

	public function onEnable() : void{
		$this->getServer()->getPluginManager()->registerEvents($this, $this);
		if(!is_dir($this->getDataFolder())){
			@mkdir($this->getDataFolder());
		}
		if(!file_exists($this->getDataFolder() . "areas.json")){
			file_put_contents($this->getDataFolder() . "areas.json", "[]");
		}
		if(!file_exists($this->getDataFolder() . "config.yml")){
			$c = $this->getResource("config.yml");
			$o = stream_get_contents($c);
			fclose($c);
			file_put_contents($this->getDataFolder() . "config.yml", str_replace("DEFAULT", $this->getServer()->getWorldManager()->getDefaultWorld()->getDisplayName(), $o));
		}
		$data = json_decode(file_get_contents($this->getDataFolder() . "areas.json"), true);
		foreach($data as $datum){
			new Area($datum["name"], $datum["flags"], new Vector3(floatval($datum["pos1"]["0"]), floatval($datum["pos1"]["1"]), floatval($datum["pos1"]["2"])), new Vector3(floatval($datum["pos2"]["0"]), floatval($datum["pos2"]["1"]), floatval($datum["pos2"]["2"])), $datum["level"], $datum["whitelist"], $this);
		}
		$c = yaml_parse_file($this->getDataFolder() . "config.yml");

		$this->god = $c["Default"]["God"];
		$this->edit = $c["Default"]["Edit"];
		$this->touch = $c["Default"]["Touch"];

		foreach($c["Worlds"] as $world => $flags){
			$this->worldFlags[$world] = $flags;
		}
	}

	public function onCommand(CommandSender $sender, Command $command, string $label, array $args) : bool{
		if(!($sender instanceof Player)){
			$sender->sendMessage(TextFormat::RED . "Command must be used in-game.");

			return true;
		}
		if(!isset($args[0])){
			return false;
		}
		$playerName = strtolower($sender->getName());
		$action = strtolower($args[0]);

		if($action === "pos1" && $command->testPermission($sender, "iprotector.command.area.pos1")){
			if(isset($this->selectingFirst[$playerName]) || isset($this->selectingSecond[$playerName])){
				$sender->sendMessage(TextFormat::RED . "You're already selecting a position!");
			}else{
				$this->selectingFirst[$playerName] = true;
				$sender->sendMessage(TextFormat::GREEN . "Please place or break the first position.");
			}
			return true;
		}elseif($action === "pos2" && $command->testPermission($sender, "iprotector.command.area.pos2")){
			if(isset($this->selectingFirst[$playerName]) || isset($this->selectingSecond[$playerName])){
				$sender->sendMessage(TextFormat::RED . "You're already selecting a position!");
			}else{
				$this->selectingSecond[$playerName] = true;
				$sender->sendMessage(TextFormat::GREEN . "Please place or break the second position.");
			}
			return true;
		}elseif($action === "create" && $command->testPermission($sender, "iprotector.command.area.create")){
			if(!isset($args[1])){
				$sender->sendMessage(TextFormat::RED . "Please specify a name for this area.");
				return true;
			}
			if(!isset($this->firstPosition[$playerName], $this->secondPosition[$playerName])){
				$sender->sendMessage(TextFormat::RED . "Please select both positions first.");
				return true;
			}
			if(isset($this->areas[strtolower($args[1])])){
				$sender->sendMessage(TextFormat::RED . "An area with that name already exists.");
				return true;
			}
			new Area(strtolower($args[1]), ["edit" => true, "god" => false, "touch" => true], $this->firstPosition[$playerName], $this->secondPosition[$playerName], $sender->getWorld()->getDisplayName(), [$playerName], $this);
			$this->saveAreas();
			unset($this->firstPosition[$playerName], $this->secondPosition[$playerName]);
			$sender->sendMessage(TextFormat::AQUA . "Area created!");
			return true;
		}elseif($action === "list" && $command->testPermission($sender, "iprotector.command.area.list")){
			$sender->sendMessage(TextFormat::AQUA . "Areas: " . TextFormat::RESET);
			$i = 0;
			$o = "";
			foreach($this->areas as $area){
				if($area->isWhitelisted($playerName)){
					$o .= $area->getName() . " (" . implode(", ", $area->getWhitelist()) . "), ";
					$i++;
				}
			}
			if($i === 0){
				$sender->sendMessage("There are no areas that you can edit");
			}else{
				$sender->sendMessage($o);
			}
			return true;
		}elseif($action === "here" && $command->testPermission($sender, "iprotector.command.area.here")){
			$o = "";
			foreach($this->areas as $area){
				if($area->contains($sender->getPosition(), $sender->getWorld()->getDisplayName()) && $area->getWhitelist() !== null){
					$o .= TextFormat::AQUA . "Area " . $area->getName() . " can be edited by " . implode(", ", $area->getWhitelist());
					break;
				}
			}
			if($o === ""){
				$sender->sendMessage(TextFormat::RED . "You are in an unknown area");
			}else{
				$sender->sendMessage($o);
			}
			return true;
		}elseif($action === "tp" && $command->testPermission($sender, "iprotector.command.area.tp")){
			if(!isset($args[1])){
				$sender->sendMessage(TextFormat::RED . "You must specify an existing Area name");
				return true;
			}
			$area = $this->areas[strtolower($args[1])];
			if($area === null || !$area->isWhitelisted($playerName)){
				$sender->sendMessage(TextFormat::RED . "The Area " . $args[1] . " could not be found ");
				return true;
			}
			$levelName = $area->getWorldName();
			if(Server::getInstance()->getWorldManager()->loadWorld($levelName) !== false){
				$sender->sendMessage(TextFormat::GREEN . "You are teleporting to Area " . $args[1]);
				$sender->teleport(new Position($area->getFirstPosition()->getX(), $area->getFirstPosition()->getY() + 0.5, $area->getFirstPosition()->getZ(), $area->getWorld()));
			}else{
				$sender->sendMessage(TextFormat::RED . "The level " . $levelName . " for Area " . $args[1] . " cannot be found");
			}
			return true;
		}elseif($action === "flag" && $command->testPermission($sender, "iprotector.command.area.flag")){
			if(!isset($args[1])){
				$sender->sendMessage(TextFormat::RED . "Please specify the area you would like to flag.");
				return true;
			}
			if(!isset($this->areas[strtolower($args[1])])){
				$sender->sendMessage(TextFormat::RED . "Area doesn't exist.");
				return true;
			}
			$area = $this->areas[strtolower($args[1])];
			if(!isset($args[2])){
				$sender->sendMessage(TextFormat::RED . "Please specify a flag. (Flags: edit, god, touch)");
				return true;
			}
			if(!isset($area->flags[strtolower($args[2])])){
				$sender->sendMessage(TextFormat::RED . "Flag not found. (Flags: edit, god, touch)");
				return true;
			}
			$flag = strtolower($args[2]);
			if(!isset($args[3])){
				$area->toggleFlag($flag);
			}else{
				$mode = strtolower($args[3]);
				$area->setFlag($flag, ($mode === "true" || $mode === "on"));
			}
			$status = $area->getFlag($flag) ? "on" : "off";
			$sender->sendMessage(TextFormat::GREEN . "Flag " . $flag . " set to " . $status . " for area " . $area->getName() . "!");
			return true;
		}elseif($action === "delete" && $command->testPermission($sender, "iprotector.command.area.delete")){
			if(!isset($args[1])){
				$sender->sendMessage(TextFormat::RED . "Please specify an area to delete.");
				return true;
			}
			if(!isset($this->areas[strtolower($args[1])])){
				$sender->sendMessage(TextFormat::RED . "Area does not exist.");
				return true;
			}
			$area = $this->areas[strtolower($args[1])];
			$area->delete();
			$sender->sendMessage(TextFormat::GREEN . "Area deleted!");
			return true;
		}elseif($action === "whitelist" && $command->testPermission($sender, "iprotector.command.area.delete")){
			if(!isset($args[1], $this->areas[strtolower($args[1])])){
				$sender->sendMessage(TextFormat::RED . "Area doesn't exist. Usage: /area whitelist <area: string> <add|list|remove> [player: target]");
				return true;
			}
			$area = $this->areas[strtolower($args[1])];
			if(!isset($args[2])){
				$sender->sendMessage(TextFormat::RED . "Please specify an action. Usage: /area whitelist " . $area->getName() . " <add|list|remove> [player: target]");
				return true;
			}
			$subaction = strtolower($args[2]);
			if($subaction === "add"){
				$w = ($this->getServer()->getPlayerByPrefix($args[3]) instanceof Player ? strtolower($this->getServer()->getPlayerByPrefix($args[3])->getName()) : strtolower($args[3]));
				if($area->isWhitelisted($w)){
					$sender->sendMessage(TextFormat::RED . "Player $w is already whitelisted in area " . $area->getName() . ".");
					return true;
				}
				$area->setWhitelisted($w);
				$sender->sendMessage(TextFormat::GREEN . "Player $w has been whitelisted in area " . $area->getName() . ".");
			}elseif($subaction === "list"){
				$sender->sendMessage(TextFormat::AQUA . "Area " . $area->getName() . "'s whitelist:" . TextFormat::RESET);
				$o = "";
				foreach($area->getWhitelist() as $w){
					$o .= " $w;";
				}
				$sender->sendMessage($o);
			}elseif($subaction === "delete" || $subaction === "remove"){
				$w = ($this->getServer()->getPlayerByPrefix($args[3]) instanceof Player ? strtolower($this->getServer()->getPlayerByPrefix($args[3])->getName()) : strtolower($args[3]));
				if(!$area->isWhitelisted($w)){
					$sender->sendMessage(TextFormat::RED . "Player $w is already unwhitelisted in area " . $area->getName() . ".");
					return true;
				}
				$area->setWhitelisted($w, false);
				$sender->sendMessage(TextFormat::GREEN . "Player $w has been unwhitelisted in area " . $area->getName() . ".");
			}else{
				$sender->sendMessage(TextFormat::RED . "Please specify a valid action. Usage: /area whitelist " . $area->getName() . " <add|list|remove> [player: target]");
			}
			return true;
		}
		return false;
	}

	public function saveAreas() : void{
		$areas = [];
		foreach($this->areas as $area){
			$areas[] = ["name" => $area->getName(), "flags" => $area->getFlags(), "pos1" => [$area->getFirstPosition()->getFloorX(), $area->getFirstPosition()->getFloorY(), $area->getFirstPosition()->getFloorZ()] , "pos2" => [$area->getSecondPosition()->getFloorX(), $area->getSecondPosition()->getFloorY(), $area->getSecondPosition()->getFloorZ()], "level" => $area->getWorldName(), "whitelist" => $area->getWhitelist()];
		}
		file_put_contents($this->getDataFolder() . "areas.json", json_encode($areas));
	}

	public function canGetHurt(Entity $entity) : bool{
		$o = true;
		$default = (isset($this->worldFlags[$entity->getWorld()->getDisplayName()]) ? $this->worldFlags[$entity->getWorld()->getDisplayName()]["God"] : $this->god);
		if($default){
			$o = false;
		}
		foreach($this->areas as $area){
			if($area->contains(new Vector3($entity->getPosition()->getX(), $entity->getPosition()->getY(), $entity->getPosition()->getZ()), $entity->getWorld()->getDisplayName())){
				if($default && !$area->getFlag("god")){
					$o = true;
					break;
				}
				if($area->getFlag("god")){
					$o = false;
				}
			}
		}

		return $o;
	}

	public function canEdit(Player $player, Position $position) : bool{
		if($player->hasPermission("iprotector.access")){
			return true;
		}
		$o = true;
		$g = (isset($this->worldFlags[$position->getWorld()->getDisplayName()]) ? $this->worldFlags[$position->getWorld()->getDisplayName()]["Edit"] : $this->edit);
		if($g){
			$o = false;
		}
		foreach($this->areas as $area){
			if($area->contains($position, $position->getWorld()->getDisplayName())){
				if($area->getFlag("edit")){
					$o = false;
				}
				if($area->isWhitelisted(strtolower($player->getName()))){
					$o = true;
					break;
				}
				if(!$area->getFlag("edit") && $g){
					$o = true;
					break;
				}
			}
		}

		return $o;
	}

	public function canTouch(Player $player, Position $position) : bool{
		if($player->hasPermission("iprotector.access")){
			return true;
		}
		$o = true;
		$default = (isset($this->worldFlags[$position->getWorld()->getDisplayName()]) ? $this->worldFlags[$position->getWorld()->getDisplayName()]["Touch"] : $this->touch);
		if($default){
			$o = false;
		}
		foreach($this->areas as $area){
			if($area->contains(new Vector3($position->getX(), $position->getY(), $position->getZ()), $position->getWorld()->getDisplayName())){
				if($area->getFlag("touch")){
					$o = false;
				}
				if($area->isWhitelisted(strtolower($player->getName()))){
					$o = true;
					break;
				}
				if(!$area->getFlag("touch") && $default){
					$o = true;
					break;
				}
			}
		}

		return $o;
	}

	public function onBlockTouch(PlayerInteractEvent $event) : void{
		$block = $event->getBlock();
		$player = $event->getPlayer();
		if(!$this->canTouch($player, $block->getPosition())){
			$event->cancel();
			return;
		}
		if($event->getAction() === PlayerInteractEvent::RIGHT_CLICK_BLOCK){
			$item = $event->getItem();
			if($item instanceof FlintSteel){
				$blockEdited = $block->getSide($event->getFace());
				if(!$this->canEdit($player, $blockEdited->getPosition())){
					$event->cancel();
					return;
				}
			}
		}
	}

	public function onBlockPlace(BlockPlaceEvent $event) : void{
		$block = $event->getBlock();
		$player = $event->getPlayer();
		$playerName = strtolower($player->getName());
		if(isset($this->selectingFirst[$playerName])){
			unset($this->selectingFirst[$playerName]);

			$this->firstPosition[$playerName] = $block->getPosition()->asVector3();
			$player->sendMessage(TextFormat::GREEN . "Position 1 set to: (" . $block->getPosition()->getX() . ", " . $block->getPosition()->getY() . ", " . $block->getPosition()->getZ() . ")");
			$event->cancel();
		}elseif(isset($this->selectingSecond[$playerName])){
			unset($this->selectingSecond[$playerName]);

			$this->secondPosition[$playerName] = $block->getPosition()->asVector3();
			$player->sendMessage(TextFormat::GREEN . "Position 2 set to: (" . $block->getPosition()->getX() . ", " . $block->getPosition()->getY() . ", " . $block->getPosition()->getZ() . ")");
			$event->cancel();
		}else{
			if(!$this->canEdit($player, $block->getPosition())){
				$event->cancel();
			}
		}
	}

	/**
	 * @param BlockBreakEvent $event
	 * @handleCancelled false
	 */
	public function onBlockBreak(BlockBreakEvent $event) : void{
		$block = $event->getBlockAgainst();
		$player = $event->getPlayer();
		$playerName = strtolower($player->getName());
		if(isset($this->selectingFirst[$playerName])){
			unset($this->selectingFirst[$playerName]);

			$this->firstPosition[$playerName] = $block->getPosition()->asVector3();
			$player->sendMessage(TextFormat::GREEN . "Position 1 set to: (" . $block->getPosition()->getX() . ", " . $block->getPosition()->getY() . ", " . $block->getPosition()->getZ() . ")");
			$event->cancel();
		}elseif(isset($this->selectingSecond[$playerName])){
			unset($this->selectingSecond[$playerName]);

			$this->secondPosition[$playerName] = $block->getPosition()->asVector3();
			$player->sendMessage(TextFormat::GREEN . "Position 2 set to: (" . $block->getPosition()->getX() . ", " . $block->getPosition()->getY() . ", " . $block->getPosition()->getZ() . ")");
			$event->cancel();
		}elseif(!$this->canEdit($player, $block->getPosition())){
			$event->cancel();
		}
	}

	/**
	 * @param PlayerBucketEvent $event
	 * @handleCancelled false
	 */
	public function onBucket(PlayerBucketEvent $event) : void{
		$block = $event->getBlockClicked();
		$player = $event->getPlayer();
		if($this->canEdit($player, $block->getPosition())){
			return;
		}
		$event->cancel();
	}

	public function onHurt(EntityDamageEvent $event) : void{
		$entity = $event->getEntity();
		if(!$entity instanceof Player){
			return;
		}
		if($this->canGetHurt($entity)){
			return;
		}
		$event->cancel();
	}
}
