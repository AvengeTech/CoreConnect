<?php namespace connect\network\protocol;

class ServerSetStatusPacket extends OneWayPacket{

	const PACKET_ID = self::SERVER_SET_STATUS;

	public function verifyHandle() : bool{
		$data = $this->getPacketData();
		return isset($data["online"]);
	}

	public function handle(ConnectPacketHandler $handler) : void{
		$data = $this->getPacketData();
		$online = $data["online"];
		$handler->getGameServer()->setOnline($online);
	}

}