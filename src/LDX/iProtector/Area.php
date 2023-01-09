<?php

declare(strict_types = 1);

namespace LDX\iProtector;

use pocketmine\math\Vector3;
use pocketmine\world\World;

final class Area{
	private string $name;

	/**
	 * @param string   $name
	 * @param bool[]   $flags
	 * @param Vector3  $pos1
	 * @param Vector3  $pos2
	 * @param string   $worldname
	 * @param string[] $whitelist
	 * @param Main     $plugin
	 */
	public function __construct(string $name, public array $flags, private Vector3 $pos1, private Vector3 $pos2, private string $worldname, private array $whitelist, private Main $plugin){
		$this->name = strtolower($name);
		$this->save();
	}

	public function getName() : string {
		return $this->name;
	}

	public function getFirstPosition() : Vector3{
		return $this->pos1;
	}

	public function getSecondPosition() : Vector3{
		return $this->pos2;
	}

	/**
	 * @return bool[]
	 */
	public function getFlags() : array{
		return $this->flags;
	}

	public function getFlag(string $flag) : bool{
		if(isset($this->flags[$flag])){
			return $this->flags[$flag];
		}

		return false;
	}

	public function setFlag(string $flag, bool $value) : bool{
		if(isset($this->flags[$flag])){
			$this->flags[$flag] = $value;
			$this->plugin->saveAreas();

			return true;
		}

		return false;
	}

	public function contains(Vector3 $pos, string $worldname) : bool{
		return ((min($this->pos1->getX(), $this->pos2->getX()) <= $pos->getX()) && (max($this->pos1->getX(), $this->pos2->getX()) >= $pos->getX()) && (min($this->pos1->getY(), $this->pos2->getY()) <= $pos->getY()) && (max($this->pos1->getY(), $this->pos2->getY()) >= $pos->getY()) && (min($this->pos1->getZ(), $this->pos2->getZ()) <= $pos->getZ()) && (max($this->pos1->getZ(), $this->pos2->getZ()) >= $pos->getZ()) && ($this->worldname === $worldname));
	}

	public function toggleFlag(string $flag) : bool{
		if(isset($this->flags[$flag])){
			$this->flags[$flag] = !$this->flags[$flag];
			$this->plugin->saveAreas();

			return $this->flags[$flag];
		}

		return false;
	}

	public function getWorldName() : string{
		return $this->worldname;
	}

	public function getWorld() : ?World{
		return $this->plugin->getServer()->getWorldManager()->getWorldByName($this->worldname);
	}

	public function isWhitelisted(string $playerName) : bool{
		if(in_array($playerName, $this->whitelist)){
			return true;
		}

		return false;
	}

	public function setWhitelisted(string $name, bool $value = true) : bool{
		if($value){
			if(!in_array($name, $this->whitelist)){
				$this->whitelist[] = $name;
				$this->plugin->saveAreas();

				return true;
			}
		}else{
			if(in_array($name, $this->whitelist)){
				$key = array_search($name, $this->whitelist);
				array_splice($this->whitelist, $key, 1);
				$this->plugin->saveAreas();

				return true;
			}
		}

		return false;
	}

	/**
	 * @return string[]
	 */
	public function getWhitelist() : array{
		return $this->whitelist;
	}

	public function delete() : void{
		unset($this->plugin->areas[$this->getName()]);
		$this->plugin->saveAreas();
	}

	public function save() : void{
		$this->plugin->areas[$this->name] = $this;
	}
}
