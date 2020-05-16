<?php

namespace Bolt\Extension\Virprince\DiscordWebHook;

use Bolt\Asset\File\JavaScript;
use Bolt\Asset\File\Stylesheet;
use Bolt\Controller\Zone;
use Bolt\Events\StorageEvent;
use Bolt\Events\StorageEvents;
use Bolt\Extension\SimpleExtension;
use Bolt\Extension\Virprince\DiscordWebHook\Classes\DataToDiscord;
use Bolt\Extension\Virprince\DiscordWebHook\Classes\DiscordMessage;
use Bolt\Extension\Virprince\DiscordWebHook\Classes\DataUtils;
use Bolt\Extension\Virprince\DiscordWebHook\Classes\JsonData;
use Bolt\Extension\Virprince\DiscordWebHook\Classes\WatchedRecord;
use Bolt\Extension\Virprince\DiscordWebHook\Controller\ExampleController;
use Bolt\Extension\Virprince\DiscordWebHook\Listener\StorageEventListener;
use Bolt\Menu\MenuEntry;
use Bolt\Storage\Entity\Content;
use Silex\Application;
use Silex\ControllerCollection;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Twig\Markup;

/**
 * DiscordWebHook extension class.
 *
 * @author Your Name <virprince@gmail.com>
 */
class DiscordWebHookExtension extends SimpleExtension
{
    private $isWatched;

    public function __construct()
    {
        $this->isWatched = false;
    }

    private function setIsWatched(bool $value)
    {
        $this->isWatched = $value;
    }

    /**
     * {@inheritdoc}
     */
    public function registerFields()
    {
        /*
         * Custom Field Types:
         * You are not limited to the field types that are provided by Bolt.
         * It's really easy to create your own.
         *
         * This example is just a simple text field to show you
         * how to store and retrieve data.
         *
         * See also the documentation page for more information and a more complex example.
         * https://docs.bolt.cm/extensions/customfields
         */

        return [
            new Field\ExampleField(),
        ];
    }

    /**
     * {@inheritdoc}
     */
    protected function subscribe(EventDispatcherInterface $dispatcher)
    {
        /*
         * Event Listener:
         *
         * Did you know that Bolt fires events based on backend actions? Now you know! :)
         *
         * Let's register listeners for all 4 storage events.
         *
         * The first listener will be an inline function, the three other ones will be in a separate class.
         * See also the documentation page:
         * https://docs.bolt.cm/extensions/essentials#adding-storage-events
         */

        $dispatcher->addListener(StorageEvents::PRE_SAVE, [$this, 'onPreSave']);
        $dispatcher->addListener(StorageEvents::POST_SAVE, [$this, 'onPostSave']);

        // $storageEventListener = new StorageEventListener($this->getContainer(), $this->getConfig(), $this->isWatched);
        // $dispatcher->addListener(StorageEvents::POST_SAVE, [$storageEventListener, 'onPostSave']);
        // $dispatcher->addListener(StorageEvents::PRE_DELETE, [$storageEventListener, 'onPreDelete']);
        // $dispatcher->addListener(StorageEvents::POST_DELETE, [$storageEventListener, 'onPostDelete']);

    }

    /**
     * Handles PRE_SAVE storage event
     *
     * @param StorageEvent $event
     */
    public function onPreSave(StorageEvent $event)
    {
        // The ContentType of the record being saved
        $contenttype = $event->getContentType();

        // The record being saved
        $record = $event->getContent();

        // A flag to tell if the record was created, updated or deleted,
        // for more information see the page in the documentation
        $created = $event->isCreate();

        $id = $event->getId();

        // find if data watched in config are the same.
        $app = $this->getContainer();
        $config = $this->getConfig();

        // est ce que le contenttype est concerné par une action dans le config ? 
        $recordActions = WatchedRecord::getRecordActions($record, $config);
        $watchRecord = false;

            if (count($recordActions) > 0) {
                // si oui quelle est cette action et est ce qu'elle est concernée par l'event ? 
                foreach ($recordActions as $action) {
                    if (array_key_exists( 'action', $action) && $action['action'] === 'new' ) {
                        // cas d'un nouveau contenu
                        // Si on récupère la confirmation d'une création de contenu alors on crée l'objet pour le record.
                        $created ? $watchRecord = true : null;
                    }
                    if (array_key_exists( 'action', $action) && is_array($action['action']) ) {
                        // cas d'une update 
                        if (array_key_exists( 'type', $action['action']) && $action['action']['type'] === 'update' ) {
                            // il faut vérifier que les champs à surveiller ont changé
                            $repo = $app['storage']->getRepository($contenttype);
                            $isRecordIdentical = WatchedRecord::isWatchedFieldsDifferent($action, $repo->find($id), $record );
                                if (!$isRecordIdentical) {
                                    $watchRecord = true;
                                }


                        }
                    }

                }
    
            }
        // Statut de surveillance du record   
        $this->setIsWatched($watchRecord);
        
    }

