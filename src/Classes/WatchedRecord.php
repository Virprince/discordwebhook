<?php

namespace Bolt\Extension\Virprince\DiscordWebHook\Classes;

use Bolt\Storage\Entity\Content;
use Silex\Application;


/**
 * Cette classe a uniquement pour but de gérer les actions a effectuer sur les records
 */
class WatchedRecord
{
    /** @var Application Bolt's Application object */
    private $app;
    private $config;
    private $recordActions;

    public function __construct(Application $app)
    {
        $this->app = $app;
        $this->config = $app['discordwebhook.config'];
    }

    public function setRecordActions(array $recordActions)
    {
        $this->recordActions = $recordActions;
    }


    /**
     * On cherche parmis les actions de la config lesquelles contiennent le contenttype 
     *
     * @param   Content  $record  [$record description]
     * @param   array    $config  [$config description]
     *
     * @return  [array]            retourne un tableau avec tout les cas concerné par le contenttype.
     */
    public static function getRecordActions(Content $record, array $config)
    {
        $items = [];
        foreach ($config as $key => $item) {
            if ( is_array($item) && array_key_exists( 'contenttype', $item)) {
                
                // est ce un tableau ou une string ? 
                if (is_array($item['contenttype'])) {
                    foreach ($item['contenttype'] as $c) {
                        $record->getContentType()->__toString() === $c ? $items[$key] = $item : null;
                    }
                } else {
                    $record->getContentType()->__toString() === $item['contenttype'] ? $items[$key] = $item : null;
                }
            }
        }
        return $items;
    }

    /**
     * [getWatchedFieldsDifference description]
     *
     * @param   array    $action     [$action description]
     * @param   Content  $r_stored   [$r_stored description]
     * @param   Content  $r_updated  [$r_updated description]
     *
     * @return  [type]               [return description]
     */
    public static function getWatchedFieldsDifference(array $action, Content $r_stored, Content $r_updated)
    {
        $fields = [];
        $fieldsResults = [];
        if (array_key_exists( 'fields', $action['action']) ) {
            $fields = $action['action']['fields'];
        }

        foreach ($fields as $field => $value) {
            $getter = Tools::getterName($field);
            // Le champs est un repeater
            if (is_array($value)) {
                $r_stored_field = $r_stored->{$getter}()->serialize();
                $r_updated_field = $r_updated->{$getter}();

                $fieldsResults[$field] = ($r_updated_field == $r_stored_field);
            } else {
                // on compare les valeurs
                $r_stored_field = $r_stored->{$getter}(); 
                $r_updated_field = $r_updated->{$getter}(); 

                $fieldsResults[$field] = $r_stored_field === $r_updated_field ?  true : false ;
            }
        }
        return $fieldsResults;
    }

    /**
     * [isWatchedFieldsDifferent description]
     *
     * @param   array    $action     [$action description]
     * @param   Content  $r_stored   [$r_stored description]
     * @param   Content  $r_updated  [$r_updated description]
     *
     * @return  [type]               [return description]
     */
    public static function isWatchedFieldsDifferent(array $action, Content $r_stored, Content $r_updated)
    {
        $fieldsResults = self::getWatchedFieldsDifference($action, $r_stored, $r_updated);

        foreach($fieldsResults as $field){
            if (!$field) {
                return false;
            }
        }

        return true;
    }

    

}