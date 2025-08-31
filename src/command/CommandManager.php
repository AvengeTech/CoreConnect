<?php namespace connect\command;

use connect\Server;

use connect\network\protocol\{
	ServerAlivePacket,
	ServerGetStatusPacket
};
use connect\network\server\GameServer;

use connect\util\{
	Color,
	ConsoleReader
};

class CommandManager{
	
	public $server;
	
	public $consoleReader;
	
	public function __construct(Server $server){
		$this->server = $server;
		
		$this->consoleReader = new ConsoleReader();
	}

	public function close() : void{
		unset($this->consoleReader);
	}
	
	public function tick() : bool{
		$commandRan = false;
		$input = $this->getConsoleReader()->readLine();
		if($input !== null){
			$commandRan = true;
			$args = explode(" ", $input);
			$command = array_shift($args);
			switch($command){
				default:
					$this->getServer()->log("Unknown command sent! Type 'help' for a list of commands", Server::LOG_FAIL);
					$commandRan = false;
					break;
				case "stop":
					$this->getServer()->isRunning = false;
					break;
				case "help":
					$commands = [
						"stop" => ["message" => "ends server process"],
						"help" => ["args" => ["[page]"], "message" => "show available commands"],
						"uptime" => ["message" => "check uptime of this server"],
						"list" => ["args" => ["[identifier]"], "message" => "list all online players"],
						"alive" => ["args" => ["<identifier>"], "message" => "sends alive tick to game server"],
						"status" => ["args" => ["<identifier>"], "message" => "sends status update to game server"],
						"staffchat" => ["args" => ["<message>"], "message" => "sends message to staff chat as sn3akrr"],
						"announce" => ["args" => ["<message>"], "message" => "sends announcement to all connected servers"],
						"debug" => ["args" => ["<level>"], "message" => "set the max debug message level"],
					];
					$message = "";
					foreach($commands as $name => $data){
						$message .= $name . " ";
						if(!empty($data["args"] ?? [])){
							foreach($data["args"] as $arg){
								$message .= $arg . " ";
							}
						}
						$message .= "- " . $data["message"] . PHP_EOL;
					}
					$this->getServer()->log("Available commands:" . PHP_EOL . trim($message));
					break;
				case "uptime":
					$this->getServer()->log("Uptime: " . $this->getServer()->getFormattedUptime());
					break;
				case "list":
					$list = "";
					$total = 0;
					$pending = 0;
					if(count($args) < 1){
						$list .= PHP_EOL;
						foreach($this->getServer()->getNetwork()->getServers() as $server){
							$hp = false;
							$hpc = false;
							if(!empty($server->getPlayers())){
								$hp = true;
								$list .= Color::YELLOW($server->getIdentifier()) . " (" . Color::CYAN(count($server->getPlayers())) . "): ";
								foreach($server->getPlayers() as $player){
									$list .= $player->getGamertag() . ", ";
									$total++;
								}
							}
							if(!empty($server->getPendingConnections())){
								if(!$hp) $list .= Color::YELLOW($server->getIdentifier()) . " (" . Color::CYAN(count($server->getPlayers())) . "): ";
								$hpc = true;
								$list .= "(";
								foreach($server->getPendingConnections() as $player){
									$list .= $player->getGamertag() . ", ";
									$pending++;
								}
								$list = trim($list, ", ");
								$list .= ")";
							}
							if($hp || $hpc) $list .= PHP_EOL;
						}
					}else{
						$identifier = array_shift($args);
						$server = $this->getServer()->getNetwork()->getServer($identifier);
						if(!$server instanceof GameServer){
							$this->getServer()->log("Invalid server identifier provided!", Server::LOG_FAIL);
							break;
						}
						foreach($server->getPlayers() as $player){
							$list .= $player->getGamertag() . ", ";
							$total++;
						}
						if(!empty($server->getPendingConnections())){
							$list .= "(";
							foreach($server->getPendingConnections() as $player){
								$list .= $player->getGamertag() . ", ";
								$pending++;
							}
							$list = trim($list, ", ");
							$list .= ")";
						}
					}
					$this->getServer()->log("There are " . Color::CYAN($total) . " players online (" . Color::CYAN($pending) . " pending): " . $list);
					break;
				case "alive":
					if(count($args) < 1){
						$this->getServer()->log("Must provide server identifier!", Server::LOG_FAIL);
						break;
					}
					$identifier = array_shift($args);
					$server = $this->getServer()->getNetwork()->getServer($identifier);
					if(!$server instanceof GameServer){
						$this->getServer()->log("Invalid server identifier provided!", Server::LOG_FAIL);
						break;
					}
					$handler = $server->getPacketHandler();
					$packet = new ServerAlivePacket();
					$handler->queuePacket($packet);
					$this->getServer()->log("Alive ping has been queued for " . Color::YELLOW($server->getIdentifier()) . "!", Server::LOG_SUCCESS);
					break;
				case "status":
					if(count($args) < 1){
						$this->getServer()->log("Must provide server identifier!", Server::LOG_FAIL);
						break;
					}
					$identifier = array_shift($args);
					$server = $this->getServer()->getNetwork()->getServer($identifier);
					if(!$server instanceof GameServer){
						$this->getServer()->log("Invalid server identifier provided!", Server::LOG_FAIL);
						break;
					}
					$handler = $server->getPacketHandler();
					$packet = new ServerGetStatusPacket();
					$handler->queuePacket($packet);
					$this->getServer()->log("Status ping has been queued for " . Color::YELLOW($server->getIdentifier()) . "!", Server::LOG_SUCCESS);
					break;
				case "staffchat":
				case "sc":
					if(count($args) < 1){
						$this->getServer()->log("Must provide message!", Server::LOG_FAIL);
						break;
					}
					$message = implode(" ", $args);
					$this->getServer()->getNetwork()->staffChat("sn3akrr", "CONNECT", $message);
					break;
				case "announce":
					if(count($args) < 1){
						$this->getServer()->log("Must provide message!", Server::LOG_FAIL);
						break;
					}
					$message = implode(" ", $args);
					$this->getServer()->getNetwork()->announce($message);
					$this->getServer()->log("Message successfully announced to all game servers: " . $message, Server::LOG_SUCCESS);
					break;
				case "debug":
					if(count($args) < 1){
						$this->getServer()->log("Usage: debug <level>");
						break;
					}
					$level = (int) array_shift($args);
					$this->getServer()->setDebugLevel($level);
					if(count($args) > 0){
						foreach($args as $arg){
							$this->getServer()->debugFilter[] = $arg;
						}
					}
					$this->getServer()->log("Debug level has been set to " . Color::CYAN($level));
					break;
			}
		}
		return $commandRan;
	}
	
	public function getServer() : Server{
		return $this->server;
	}
	
	public function getConsoleReader() : ConsoleReader{
		return $this->consoleReader;
	}
}