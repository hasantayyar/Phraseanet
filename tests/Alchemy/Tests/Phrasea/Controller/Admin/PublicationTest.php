<?php

namespace Alchemy\Tests\Phrasea\Controller\Admin;

class PublicationTest extends \PhraseanetAuthenticatedWebTestCase
{
    public static $account = null;
    public static $api = null;
    protected $client;

    public static function tearDownAfterClass()
    {
        self::$account = self::$api = null;
        parent::tearDownAfterClass();
    }

    public function testList()
    {
        $crawler = self::$DI['client']->request('GET', '/admin/publications/list/');
        $pageContent = self::$DI['client']->getResponse()->getContent();
        $this->assertTrue(self::$DI['client']->getResponse()->isOk());
        $feeds = self::$DI['app']['EM']->getRepository('Phraseanet:Feed')->getAllForUser(self::$DI['app']['acl']->get(self::$DI['user']));

        foreach ($feeds as $feed) {
            $this->assertRegExp('/\/admin\/publications\/feed\/' . $feed->getId() . '/', $pageContent);
            if ($feed->getCollection(self::$DI['app']) != null) {
                $this->assertRegExp('/' . $feed->getCollection()->get_label(self::$DI['app']['locale']) . '/', $pageContent);
            }
            if ($feed->isOwner(self::$DI['user'])) {
                $this->assertEquals(1, $crawler->filterXPath("//form[@action='/admin/publications/feed/" . $feed->getId() . "/delete/']")->count());
            }
        }
    }

    public function testCreate()
    {
        $feeds = self::$DI['app']['EM']->getRepository('Phraseanet:Feed')->getAllForUser(self::$DI['app']['acl']->get(self::$DI['user']));
        $count = sizeof($feeds);

        $crawler = self::$DI['client']->request('POST', '/admin/publications/create/', ["title"    => "hello", "subtitle" => "coucou", "base_id"  => self::$DI['collection']->get_base_id()]);

        $this->assertTrue(self::$DI['client']->getResponse()->isRedirect('/admin/publications/list/'));

        $feeds = self::$DI['app']['EM']->getRepository('Phraseanet:Feed')->getAllForUser(self::$DI['app']['acl']->get(self::$DI['user']));
        $count_after = sizeof($feeds);
        $this->assertGreaterThan($count, $count_after);
    }

    public function testGetFeed()
    {
        $feed = self::$DI['app']['EM']->find('Phraseanet:Feed', 1);
        $crawler = self::$DI['client']->request('GET', '/admin/publications/feed/' . $feed->getId() . '/');
        $this->assertTrue(self::$DI['client']->getResponse()->isOk());
        $this->assertEquals(1, $crawler->filterXPath("//form[@action='/admin/publications/feed/" . $feed->getId() . "/update/']")->count());
        $this->assertEquals(1, $crawler->filterXPath("//input[@value='Feed test, YOLO!']")->count());
    }

    public function testUpdatedFeedException()
    {
        $feed = self::$DI['app']['EM']->find('Phraseanet:Feed', 1);

        self::$DI['client']->request("POST", "/admin/publications/feed/" . $feed->getId() . "/update/", [
            'title'    => 'test'
            , 'subtitle' => 'test'
            , 'public'   => '1'
        ]);

        $feed = self::$DI['app']['EM']->find('Phraseanet:Feed', $feed->getId());

        $this->assertTrue(
            strpos(
                self::$DI['client']->getResponse()->headers->get('Location')
                , '/admin/publications/list/'
            ) === 0);

        $this->assertEquals('test', $feed->getTitle());
        $this->assertEquals('test', $feed->getSubtitle());
        $this->assertTrue($feed->isPublic());
        $this->assertNull($feed->getCollection(self::$DI['app']));
    }

    public function testUpdatedFeedOwner()
    {
        $feed = self::$DI['app']['EM']->find('Phraseanet:Feed', 1);

        self::$DI['client']->request("POST", "/admin/publications/feed/" . $feed->getId() . "/update/", [
            'title'    => 'test'
            , 'subtitle' => 'test'
            , 'public'   => '1'
            , 'base_id'  => self::$DI['collection']->get_base_id()
        ]);

        $this->assertTrue(
            strpos(
                self::$DI['client']->getResponse()->headers->get('Location')
                , '/admin/publications/list/'
            ) === 0);

        $feed = self::$DI['app']['EM']->find('Phraseanet:Feed', $feed->getId());

        $collection = $feed->getCollection(self::$DI['app']);

        $this->assertEquals('test', $feed->getTitle());
        $this->assertEquals('test', $feed->getSubtitle());
        $this->assertTrue($feed->isPublic());
        $this->assertEquals(self::$DI['collection']->get_base_id(), $collection->get_base_id());

        $this->assertTrue(
            strpos(
                self::$DI['client']->getResponse()->headers->get('Location')
                , '/admin/publications/list/'
            ) === 0);
    }

    public function testIconUploadErrorOwner()
    {
        $feed = self::$DI['app']['EM']->find('Phraseanet:Feed', 3);

        self::$DI['client']->request("POST", "/admin/publications/feed/" . $feed->getId() . "/iconupload/", [], [], ['HTTP_ACCEPT' => 'application/json']);

        $response = self::$DI['client']->getResponse();

        $this->assertEquals(403, $response->getStatusCode());
    }

