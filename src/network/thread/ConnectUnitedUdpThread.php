<?php namespace connect\network\thread;

use connect\Server;
use connect\util\Color;

class ConnectUnitedUdpThread extends \Worker{

	const NAME = "UNITED";
	const SOCKET_ADDRESS = "127.0.0.1";

	public $host_socket;
	public $client_socket;

	public $identifier;

	public $host_port;
	public $client_port;

	public $pendingPackets;
	public $processedPackets;
	public $responsePackets;

	public $receivedPackets;
	public $returningPackets;
	public $returnedPackets;

	public $needReconnect = false;
	public $alive = true;
	public $shutdown = false;

	public function __construct(string $identifier, int $host_port, int $client_port){
		$this->identifier = $identifier;
		$this->host_port = $host_port;
		$this->client_port = $client_port;

		$this->pendingPackets = new \Volatile();
		$this->processedPackets = new \Volatile();
		$this->responsePackets = new \Volatile();

		$this->receivedPackets = new \Volatile();
		$this->returningPackets = new \Volatile();
		$this->returnedPackets = new \Volatile();

		$connected = true;
		if(!($host_socket = @socket_create(AF_INET, SOCK_DGRAM, SOL_UDP))){
			$errorcode = socket_last_error($host_socket);
			$errormsg = socket_strerror($errorcode);
			Server::getInstance()->log("Couldn't create " . Color::YELLOW($this->identifier) . " " . self::NAME . " host UDP socket: [$errorcode] $errormsg", Server::LOG_ERROR);
			$this->needReconnect = true;
			$connected = false;
		}

		if(!($client_socket = @socket_create(AF_INET, SOCK_DGRAM, SOL_UDP))){
			$errorcode = socket_last_error($client_socket);
			$errormsg = socket_strerror($errorcode);
			Server::getInstance()->log("Couldn't create " . Color::YELLOW($this->identifier) . " " . self::NAME . " client UDP socket: [$errorcode] $errormsg", Server::LOG_ERROR);
			$this->needReconnect = true;
			$connected = false;
		}
		if(!@socket_bind($client_socket, self::SOCKET_ADDRESS, $client_port)){
			$errorcode = socket_last_error($host_socket);
			$errormsg = socket_strerror($errorcode);
			Server::getInstance()->log("Couldn't bind to " . Color::YELLOW($this->identifier) . " " . self::NAME . " client UDP socket: [$errorcode] $errormsg", Server::LOG_ERROR);
			$this->needReconnect = true;
			$connected = false;
		}

		if($connected){
			@socket_set_nonblock($host_socket);
			@socket_set_nonblock($client_socket);
			$this->host_socket = $host_socket;
			$this->client_socket = $client_socket;
			Server::getInstance()->log("Successfully created " . Color::YELLOW($this->getIdentifier()) . " " . self::NAME . " UDP sockets!");
		}else{
			Server::getInstance()->log("Failed to create " . Color::YELLOW($this->getIdentifier()) . " " . self::NAME . " UDP sockets, retrying in 3 seconds...");
		}

		$this->start(PTHREADS_INHERIT_INI | PTHREADS_INHERIT_CONSTANTS);
	}

	public function tryReconnect() : void{
		//echo "Attempting socket reconnect...", PHP_EOL;
		if(!($host_socket = @socket_create(AF_INET, SOCK_DGRAM, SOL_UDP))){
			$errorcode = socket_last_error($host_socket);
			$errormsg = socket_strerror($errorcode);
			echo "Couldn't create " . $this->identifier . " " . self::NAME . " host UDP socket while reconnecting: [$errorcode] $errormsg", PHP_EOL;
			return;
		}

		if(!($client_socket = @socket_create(AF_INET, SOCK_DGRAM, SOL_UDP))){
			$errorcode = socket_last_error($client_socket);
			$errormsg = socket_strerror($errorcode);
			echo "Couldn't create " . $this->identifier . " " . self::NAME . " client UDP socket: [$errorcode] $errormsg", PHP_EOL;
			return;
		}
		if(!@socket_bind($client_socket, self::SOCKET_ADDRESS, $this->client_port)){
			$errorcode = socket_last_error($host_socket);
			$errormsg = socket_strerror($errorcode);
			echo "Couldn't bind to " . self::NAME . " client UDP socket: [$errorcode] $errormsg", PHP_EOL;
			$this->needReconnect = true;
			$connected = false;
		}

		@socket_set_nonblock($host_socket);
		@socket_set_nonblock($client_socket);
		$this->host_socket = $host_socket;
		$this->client_socket = $client_socket;

		$this->alive = true;
		$this->needReconnect = false;

		echo "Successfully reestablished " . $this->getIdentifier() . " " . self::NAME . " UDP sockets!", PHP_EOL;
	}

