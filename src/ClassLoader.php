<?php namespace connect;

foreach([
	"Server.php",

	"command/CommandManager.php",

	"network/Network.php",

	"network/protocol/ConnectPacketHandler.php",
	"network/protocol/PacketIds.php",
	"network/protocol/ConnectPacket.php",
	"network/protocol/OneWayPacket.php",

	"network/protocol/ServerAlivePacket.php",
	"network/protocol/ServerGetPlayersPacket.php",
	"network/protocol/ServerGetAllPlayersPacket.php",
	"network/protocol/ServerPostPlayersPacket.php",
	"network/protocol/ServerGetStatusPacket.php",
	"network/protocol/ServerSetStatusPacket.php",
	"network/protocol/ServerWhitelistPacket.php",
	"network/protocol/ServerAnnouncementPacket.php",

	"network/protocol/ServerSubUpdatePacket.php",

	"network/protocol/StaffChatPacket.php",
	"network/protocol/StaffBanPacket.php",
	"network/protocol/StaffBanIpPacket.php",
	"network/protocol/StaffBanDevicePacket.php",
	"network/protocol/StaffMutePacket.php",
	"network/protocol/StaffWarnPacket.php",
	"network/protocol/StaffAnticheatPacket.php",
	"network/protocol/StaffCommandSeePacket.php",

	"network/protocol/PlayerMessagePacket.php",
	"network/protocol/PlayerTransferPacket.php",
	"network/protocol/PlayerTransferCompletePacket.php",
	"network/protocol/PlayerConnectPacket.php",
	"network/protocol/PlayerDisconnectPacket.php",
	"network/protocol/PlayerSummonPacket.php",
	"network/protocol/PlayerChatPacket.php",
	"network/protocol/PlayerSessionSavedPacket.php",
	"network/protocol/PlayerReconnectPacket.php",
	"network/protocol/PlayerLoadActionPacket.php",
	"network/protocol/PlayerDataTransferPacket.php",

	"network/protocol/DataSyncPacket.php",


	"network/server/GameServer.php",
	"network/server/GamePlayer.php",
	"network/server/PendingConnection.php",

	"network/thread/ConnectUnitedUdpThread.php",

	"party/PartyManager.php",
	"party/PartyMember.php",
	"party/Party.php",

	"util/ConsoleReader.php",
	"util/Color.php",

	"data/DataManager.php",
	"data/mysql/SqlColumnInfo.php",
	"data/mysql/SqlError.php",
	"data/mysql/SqlResult.php",
	"data/mysql/SqlChangeResult.php",
	"data/mysql/SqlInsertResult.php",
	"data/mysql/SqlSelectResult.php",
	"data/mysql/MysqlColumnInfo.php",
	"data/mysql/MysqlFlags.php",
	"data/mysql/MySqlQuery.php",
	"data/mysql/MysqlTypes.php",
	"data/mysql/MySqlUtils.php",
	"data/mysql/MySqlRequest.php",
	] as $file) require_once($file);