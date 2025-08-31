<?php namespace connect;

use connect\command\CommandManager;
use connect\data\DataManager;
use connect\network\Network;
use connect\network\protocol\{
	ServerAlivePacket,
	ServerGetStatusPacket
};
use connect\network\server\GameServer;

use connect\util\{
	Color,
	ConsoleReader
};

class Server{

	const TPS = 40; // baseline is 20 | fast forwarding the tps of coreconnect actually shows improved speeds in PM servers
	const MICROSECOND = 1000000;

	const LOG_INFO = 0;
	const LOG_WARNING = 1;
	const LOG_EXCEPTION = 2;
	const LOG_ERROR = 3;
	const LOG_DEBUG = 4;

	const LOG_SUCCESS = 69;
	const LOG_FAIL = 21;
	
	public $isRunning = true;

	public $debugLevel = 0;
	public $debugFilter = [];

	public static $instance = null;
	public $started;

	public $commandManager;

	public $dataManager;

	public $network;

	public $ticks = 0;

	public function __construct(){
		cli_set_process_title("CoreConnect Server");
		date_default_timezone_set("America/New_York");

		self::$instance = $this;
		$this->started = time();

		$this->commandManager = new CommandManager($this);

		$this->dataManager = new DataManager($this);

		$this->network = new Network();

		$this->log("Server has been started!");
		$this->tickProcessor();
		$this->log("Server shutting down...");
		
		$this->getCommandManager()->close();
		$this->getNetwork()->close();
	}

	public function isRunning() : bool{
		return $this->isRunning;
	}

	public function getDebugLevel() : int{
		return $this->debugLevel;
	}

	public function setDebugLevel(int $level) : void{
		$this->debugLevel = $level;
	}

	public static function getInstance() : self{
		return self::$instance;
	}

	private function tickProcessor() : void{
		while($this->isRunning()){
			try{
				$this->tick();
				$this->getCommandManager()->tick();
			}catch(\Throwable $e){
				$type = get_class($e);
				$errstr = $e->getMessage();
				$errfile = $e->getFile();
				$errline = $e->getLine();

				$this->log($type . " thrown by " . $errfile . " on line " . $errline . ": " . $e->getMessage(), stristr($type, "error") ? Server::LOG_ERROR : Server::LOG_EXCEPTION);
			}
			usleep(self::MICROSECOND / min(max(self::TPS, 1), self::MICROSECOND));
		}
	}

	private function tick() : int{
		$this->ticks++;
		$this->getNetwork()->tick($this->ticks);
		$this->getDataManager()->tick();

		return $this->ticks;
	}

	public function getCommandManager() : CommandManager{
		return $this->commandManager;
	}

	public function getDataManager() : DataManager{
		return $this->dataManager;
	}

	public function getNetwork() : Network{
		return $this->network;
	}

	public function log(string $message, int $type = self::LOG_INFO, int $level = 1) : void{
		if($type == self::LOG_DEBUG){
			if($this->getDebugLevel() < $level) return;
			/**if(count($this->debugFilter) > 0){
				foreach($this->debugFilter as $filterw){
					if(!stripos($message, $filterw)){
						return;
					}
				}
			}*/ //TODO: fix
		}

		$time = new \DateTime("now");
		echo Color::LIGHT_CYAN("[" . $time->format("H:i:s.v") . "] ") . $this->getLogLevelName($type) . ": " . $message, PHP_EOL;
	}

	public function getLogLevelName(int $type) : string{
		return match($type){
			self::LOG_INFO => Color::LIGHT_CYAN("INFO"),
			self::LOG_WARNING => Color::YELLOW("WARNING"),
			self::LOG_EXCEPTION => Color::LIGHT_RED("EXCEPTION"),
			self::LOG_ERROR => Color::RED("ERROR"),

			self::LOG_DEBUG => Color::CYAN("DEBUG"),
			self::LOG_SUCCESS => Color::GREEN("SUCCESS"),
			self::LOG_FAIL => Color::RED("FAIL"),

			default => Color::RED("UNKNOWN")
		};
	}

	public function getStarted() : int{
		return $this->started;
	}

	public function getUptime() : int{
		return time() - $this->getStarted();
	}

	public function getFormattedUptime(bool $long = false) : string{
		$seconds = $this->getUptime();
		$dtF = new \DateTime('@0');
		$dtT = new \DateTime("@$seconds");
		return $dtF->diff($dtT)->format('%a days, %h hours, %i minutes and %s seconds');
	}

}