    public function testIconUploadErrorFileData()
    {
        $feed = self::$DI['app']['EM']->find('Phraseanet:Feed', 1);

        self::$DI['client']->request(
            "POST"
            , "/admin/publications/feed/" . $feed->getId() . "/iconupload/"
            , []
            , ['Filedata' => ['error'   => 1]]
        );
        $response = self::$DI['client']->getResponse();
        $this->assertTrue($response->isOk());

        $content = json_decode($response->getContent());

        $this->assertFalse($content->success);
    }

    public function testIconUploadErrorFileType()
    {
        $feed = self::$DI['app']['EM']->find('Phraseanet:Feed', 1);

        self::$DI['client']->request(
            "POST"
            , "/admin/publications/feed/" . $feed->getId() . "/iconupload/"
            , []
            , ['Filedata' => ['error'    => 0, 'tmp_name' => __DIR__ . '/../../../../../files/test007.ppt']]
        );
        $response = self::$DI['client']->getResponse();
        $this->assertTrue($response->isOk());

        $content = json_decode($response->getContent());

        $this->assertFalse($content->success);
    }

    public function testIconUpload()
    {
        $feed = self::$DI['app']['EM']->find('Phraseanet:Feed', 1);

        $files = [
            'files' => [
                new \Symfony\Component\HttpFoundation\File\UploadedFile(
                    __DIR__ . '/../../../../../files/logocoll.gif', 'logocoll.gif'
                )
            ]
        ];

        self::$DI['client']->request(
            "POST"
            , "/admin/publications/feed/" . $feed->getId() . "/iconupload/"
            , []
            , $files
        );

        $response = self::$DI['client']->getResponse();

        $this->assertTrue($response->isOk());

        $content = json_decode($response->getContent());

        $this->assertTrue($content->success);
    }

    public function testAddPublisher()
    {
        $feed = self::$DI['app']['EM']->find('Phraseanet:Feed', 1);

        self::$DI['client']->request("POST", "/admin/publications/feed/" . $feed->getId() . "/addpublisher/", [
            'usr_id' => self::$DI['user_alt1']->getId()
        ]);

        $response = self::$DI['client']->getResponse();
        $this->assertTrue($response->isRedirect());

        $feed = self::$DI['app']['EM']->find('Phraseanet:Feed', $feed->getId());
        $publishers = $feed->getPublishers();

        $this->assertTrue($feed->isPublisher(self::$DI['user_alt1']));
        $this->assertTrue(
            strpos(
                self::$DI['client']->getResponse()->headers->get('Location')
                , '/admin/publications/feed/' . $feed->getId() . '/'
            ) === 0);
    }

    public function testAddPublisherException()
    {
        $feed = self::$DI['app']['EM']->find('Phraseanet:Feed', 1);

        self::$DI['client']->request("POST", "/admin/publications/feed/" . $feed->getId() . "/addpublisher/");

        $feed = self::$DI['app']['EM']->find('Phraseanet:Feed', $feed->getId());
        $response = self::$DI['client']->getResponse();
        $this->assertTrue($response->isRedirect());
        $this->assertTrue(
            strpos(
                self::$DI['client']->getResponse()->headers->get('Location')
                , '/admin/publications/feed/' . $feed->getId() . '/?err'
            ) === 0);
    }

    public function testRemovePublisher()
    {
        $feed = self::$DI['app']['EM']->find('Phraseanet:Feed', 1);

        self::$DI['client']->request("POST", "/admin/publications/feed/" . $feed->getId() . "/removepublisher/", [
            'usr_id' => self::$DI['user_alt1']->getId()
        ]);

        $response = self::$DI['client']->getResponse();
        $this->assertTrue($response->isRedirect());

        $feed = self::$DI['app']['EM']->find('Phraseanet:Feed', $feed->getId());
        $publishers = $feed->getPublishers();

        $this->assertFalse(isset($publishers[self::$DI['user_alt1']->getId()]));
        $this->assertTrue(
            strpos(
                self::$DI['client']->getResponse()->headers->get('Location')
                , '/admin/publications/feed/' . $feed->getId() . '/'
            ) === 0);
    }

    public function testRemovePublisherException()
    {
        $feed = self::$DI['app']['EM']->find('Phraseanet:Feed', 1);

        self::$DI['client']->request("POST", "/admin/publications/feed/" . $feed->getId() . "/removepublisher/");

        $response = self::$DI['client']->getResponse();
        $this->assertTrue($response->isRedirect());

        $feed = self::$DI['app']['EM']->find('Phraseanet:Feed', $feed->getId());

        $this->assertTrue(
            strpos(
                self::$DI['client']->getResponse()->headers->get('Location')
                , '/admin/publications/feed/' . $feed->getId() . '/?err'
            ) === 0);
    }

    public function testDeleteFeed()
    {
        $feed = self::$DI['app']['EM']->find('Phraseanet:Feed', 1);

        self::$DI['client']->request("POST", "/admin/publications/feed/" . $feed->getId() . "/delete/");

        $response = self::$DI['client']->getResponse();
        $this->assertTrue($response->isRedirect());
        $this->assertNull($feed->getId());
    }
}
