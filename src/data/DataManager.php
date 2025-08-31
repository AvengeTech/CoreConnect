<?php

namespace connect\data;

use Closure;
use conenct\data\mysql\MysqlFlags;
use connect\data\mysql\MysqlColumnInfo;
use connect\Server;
use connect\data\mysql\MySqlRequest;
use connect\data\mysql\MysqlTypes;
use connect\data\mysql\SqlChangeResult;
use connect\data\mysql\SqlColumnInfo;
use connect\data\mysql\SqlError;
use connect\data\mysql\SqlInsertResult;
use connect\data\mysql\SqlResult;
use connect\data\mysql\SqlSelectResult;
use connect\network\server\GamePlayer;
use InvalidArgumentException;
use mysqli;
use mysqli_result;
use mysqli_sql_exception;

class DataManager {
	public const MODE_GENERIC = 0;
	public const MODE_CHANGE = 1;
	public const MODE_INSERT = 2;
	public const MODE_SELECT = 3;

	const CRED_PATH = "/[REDACTED]";
	const SCHEMAS = ["core", "skyblock_1", "prison_1", "skyblock_test", "prison_test"];

	/** @var MySqlRequest[] */
	public array $mysqlQueue = [];
	/** @var Closure[] */
	public array $mysqlReturns = [];

	/** @var mysqli[] */
	public array $databases = [];

	public array $dataCache = [];
	public array $lastUpdate = [];

	public function __construct(public Server $server) {
		$creds = array_merge(file(self::CRED_PATH));
		foreach (self::SCHEMAS as $schema) {
			$c_creds = [...$creds];
			$c_creds = array_merge($c_creds, [$schema]);
			$c_creds = array_map('trim', $c_creds);
			$db = new mysqli(...$c_creds);
			if ($db->connect_error) {
				throw new \Exception("MySQL connection failed: " . $db->connect_error);
			}
			$db->query("SET SESSION wait_timeout=2147483");
			$this->databases[$schema] = $db;
		}
	}

	public function tick(): void {
		foreach ($this->mysqlQueue as $id => $request) {
			[$which, $schema, $table, $xuid] = explode(".", $id);
			if (!isset($this->databases[$schema])) {
				$this->server->log("MySQL database for schema '$schema' is not connected.", Server::LOG_ERROR);
				continue;
			}
			$db = $this->databases[$schema];
			$results = [];
			foreach ($request->getQueries() as $query) {
				$result = $this->executeQuery($db, match ($which) {
					"select" => self::MODE_SELECT,
					"insert" => self::MODE_INSERT,
					"update" => self::MODE_CHANGE,
					default => self::MODE_GENERIC
				}, $query->getQuery(), $query->getParameters());
				$results[] = $result;
				$query->setExecuted();
			}
			$this->mysqlReturns[$id]($results);
			unset($this->mysqlQueue[$id], $this->mysqlReturns[$id]);
		}
		foreach (array_keys($this->lastUpdate) as $xuid) {
			if (microtime(true) - $this->lastUpdate[$xuid] > 60 * 10) { // clear cache if not updated for 10 minutes (cache will be updated by GameServer regularly while player is online)
				unset($this->dataCache[$xuid], $this->lastUpdate[$xuid]); // need to clear unused data to prevent memory leaks & overuse of memory
			}
		}
	}

	public function getDataFor(GamePlayer|int $player, string $table): array {
		if ($player instanceof GamePlayer) $player = $player->getXuid();
		$this->lastUpdate[$player] ??= 0;
		return ($this->dataCache[$player] ??= [])[$table] ?? [];
	}

	public function cachePlayerData(GamePlayer|int|null $player, array $data, string $table): void {
		if ($player instanceof GamePlayer) $player = $player->getXuid();
		if (is_null($player)) {
			$this->server->log("Cannot cache data for null player.", Server::LOG_ERROR);
			return;
		}
		$currentData = $this->getDataFor($player, $table);
		$currentData = array_merge($data, ["lastUpdate" => microtime(true)]);
		$this->dataCache[$player][$table] = $currentData;
		$this->lastUpdate[$player] = microtime(true);
	}

	public function queueRequest(MySqlRequest $request, Closure $onReturn): void {
		$id = $request->getId();
		if (isset($this->mysqlQueue[$id])) {
			throw new InvalidArgumentException("MySQL request with ID '$id' is already queued.");
		}
		$this->mysqlQueue[$id] = $request;
		$this->mysqlReturns[$id] = $onReturn;
	}

	private static function queryWithErrors(Closure $handle, ?Closure $onError = null) {
		mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

		try {
			$result = $handle();
			return $result;
		} catch (mysqli_sql_exception $err) {
			if ($onError !== null) $onError($err);
		} finally {
			mysqli_report(MYSQLI_REPORT_OFF);
		}
	}

