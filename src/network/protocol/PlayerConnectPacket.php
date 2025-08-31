<?php namespace connect\network\protocol;

use connect\Server;
use connect\util\Color;
use connect\network\server\PendingConnection;

class PlayerConnectPacket extends OneWayPacket{

	const PACKET_ID = self::PLAYER_CONNECT;

	public function verifyHandle() : bool{
		$data = $this->getPacketData();
		return isset($data["player"]);
	}

	public function handle(ConnectPacketHandler $handler) : void{
		//todo: party + friend handling
		$player = $this->getPacketData()["player"];
		$xuid = $this->getPacketData()["xuid"] ?? 0;
		foreach($handler->getGameServer()->getNetwork()->getServers() as $server){
			$pending = $server->getPendingConnection($player);
			if($pending instanceof PendingConnection){
				unset($server->pending[$player]);
				Server::getInstance()->log(Color::GREEN($player) . " had a pending connection on " . Color::YELLOW($server->getIdentifier()) . " that was overwritten by a new connection!", Server::LOG_DEBUG, 1);
			}
		}
		Server::getInstance()->log(Color::GREEN($player) . " has connected to " . Color::YELLOW($handler->getGameServer()->getIdentifier()), Server::LOG_DEBUG, 1);
		if($xuid != 0) $handler->getGameServer()->addPlayer($player, (int) $xuid);
	}

}