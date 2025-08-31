<?php

namespace connect\network\protocol;

use connect\data\mysql\MySqlQuery;
use connect\data\mysql\MySqlRequest;
use connect\data\mysql\SqlSelectResult;
use connect\network\Network;
use connect\Server;
use core\chat\Data;

class DataSyncPacket extends OneWayPacket {

	const PACKET_ID = self::DATA_SYNC;

	public function verifyHandle(): bool {
		$data = $this->getPacketData();
		return isset($data["identifier"], Network::SOCKET_PORTS[$data["identifier"]], $data["xuid"], $data["schema"], $data["table"], $data["response"], $data["lastUpdate"], $data["data"]);
	}

	public function handle(ConnectPacketHandler $handler): void {
		$pkData = $this->getPacketData();
		$identifier = $pkData["identifier"];
		$xuid = $pkData["xuid"];
		$schema = $pkData["schema"];
		$table = $pkData["table"];
		$isResponse = $pkData["response"];
		$lastUpdate = $pkData["lastUpdate"];
		$existingData = $pkData["data"];

		$manager = Server::getInstance()->getDataManager();
		$cLastUpdate = ($cachedData = $manager->getDataFor($xuid, $table))["lastUpdate"] ?? 0;

		if ($isResponse) {
			if ($cLastUpdate <= $lastUpdate) $manager->cachePlayerData($xuid, $existingData, $table); // attemp to curb overwriting newer data
		} else {
			if (!is_null($cachedData)) {
				if ($cLastUpdate > $lastUpdate) {
					Server::getInstance()->getNetwork()->getServer($identifier)?->getPacketHandler()?->queuePacket(new DataSyncPacket([
						"identifier" => $identifier,
						"xuid" => $xuid,
						"schema" => $schema,
						"table" => $table,
						"response" => true,
						"data" => $cachedData ?? [],
						"lastUpdate" => $cLastUpdate
					]));
				} else { // if the existing data is newer, we send it back to the server & cache it
					$manager->cachePlayerData($xuid, $existingData, $table);
					Server::getInstance()->getNetwork()->getServer($identifier)?->getPacketHandler()?->queuePacket(new DataSyncPacket([
						"identifier" => $identifier,
						"xuid" => $xuid,
						"schema" => $schema,
						"table" => $table,
						"response" => true,
						"data" => $existingData,
						"lastUpdate" => $cLastUpdate
					]));
				}
			} else {
				$manager->queueRequest(new MySqlRequest("select." . $schema . "." . $table . "." . $xuid, [
					new MySqlQuery('null', 'SELECT * FROM `' . $table . '` WHERE `xuid`=?', [$xuid])
				]), function (array $results) use ($identifier, $xuid, $schema, $table, $manager) {
					/** @var SqlSelectResult[] $results */
					$response = [
						"identifier" => $identifier,
						"xuid" => $xuid,
						"schema" => $schema,
						"table" => $table,
						"response" => true,
						"data" => array_map(function (SqlSelectResult $result) {
							return array_shift($result->getRows());
						}, $results),
						"lastUpdate" => microtime(true)
					];
					Server::getInstance()->getNetwork()->getServer($identifier)?->getPacketHandler()?->queuePacket(new DataSyncPacket($response));
					$manager->cachePlayerData($xuid, $response["data"], $response["table"]);
				});
			}
		}
	}
}
