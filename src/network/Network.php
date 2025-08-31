<?php namespace connect\network;

use connect\network\server\{
	GamePlayer,
	GameServer
};
use connect\network\protocol\{
	ServerAnnouncementPacket,
	StaffChatPacket,
	StaffCommandSeePacket,
	StaffAnticheatPacket
};
use connect\party\PartyManager;
use connect\Server;

class Network{

	/**
	 * @array[Host port, client port]
	 */
	const SOCKET_PORTS = [
		"lobby-1" => [0, 0],
		"lobby-2" => [0, 0],
		"lobby-3" => [0, 0],
		"lobby-test" => [0, 0],

		"prison-1" => [0, 0],
		"prison-1-pvp" => [0, 0],
		"prison-1-plots" => [0, 0],
		"prison-test-cells" => [0, 0],

		"prison-event" => [0, 0],

		"prison-test" => [0, 0],
		"prison-test-pvp" => [0, 0],
		"prison-test-plots" => [0, 0],
		"prison-test-cells" => [0, 0],

		"skyblock-1" => [0, 0],
		"skyblock-1-pvp" => [0, 0],
		"skyblock-1-is1" => [0, 0],
		"skyblock-1-is2" => [0, 0],
		"skyblock-1-is3" => [0, 0],

		"skyblock-1archive" => [0, 0],
		"skyblock-2archive" => [0, 0],

		"skyblock-test" => [0, 0],
		"skyblock-test-pvp" => [0, 0],
		"skyblock-test-is1" => [0, 0],

		"pvp-1" => [0, 0],
		"pvp-test" => [0, 0],

		"build-test" => [0, 0],

		"creative-test" => [0, 0],
		"creative-test-w1" => [0, 0],

		"idle-1" => [0, 0],

		"web" => [0, 0],
	];

	public static $instance = null;

	public $servers = [];

	public function __construct(){
		self::$instance = $this;

		foreach(self::SOCKET_PORTS as $identifier => $socket_ports){
			$this->servers[$identifier] = new GameServer($identifier);
		}
	}

	public static function getInstance() : self{
		return self::$instance;
	}

	public function tick(int $currentTick) : void{
		foreach($this->getServers() as $server){
			$server->tick($currentTick);
		}
	}

	public function close() : void{
		Server::getInstance()->log("Shutting down network threads...");
		foreach($this->getServers() as $server){
			$server->close();
		}
	}

	/** @return GameServer[] */
	public function getServers() : array{
		return $this->servers;
	}

	public function getServer(string $identifier) : ?GameServer{
		return $this->servers[$identifier] ?? null;
	}

	public function getPlayerExact(string $name) : ?GamePlayer{
		foreach($this->getServers() as $server){
			if(($player = $server->getPlayer($name)) instanceof GamePlayer) return $player;
		}
		return null;
	}

	public function getPlayerByXuid(int $xuid): ?GamePlayer {
		foreach ($this->getServers() as $server) {
			if (($player = $server->getPlayerByXuid($xuid)) instanceof GamePlayer) return $player;
		}
		return null;
	}

	public function getPlayerCount() : int{
		$total = 0;
		foreach($this->getServers() as $server){
			$total += count($server->getPlayers());
		}
		return $total;
	}

	public function allPlayersString() : string{
		$string = "";
		foreach($this->getServers() as $server){
			if(!empty($server->getPlayers())){
				$string .= $server->getIdentifier() . ":";
				foreach($server->getPlayers() as $player){
					$string .= $player->getGamertag() . "-" . $player->getXuid() . ",";
				}
				$string = trim($string, ",") . ";";
			}
		}
		return trim($string, ";");
	}

	public function announce(string $message) : void{
		foreach($this->getServers() as $server){
			$server->getPacketHandler()->queuePacket(new ServerAnnouncementPacket([
				"message" => $message
			]));
		}
	}

	public function staffChat(string $sender, string $identifier, string $message) : void{
		foreach($this->getServers() as $server){
			if($server->getIdentifier() != $identifier){
				$server->getPacketHandler()->queuePacket(new StaffChatPacket([
					"sender" => $sender,
					"identifier" => $identifier,
					"message" => $message
				]));
			}
		}
		Server::getInstance()->log("[STAFF CHAT|" . $identifier . "] " . $sender . ": " . $message);
	}

	public function broadcastChat(string $sender, string $identifier, string $message, string $chatformat) : void{
		foreach($this->getServers() as $server){
			if($server->getIdentifier() != $identifier){
				$server->getPacketHandler()->queuePacket(new StaffChatPacket([
					"sender" => $sender,
					"identifier" => $identifier,
					"message" => $message
				]));
			}
		}
		Server::getInstance()->log("[STAFF CHAT|" . $identifier . "] " . $sender . ": " . $message);
	}

	public function commandSee(string $sender, string $identifier, string $command) : void{
		foreach($this->getServers() as $server){
			if($server->getIdentifier() != $identifier){
				$server->getPacketHandler()->queuePacket(new StaffCommandSeePacket([
					"sender" => $sender,
					"identifier" => $identifier,
					"command" => $command
				]));
			}
		}
		Server::getInstance()->log("[CMD SEE|" . $identifier . "] " . $sender . ": " . $command);
	}

	public function anticheatAlert(string $message, string $identifier) : void{
		foreach ($this->getServers() as $server) {
			if ($server->getIdentifier() != $identifier) {
				$server->getPacketHandler()->queuePacket(new StaffAnticheatPacket([
					"message" => $message
				]));
			}
		}
		Server::getInstance()->log("[ANTICHEAT|" . $identifier . "] " . $message);
	}

}