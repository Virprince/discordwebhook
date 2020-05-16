<?php

namespace Bolt\Extension\Virprince\DiscordWebHook\Classes;

use Silex\Application;


/**
 * Cette classe a uniquement pour but de gérer l'envoi des données à Discord.
 */
class DataToDiscord
{
    private $dataToDiscord;

    public function __construct()
    {
        $this->dataToDiscord = [
            'json_data' => "",
            'webhookurl' => ""
        ];
    }

    public function setJsonData($jsonData)
    {
        $this->dataToDiscord['json_data'] = json_encode($jsonData, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
    }

    public function setWebHookUrl($webHookUrl)
    {
        $this->dataToDiscord['webhookurl'] = $webHookUrl;
    }

    public function getDataToDiscord()
    {
        return $this->dataToDiscord;
    }

    public static function sendMessage(Array $params)
    {
        // merge params and default to stop function if no webhookurl
        // $params = array_merge($default, $params);

        if ( $params['webhookurl'] === "" ) {
            trigger_error('Webhookurl requis', E_USER_WARNING);
            return;
        }

        $ch = curl_init( $params['webhookurl'] );
        curl_setopt( $ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
        curl_setopt( $ch, CURLOPT_POST, 1);
        curl_setopt( $ch, CURLOPT_POSTFIELDS, $params['json_data']);
        curl_setopt( $ch, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt( $ch, CURLOPT_HEADER, 0);
        curl_setopt( $ch, CURLOPT_RETURNTRANSFER, 1);

        $response = curl_exec( $ch );
        // If you need to debug, or find out why you can't send message uncomment line below, and execute script.
        curl_close( $ch );
    }

}