    /**
     * [onPostSave description]
     *
     * @param   StorageEvent  $event  [$event description]
     *
     * @return  [type]                [return description]
     */
    public function onPostSave(StorageEvent $event)
    {
        $app = $this->getContainer();
        $config = $this->getConfig();
        // The record being saved
        $record = $event->getContent();        
        if ($this->isWatched) {
        
            // Nouvel objet pour le record
            $watchRecord = new WatchedRecord($app);
            // On récupère la liste des actions du config et on l'enregistre.
            $actions = WatchedRecord::getRecordActions($record, $config);
            $watchRecord->setRecordActions($actions);
            // Pour chaque action il faudra faire un envoi.
            foreach ($actions as $key => $action) {

                $message = JsonData::createMessage($record, $action);
                $jsonData = new JsonData($app);
                $jsonData->setAction($action);
                $jsonData->setEmbeds($action);
                $jsonData->setUsername();
                $jsonData->setContent($message);

               
                $json_data = $jsonData->createJsonData();
                
                if (array_key_exists('webhook', $action)) {
                    // on procède à un envoi
                    $sendToDiscord = new DataToDiscord();
                    $sendToDiscord->setJsonData($json_data);
                    $sendToDiscord->setWebHookUrl($action['webhook']);
                    
                    DataToDiscord::sendMessage($sendToDiscord->getDataToDiscord());
                }

            }

        }

    }


    /**
     * {@inheritdoc}
     */
    protected function registerAssets()
    {
        return [
            // Web assets that will be loaded in the frontend
            new Stylesheet('extension.css'),
            new JavaScript('extension.js'),
            // Web assets that will be loaded in the backend
            // Note that ::create() requires Bolt 3.3+
            Stylesheet::create('clippy.js/clippy.css')->setZone(Zone::BACKEND),
            JavaScript::create('clippy.js/clippy.min.js')->setZone(Zone::BACKEND),
        ];
    }

    /**
     * {@inheritdoc}
     */
    protected function registerTwigFunctions()
    {
        return [
            'my_twig_function' => 'myTwigFunction',
        ];
    }

    /**
     * The callback function when {{ my_twig_function() }} is used in a template.
     *
     * @return string
     */
    public function myTwigFunction()
    {
        $context = [
            'something' => mt_rand(),
        ];

        $html = $this->renderTemplate('extension.twig', $context);

        return new Markup($html, 'UTF-8');
    }

    /**
     * {@inheritdoc}
     *
     * Extending the backend menu:
     *
     * You can provide new Backend sites with their own menu option and template.
     *
     * Here we will add a new route to the system and register the menu option in the backend.
     *
     * You'll find the new menu option under "Extras".
     */
    protected function registerMenuEntries()
    {
        /*
         * Define a menu entry object and register it:
         *   - Route http://example.com/bolt/extensions/my-custom-backend-page-route
         *   - Menu label 'MyExtension Admin'
         *   - Menu icon a Font Awesome small child
         *   - Required Bolt permissions 'settings'
         */
        $adminMenuEntry = (new MenuEntry('my-custom-backend-page', 'my-custom-backend-page-route'))
            ->setLabel('MyExtension Admin')
            ->setIcon('fa:child')
            ->setPermission('settings')
        ;

        return [$adminMenuEntry];
    }

    /**
     * We can share our configuration as a service so our other classes can use it.
     *
     * {@inheritdoc}
     */
    protected function registerServices(Application $app)
    {
        $app['discordwebhook.config'] = $app->share(function ($app) {
            return $this->getConfig();
        });

        $app['discordmessage'] = $app->share(
            function ($app) {
                return new DiscordMessage($app, $this->getConfig());
            }
        );
        $app['datautils'] = $app->share(
            function ($app) {
                return new DataUtils($app, $this->getConfig());
            }
        );
    }

    /**
     * {@inheritdoc}
     *
     * Mount the ExampleController class to all routes that match '/example/url/*'
     *
     * To see specific bindings between route and controller method see 'connect()'
     * function in the ExampleController class.
     */
    protected function registerFrontendControllers()
    {
        return [
            '/example/url' => new ExampleController(),
        ];
    }

    /**
     * {@inheritdoc}
     *
     * This first route will be handled in this extension class,
     * then we switch to an extra controller class for the routes.
     */
    protected function registerFrontendRoutes(ControllerCollection $collection)
    {
        $collection->match('/example/url', [$this, 'routeExampleUrl']);
    }

    /**
     * Handles GET requests on the /example/url route.
     *
     * @param Request $request
     *
     * @return Response
     */
    public function routeExampleUrl(Request $request)
    {
        $response = new Response('Hello, Bolt!', Response::HTTP_OK);

        return $response;
    }

    /**
     * {@inheritdoc}
     */
    protected function registerBackendRoutes(ControllerCollection $collection)
    {
        $collection->match('/extend/my-custom-backend-page-route', [$this, 'exampleBackendPage']);
    }

    /**
     * Handles GET requests on /bolt/my-custom-backend-page and return a template.
     *
     * @param Request $request
     *
     * @return string
     */
    public function exampleBackendPage(Request $request)
    {
        $html = $this->renderTemplate('custom_backend_site.twig', ['title' => 'My Custom Page']);

        return new Markup($html, 'UTF-8');
    }
}
