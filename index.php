<?php
try {

  file_put_contents("logs/receive.txt", "-- start  -- ".date("Y/m/d/ H:i:s")." ------------------------\n\n", FILE_APPEND);

  if( (!inCIDR($_SERVER[ "REMOTE_ADDR" ], "204.232.175.64/27") && !inCIDR($_SERVER[ "REMOTE_ADDR" ], "192.30.252.0/22") )
      || empty($_REQUEST["payload"])
  ){
    file_put_contents("logs/receive.txt", "-- OTHER IP ACCESS: ". $_SERVER[ "REMOTE_ADDR" ]." -------------------------------\n\n", FILE_APPEND);
    sent_mail('Error: Cannnot Accept IP','Cannnot Accept IP:' .$_SERVER[ "REMOTE_ADDR" ]);
    // return 500 code for request ecxept github
    $errorStatus = "HTTP/1.1 500 Internal Server Error";
    header( $errorStatus );
    exit(0);
  }

  $json = (get_magic_quotes_gpc())? stripslashes($_REQUEST["payload"]) : $_REQUEST["payload"];
  $payload = json_decode($json);


  if(strpos($payload->ref, "tags") !== false){
    if(strpos($payload->head_commit->message, "release") !== false || strpos($payload->head_commit->message, "hotfix") !== false ){
      write_log($payload);
      run_pull_script($payload,"master");
    }
  }elseif(is_dev_server() && strpos($payload->ref, "tags") === false){
    if(strpos($payload->head_commit->message, "release") !== false && strpos($payload->ref, "master") !== false){
      write_log($payload);
      run_pull_script($payload,"master");
    }elseif(strpos($payload->ref, "develop") !== false){
      write_log($payload);
      run_pull_script($payload,"develop");
    }
  }

  file_put_contents("logs/receive.txt", "\n-- finish -- ".date("Y/m/d/ H:i:s")." ------------------------\n\n", FILE_APPEND);

} catch (Exception $e) {
  sent_mail("ERROR: Exception", $e->getMessage(), "\n");
}



//functions

function sent_mail($subject,$message){
  file_put_contents("logs/receive.txt", "start: sentMain\n", FILE_APPEND);

  $headers = array(
      'From: nD Deploy <system@ndinc.jp>' . "\r\n",
      'X-Mailer: PHP/' . phpversion()
  );

  $proto = isset($_SERVER["HTTPS"])? "https://" : "http://";
  $message = $message."\n\n\n".$proto.$_SERVER[ "SERVER_NAME" ];
  mail('yahsan2@gmail.com', $subject, $message,join('',$headers));

  file_put_contents("logs/receive.txt", "finish: sentMain\n", FILE_APPEND);
}

function inCIDR($ip, $cidr) {
    list($network, $mask_bit_len) = explode('/', $cidr);
    $host = 32 - $mask_bit_len;

    $net = ip2long($network) >> $host << $host; // 11000000101010000000000000000000
    $ip_net = ip2long($ip) >> $host << $host; // 11000000101010000000000000000000

    return $net === $ip_net;
}

function is_dev_server(){
  // development domain
  file_put_contents("logs/receive.txt", "server: ".$_SERVER[ "SERVER_NAME" ]."\n", FILE_APPEND);

  return (strpos($_SERVER[ "SERVER_NAME" ], "ndv.me") !== false || strpos($_SERVER[ "SERVER_NAME" ], "nd-dev.com") !== false);
}

function write_log($payload){
  file_put_contents("logs/receive.txt", "start: write_log\n", FILE_APPEND);

  $head_commit = $payload->head_commit;
  if(!empty($payload->ref))
    file_put_contents("logs/receive.txt", "branch: ".$payload->ref."\n", FILE_APPEND);
  if(!empty($head_commit))
    file_put_contents("logs/receive.txt", "message: ".$head_commit->message."\n", FILE_APPEND);
  if(!empty($head_commit->author))
    file_put_contents("logs/receive.txt", "author: ".$head_commit->author->name."\n", FILE_APPEND);


  if (!empty($payload)) {
    file_put_contents("logs/github.txt", "---------- ".date("Y/m/d/ H:i:s")." ----------\n\n", FILE_APPEND);
    if(!empty($payload->ref))
      file_put_contents("logs/github.txt", "branch: ".$payload->ref."\n\n", FILE_APPEND);
    file_put_contents("logs/github.txt", print_r($payload->head_commit,true)."\n", FILE_APPEND);
    file_put_contents("logs/github.txt", "\n------------------------------------------\n", FILE_APPEND);
  }

  file_put_contents("logs/receive.txt", "finish: write_log\n", FILE_APPEND);
}


function run_pull_script($payload,$branch){
  file_put_contents("logs/receive.txt", "start: pull_script\n", FILE_APPEND);
  exec("chmod +x deploy.sh");
  $res = exec("./deploy.sh ".$branch);
  file_put_contents("logs/receive.txt", "finish: pull_script\n", FILE_APPEND);

  sent_success_mail($payload, $res);
}

function sent_success_mail($payload,$res){
  $proto = isset($_SERVER["HTTPS"])? "https://" : "http://";
  $message = array(
      "Project:\n".$payload->repository->name,
      "URL:\n".$proto.$_SERVER[ "SERVER_NAME" ],
      "Date:\n".date("Y/m/d@H:i:s"),
      "Message:\n".$payload->head_commit->message,
      "Refs:\n".$payload->ref,
      "Github:\n".$payload->repository->url,
      "PullResponce:\n".$res,
      print_r($payload,FILE_APPEND)
    );
  sent_mail("SUCCESS: ".$payload->repository->name , join("\n\n",$message));
}


exit(0);

?>