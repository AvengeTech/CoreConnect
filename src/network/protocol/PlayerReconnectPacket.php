<?php namespace connect\network\protocol;

use connect\Server;
use connect\util\Color;
use connect\network\server\PendingConnection;

class PlayerReconnectPacket extends ConnectPacket{

	const PACKET_ID = self::PLAYER_RECONNECT;

	public function verifyHandle() : bool{
		$data = $this->getPacketData();
		return isset($data["player"], $data["rfrom"], $data["server"]);
	}

	public function handle(ConnectPacketHandler $handler) : void{
		$data = $this->getPacketData();
		$server = $handler->getGameServer()->getNetwork()->getServer($data["rfrom"]);
		if($server !== null){
			$server->getPacketHandler()->queuePacket($this);
		}
	}

}