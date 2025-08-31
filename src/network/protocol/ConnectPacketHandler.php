<?php namespace connect\network\protocol;

use connect\Server;
use connect\network\Network;
use connect\network\server\GameServer;
use connect\network\thread\ConnectUnitedUdpThread;
use connect\util\Color;

class ConnectPacketHandler{

	public static $runtimeId = 0;

	public static $registeredPackets = [];

	public $server;
	public $thread;

	public $waitingPackets = [];
	public $returningPackets = [];

	public function __construct(GameServer $server){
		$this->server = $server;
		$this->registerPackets();

		$this->thread = new ConnectUnitedUdpThread(
			$identifier = $server->getIdentifier(),
			Network::SOCKET_PORTS[$identifier][0],
			Network::SOCKET_PORTS[$identifier][1]
		);
	}

	public function registerPackets() : void{
		self::registerPacket(PacketIds::SERVER_ALIVE, ServerAlivePacket::class);
		self::registerPacket(PacketIds::SERVER_GET_PLAYERS, ServerGetPlayersPacket::class);
		self::registerPacket(PacketIds::SERVER_GET_ALL_PLAYERS, ServerGetAllPlayersPacket::class);
		self::registerPacket(PacketIds::SERVER_POST_PLAYERS, ServerPostPlayersPacket::class);
		self::registerPacket(PacketIds::SERVER_GET_STATUS, ServerGetStatusPacket::class);
		self::registerPacket(PacketIds::SERVER_SET_STATUS, ServerSetStatusPacket::class);
		self::registerPacket(PacketIds::SERVER_WHITELIST, ServerWhitelistPacket::class);
		self::registerPacket(PacketIds::SERVER_ANNOUNCEMENT, ServerAnnouncementPacket::class);

		self::registerPacket(PacketIds::SERVER_SUB_UPDATE, ServerSubUpdatePacket::class);

		self::registerPacket(PacketIds::STAFF_CHAT, StaffChatPacket::class);
		self::registerPacket(PacketIds::STAFF_BAN, StaffBanPacket::class);
		self::registerPacket(PacketIds::STAFF_BAN_IP, StaffBanIpPacket::class);
		self::registerPacket(PacketIds::STAFF_BAN_DEVICE, StaffBanDevicePacket::class);
		self::registerPacket(PacketIds::STAFF_MUTE, StaffMutePacket::class);
		self::registerPacket(PacketIds::STAFF_WARN, StaffWarnPacket::class);
		self::registerPacket(PacketIds::STAFF_ANTICHEAT_NOTICE, StaffAnticheatPacket::class);
		self::registerPacket(PacketIds::STAFF_COMMAND_SEE, StaffCommandSeePacket::class);

		self::registerPacket(PacketIds::PLAYER_MESSAGE, PlayerMessagePacket::class);
		self::registerPacket(PacketIds::PLAYER_TRANSFER, PlayerTransferPacket::class);
		self::registerPacket(PacketIds::PLAYER_TRANSFER_COMPLETE, PlayerTransferCompletePacket::class);
		self::registerPacket(PacketIds::PLAYER_CONNECT, PlayerConnectPacket::class);
		self::registerPacket(PacketIds::PLAYER_DISCONNECT, PlayerDisconnectPacket::class);
		self::registerPacket(PacketIds::PLAYER_SUMMON, PlayerSummonPacket::class);
		self::registerPacket(PacketIds::PLAYER_CHAT, PlayerChatPacket::class);
		//self::registerPacket(PacketIds::PLAYER_TELL, PlayerTellPacket::class);
		self::registerPacket(PacketIds::PLAYER_SESSION_SAVED, PlayerSessionSavedPacket::class);
		self::registerPacket(PacketIds::PLAYER_RECONNECT, PlayerReconnectPacket::class);
		self::registerPacket(PacketIds::PLAYER_LOAD_ACTION, PlayerLoadActionPacket::class);
		self::registerPacket(PacketIds::PLAYER_DATA_TRANSFER, PlayerDataTransferPacket::class);

		self::registerPacket(PacketIds::DATA_SYNC, DataSyncPacket::class);
	}

	public static function registerPacket(int $packetId, string $class) : bool{
		if(isset(self::$registeredPackets[$packetId])){
			return false; //Packet already registered with same ID
		}
		$packet = new $class();
		if(!$packet instanceof ConnectPacket){
			return false;
		}
		self::$registeredPackets[$packetId] = $class;
		return true;
	}

	public static function getPacketClass(int $packetId) : ?string{
		if(!isset(self::$registeredPackets[$packetId]))
			return null;
		return self::$registeredPackets[$packetId];
	}

	public function getPacketFromData(array|\Volatile $data) : ?ConnectPacket{
		if(!isset($data["packetId"]))
			return null;
		$class = self::getPacketClass($data["packetId"]);
		if($class == null) return null;
		return new $class($data["data"] ?? [], $data["runtimeId"] ?? null, $data["created"] ?? -1, $data["response"] ?? []);
	}

	public static function newRuntimeId() : int{
		return self::$runtimeId++;
	}

	public function getGameServer() : GameServer{
		return $this->server;
	}

	public function getThread() : ?ConnectUnitedUdpThread{
		return $this->thread;
	}

	public function close() : void{
		if(($thread = $this->getThread()) !== null){
			$thread->needReconnect = false;
			$thread->alive = false;
			$thread->shutdown = true;
		}
	}

