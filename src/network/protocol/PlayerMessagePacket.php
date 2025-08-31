<?php namespace connect\network\protocol;

use connect\Server;
use connect\network\server\GamePlayer;

class PlayerMessagePacket extends ConnectPacket{

	const PACKET_ID = self::PLAYER_MESSAGE;

	public function timeoutReturn(ConnectPacketHandler $handler) : void{
		Server::getInstance()->log("Unable to return player message status");
	}

	public function verifyHandle() : bool{
		$data = $this->getPacketData();
		return isset($data["from"], $data["to"], $data["message"]) && count($this->getResponseData()) == 0;
	}

	public function handle(ConnectPacketHandler $handler) : void{
		$data = $this->getPacketData();
		$to = ($network = $handler->getGameServer()->getNetwork())->getPlayerExact($data["to"]);
		if($to instanceof GamePlayer){
			$this->data["to"] = $to->getGamertag();
			$server = $network->getServer($to->getIdentifier());
			$server->getPacketHandler()->queuePacket($this);

			$response = [
				"error" => false,
				"message" => "Successfully sent message to server player was last seen on!",
			];
		}else{
			$response = [
				"error" => true,
				"message" => "Player not online!",
			];
		}
		$this->setResponseData($response);
	}

}