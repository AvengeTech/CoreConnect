<?php namespace connect\network\protocol;

use connect\Server;
use connect\network\Network;
use connect\util\Color;

class PlayerChatPacket extends OneWayPacket{

	const PACKET_ID = self::PLAYER_CHAT;

	public function verifyHandle() : bool{
		$data = $this->getPacketData();
		return isset($data["player"], $data["message"], $data["server"], $data["formatted"]);
	}

	public function handle(ConnectPacketHandler $handler) : void{
		$data = $this->getPacketData();
		if(!isset($data["sendto"])){
			foreach(Network::getInstance()->getServers() as $server){
				if($server->getIdentifier() !== $data["server"]){
					$server->getPacketHandler()->queuePacket($this);
				}
			}
		}else{
			foreach(Network::getInstance()->getServers() as $server){
				if(in_array($server->getIdentifier(), (array) $data["sendto"])){
					$server->getPacketHandler()->queuePacket($this);
				}
			}
		}
	}

}