<?php namespace connect\party;

use connect\network\server\GamePlayer;

class Party{

	public $members = [];

	public function __construct(GamePlayer $leader){
		$this->addMember($leader, true);
	}

	public function addMember(GamePlayer $player) : void{
		$this->members[] = new PartyMember($player);
	}

}