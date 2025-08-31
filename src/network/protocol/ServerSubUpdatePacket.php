<?php namespace connect\network\protocol;

use connect\network\server\GameServer;

class ServerSubUpdatePacket extends OneWayPacket{

	const PACKET_ID = self::SERVER_SUB_UPDATE;

	public function verifyHandle() : bool{
		$data = $this->getPacketData();
		return isset($data["server"], $data["type"]);
	}

	public function handle(ConnectPacketHandler $handler) : void{
		$data = $this->getPacketData();
		$server = $data["server"];
		$this->data["server"] = $handler->getGameServer()->getIdentifier();
		if(!is_string($server)){
			$server = (array) $server;
			foreach($server as $serv){
				$gserver = $handler->getGameServer()->getNetwork()->getServer($serv);
				if($gserver instanceof GameServer){
					$gserver->getPacketHandler()->queuePacket($this);
				}
			}
		}else{
			$server = $handler->getGameServer()->getNetwork()->getServer($server);
			if($server instanceof GameServer){
				$server->getPacketHandler()->queuePacket($this);
			}
		}
	}

}