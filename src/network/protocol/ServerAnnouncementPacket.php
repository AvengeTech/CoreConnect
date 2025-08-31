<?php namespace connect\network\protocol;

use connect\network\Network;

class ServerAnnouncementPacket extends OneWayPacket{

	const PACKET_ID = self::SERVER_ANNOUNCEMENT;

	public function verifyHandle() : bool{
		return isset($this->getPacketData()["message"]);
	}

	public function handle(ConnectPacketHandler $handler) : void{
		Network::getInstance()->announce($this->getPacketData()["message"]);
	}

}