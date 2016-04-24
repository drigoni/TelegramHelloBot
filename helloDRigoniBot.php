<!--
    This program is a bot for Telegram chat, it enables some nice features like spam mode.
    Copyright (C) 2016  Davide Rigoni
    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation; either version 2 of the License, or
    (at your option) any later version.
    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.
    You should have received a copy of the GNU General Public License along
    with this program; if not, write to the Free Software Foundation, Inc.,
    51 Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA.
-->

<!-- html tag -->
<html>
  <head>
  </head>
<body>
<!-- start body -->
<?php

define('BOT_TOKEN', '182857120:AAHYnWtUny2QHhkPP8F9xSVAJ9BeGzt-vHw');
define('API_URL', 'https://api.telegram.org/bot'.BOT_TOKEN.'/');

function apiRequestWebhook($method, $parameters) {
  if (!is_string($method)) {
    error_log("Method name must be a string\n");
    return false;
  }

  if (!$parameters) {
    $parameters = array();
  } else if (!is_array($parameters)) {
    error_log("Parameters must be an array\n");
    return false;
  }

  $parameters["method"] = $method;

  header("Content-Type: application/json");
  echo json_encode($parameters);
  return true;
}

function exec_curl_request($handle) {
  $response = curl_exec($handle);

  if ($response === false) {
    $errno = curl_errno($handle);
    $error = curl_error($handle);
    error_log("Curl returned error $errno: $error\n");
    curl_close($handle);
    return false;
  }

  $http_code = intval(curl_getinfo($handle, CURLINFO_HTTP_CODE));
  curl_close($handle);

  if ($http_code >= 500) {
    // do not wat to DDOS server if something goes wrong
    sleep(10);
    return false;
  } else if ($http_code != 200) {
    $response = json_decode($response, true);
    error_log("Request has failed with error {$response['error_code']}: {$response['description']}\n");
    if ($http_code == 401) {
      throw new Exception('Invalid access token provided');
    }
    return false;
  } else {
    $response = json_decode($response, true);
    if (isset($response['description'])) {
      error_log("Request was successfull: {$response['description']}\n");
    }
    $response = $response['result'];
  }

  return $response;
}

function apiRequest($method, $parameters) {
  if (!is_string($method)) {
    error_log("Method name must be a string\n");
    return false;
  }

  if (!$parameters) {
    $parameters = array();
  } else if (!is_array($parameters)) {
    error_log("Parameters must be an array\n");
    return false;
  }

  foreach ($parameters as $key => &$val) {
    // encoding to JSON array parameters, for example reply_markup
    if (!is_numeric($val) && !is_string($val)) {
      $val = json_encode($val);
    }
  }
  $url = API_URL.$method.'?'.http_build_query($parameters);

  $handle = curl_init($url);
  curl_setopt($handle, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($handle, CURLOPT_CONNECTTIMEOUT, 5);
  curl_setopt($handle, CURLOPT_TIMEOUT, 60);

  return exec_curl_request($handle);
}

function apiRequestJson($method, $parameters) {
  if (!is_string($method)) {
    error_log("Method name must be a string\n");
    return false;
  }

  if (!$parameters) {
    $parameters = array();
  } else if (!is_array($parameters)) {
    error_log("Parameters must be an array\n");
    return false;
  }

  $parameters["method"] = $method;

  $handle = curl_init(API_URL);
  curl_setopt($handle, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($handle, CURLOPT_CONNECTTIMEOUT, 5);
  curl_setopt($handle, CURLOPT_TIMEOUT, 60);
  curl_setopt($handle, CURLOPT_POSTFIELDS, json_encode($parameters));
  curl_setopt($handle, CURLOPT_HTTPHEADER, array("Content-Type: application/json"));

  return exec_curl_request($handle);
}

function processMessage($message) {
	//ref to global var
	global $spamMode;
  	// process incoming message
	$message_id = $message['message_id'];
	$chat_id = $message['chat']['id'];
	if (isset($message['text'])) {
		// incoming text message
		$text = $message['text'];

		if($spamMode == false){
			if ($text === "/start") {
			  	apiRequestJson("sendMessage", array('chat_id' => $chat_id, "text" => 'Choose the command!', 'reply_markup' => array(
			    'keyboard' => array(array('Hello', '/startSpam','/stopSpam')),
			    'one_time_keyboard' => true,
			    'resize_keyboard' => true)));
			} else if ($text === "Hello") {
			  	apiRequest("sendMessage", array('chat_id' => $chat_id, "text" => 'Nice to met you!'));
			} else if ($text === "/stop") {
			  	apiRequest("sendMessage", array('chat_id' => $chat_id, "text" => '/stop? please don\'t joke!'));
			  	apiRequest("sendMessage", array('chat_id' => $chat_id, "text" => 'Spam mode activated!'));
			  	$spamMode = true;
			} else if ($text === "/startSpam") {
			  	apiRequest("sendMessage", array('chat_id' => $chat_id, "text" => 'Spam mode activated!'));
			  	$spamMode = true;
			} else {
			  	apiRequestWebhook("sendMessage", array('chat_id' => $chat_id, "reply_to_message_id" => $message_id, "text" => 'Cool'));}
		}
		else
		{
			if ($text === "/stopSpam") 
			{
			  	apiRequest("sendMessage", array('chat_id' => $chat_id, "text" => 'Spam mode disabled!'));
			  	$spamMode = false;
			}
			else
			{
				apiRequest("sendMessage", array('chat_id' => $chat_id, "text" => $text));
			}
		}
	}
  else {
    apiRequest("sendMessage", array('chat_id' => $chat_id, "text" => 'Nope!'));}
}

function getMessages($offset){
  $content = file_get_contents(API_URL."getUpdates?offset=".$offset);
  $result = json_decode($content, true);
  if(isset($result["result"]))
    return $result["result"];
}


echo "Start";
//Global Variables
$spamMode = false;

while (true) {
  $messages = getMessages(-2);
  if (isset($messages) && count($messages)==2) {
    //check sender
    $last = $messages[count($messages)-1];
    processMessage($last["message"]);
    getMessages(-1);
  }
  //sleep(1);
  usleep(500000);
}
echo "Fine";



?>
<!-- end body -->
</body>
</html>