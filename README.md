# Mail Reader for Outlook, Office 365 accounts with OAuth IMAP protocol

This repo is just a summary of an answer on [StackOverflow](https://stackoverflow.com/a/74735069/10095656)

Bear in mind that there is no need that your mail have the @outlook.com extension, it can be hosted by microsoft with any domain such as this example: `docs@enterprise.com`.

### Before seeing any of the scripts below, let me tell you that you can run them in two ways (at least 2 ways is what I know):

1. Run you script on terminal:

    Let's say you have `script.php`, to run this:
    
    ```bash
    php -f script.php
    ```
2. Run your script on web browser:

    You have a `script.php`.

    Open a terminal and go to the folder where your script is.

    Then write the following (you can choose any port, in my case I choose 4050)
    ```bash
    php -S localhost:4050
    #                ^^^^ you can change this port
    ```
    Open your favorite browser and go to: <u>localhost:4050/script.php</u>

    Remember to change to the port you use and the name of the script you created.

    Let's begin.


## 1 - Configure your mail box in Azure

This [link](https://learn.microsoft.com/en-us/azure/active-directory/develop/quickstart-register-app) takes you to the steps to register an app.

You will need :

- The client Id
- The tenant Id
- The secret client
- The redirect Uri (Set it to http://localhost/test_imap)


## 2 Grab a code to get a token
By creating a script using php: Eg: `step2.php`
```php
<?php
require 'vendor/autoload.php';

$TENANT="...";
$CLIENT_ID="...";
$SCOPE="https://outlook.office365.com/IMAP.AccessAsUser.All";
$REDIRECT_URI="http://localhost/test_imap";

$authUri = 'https://login.microsoftonline.com/' . $TENANT
           . '/oauth2/v2.0/authorize?client_id=' . $CLIENT_ID
           . '&scope=' . $SCOPE
           . '&redirect_uri=' . urlencode($REDIRECT_URI)
           . '&response_type=code'
           . '&approval_prompt=auto';

echo($authUri);
?>
```
As [FoxInDisguise](https://stackoverflow.com/a/74491148/10095656) says:

> You'll be redirected to <u>http://localhost/test_imap?code=LmpxSnTw...&session_state=b5d713....</u> <br /> Save the code **(remove the '&' at the end !)** and the session state inside the url. These codes expired after a few hours !

## 3 Get an access token
In this script `CLIENT_SECRET` is the one named as **Secret Value** in your microsoft
```php
<?php
$CLIENT_ID="...";
$CLIENT_SECRET="...";
$TENANT="...";
$SCOPE="https://outlook.office365.com/IMAP.AccessAsUser.All offline_access";
$CODE="...";
$REDIRECT_URI="http://localhost/test_imap";

echo "Trying to authenticate the session..";

$url= "https://login.microsoftonline.com/$TENANT/oauth2/v2.0/token";

$param_post_curl = [ 
 'client_id'=>$CLIENT_ID,
 'scope'=>$SCOPE,
 'code'=>$CODE,
 'session_state'=>$SESSION,
 'client_secret'=>$CLIENT_SECRET,
 'redirect_uri'=>$REDIRECT_URI,
 'grant_type'=>'authorization_code' ];

$ch=curl_init();
curl_setopt($ch,CURLOPT_URL,$url);
curl_setopt($ch,CURLOPT_POSTFIELDS, http_build_query($param_post_curl));
curl_setopt($ch,CURLOPT_POST, 1);
curl_setopt($ch,CURLOPT_RETURNTRANSFER, true);

$oResult=curl_exec($ch);

echo "result : \n";

var_dump($oResult);
?>
```
In this part, a token and token_refresh will be retrieved, store both of them.

As [FoxInDisguise](https://stackoverflow.com/a/74491148/10095656) says:

> If you don't have the "refresh_token" you have forgot to put "offline_access" in the scope)

## 4 Connect to mail box

In this case the answer's author choose to use [php-imap](https://www.php-imap.com/). I am doing the same due to its simplicity to connect to mail box.

```php
<?php
include __DIR__.'/vendor/autoload.php'; 

use Webklex\PHPIMAP\ClientManager;

$access_token="...";

$cm = new ClientManager();

$client = $cm->make([
    'host' => 'outlook.office365.com',
    'port' => 993,
    'encryption' => 'ssl', // 'tls',
    'validate_cert' => false,
    'username' => 'docs@enterprise.com',
    'password' => $access_token,
    'protocol' => 'imap',
    'authentication' => "oauth",
]);


try {
    $client->connect();
    $folder = $client->getFolder('INBOX');
    $all_messages = $folder->query()->all()->get();
    
    echo "<h1>Asunto de mensajes:</h1>", "\n";

    foreach($all_messages as $message){
      // Just fetching the mail's subject for reading simplicity:
      echo $message->getSubject().'<br />';
      echo "\n";
    }
    
} catch (Exception $e) {
    echo 'Exception : ', $e->getMessage(), "\n";
}

?>
```
You have finally made a connection and can see messages. But what happens if  you want to do the same tomorrow or in next days?

## 5 Connecting to mail box everyday:
Since access_token last less than a day, the way to connect to mail box in the future is to use the refresh_token: <br />
For this step we just have to run this script. I assume you have already saved your refresh_token.

```php
include __DIR__.'/vendor/autoload.php'; 
    
use Webklex\PHPIMAP\ClientManager;

$CLIENT_ID="c-9c-....";
$CLIENT_SECRET="Y~tN...";
$TENANT="5-48...";
$REFRESH_TOKEN="EebH9H8S7...";

$url= "https://login.microsoftonline.com/$TENANT/oauth2/v2.0/token";

$param_post_curl = [ 
 'client_id'=>$CLIENT_ID,
 'client_secret'=>$CLIENT_SECRET,
 'refresh_token'=>$REFRESH_TOKEN,
 'grant_type'=>'refresh_token' ];

$ch=curl_init();

curl_setopt($ch,CURLOPT_URL,$url);
curl_setopt($ch,CURLOPT_POSTFIELDS, http_build_query($param_post_curl));
curl_setopt($ch,CURLOPT_POST, 1);
curl_setopt($ch,CURLOPT_RETURNTRANSFER, true);
//ONLY USE CURLOPT_SSL_VERIFYPEER AT FALSE IF YOU ARE IN LOCALHOST !!!
curl_setopt($ch,CURLOPT_SSL_VERIFYPEER, false);// NOT IN LOCALHOST ? ERASE IT !

$oResult=curl_exec($ch);

echo("Trying to get the token.... \n");

if(!empty($oResult)){
    
    echo("Connecting to the mail box... \n");
    
    //The token is a JSON object
    $array_php_resul = json_decode($oResult,true);
    
    if( isset($array_php_resul["access_token"]) ){

        $access_token = $array_php_resul["access_token"];

        //$cm = new ClientManager($options = ["options" => ["debug" => true]]);                     
        $cm = new ClientManager();                      
        $client = $cm->make([
            'host'          => 'outlook.office365.com',                
            'port'          => 993,
            'encryption'    => 'ssl',
            'validate_cert' => false,
            'username'      => 'docs@enterprise.com',
            'password'      => $access_token,
            'protocol'      => 'imap',
            'authentication' => "oauth"
        ]);
        
        try {
            //Connect to the IMAP Server
            $client->connect();
        }catch (Exception $e) {
            echo 'Exception : ',  $e->getMessage(), "\n";
        }

    }else{
        echo('Error : '.$array_php_resul["error_description"]); 
    }
}   
```