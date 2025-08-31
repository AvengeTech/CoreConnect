<?php namespace connect\network\server;

use connect\Server;
use connect\util\Color;

class PendingConnection{

	const TIMEOUT = 90;

	public int $created;

	public function __construct(
		public GameServer $server,
		public string $gamertag
	){
		$this->created = time();
	}

	public function getGameServer() : GameServer{
		return $this->server;
	}

	public function getGamertag() : string{
		return $this->gamertag;
	}

	public function getCreated() : int{
		return $this->created;
	}

	public function canTimeout() : bool{
		return time() - $this->getCreated() > self::TIMEOUT;
	}

	public function timeout() : void{
		Server::getInstance()->log(Color::GREEN($this->getGamertag()) . "'s pending connection to " . Color::YELLOW($this->getGameServer()->getIdentifier()) . " timed out after " . Color::CYAN((time() - $this->getCreated()) . " seconds"), Server::LOG_DEBUG, 1);

		//todo: party support?
	}

	public function complete() : void{
		//todo: party support?
	}

}