<?php

namespace connect\data\mysql;

class MySqlRequest {

	const TYPE_LOAD = 0;
	const TYPE_SAVE = 1;
	const TYPE_STRAY = 2;

	public $id;

	/** @var MySqlQuery[] */
	public $queries = [];

	public function __construct(string $id, MySqlQuery|array $queries) {
		$this->id = $id;
		$this->queries = is_array($queries) ? $queries : [$queries];
	}

	public function getId(): string {
		return $this->id;
	}

	/** @return MySqlQuery[] */
	public function getQueries(): array {
		return $this->queries;
	}

	public function addQuery(MySqlQuery $query): void {
		$this->queries[$query->getId()] = $query;
	}

	public function getQuery(string $id = "main"): ?MySqlQuery {
		foreach ($this->queries as $query) {
			if ($query->getId() == $id) return $query;
		}
		return null;
	}
}
