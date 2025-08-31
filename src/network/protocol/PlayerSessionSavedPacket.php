<?php namespace connect\network\protocol;

use connect\Server;
use connect\network\Network;
use connect\network\server\GameServer;
use connect\util\Color;

class PlayerSessionSavedPacket extends OneWayPacket{

	const PACKET_ID = self::PLAYER_SESSION_SAVED;

	public function verifyHandle() : bool{
		$data = $this->getPacketData();
		return isset($data["player"], $data["server"]);
	}

	public function handle(ConnectPacketHandler $handler) : void{
		$data = $this->getPacketData();
		$server = Network::getInstance()->getServer($data["server"]);
		if($server instanceof GameServer){
			$server->getPacketHandler()->queuePacket($this);
		}
	}

}