<?php

namespace Bolt\Extension\Virprince\DiscordWebHook\Classes;

use Silex\Application;
use Bolt\Storage\Entity\Content;
use DateTime;

/**
 * Cette classe a uniquement pour but de générer l'objet Json_data
 */
class JsonData
{
    /** @var Application Bolt's Application object */
    private $app;
    private $config;
    private $action;

    private $tts;
    private $content;
    private $username;
    private $embeds;

    const EMBEDS_KEYS = [
        'title'       => "",
        'type'        => "",
        'description' => "",
        'url'         => "",
        'timestamp'   => "",
        'color'       => "",
        'footer'      => [
            'text'     => "",
            'icom_url' => ""
        ],
        'image' => [
            'url' => ""
        ],
        'thumbnail' => [
            'url' => ""
        ],
        'author' => [
            'name' => "",
            'url'  => "",
        ],
        "fields" => [
            [
                'name'   => "",
                'value'  => "",
                'inline' => false
            ]
        ]
    ];

    public function __construct(Application $app)
    {
        $this->app = $app;
        $this->config = $app['discordwebhook.config'];

        $this->content   = 'Oups';
        $this->username  = false;
        $this->embeds    = false;
        $this->tts       = false;
        $this->action    = [];
    }

    /**
     * Defini la valeur du message
     *
     * @param   string  $message  [$message description]
     *
     * @return  [type]            [return description]
     */
    public function setContent(string $message)
    {
        $this->content = $message;
    }

    /**
     * Défini la valeur du username
     *
     * @param   string  $username  [$username description]
     *
     * @return  [type]             [return description]
     */
    public function setUsername($username = null)
    {
        if ( is_null($username) && count($this->action) > 0 && array_key_exists('username', $this->action)) {
            $this->username = $this->action['username'];
        } else if(is_null($username) && count($this->action) === 0) {
            $this->username = false;
        } else {
            $this->username = $username;
        }
    }

    /**
     * Défini la valeur de tts (text to speach)
     *
     * @param   bool  $tts  par défaut à false
     *
     * @return  [type]      [return description]
     */
    public function setTts(bool $tts)
    {
        $this->tts = $tts;
    }

    /**
     * Défini la valeur de l'objet Embed
     * False s'il n'y en a pas dans la configuration
     *
     * @param   Array  $data  [$data description]
     *
     * @return  [type]        [return description]
     */
    public function setEmbeds(Array $data)
    {
        $embeds = self::createEmbeds($data);

        !$embeds ? $this->embeds = false : $this->embeds = [$embeds];
    }

    /**
     * Défini la valeur de l'action à faire
     *
     * @param   Array  $action  [$action description]
     *
     * @return  [type]          [return description]
     */
    public function setAction(Array $action)
    {
        $this->action = $action;
    }
    

    /**
     * Défini l'objet Embed en fonction des clés obligatoires 
     * et de ce qui est fourni dans la configuration.
     *
     * @param   Array  $data  [$data description]
     *
     * @return  [type]        [return description]
     */
    public static function createEmbeds(Array $data)
    {
        if (!array_key_exists('embeds', $data)) {
           return false;
        }

        $embeds = [];
        $embeds = array_merge($embeds, self::EMBEDS_KEYS);
        

        // on regarde si la liste des clés est compatible
        foreach (self::EMBEDS_KEYS as $key => $value) {
            // pour chaque clé
            if (array_key_exists( $key, $data)) {

                // si la clé obligatoire est présente dans la variable 
                // mais qu'elle est un tableau on vérifie les clés à l'intérieur.
                if (is_array($value)) {
                    $temp = [];
                    foreach ($value as $k => $v) {
                        if (array_key_exists( $k, $data[$key])) {
                            $temp[$k]=$data[$key][$k];
                        }
                    }
                    $embeds[$key] = $temp;
                } else {
                    // Si la clé obligatoire est présente dans la variable 
                    // on l'enregistre avec sa valeur directement
                    $embeds[$key] = $data[$key];
                }
            }
        }

        // on ajoute le timestamp
        $embeds['timestamp'] = date("c", strtotime("now"));
        return $embeds;

    }

    /**
     * [createMessage description]
     *
     * @param   Content  $record    [$record description]
     * @param   Array    $c_action  [$c_action description]
     *
     * @return  [type]              [return description]
     */
    public static function createMessage( Content $record, Array $c_action)
    {
        $message_pattern = array_key_exists('message', $c_action['options']) ? $c_action['options']['message'] : "";

        // on recherche les champs demandés dans la config
        $fields = array_key_exists('fields', $c_action['options']) ? $c_action['options']['fields'] : [];

        // on retourne le message si c'est vide
        if (count($fields) === 0) {
            return $message_pattern;
        }

        // génération d'un tableau avec les données du record pour le message.
        $fieldsData = [];
        foreach ($fields as $key => $field) {
            
            // TODO Option pour le formatage des champs date, pour le moment on décide arbitrairement.
            // TODO Option pour le formatage des champs repeater, pour le moment on décide arbitrairement.
            
            $getter = Tools::getterName($key);
            if (is_array($field)) {
                // récupération des données sérializé du record
                $data = $record->{$getter}()->serialize();
                $string = '';

                // pour chaque objet dans les données
                foreach ($data as  $o) {
                    // on compare pour chaque champs dans la config
                    foreach ($field as $f) {
                        // si le champ existe on le traite
                        if (array_key_exists($f, $o)) {
                            // on le modifie si c'est une date
                            $o[$f] = $o[$f] instanceof DateTime ? $o[$f]->format('d/m/Y à H:i:s'): $o[$f];
                            $string .= $o[$f].' | ';
                        }
                    }
                    $string .= "\r\n"; // saut à la ligne à la fin du groupe de champ répété
                }
                            
                $fieldsData[$key] = $string;
            } else {
                $data = $record->{$getter}();
                $data = $data instanceof DateTime ? $data->format('d/m/Y à H:i:s'): $data;
                $fieldsData[$key] = $data;
            }

            $message_pattern = str_replace('['.$key.']', $fieldsData[$key], $message_pattern);

        }

        return $message_pattern;
    }

    /**
     * retourne l'objet json_data prêt à être envoyé à discord.
     *
     * @return  [array]  [return description]
     */
    public function createJsonData()
    {
        $jsonData = [];
        $jsonData['content'] = $this->content;
        $jsonData['tts'] = $this->tts;
        !$this->username ? null : $jsonData['username'] = $this->username;
        !$this->embeds ? null : $jsonData['embeds'] = $this->embeds;

        return $jsonData;

    }

}