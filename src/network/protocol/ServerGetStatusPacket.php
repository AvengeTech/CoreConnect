<?php namespace connect\network\protocol;

use connect\Server;
use connect\util\Color;

class ServerGetStatusPacket extends ConnectPacket{

	const PACKET_ID = self::SERVER_GET_STATUS;

	public function timeout(ConnectPacketHandler $handler) : void{
		$handler->getGameServer()->setOnline(false);
		Server::getInstance()->log("Status update sent to " . Color::YELLOW($handler->getGameServer()->getIdentifier()) . " has timed out.", Server::LOG_DEBUG, 2);
	}

	public function handle(ConnectPacketHandler $handler) : void{
		$data = $this->getPacketData();
		$identifier = $data["identifier"];
		$statuses = [];
		if(is_array($identifier)){
			foreach($identifier as $id){
				$server = $handler->getGameServer()->getNetwork()->getServer($id);
				if($server instanceof GameServer){
					$statuses[$id] = $server->isOnline();
				}
			}
		}else{
			$server = $handler->getGameServer()->getNetwork()->getServer($identifier);
			if($server instanceof GameServer){
				$statuses[$identifier] = $server->isOnline();
			}
		}
		$this->setResponseData([
			"error" => false,
			"message" => "Server statuses returned!",
			"statuses" => $statuses
		]);
	}

	public function verifyResponse() : bool{
		$response = $this->getResponseData();
		return isset($response["online"]);
	}

	public function handleResponse(ConnectPacketHandler $handler) : void{
		$response = $this->getResponseData();
		$handler->getGameServer()->setOnline($response["online"] ?? false);
		Server::getInstance()->log("Status update received from " . Color::YELLOW($handler->getGameServer()->getIdentifier()) . ": " . Color::GREEN("ONLINE"), Server::LOG_DEBUG, 2);
	}

}