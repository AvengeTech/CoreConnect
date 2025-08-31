<?php

namespace connect\network\protocol;

use connect\network\Network;

class PlayerDataTransferPacket extends OneWayPacket {

	const PACKET_ID = self::PLAYER_DATA_TRANSFER;

	public function verifyHandle(): bool {
		$data = $this->getPacketData();
		return isset($data["from"], $data["to"]);
	}

	public function handle(ConnectPacketHandler $handler): void {
		foreach (Network::getInstance()->getServers() as $server) {
			$server->getPacketHandler()->queuePacket($this);
		}
	}
}
