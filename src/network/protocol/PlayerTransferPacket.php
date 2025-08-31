<?php namespace connect\network\protocol;

use connect\Server;
use connect\network\server\GameServer;
use connect\network\Network;
use connect\util\Color;

class PlayerTransferPacket extends OneWayPacket{

	const PACKET_ID = self::PLAYER_TRANSFER;

	public function verifyHandle() : bool{
		$data = $this->getPacketData();
		return isset($data["player"], $data["to"], $data["message"]);
	}

	public function handle(ConnectPacketHandler $handler) : void{
		$data = $this->getPacketData();
		$player = $data["player"];
		$to = $data["to"];
		$message = $data["message"];
		$server = $handler->getGameServer()->getNetwork()->getServer($to);
		if($server instanceof GameServer){
			$server->getPacketHandler()->queuePacket(new PlayerTransferPacket([
				"player" => $player,
				"from" => $handler->getGameServer()->getIdentifier(),
				"message" => $message
			]));
			$server->addPendingConnection($player);

			Server::getInstance()->log("Pending connection on " . Color::YELLOW($server->getIdentifier()) . " created for " . Color::GREEN($player), Server::LOG_DEBUG, 1);
		}
		$handler->getGameServer()->removePlayer($player);
	}

}