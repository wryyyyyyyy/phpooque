<?php
//cheat
set_time_limit(0);

// global vars
$id = 0;
$chan = "";
$date = "";
$title = "";
$url = "";

// fork START
$pid = pcntl_fork();
if ($pid == -1) {
     die('could not fork');
} else if ($pid) {
     // parent
     //echo "connectIRC() STARTED as PARENT PROCESS\n";
     pcntl_wait($status); // no more children
     connectIRC();
} else {
     // child
     //echo "WRITE DB ROUTINE STARTED AS CHILD PROCESS\n";

    class DB extends SQLite3 {
      function __construct() {
        $this->open('./news.db');
      }
    }

     writeData();
}
// fork END



// readData START
function readData($n) {

  global $id,$chan,$date,$title,$url;
  $db = new DB('./news.db');

$sql =<<<EOF
  SELECT * from NEWS WHERE id == $n;
EOF;

  //echo "READING DB\n";
$jret = array($id,$chan,$date,$title,$url);

  $ret = $db->query($sql);
  while($row = $ret->fetchArray(SQLITE3_ASSOC) ) {
    $id = $row['ID'] . "\n";
    $chan = $row['CHAN'] . "\n";
    $date = $row['DATE'] ."\n";
    $title = $row['TITLE'] ."\n";
    $url = $row['URL'] ."\n\n";

    $tmp = array($id,$chan,$date,$title,$url);
    array_merge($jret,$tmp);
    //print_r($tmp);
  }
$db->close();
}
// readData END



// writeData START
function writeData() {
  $f = file_exists('./news.db'); // check DB file

/*
  class DB extends SQLite3 {
     function __construct() {
        $this->open('./news.db');
     }
  }
*/

  if (!$f) {
    // create new DB handle
    $db = new DB('./news.db');
    echo "CREATING DB\n";
    createTable($db);
  }
}
// writeData END




// renewData START
function renewData() {
  $fh = ('./news.db');
  $res = unlink($fh);
  echo $fh." DELETED\n";
  writeData();
}
// renewData END




// createTable START
function createTable($db) {
$sql =<<<EOF
  CREATE TABLE NEWS
  (ID INT PRIMARY KEY    NOT NULL,
  CHAN           TEXT    NOT NULL,
  DATE           TEXT    NOT NULL,
  TITLE          TEXT    NOT NULL,
  URL            TEXT    NOT NULL);
EOF;

$ret = $db->exec($sql);
if(!$ret) {
  echo $db->lastErrorMsg();
} else {
  echo "Table created successfully\n";
}
$db->close();
fillTable();
}
// createTable END



// fillTable START
function fillTable() {
$db = new DB('./news.db');

global $id,$chan,$date,$title,$url;

$i = 0; // $id & counter
$xml = getUrl();
foreach($xml->channel->item as $item) {
  if ($i < 30) { // parse only 30 items
    $id = $i;
    $chan = $xml->channel->title;
    $date = $item->pubDate;
    $title = $item->title;
    $url = $item->link;

$sql =<<<EOF
  INSERT INTO NEWS(ID, CHAN, DATE, TITLE, URL) VALUES ($id, "$chan", "$date", "$title", "$url");
EOF;

  $ret = $db->exec($sql);
  if(!$ret) {
    echo $db->lastErrorMsg();
  } else {
    echo "Record $id added successfully\n";
  }

  }
$i++;
}
$db->close();
}
// fillTable END



// getUrl START
function getUrl() {
  $rss = "https://news.ycombinator.com/rss";
  $feed = implode(file($rss));
  $xml = simplexml_load_string($feed);

  return ($xml);
}
// getUrl END



// connectIRC START
function connectIRC() {
  echo "CONNECTING TO IRC NOW\n";
  global $id,$chan,$date,$title,$url;

  class DB extends SQLite3 { ### declare this shit here once too ###
     function __construct() {
        $this->open('./news.db');
     }
  }

// irc vars //
$channel = "#s2ch";
$server = "ssl://chat.freenode.net";
$port = 6697;
$nick = "sqliteV3_";

// connect routine //
$socket = fsockopen("$server", $port);
fputs($socket,"USER $nick $nick $nick $nick :$nick\n");
fputs($socket,"NICK $nick\n");

// MAIN LOOP START
while(1) {
    while($data = fgets($socket)) {
            echo $data; ### FOR IRC VISUAL CONTROL ###
            flush();

            $ex = explode(' ', $data);

            if($ex[0] == "PING"){
                fputs($socket, "PONG ".$ex[1]."\n");
            }

            if($ex[1] == "366") { ### :END of /NAMES list ###
                fputs($socket, "PRIVMSG ".$channel." :Йа креведко!\t!help жи\n");
            }

            if($ex[1] == "376") { ### :END of /MOTD command ###
                fputs($socket,"JOIN ".$channel."\n");
            }

            $cmd = explode(':', $ex[3]);
            $cmd = preg_replace('/\n/', '', $cmd);

            $userinfo = explode(':', $ex[0]);
            $sender = explode('!',$userinfo[1]);

            $args = NULL; for ($i = 4; $i < count($ex); $i++) { $args .= $ex[$i] . ' '; }


            if ($cmd[1] == "!botinfo\r") { ### $ex[0] = user; $ex[1] = PRIVMSG; $ex[2] = #channel ###
                fputs($socket, "NOTICE ".$sender[0]." :".system('id')."\n");
                fputs($socket, "NOTICE ".$sender[0]." :".system('uname -a')."\n");
                fputs($socket, "NOTICE ".$sender[0]." :".system('php -v|grep built')."\n");
            }

            elseif ($cmd[1] == "!die\r") {
                //fputs($socket, "QUIT :Killed by services\n");
                fputs($socket, "NOTICE ".$sender[0]." :Ня ^_^\n");
            }

            elseif ($cmd[1] == "!help\r") {
                fputs($socket, "NOTICE ".$sender[0]." :!help | !botinfo | !renews | !news (0-29) | !md5 somewhat | !die\n");
            }

            elseif ($cmd[1] == "!md5") {
                fputs($socket, "NOTICE ".$sender[0]." :MD5 ".md5($args)."\n");
            }

            elseif ($cmd[1] == "!news") {
                $n = $args;
                readData($n);
                fputs($socket, "NOTICE ".$sender[0]." :".$chan."\n\n");
                fputs($socket, "NOTICE ".$sender[0]." :ID: ".$id."\n");
                fputs($socket, "NOTICE ".$sender[0]." :".$date."\n");
                fputs($socket, "NOTICE ".$sender[0]." :".$title."\n");
                fputs($socket, "NOTICE ".$sender[0]." :".$url."\n");
            }

            elseif ($cmd[1] == "!renews\r") {
                fputs($socket, "NOTICE ".$sender[0]." :Please wait, recreating news database...\n");
                renewData();
                sleep(3);
                fputs($socket, "NOTICE ".$sender[0]." :Thanks! Hope its updated yet\n");
            }

            #elseif (($cmd[1] == "!sh") && ($sender[0] == "xzibit")) {
                #$sh = passthru($args);
                #$sh = preg_replace('/\n/', '', $sh);
                #echo $sh;
                #fputs($socket, "NOTICE ".$sender[0]." :".$sh."\n");
            #}
    }

    sleep(0); // fake save CPU
}
// MAIN LOOP END
}
// connectIRC END

?>