	protected function executeQuery($mysqli, int $mode, string $query, array $params): SqlResult {
		mysqli_report(MYSQLI_REPORT_OFF);

		if (count($params) === 0) {
			$result = self::queryWithErrors(
				fn() => $mysqli->query($query),
				fn() => throw new SqlError(SqlError::STAGE_EXECUTE, $mysqli->error, $query, [])
			);

			switch ($mode) {
				case self::MODE_GENERIC:
				case self::MODE_CHANGE:
				case self::MODE_INSERT:
					if ($result instanceof mysqli_result) {
						$result->close();
					}
					if ($mode === self::MODE_INSERT) {
						return new SqlInsertResult($mysqli->affected_rows, $mysqli->insert_id);
					}
					if ($mode === self::MODE_CHANGE) {
						return new SqlChangeResult($mysqli->affected_rows);
					}
					return new SqlResult();

				case self::MODE_SELECT:
					$ret = $this->toSelectResult($result);
					$result->close();
					return $ret;
			}
		} else {
			$stmt = self::queryWithErrors(
				fn() => $mysqli->prepare($query),
				fn() => throw new SqlError(SqlError::STAGE_PREPARE, $mysqli->error, $query, $params)
			);

			$types = implode(array_map(static function ($param) use ($query, $params) {
				if (is_string($param)) {
					return "s";
				}
				if (is_float($param)) {
					return "d";
				}
				if (is_int($param)) {
					return "i";
				}
				throw new SqlError(SqlError::STAGE_PREPARE, "Cannot bind value of type " . gettype($param), $query, $params);
			}, $params));
			$stmt->bind_param($types, ...$params);
			try {
				$stmt->execute();
			} catch (mysqli_sql_exception) {
				throw new SqlError(SqlError::STAGE_EXECUTE, $mysqli->error, $query, $params);
			}
			switch ($mode) {
				case self::MODE_GENERIC:
					$ret = new SqlResult();
					$stmt->close();
					return $ret;

				case self::MODE_CHANGE:
					$ret = new SqlChangeResult($stmt->affected_rows);
					$stmt->close();
					return $ret;

				case self::MODE_INSERT:
					$ret = new SqlInsertResult($stmt->affected_rows, $stmt->insert_id);
					$stmt->close();
					return $ret;

				case self::MODE_SELECT:
					$set = $stmt->get_result();
					$ret = $this->toSelectResult($set);
					$set->close();
					return $ret;
			}
		}

		throw new InvalidArgumentException("Unknown mode $mode");
	}

	private function toSelectResult(mysqli_result $result): SqlSelectResult {
		$columns = [];
		$columnFunc = [];

		while (($field = $result->fetch_field()) !== false) {
			if ($field->length === 1) {
				if ($field->type === MysqlTypes::TINY) {
					$type = SqlColumnInfo::TYPE_BOOL;
					$columnFunc[$field->name] = static function ($tiny) {
						return $tiny > 0;
					};
				} elseif ($field->type === MysqlTypes::BIT) {
					$type = SqlColumnInfo::TYPE_BOOL;
					$columnFunc[$field->name] = static function ($bit) {
						return $bit === "\1";
					};
				}
			}
			if ($field->type === MysqlTypes::LONGLONG) {
				$type = SqlColumnInfo::TYPE_INT;
				$columnFunc[$field->name] = static function ($longLong) use ($field) {
					if ($field->flags & MysqlFlags::UNSIGNED_FLAG) {
						if (bccomp(strval($longLong), "9223372036854775807") === 1) {
							$longLong = bcsub($longLong, "18446744073709551616");
						}
						return (int) $longLong;
					}

					return (int) $longLong;
				};
			} elseif ($field->flags & MysqlFlags::TIMESTAMP_FLAG) {
				$type = SqlColumnInfo::TYPE_TIMESTAMP;
				$columnFunc[$field->name] = static function ($stamp) {
					return strtotime($stamp);
				};
			} elseif ($field->type === MysqlTypes::NULL) {
				$type = SqlColumnInfo::TYPE_NULL;
			} elseif (in_array($field->type, [
				MysqlTypes::VARCHAR,
				MysqlTypes::STRING,
				MysqlTypes::VAR_STRING,
			], true)) {
				$type = SqlColumnInfo::TYPE_STRING;
			} elseif (in_array($field->type, [MysqlTypes::FLOAT, MysqlTypes::DOUBLE, MysqlTypes::DECIMAL, MysqlTypes::NEWDECIMAL], true)) {
				$type = SqlColumnInfo::TYPE_FLOAT;
				$columnFunc[$field->name] = "floatval";
			} elseif (in_array($field->type, [MysqlTypes::TINY, MysqlTypes::SHORT, MysqlTypes::INT24, MysqlTypes::LONG], true)) {
				$type = SqlColumnInfo::TYPE_INT;
				$columnFunc[$field->name] = "intval";
			}
			if (!isset($type)) {
				$type = SqlColumnInfo::TYPE_OTHER;
			}
			$columns[$field->name] = new MysqlColumnInfo($field->name, $type, $field->flags, $field->type);
		}

		$rows = [];
		while (($row = $result->fetch_assoc()) !== null) {
			foreach ($row as $col => &$cell) {
				if ($cell !== null && isset($columnFunc[$col])) {
					$cell = $columnFunc[$col]($cell);
				}
			}
			unset($cell);
			$rows[] = $row;
		}

		return new SqlSelectResult($columns, $rows);
	}
}
