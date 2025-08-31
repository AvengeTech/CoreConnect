<?php namespace connect\network\protocol;

use connect\network\Network;

class StaffCommandSeePacket extends OneWayPacket{

	const PACKET_ID = self::STAFF_COMMAND_SEE;

	public function verifyHandle() : bool{
		$data = $this->getPacketData();
		return isset($data["sender"], $data["command"]);
	}

	public function handle(ConnectPacketHandler $handler) : void{
		$data = $this->getPacketData();
		Network::getInstance()->commandSee($data["sender"], $handler->getGameServer()->getIdentifier(), $data["command"]);
	}

}