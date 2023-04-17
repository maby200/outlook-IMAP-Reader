<?php
include __DIR__.'/vendor/autoload.php'; 

// Script para obtener bandeja de entrada de docs@enterprise.com

use Webklex\PHPIMAP\ClientManager;
// To use dotenv: `composer require vlucas/phpdotenv`
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

// Usar Application (client) ID
$CLIENT_ID=$_ENV['CLIENT_ID'];

// Usar Scret value
$CLIENT_SECRET=$_ENV['CLIENT_SECRET'];

// Usar Directory (tenant) ID
$TENANT=$_ENV['TENANT'];

// Para esta cuenta, se tiene el refresh token
$REFRESH_TOKEN=$_ENV['REFRESH_TOKEN'];

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

// SOLO poner CURLOPT_SSL_VERIFYPEER en FALSE si se está en LOCALHOST !!
curl_setopt($ch,CURLOPT_SSL_VERIFYPEER, false);// NO ESTÁS EN LOCALHOST? BORRA ESTA lINEA

$oResult=curl_exec($ch);

echo("Obteniendo token.... \n");

if(!empty($oResult)){
    
    echo("Conectándose a la bandeja de entrada... \n");
    
    // La token viene en un objeto JSON
    $array_php_resul = json_decode($oResult,true);
    
    if( isset($array_php_resul["access_token"]) ){

        $access_token = $array_php_resul["access_token"];
                   
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
            // Conexion al IMAP Server
            $client->connect();
            $folder = $client->getFolder('INBOX');
            $all_messages = $folder->query()->all()->get();

            echo "<h1>Asunto de mensajes:</h1>", "\n";
            $counter = 1;

            foreach($all_messages as $message){
              echo "<h2>Mensaje $counter</h2>";
              $subject = $message->getSubject();
              $body = $message->getHTMLBody();
              echo $subject.'<br />';
              echo "\n";
              echo $body, '<br />', '<hr>';
              $counter += 1;
            }
        }catch (Exception $e) {
            echo 'Exception : ',  $e->getMessage(), "\n";
        }

    }else{
        echo('Error : '.$array_php_resul["error_description"]); 
    }
}
?>
