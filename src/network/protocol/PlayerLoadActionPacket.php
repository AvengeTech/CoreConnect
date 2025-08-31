<?php namespace connect\network\protocol;

use connect\Server;
use connect\network\Network;
use connect\network\server\GameServer;
use connect\util\Color;

class PlayerLoadActionPacket extends OneWayPacket{

	const PACKET_ID = self::PLAYER_LOAD_ACTION;

	public function verifyHandle() : bool{
		$data = $this->getPacketData();
		return isset($data["player"], $data["server"], $data["action"]);
	}

	public function handle(ConnectPacketHandler $handler) : void{
		$data = $this->getPacketData();
		$server = $handler->getGameServer()->getNetwork()->getServer($data["server"]);
		if($server instanceof GameServer){
			$server->getPacketHandler()->queuePacket($this);
		}
	}

}