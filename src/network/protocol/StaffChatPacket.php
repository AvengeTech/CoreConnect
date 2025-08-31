<?php namespace connect\network\protocol;

use connect\network\Network;

class StaffChatPacket extends OneWayPacket{

	const PACKET_ID = self::STAFF_CHAT;

	public function verifyHandle() : bool{
		$data = $this->getPacketData();
		return isset($data["sender"], $data["message"]);
	}

	public function handle(ConnectPacketHandler $handler) : void{
		$data = $this->getPacketData();
		Network::getInstance()->staffChat($data["sender"], $handler->getGameServer()->getIdentifier(), $data["message"]);
	}

}