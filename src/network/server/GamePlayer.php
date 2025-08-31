<?php namespace connect\network\server;

class GamePlayer{

	public string $gamertag;
	public int $xuid;
	public string $identifier;
	public ?string $nickname;

	public function __construct(string $gamertag, int $xuid, string $identifier, ?string $nickname = null) {
		$this->gamertag = $gamertag;
		$this->xuid = $xuid;
		$this->identifier = $identifier;
		$this->nickname = $nickname;
	}

	public function getGamertag() : string{
		return $this->gamertag;
	}

	public function getXuid() : int{
		return $this->xuid;
	}

	public function hasNick(): bool {
		return !is_null($this->nickname);
	}

	public function getNick(): ?string {
		return $this->nickname;
	}

	public function getIdentifier() : string{
		return $this->identifier;
	}

}