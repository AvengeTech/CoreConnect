<?php namespace connect{

	include "ClassLoader.php";

	function run() : void{
		echo "Starting up server...", PHP_EOL;
		new Server();
	}

	\connect\run();

}