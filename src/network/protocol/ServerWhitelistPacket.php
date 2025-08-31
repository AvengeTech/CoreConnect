<?php namespace connect\network\protocol;

use connect\network\server\GamePlayer;

class ServerWhitelistPacket extends OneWayPacket{

	const PACKET_ID = self::SERVER_WHITELIST;

	public function verifyHandle() : bool{
		$data = $this->getPacketData();
		return isset($data["whitelisted"]) && isset($data["whitelist"]);
	}

	public function handle(ConnectPacketHandler $handler) : void{
		$data = $this->getPacketData();
		$whitelisted = $data["whitelisted"];
		$whitelist = $data["whitelist"];
		$handler->getGameServer()->setWhitelistStatus($whitelisted, $whitelist);
	}

}