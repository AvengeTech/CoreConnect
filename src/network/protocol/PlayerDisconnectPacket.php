<?php namespace connect\network\protocol;

use connect\Server;
use connect\util\Color;

class PlayerDisconnectPacket extends OneWayPacket{

	const PACKET_ID = self::PLAYER_DISCONNECT;

	public function verifyHandle() : bool{
		$data = $this->getPacketData();
		return isset($data["player"]);
	}

	public function handle(ConnectPacketHandler $handler) : void{
		//todo: party + friend handling
		$player = $this->getPacketData()["player"];
		Server::getInstance()->log(Color::GREEN($player) . " has disconnected from " . Color::YELLOW($handler->getGameServer()->getIdentifier()), Server::LOG_DEBUG, 1);
		$handler->getGameServer()->removePlayer($player);
	}

}