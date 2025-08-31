<?php namespace connect\network\protocol;

use connect\Server;
use connect\util\Color;

class ServerAlivePacket extends ConnectPacket{

	const PACKET_ID = self::SERVER_ALIVE;

	public function send(ConnectPacketHandler $handler) : void{
		echo Server::getInstance()->log("Alive ping successfully sent to " . Color::YELLOW($handler->getGameServer()->getIdentifier()) . "! Waiting for response...");
	}

	public function timeout(ConnectPacketHandler $handler) : void{
		Server::getInstance()->log("Server " . Color::YELLOW($handler->getGameServer()->getIdentifier()) . " was not alive... rip");
	}

	public function handleResponse(ConnectPacketHandler $handler) : void{
		Server::getInstance()->log("Server " . Color::YELLOW($handler->getGameServer()->getIdentifier()) . " is alive! Response message: " . Color::CYAN($this->getResponseData()["message"] ?? "none"));
	}

}