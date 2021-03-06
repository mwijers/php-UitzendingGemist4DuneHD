<?php
// Provide access to store Media Player information in MySQL database

require_once dirname(__FILE__).'/dune.php';

function lookupDuneSerial()
{
    $duneSerial = getDuneSerial();
    if($duneSerial == null) $duneSerial = findSerialByIP();
    return $duneSerial;
}

function connectToDb()
{
    global $mysql_host, $mysql_user, $mysql_password, $mysql_database;
    // we connect to example.com and port 3307
    $mysqli = new mysqli($mysql_host, $mysql_user, $mysql_password, $mysql_database);
    if ($mysqli->connect_error)
        die('# Connect Error (' . $mysqli->connect_errno . ') '. $mysqli->connect_error);
 
    return $mysqli;
}

function registerMediaPlayer()
{
    $duneSerial = getDuneSerial();
    
    if(!$duneSerial) return;
	
	$mysqli = connectToDb();
	if( isRegistered($duneSerial) )
	{
		$stmt = $mysqli->prepare("UPDATE dunehd_player SET ipAddress=?, lastSeen=UTC_TIMESTAMP(), lang=?, userAgent=?, hits=hits+1 WHERE duneSerial=?");
		if(!$stmt) die('Prepare statement error (' . $mysqli->errno . ') '. $mysqli->error);
		$stmt->bind_param('isss', ip2long32(getRemoteIp()), getDuneLang(), $_SERVER['HTTP_USER_AGENT'], $duneSerial);
	}
	else
	{
		$stmt = $mysqli->prepare("INSERT INTO dunehd_player (duneSerial, ipAddress, firstSeen, lastSeen, lang, userAgent) VALUES(?, ?, UTC_TIMESTAMP(), firstSeen, ?, ?)");
        $stmt->bind_param('siss', $duneSerial, ip2long32(getRemoteIp()), getDuneLang(), $_SERVER['HTTP_USER_AGENT']);
		if(!stmt) die('Prepare statement error (' . $mysqli->errno . ') '. $mysqli->error);

    }
	/* execute query */
	$stmt->execute();
	$stmt->close();
    $mysqli->close();

    // initialize favorites

}

function isRegistered($duneSerial)
{
    if(!$duneSerial) return false;
    
    $mysqli = connectToDb();
    
    /* create a prepared statement */
    if( $stmt = $mysqli->prepare("SELECT ipAddress FROM dunehd_player WHERE duneSerial=?") )
    {
        $stmt->bind_param('s', $duneSerial);
        /* execute query */
        $stmt->execute();
		$found = $stmt->fetch();
        $stmt->close();
		return $found;
    }
    else die('Prepare statement error (' . $mysqli->errno . ') '. $mysqli->error);
    
    $mysqli->close();
}

function findSerialByIP()
{
    $player = getPlayerByIP();
    return $player['serial'];
}
    
function getPlayerByIP()
{
	$numIp = ip2long32(getRemoteIp());
	
	if($numIp > -1062731776 && $numIp < -1062666241) // Private range: 192.168.0.0 .. 192.168.255.255
		$players = getPlayersByRange(-1062731776, -1062666241);
	else if($numIp > 167772160 && $numIp < 184549375) // Private range: 10.0.0.0 ..  10.255.255.255
		$players = getPlayersByRange(167772160, 184549375);
	else if($numIp > -1408237568 && $numIp < -1407188993) // Private range: 172.16.0.0 ..  172.31.255.255
		$players = getPlayersByRange(-1408237568, -1407188993);
	else // Public range, assume same IP address for player and HTML browser
		$players = getPlayersByRange($numIp, $numIp);
	return isset($players[0]) ? $players[0] : null;
}

function getPlayers()
{
	// 128.0.0.0=-2147483648 255.255.255=2147483647
	return getPlayersByRange(-2147483648, 2147483647);
}

