<?php namespace connect\network\protocol;

use connect\Server;
use connect\network\server\GameServer;
use connect\network\Network;
use connect\util\Color;

class PlayerTransferCompletePacket extends OneWayPacket{

	const PACKET_ID = self::PLAYER_TRANSFER_COMPLETE;

	public function verifyHandle() : bool{
		$data = $this->getPacketData();
		return isset($data["player"]);
	}

	public function handle(ConnectPacketHandler $handler) : void{
		$data = $this->getPacketData();
		$player = $data["player"];
		$xuid = $data["xuid"] ?? 0;
		$server = $handler->getGameServer();
		$time = $server->completeConnection($player, (int) $xuid);

		Server::getInstance()->log(Color::GREEN($player) . " successfully connected to " . Color::YELLOW($server->getIdentifier()) . " (took " . $time . " second" . ($time > 1 ? "s" : "") . ")", Server::LOG_DEBUG, 1);
	}

}