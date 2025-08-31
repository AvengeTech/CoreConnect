<?php namespace connect\network\protocol;

use connect\Server;
use connect\network\server\GamePlayer;
use connect\util\Color;

class PlayerSummonPacket extends OneWayPacket{

	const PACKET_ID = self::PLAYER_SUMMON;

	public function verifyHandle() : bool{
		$data = $this->getPacketData();
		return isset($data["player"], $data["sentby"]);
	}

	public function handle(ConnectPacketHandler $handler) : void{
		$data = $this->getPacketData();
		$player = $handler->getGameServer()->getNetwork()->getPlayerExact($data["player"]);
		if($player instanceof GamePlayer){
			$sentby = $data["sentby"];
			$server = $handler->getGameServer()->getNetwork()->getServer($player->getIdentifier());
			$server->getPacketHandler()->queuePacket(new PlayerSummonPacket([
				"player" => $player->getGamertag(),
				"sentby" => $sentby,
				"to" => $identifier = $handler->getGameServer()->getIdentifier()
			]));

			Server::getInstance()->log("Player " . Color::GREEN($player->getGamertag()) . " summoned to " . Color::YELLOW($identifier) . " from " . Color::YELLOW($server->getIdentifier()), Server::LOG_DEBUG, 1);
		}
	}

}