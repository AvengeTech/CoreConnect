<?php namespace connect\network\protocol;

class ServerGetAllPlayersPacket extends ConnectPacket{

	const PACKET_ID = self::SERVER_GET_ALL_PLAYERS;

	public function timeoutReturn(ConnectPacketHandler $handler) : void{
		echo "Unable to return player counts", PHP_EOL;
	}

	public function handle(ConnectPacketHandler $handler) : void{
		$players = [];
		foreach($handler->getGameServer()->getNetwork()->getServers() as $server){
			$players[$server->getIdentifier()] = [];
			foreach($server->getPlayers() as $player){
				$players[$server->getIdentifier()][] = $player->getGamertag() . "-" . $player->getXuid() . ($player->hasNick() ? "-" . $player->getNick() : "");
			}
		}
		$response = [
			"error" => false,
			"message" => "Successfully returned player data of " . $server->getIdentifier(),
			"players" => $players
		];
		$this->setResponseData($response);
	}

	public function verifyResponse() : bool{
		$response = $this->getResponseData();
		return isset($response["players"]);
	}

}