function getPlayersByRange($firstIp, $lastIp)
{
    $mysqli = connectToDb();
	$result = array();
    
    /* create a prepared statement */
    if( $stmt = $mysqli->prepare("SELECT mp.duneSerial, ipAddress, firstSeen, lastSeen, hits, lang, favorites, userAgent FROM dunehd_player mp LEFT JOIN( SELECT duneSerial, COUNT(*) favorites FROM favorite GROUP BY duneSerial ) fav ON fav.duneSerial=mp.duneSerial WHERE ipAddress>=? AND ipAddress<=? ORDER BY lastSeen DESC") )
    {
        $stmt->bind_param('ii', $firstIp, $lastIp);

        /* execute query */
        $stmt->execute();
        
        $stmt->bind_result($duneSerial, $ipAddress, $firstSeen, $lastSeen, $hits, $lang, $favorites, $userAgent);
        
        $player = null;
        
        while($stmt->fetch())
        {
            $player = array();
            $player['serial'] = $duneSerial;
            $player['ip'] = $ipAddress;
            $player['firstSeen'] = $firstSeen;
			$player['lastSeen'] = $lastSeen;
			$player['hits'] = $hits;
            $player['lang'] = $lang;
			$player['favorites'] = $favorites;
			$player['userAgent'] = $userAgent;
			$result[] = $player;
        }

        $stmt->close();
        
        // if($result == null) return 'FFFF-FFFF-FFFF-FFFF-FFFF-FFFF-FFFF-FFFF';
        
        return $result;
    }
    else trigger_error('Prepare statement error (' . $mysqli->errno . ') '. $mysqli->error);
    
    $mysqli->close();
}

function getRemoteIp()
{
    /*
	if(isset($_SERVER['HTTP_X_FORWARDED_FOR']) && $_SERVER['HTTP_X_FORWARDED_FOR'] != '')
    {
        return $_SERVER['HTTP_X_FORWARDED_FOR'];
    }*/
    return getenv('REMOTE_ADDR'); //$_SERVER['REMOTE_ADDR'];
}

function ip2long32($ipstr)
{
    $ip = ip2long($ipstr);
    if (PHP_INT_SIZE == 8)
    {
        if ($ip>0x7FFFFFFF)
        {
            $ip-=0x100000000;
        }
    }
    return $ip;
}

// ---- Provides access to storage of favorites in MySQL database ---
   
function addToFavorite($provider, $type, $refid, $title, $img = null)
{
    $duneSerial = lookupDuneSerial();
    if( $duneSerial == null) die('addToFavorite(): Dune HD serial number cannot be null');
    
    $mysqli = connectToDb();
    
   /* create a prepared statement */
    if( $stmt = $mysqli->prepare("REPLACE INTO favorite (duneSerial, provider, type, refid, title, img) VALUES(?, ?, ?, ?, ?, ?)") )
    {
        $stmt->bind_param('ssssss', $duneSerial, $provider, $type, $refid, $title, $img);

        /* execute query */
        $stmt->execute() or user_error('# Query Error (' . $mysqli->errno . ') '.  $mysqli->error);

        $stmt->close();
    }
    else trigger_error('Prepare statement error (' .  $mysqli->errno . ') '.  $mysqli->error);
    
    $mysqli->close();
}
    
function deleteFromFavorite($provider, $type, $refid)
{
    $duneSerial = lookupDuneSerial() or die('Dune HD serial number cannot be null');
    
    $mysqli = connectToDb();
 
   /* create a prepared statement */
    if( $stmt = $mysqli->prepare("DELETE FROM favorite WHERE duneSerial=? AND provider=? AND type=? AND refid=?") )
    {
        $stmt->bind_param('ssss', $duneSerial, $provider, $type, $refid);

        /* execute query */
        $stmt->execute() or die('# Query Error (' . $mysqli->errno . ') '.  $mysqli->error);

        $stmt->close();
    }
    else trigger_error('# Prepare statement error (' .  $mysqli->errno . ') '.  $mysqli->error);
    
    $mysqli->close();
}
    
function readFavorites($provider, $type, $duneSerial = false)
{
    $mysqli = connectToDb();
    
    require_once dirname(__FILE__).'/dune.php';
    
    if(!$duneSerial) $duneSerial = lookupDuneSerial();
    if(!$duneSerial) die('Failed to resolve Dune HD Serial');
  
    $favorites = array();
        
    /* create a prepared statement */
    if( $stmt = $mysqli->prepare("SELECT provider, refid, title, img FROM favorite WHERE duneSerial=? AND provider=? AND type=? ORDER by title") )
    {
        $stmt->bind_param('sss', $duneSerial, $provider, $type);

        /* execute query */
        $stmt->execute();
        
        $stmt->bind_result($provider, $refid, $title, $img);
        while($stmt->fetch())
        {
            $favorite['type'] = $type;
            $favorite['refid'] = $refid;
            $favorite['title'] = $title;
            $favorite['img'] = $img;
            
            $favorites[] = $favorite;
        }

        $stmt->close();
        
    }
    else trigger_error('Prepare statement error (' . $mysqli->errno . ') '. $mysqli->error);

    $mysqli->close();
    
    return $favorites;
} 
?>