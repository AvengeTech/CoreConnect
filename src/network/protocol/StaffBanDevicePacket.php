<?php namespace connect\network\protocol;

class StaffBanDevicePacket extends OneWayPacket{

	const PACKET_ID = self::STAFF_BAN_DEVICE;

	public function verifyHandle() : bool{
		$data = $this->getPacketData();
		return isset($data["did"], $data["by"], $data["length"], $data["reason"]);
	}

	public function handle(ConnectPacketHandler $handler) : void{
		$identifier = $this->data["identifier"] = ($server = $handler->getGameServer())->getIdentifier();
		foreach($handler->getGameServer()->getNetwork()->getServers() as $server){
			if($server->getIdentifier() != $identifier){
				$server->getPacketHandler()->queuePacket($this);
			}
		}
	}

}