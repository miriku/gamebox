<?php
/*
 * This is the server side code for the AJAX createGame call.
 * 
 * Input is the following JSON data structure.
 * {
 *   "gname": "name",
 *   "boxid": "boxid",
 *   "players": [
 *     "pname0",
 *     . . . . . 
 *     "pnamen",
 *   ] 
 * }
 * 
 * Output is the return status: 
 *   "success", "fail", "nobox" or "noplayer #".
 */
require_once('auth.php');
require_once('config.php');
$link = @mysqli_connect(DB_HOST, DB_USER, 
        DB_PASSWORD, DB_DATABASE);
if (mysqli_connect_error()) {
  $logMessage = 'MySQL Error 1: ' . mysqli_connect_error();
  error_log($logMessage);
  echo "fail";
  exit;
}
mysqli_set_charset($link, "utf-8");
$qry0 = "ROLLBACK";

//Function to sanitize values received from the form. 
//Prevents SQL injection
function clean($link,$str) {
  $str = @trim($str);
  return mysqli_real_escape_string($link,$str);
}

$game = json_decode($_REQUEST['newgame'], true);
//Sanitize the POST values
$name = clean($link,$game["gname"]);
$boxid = intval(clean($link,$game["boxid"]));
$count = count($game["players"]);
for ($i = 0; $i < $count; $i++) {
  $player[$i] = clean($link,$game["players"][$i]);
}

// Start transaction.
$qry1 = "START TRANSACTION";
$result1 = mysqli_query($link, $qry1);
if (!$result1) {
  $logMessage = 'MySQL Error 2: ' . mysqli_error($link);
  error_log($logMessage);
  echo "fail";
  exit;
}

//Check for valid boxid ID
$qry2 = "SELECT bname FROM box WHERE box_id='$boxid'";
$result2 = mysqli_query($link,$qry2);
if ($result2) {
  if (mysqli_num_rows($result2) == 0) { // Invalid Box ID!
    echo 'nobox';
    mysqli_query($link, $qry0); // ROLLBACK
    exit;
  }
} else {
  $logMessage = 'MySQL Error 3: ' . mysqli_error($link);
  error_log($logMessage);
  echo "fail"; 
  mysqli_query($link, $qry0); // ROLLBACK
  exit;
}

// Validate Player Names and lookup player IDs.
for ($i = 0; $i < $count; $i++) {
  $j = $i + 1; // $j is player number from input form.
  $qry3 = "SELECT player_id FROM players WHERE login = '$player[$i]'";
  $result3 = mysqli_query($link,$qry3);
  if ($result3) {
    if (mysqli_num_rows($result3) == 0) { // Invalid Player name!
      echo "noplayer $j";
      mysqli_query($link, $qry0); // ROLLBACK
      exit;
    } else {
      $temp = mysqli_fetch_array($result3);
      $playerid[$i] = $temp[0];
    }
  } else {
    $logMessage = 'MySQL Error 4: ' . mysqli_error($link);
    error_log($logMessage);
    echo "fail"; 
    mysqli_query($link, $qry0); // ROLLBACK
    exit;
  }
  mysqli_free_result($result3);
}

//Create INSERT query
$jtxt = '{ "gname": "';
$jtxt .= $name;
$jtxt .= '", "boxID": "';
$jtxt .= $boxid;
$jtxt .= '", "brdTls": [], "brdTks": [], ';
$jtxt .= '"mktTks": [], "trayCounts": []}';
$qry4 = "INSERT INTO game SET gname='$name', box_id='$boxid',
          json_text='$jtxt'";  
$result4 = mysqli_query($link,$qry4);
if (!$result4) {   // Did the query fail
  $logMessage = 'MySQL Error 5: ' . mysqli_error($link);
  error_log($logMessage);
  echo "fail"; 
  mysqli_query($link, $qry0); // ROLLBACK
  exit;
}
$gameid = mysqli_insert_id($link);

// Fix start date
$qry5 = "SELECT activity_date FROM game WHERE game_id = '$gameid'";
$result5 = mysqli_query($link,$qry5);
if (!$result5 || (mysqli_num_rows($result5) != 1)) {
  $logMessage = 'MySQL Error 6: ' . mysqli_error($link);
  error_log($logMessage);
  echo "fail"; 
  mysqli_query($link, $qry0); // ROLLBACK
  exit;
}
$ad = mysqli_fetch_array($result5);
$qry6 = "UPDATE game SET start_date = '$ad[0]' WHERE game_id = '$gameid'";
$result6 = mysqli_query($link,$qry6);
if (!$result6) {   // Did the query fail
  $logMessage = 'MySQL Error 7: ' . mysqli_error($link);
  error_log($logMessage);
  echo "fail"; 
  mysqli_query($link, $qry0); // ROLLBACK
  exit;
}

// create game_player rows.
for ($i = 0; $i < $count; $i++) {
  $qry7 = "INSERT INTO game_player SET game_id='$gameid', 
      player_id='$playerid[$i]'";
  $result7 = mysqli_query($link,$qry7);
  if (!$result7) {   // Did the query fail
    $logMessage = 'MySQL Error 8: ' . mysqli_error($link);
    error_log($logMessage);
    echo "fail"; 
    mysqli_query($link, $qry0); // ROLLBACK
    exit;
  }
}
$qry8 = "COMMIT";
echo "success";
$_SESSION['SESS_HEADER_MESSAGE'] = 
   'New game has been created.';
mysqli_query($link, $qry8); // COMMIT
?>
