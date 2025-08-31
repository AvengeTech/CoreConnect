<?php namespace connect\network\server;

use connect\Server;
use connect\network\Network;
use connect\network\protocol\{
	ConnectPacketHandler,
	ServerGetStatusPacket,
	ServerSetStatusPacket,
	ServerWhitelistPacket
};
use connect\util\Color;

class GameServer{

	public bool $online = true;
	public string $whitelisted = "";
	public array $whitelist = [];

	public array $players = [];
	public array $pending = [];

	public ConnectPacketHandler $connectPacketHandler;

	public function __construct(public string $identifier){
		$this->connectPacketHandler = new ConnectPacketHandler($this);
	}

	public function tick(int $currentTick) : void{
		if ($currentTick % (0.1 * Server::TPS) == 0) {
			$this->getPacketHandler()->tick();
		}
		if ($currentTick % (1 * Server::TPS) == 0) {
			foreach($this->pending as $gamertag => $connection){
				if($connection->canTimeout()){
					$connection->timeout();
					unset($this->pending[$gamertag]);
				}
			}
		}
		if ($currentTick % (5 * Server::TPS) == 0) {
			$this->getPacketHandler()->queuePacket(new ServerGetStatusPacket());
		}
	}

	public function getNetwork() : Network{
		return Network::getInstance();
	}

	public function getPacketHandler() : ?ConnectPacketHandler{
		return $this->connectPacketHandler;
	}

	public function close() : void{
		$this->getPacketHandler()->close();
	}

	public function getIdentifier() : string{
		return $this->identifier;
	}

	public function isOnline() : bool{
		return $this->online;
	}

	public function setOnline(bool $online = true, bool $send = true) : void{
		$this->online = $online;

		if($send){
			$packet = new ServerSetStatusPacket([
				"identifier" => $this->getIdentifier(),
				"online" => $online
			]);
			foreach($this->getNetwork()->getServers() as $server){
				if($server->getIdentifier() != $this->getIdentifier()){
					$server->getPacketHandler()->queuePacket($packet);
				}
			}
		}
	}

	public function isWhitelisted() : bool{
		return $this->whitelisted != "";
	}

	public function getWhitelist() : array|\Volatile{
		return $this->whitelist;
	}

	public function onWhitelist(int $xuid) : bool{
		return in_array($this->getWhitelist(), $xuid);
	}

	public function setWhitelistStatus(string $whitelisted = "default", array|\Volatile $whitelist = [], bool $send = true) : void{
		$this->whitelisted = $whitelisted;
		$this->whitelist = (array) $whitelist;

		if($send){
			$packet = new ServerWhitelistPacket([
				"identifier" => $this->getIdentifier(),
				"whitelisted" => $whitelisted,
				"whitelist" => $whitelist
			]);
			foreach($this->getNetwork()->getServers() as $server){
				if($server->getIdentifier() != $this->getIdentifier()){
					$server->getPacketHandler()->queuePacket($packet);
				}
			}
		}
	}

	/** @return GamePlayer[] */
	public function getPlayers() : array{
		return $this->players;
	}

	public function getPlayer(string $gamertag) : ?GamePlayer{
		return $this->players[strtolower($gamertag)] ?? null;
	}

	public function getPlayerByXuid(int $xuid) : ?GamePlayer{
		foreach($this->getPlayers() as $player){
			if($player->getXuid() === $xuid){
				return $player;
			}
		}
		return null;
	}

	public function addPlayer(string $gamertag, int $xuid) : void{
		$this->players[strtolower($gamertag)] = new GamePlayer($gamertag, $xuid, $this->getIdentifier());
	}

	public function removePlayer(string $gamertag) : void{
		$player = $this->players[strtolower($gamertag)] ?? null;
		if($player !== null){
			unset($this->players[strtolower($gamertag)]);
		}
	}

	public function getPlayersString() : string{
		$string = "";
		foreach($this->getPlayers() as $player){
			$string .= $player->getGamertag() . ",";
		}
		return $string;
	}

	public function setPlayers(array|\Volatile $players) : void{
		$this->players = [];
		foreach($players as $player){
			$data = explode("-", $player);
			if (!isset($data[1])) $data[1] = 0;
			if (!isset($data[2])) $data[2] = null;
			$this->players[strtolower($data[0])] = new GamePlayer($data[0], (int) $data[1], $this->getIdentifier(), $data[2]);
		}
		Server::getInstance()->log("Player update received from " . Color::YELLOW($this->getIdentifier()) . "! (" . count($this->getPlayers()) . " total)", Server::LOG_DEBUG, 2);
	}

	public function getPendingConnections() : array{
		return $this->pending;
	}

	public function addPendingConnection(string $gamertag) : void{
		$this->pending[$gamertag] = new PendingConnection($this, $gamertag);
	}

	public function getPendingConnection(string $gamertag) : ?PendingConnection{
		return $this->pending[$gamertag] ?? null;
	}

	public function completeConnection(string $gamertag, int $xuid) : int{
		$connection = $this->getPendingConnection($gamertag);
		if($connection instanceof PendingConnection){
			$connection->complete();
			unset($this->pending[$gamertag]);
			$this->addPlayer($gamertag, $xuid);
			return time() - $connection->getCreated();
		}
		return -1;
	}

}