	public function getIdentifier() : string{
		return $this->identifier;
	}

	public function getHostSocket() : false|\resource|\Socket|null{
		return $this->host_socket;
	}

	public function getClientSocket() : false|\resource|\Socket|null{
		return $this->client_socket;
	}

	public function run(){
		while(
			!$this->shutdown &&
			$this->getHostSocket() === null ||
			$this->getClientSocket() === null ||
			$this->needReconnect
		){
			sleep(3);
			$this->tryReconnect();
		}
		$host_socket = $this->getHostSocket();
		$client_socket = $this->getClientSocket();
		while($this->alive && !$this->shutdown){
			$input = @socket_recvfrom($host_socket, $buf, 36900, MSG_DONTWAIT, $remote_ip, $remote_port);
			if($input !== false){
				$data = json_decode($buf, true);
				if($data !== null){
					if(($runtimeId = $data["runtimeId"] ?? -1) !== -1){
						$this->responsePackets[$runtimeId] = $data;
					}
					//var_dump($data);
				}
			}

			$input = @socket_recvfrom($client_socket, $buf, 36900, MSG_DONTWAIT, $remote_ip, $remote_port);
			if($input !== false){
				$data = json_decode($buf, true);
				if($data !== null){
					if(($runtimeId = $data["runtimeId"] ?? -1) !== -1){
						$data["data"]["remote_port"] = $remote_port;
						$this->receivedPackets[$runtimeId] = $data;
					}
				}
			}

			while(count($this->pendingPackets) != 0){
				$command = $this->pendingPackets->shift();
				//var_dump($command);
				if(!@socket_sendto($host_socket, $command, strlen($command), 0, self::SOCKET_ADDRESS, $this->host_port)){
					$errorcode = socket_last_error();
					$errormsg = socket_strerror($errorcode);
					echo "Could not send pending packet data to " . $this->getIdentifier() . " " . self::NAME . " UDP socket: [$errorcode] $errormsg", PHP_EOL;
					$this->alive = false;
					$this->needReconnect = true;
					break;
				}
				$data = json_decode($command, true);
				if(($runtimeId = $data["runtimeId"] ?? -1) !== -1){
					$this->processedPackets[] = $runtimeId;
				}
			}

			while(count($this->returningPackets) != 0){
				$command = $this->returningPackets->shift();
				$data = json_decode($command, true);
				if(isset($data["data"]["remote_port"])){
					$port = $data["data"]["remote_port"];
				}
				if(!@socket_sendto($client_socket, $command, strlen($command), 0, self::SOCKET_ADDRESS, $port ?? 0)){
					$errorcode = socket_last_error();
					$errormsg = socket_strerror($errorcode);
					echo "Could not return packet data to " . $this->getIdentifier() . " " . self::NAME . " UDP socket: [$errorcode] $errormsg", PHP_EOL;
					$this->alive = false;
					$this->needReconnect = true;
					break;
				}
				if(($runtimeId = $data["runtimeId"] ?? -1) !== -1){
					$this->returnedPackets[] = $runtimeId;
				}
			}
			usleep(100000);
		}
		if($host_socket !== null) socket_close($host_socket);
		if($client_socket !== null) socket_close($client_socket);

		while($this->needReconnect && !$this->shutdown){
			sleep(3);
			$this->tryReconnect();
		}

		if($this->alive && !$this->shutdown) $this->run();
	}
}
