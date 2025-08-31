<?php namespace connect\util;

final class ConsoleReader{ //stole this from pocketmine luls
	/** @var resource */
	private $stdin;

	public function __construct(){
		$this->initStdin();
	}

	private function initStdin() : void{
		if(is_resource($this->stdin)){
			fclose($this->stdin);
		}

		$this->stdin = fopen("php://stdin", "r");
	}

	/**
	 * Reads a line from the console and adds it to the buffer. This method may block the thread.
	 */
	public function readLine() : ?string{
		if(!is_resource($this->stdin)){
			$this->initStdin();
		}

		$r = [$this->stdin];
		$w = $e = null;
		if(($count = stream_select($r, $w, $e, 0, 200000)) === 0){
			return null;
		}elseif($count === false){
			$this->initStdin();
		}

		if(($raw = fgets($this->stdin)) === false){
			$this->initStdin();
			usleep(200000);
			return null;
		}

		$line = trim($raw);

		return $line !== "" ? $line : null;
	}

	public function __destruct(){
		fclose($this->stdin);
	}

}