	public function tick() : void{
		$thread = $this->getThread();
		foreach($thread->processedPackets as $runtimeId){
			$packet = $this->getWaitingPacket($runtimeId);
			if($packet instanceof ConnectPacket){
				$packet->setSent();
				$packet->send($this);
				$packetName = substr(get_class($packet), strrpos(get_class($packet), '\\') + 1);
				Server::getInstance()->log($packetName . " with runtime ID " . Color::CYAN($runtimeId) . " has been sent to " . Color::YELLOW($this->getGameServer()->getIdentifier()), Server::LOG_DEBUG, 3);
			}
		}
		while(count($thread->processedPackets) > 0) $thread->processedPackets->shift(); //not sure if this will cause race conditions

		foreach($thread->responsePackets as $runtimeId => $data){
			$packet = $this->getWaitingPacket($runtimeId);
			if($packet instanceof ConnectPacket){
				$packet->setResponseData($data["response"] ?? []);
				if($packet->verifyResponse()){
					$packet->handleResponse($this);
					$packetName = substr(get_class($packet), strrpos(get_class($packet), '\\') + 1);
					Server::getInstance()->log("Sent " . $packetName . " with runtime ID " . Color::CYAN($runtimeId) . " has received response from " . Color::YELLOW($this->getGameServer()->getIdentifier()), Server::LOG_DEBUG, 3);
				}else{
					Server::getInstance()->log("Could not verify response given", Server::LOG_DEBUG, 3);
				}
				unset($this->waitingPackets[$runtimeId]);
			}else{
				Server::getInstance()->log("Couldn't send packet with runtime ID " . Color::CYAN($runtimeId) . " to " . Color::YELLOW($this->getGameServer()->getIdentifier()), Server::LOG_DEBUG, 3);
			}
		}
		while(count($thread->responsePackets) > 0) $thread->responsePackets->shift();

		foreach($this->waitingPackets as $runtimeId => $packet){
			if($packet->canTimeout()){
				$packet->timeout($this);
				unset($this->waitingPackets[$runtimeId]);
				$packetName = substr(get_class($packet), strrpos(get_class($packet), '\\') + 1);
				Server::getInstance()->log("Sent " . $packetName . " with runtime ID " . Color::CYAN($runtimeId) . " has timed out", Server::LOG_DEBUG, 3);
			}
		}

		foreach($thread->receivedPackets as $runtimeId => $data){
			$packet = $this->getPacketFromData($data);
			if($packet instanceof ConnectPacket){
				if($packet->verifyHandle()){
					$packet->handle($this);
					if($response = !$packet instanceof OneWayPacket){
						$thread->returningPackets[] = $packet->toJson(true);
						$packet->created = time(); //reset timeout
						$this->returningPackets[$runtimeId] = $packet;	
					}
					$packetName = substr(get_class($packet), strrpos(get_class($packet), '\\') + 1);
					Server::getInstance()->log("Received " . $packetName . " with runtime ID " . Color::CYAN($runtimeId) . " from " . Color::YELLOW($this->getGameServer()->getIdentifier()) . "! " . ($response ? "sending response back..." : ""), Server::LOG_DEBUG, 3);
				}else{
					Server::getInstance()->log("Couldn't verify data for received packet with runtime ID " . Color::CYAN($runtimeId), Server::LOG_DEBUG, 3);
				}
			}else{
				Server::getInstance()->log("Invalid packet ID for received packet with runtime ID " . Color::CYAN($runtimeId), Server::LOG_DEBUG, 3);
			}
		}
		while(count($thread->receivedPackets) > 0) $thread->receivedPackets->shift();

		foreach($thread->returnedPackets as $runtimeId){
			$packet = $this->getReturningPacket($runtimeId);
			if($packet instanceof ConnectPacket){
				$packet->sendReturn($this);
				unset($this->returningPackets[$runtimeId]);
				$packetName = substr(get_class($packet), strrpos(get_class($packet), '\\') + 1);
				Server::getInstance()->log("Returned " . Color::LIGHT_GRAY($packetName) . " with runtime ID " . Color::CYAN($runtimeId) . " to " . Color::YELLOW($this->getGameServer()->getIdentifier()), Server::LOG_DEBUG, 3);
			}else{
				Server::getInstance()->log("Invalid packet runtime ID for returned packet: " . Color::CYAN($runtimeId), Server::LOG_DEBUG, 3);
			}
		}
		while(count($thread->returnedPackets) > 0) $thread->returnedPackets->shift();

		foreach($this->returningPackets as $runtimeId => $packet){
			if($packet->canTimeout()){
				$packet->timeoutReturn($this);
				unset($this->returningPackets[$runtimeId]);
				$packetName = substr(get_class($packet), strrpos(get_class($packet), '\\') + 1);
				Server::getInstance()->log("Received " . Color::LIGHT_GRAY($packetName) . " with runtime ID " . Color::CYAN($runtimeId) . " has timed out returning to " . Color::YELLOW($this->getGameServer()->getIdentifier()), Server::LOG_DEBUG, 3);
			}
		}

	}

	public function getWaitingPackets() : array{
		return $this->waitingPackets;
	}

	public function getWaitingPacket(int $runtimeId) : ?ConnectPacket{
		return $this->waitingPackets[$runtimeId] ?? null;
	}

	public function getReturningPacket(int $runtimeId) : ?ConnectPacket{
		return $this->returningPackets[$runtimeId] ?? null;
	}

	public function queuePacket(ConnectPacket $packet) : bool{
		if($packet->verifySend()){
			if(!$packet->hasResponseData() && !$packet instanceof OneWayPacket) $this->waitingPackets[$packet->getRuntimeId()] = $packet;
			$this->getThread()->pendingPackets[] = $packet->toJson($packet->hasResponseData());
			return true;
		}
		return false;
	}

}