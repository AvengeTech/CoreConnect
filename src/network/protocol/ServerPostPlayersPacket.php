<?php namespace connect\network\protocol;

class ServerPostPlayersPacket extends ConnectPacket{

	const PACKET_ID = self::SERVER_POST_PLAYERS;

	public function send(ConnectPacketHandler $handler) : void{
		echo "Server alive ping successfully sent to " . $handler->getGameServer()->getIdentifier(), PHP_EOL;
	}

	public function handle(ConnectPacketHandler $handler) : void{
		$data = $this->getPacketData();
		$server = $handler->getGameServer();
		$server->setPlayers($data["players"] ?? []);
		$response = [
			"error" => false,
			"message" => "Updated player data for " . $server->getIdentifier() . " (" . count($data["players"] ?? []) . " players)"
		];
		$this->setResponseData($response);
		//echo "Updated player data for " . $server->getIdentifier() . " (" . count($data["players"] ?? []) . " players)", PHP_EOL;
	}

	public function verifyResponse() : bool{
		$response = $this->getResponseData();
		return isset($response["players"]);
	}

}