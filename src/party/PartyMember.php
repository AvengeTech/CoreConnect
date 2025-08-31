<?php namespace connect\party;

use connect\network\server\GamePlayer;
use connect\network\Network;

class PartyMember{

	public $player;

	public function __construct(GamePlayer $player, bool $leader = false){
		$this->player = $player->getGamertag();
	}

	public function getPlayer() : ?GamePlayer{
		return Network::getInstance()->getPlayerExact($this->player);
	}

}