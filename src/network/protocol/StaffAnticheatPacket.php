<?php namespace connect\network\protocol;

use connect\network\Network;

class StaffAnticheatPacket extends OneWayPacket{

	const PACKET_ID = self::STAFF_ANTICHEAT_NOTICE;

	public function verifyHandle() : bool{
		$data = $this->getPacketData();
		return isset($data["message"]);
	}

	public function handle(ConnectPacketHandler $handler) : void{
		$data = $this->getPacketData();
		Network::getInstance()->anticheatAlert($data["message"], $handler->getGameServer()->getIdentifier());
	}

}