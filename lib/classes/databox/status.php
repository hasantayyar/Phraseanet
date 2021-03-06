<?php

/*
 * This file is part of Phraseanet
 *
 * (c) 2005-2014 Alchemy
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Alchemy\Phrasea\Application;
use MediaAlchemyst\Specification\Image as ImageSpecification;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\File\Exception\FileException;

class databox_status
{
    /**
     *
     * @var Array
     */
    private static $_status = [];

    /**
     *
     * @var Array
     */
    protected static $_statuses;

    /**
     *
     * @var Array
     */
    private $status = [];

    /**
     *
     * @var string
     */
    private $path = '';

    /**
     *
     * @var string
     */
    private $url = '';

    /**
     *
     * @param  int    $sbas_id
     * @return status
     */
    private function __construct(Application $app, $sbas_id)
    {
        $this->status = [];

        $path = $url = false;

        $sbas_params = phrasea::sbas_params($app);

        if ( ! isset($sbas_params[$sbas_id])) {
            return;
        }

        $uniqid = md5(implode('-', [
            $sbas_params[$sbas_id]["host"],
            $sbas_params[$sbas_id]["port"],
            $sbas_params[$sbas_id]["dbname"]
        ]));

        $path = $this->path = $app['root.path'] . "/config/status/" . $uniqid;
        $url = $this->url = "/custom/status/" . $uniqid;

        $databox = $app['phraseanet.appbox']->get_databox((int) $sbas_id);
        $xmlpref = $databox->get_structure();
        $sxe = simplexml_load_string($xmlpref);

        if ($sxe !== false) {

            foreach ($sxe->statbits->bit as $sb) {
                $bit = (int) ($sb["n"]);
                if ($bit < 4 && $bit > 31)
                    continue;

                $this->status[$bit]["labeloff"] = (string) $sb['labelOff'];
                $this->status[$bit]["labelon"] = (string) $sb['labelOn'];

                foreach ($app['locales.available'] as $code => $language) {
                    $this->status[$bit]['labels_on'][$code] = null;
                    $this->status[$bit]['labels_off'][$code] = null;
                }

                foreach ($sb->label as $label) {
                    $this->status[$bit]['labels_'.$label['switch']][(string) $label['code']] = (string) $label;
                }

                foreach ($app['locales.available'] as $code => $language) {
                    $this->status[$bit]['labels_on_i18n'][$code] = '' !== trim($this->status[$bit]['labels_on'][$code]) ? $this->status[$bit]['labels_on'][$code] : $this->status[$bit]["labelon"];
                    $this->status[$bit]['labels_off_i18n'][$code] = '' !== trim($this->status[$bit]['labels_off'][$code]) ? $this->status[$bit]['labels_off'][$code] : $this->status[$bit]["labeloff"];
                }

                $this->status[$bit]["img_off"] = null;
                $this->status[$bit]["img_on"] = null;

                if (is_file($path . "-stat_" . $bit . "_0.gif")) {
                    $this->status[$bit]["img_off"] = $url . "-stat_" . $bit . "_0.gif?etag=".md5_file($path . "-stat_" . $bit . "_0.gif");
                    $this->status[$bit]["path_off"] = $path . "-stat_" . $bit . "_0.gif";
                }
                if (is_file($path . "-stat_" . $bit . "_1.gif")) {
                    $this->status[$bit]["img_on"] = $url . "-stat_" . $bit . "_1.gif?etag=".md5_file($path . "-stat_" . $bit . "_1.gif");
                    $this->status[$bit]["path_on"] = $path . "-stat_" . $bit . "_1.gif";
                }

                $this->status[$bit]["searchable"] = isset($sb['searchable']) ? (int) $sb['searchable'] : 0;
                $this->status[$bit]["printable"] = isset($sb['printable']) ? (int) $sb['printable'] : 0;
            }
        }
        ksort($this->status);

        return $this;
    }

    public static function getStatus(Application $app, $sbas_id)
    {

        if ( ! isset(self::$_status[$sbas_id]))
            self::$_status[$sbas_id] = new databox_status($app, $sbas_id);

        return self::$_status[$sbas_id]->status;
    }

    public static function getDisplayStatus(Application $app)
    {
        if (self::$_statuses) {
            return self::$_statuses;
        }

        $sbas_ids = $app['acl']->get($app['authentication']->getUser())->get_granted_sbas();

        $statuses = [];

        foreach ($sbas_ids as $databox) {
            try {
                $statuses[$databox->get_sbas_id()] = $databox->get_statusbits();
            } catch (\Exception $e) {

            }
        }

        self::$_statuses = $statuses;

        return self::$_statuses;
    }

    public static function getSearchStatus(Application $app)
    {
        $statuses = [];

        $sbas_ids = $app['acl']->get($app['authentication']->getUser())->get_granted_sbas();

        $see_all = [];

        foreach ($sbas_ids as $databox) {
            $see_all[$databox->get_sbas_id()] = false;

            foreach ($databox->get_collections() as $collection) {
                if ($app['acl']->get($app['authentication']->getUser())->has_right_on_base($collection->get_base_id(), 'chgstatus')) {
                    $see_all[$databox->get_sbas_id()] = true;
                    break;
                }
            }
            try {
                $statuses[$databox->get_sbas_id()] = $databox->get_statusbits();
            } catch (\Exception $e) {

            }
        }

        $stats = [];

        foreach ($statuses as $sbas_id => $status) {

            $see_this = isset($see_all[$sbas_id]) ? $see_all[$sbas_id] : false;

            if ($app['acl']->get($app['authentication']->getUser())->has_right_on_sbas($sbas_id, 'bas_modify_struct')) {
                $see_this = true;
            }

            foreach ($status as $bit => $props) {

                if ($props['searchable'] == 0 && ! $see_this)
                    continue;

                $set = false;
                if (isset($stats[$bit])) {
                    foreach ($stats[$bit] as $k => $s_desc) {
                        if (mb_strtolower($s_desc['labelon']) == mb_strtolower($props['labelon'])
                            && mb_strtolower($s_desc['labeloff']) == mb_strtolower($props['labeloff'])) {
                            $stats[$bit][$k]['sbas'][] = $sbas_id;
                            $set = true;
                        }
                    }
                    if (! $set) {
                        $stats[$bit][] = [
                            'sbas'            => [$sbas_id],
                            'labeloff'        => $props['labeloff'],
                            'labelon'         => $props['labelon'],
                            'labels_on_i18n'  => $props['labels_on_i18n'],
                            'labels_off_i18n' => $props['labels_off_i18n'],
                            'imgoff'          => $props['img_off'],
                            'imgon'           => $props['img_on']
                        ];
                        $set = true;
                    }
                }

                if (! $set) {
                    $stats[$bit] = [
                        [
                            'sbas'            => [$sbas_id],
                            'labeloff'        => $props['labeloff'],
                            'labelon'         => $props['labelon'],
                            'labels_on_i18n'  => $props['labels_on_i18n'],
                            'labels_off_i18n' => $props['labels_off_i18n'],
                            'imgoff'          => $props['img_off'],
                            'imgon'           => $props['img_on']
                        ]
                    ];
                }
            }
        }

        return $stats;
    }

    public static function getPath(Application $app, $sbas_id)
    {
        if ( ! isset(self::$_status[$sbas_id])) {
            self::$_status[$sbas_id] = new databox_status($app, $sbas_id);
        }

        return self::$_status[$sbas_id]->path;
    }

    public static function getUrl(Application $app, $sbas_id)
    {
        if ( ! isset(self::$_status[$sbas_id])) {
            self::$_status[$sbas_id] = new databox_status($app, $sbas_id);
        }

        return self::$_status[$sbas_id]->url;
    }

    public static function deleteStatus(Application $app, \databox $databox, $bit)
    {
        $status = self::getStatus($app, $databox->get_sbas_id());

        if (isset($status[$bit])) {
            $doc = $databox->get_dom_structure();
            if ($doc) {
                $xpath = $databox->get_xpath_structure();
                $entries = $xpath->query("/record/statbits/bit[@n=" . $bit . "]");

                foreach ($entries as $sbit) {
                    if ($p = $sbit->previousSibling) {
                        if ($p->nodeType == XML_TEXT_NODE && $p->nodeValue == "\n\t\t")
                            $p->parentNode->removeChild($p);
                    }
                    if ($sbit->parentNode->removeChild($sbit)) {
                        $sql = 'UPDATE record SET status = status&(~(1<<' . $bit . '))';
                        $stmt = $databox->get_connection()->prepare($sql);
                        $stmt->execute();
                        $stmt->closeCursor();
                    }
                }

                $databox->saveStructure($doc);

                if (null !== $status[$bit]['img_off']) {
                    $app['filesystem']->remove($status[$bit]['path_off']);
                }

                if (null !== $status[$bit]['img_on']) {
                    $app['filesystem']->remove($status[$bit]['path_on']);
                }

                unset(self::$_status[$databox->get_sbas_id()]->status[$bit]);

                return true;
            }
        }

        return false;
    }

    public static function updateStatus(Application $app, $sbas_id, $bit, $properties)
    {
         self::getStatus($app, $sbas_id);

        $databox = $app['phraseanet.appbox']->get_databox((int) $sbas_id);

        $doc = $databox->get_dom_structure($sbas_id);
        if ($doc) {
            $xpath = $databox->get_xpath_structure($sbas_id);
            $entries = $xpath->query("/record/statbits");
            if ($entries->length == 0) {
                $statbits = $doc->documentElement->appendChild($doc->createElement("statbits"));
            } else {
                $statbits = $entries->item(0);
            }

            if ($statbits) {
                $entries = $xpath->query("/record/statbits/bit[@n=" . $bit . "]");

                if ($entries->length >= 1) {
                    foreach ($entries as $k => $sbit) {
                        if ($p = $sbit->previousSibling) {
                            if ($p->nodeType == XML_TEXT_NODE && $p->nodeValue == "\n\t\t")
                                $p->parentNode->removeChild($p);
                        }
                        $sbit->parentNode->removeChild($sbit);
                    }
                }

                $sbit = $statbits->appendChild($doc->createElement("bit"));

                if ($n = $sbit->appendChild($doc->createAttribute("n"))) {
                    $n->value = $bit;
                }

                if ($labOn = $sbit->appendChild($doc->createAttribute("labelOn"))) {
                    $labOn->value = $properties['labelon'];
                }

                if ($searchable = $sbit->appendChild($doc->createAttribute("searchable"))) {
                    $searchable->value = $properties['searchable'];
                }

                if ($printable = $sbit->appendChild($doc->createAttribute("printable"))) {
                    $printable->value = $properties['printable'];
                }

                if ($labOff = $sbit->appendChild($doc->createAttribute("labelOff"))) {
                    $labOff->value = $properties['labeloff'];
                }

                foreach ($properties['labels_off'] as $code => $label) {
                    $labelTag = $sbit->appendChild($doc->createElement("label"));
                    $switch = $labelTag->appendChild($doc->createAttribute("switch"));
                    $switch->value = 'off';
                    $codeTag = $labelTag->appendChild($doc->createAttribute("code"));
                    $codeTag->value = $code;
                    $labelTag->appendChild($doc->createTextNode($label));
                }

                foreach ($properties['labels_on'] as $code => $label) {
                    $labelTag = $sbit->appendChild($doc->createElement("label"));
                    $switch = $labelTag->appendChild($doc->createAttribute("switch"));
                    $switch->value = 'on';
                    $codeTag = $labelTag->appendChild($doc->createAttribute("code"));
                    $codeTag->value = $code;
                    $labelTag->appendChild($doc->createTextNode($label));
                }
            }

            $databox->saveStructure($doc);

            self::$_status[$sbas_id]->status[$bit]["labelon"] = $properties['labelon'];
            self::$_status[$sbas_id]->status[$bit]["labeloff"] = $properties['labeloff'];
            self::$_status[$sbas_id]->status[$bit]["searchable"] = (Boolean) $properties['searchable'];
            self::$_status[$sbas_id]->status[$bit]["printable"] = (Boolean) $properties['printable'];

            if ( ! isset(self::$_status[$sbas_id]->status[$bit]['img_on'])) {
                self::$_status[$sbas_id]->status[$bit]['img_on'] = null;
            }

            if ( ! isset(self::$_status[$sbas_id]->status[$bit]['img_off'])) {
                self::$_status[$sbas_id]->status[$bit]['img_off'] = null;
            }
        }

        return false;
    }

    public static function deleteIcon(Application $app, $sbas_id, $bit, $switch)
    {
        $status = self::getStatus($app, $sbas_id);

        $switch = in_array($switch, ['on', 'off']) ? $switch : false;

        if (! $switch) {
            return false;
        }

        if ($status[$bit]['img_' . $switch]) {
            if (isset($status[$bit]['path_' . $switch])) {
                $app['filesystem']->remove($status[$bit]['path_' . $switch]);
            }

            $status[$bit]['img_' . $switch] = false;
            unset($status[$bit]['path_' . $switch]);
        }

        return true;
    }

    public static function updateIcon(Application $app, $sbas_id, $bit, $switch, UploadedFile $file)
    {
        $switch = in_array($switch, ['on', 'off']) ? $switch : false;

        if (! $switch) {
            throw new Exception_InvalidArgument();
        }

        $url = self::getUrl($app, $sbas_id);
        $path = self::getPath($app, $sbas_id);

        if ($file->getSize() >= 65535) {
            throw new Exception_Upload_FileTooBig();
        }

        if ( ! $file->isValid()) {
            throw new Exception_Upload_Error();
        }

        self::deleteIcon($app, $sbas_id, $bit, $switch);

        $name = "-stat_" . $bit . "_" . ($switch == 'on' ? '1' : '0') . ".gif";

        try {
            $file = $file->move($app['root.path'] . "/config/status/", $path.$name);
        } catch (FileException $e) {
            throw new Exception_Upload_CannotWriteFile();
        }

        $custom_path = $app['root.path'] . '/www/custom/status/';

        $app['filesystem']->mkdir($custom_path, 0750);

        //resize status icon 16x16px
        $imageSpec = new ImageSpecification();
        $imageSpec->setResizeMode(ImageSpecification::RESIZE_MODE_OUTBOUND);
        $imageSpec->setDimensions(16, 16);

        $filePath = sprintf("%s%s", $path, $name);
        $destPath = sprintf("%s%s", $custom_path, basename($path . $name));

        try {
            $app['media-alchemyst']->turninto($filePath, $destPath, $imageSpec);
        } catch (\MediaAlchemyst\Exception $e) {

        }

        self::$_status[$sbas_id]->status[$bit]['img_' . $switch] = $url . $name;
        self::$_status[$sbas_id]->status[$bit]['path_' . $switch] = $filePath;

        return true;
    }

    public static function operation_and(Application $app, $stat1, $stat2)
    {
        $conn = $app['phraseanet.appbox']->get_connection();

        $status = '0';

        if (substr($stat1, 0, 2) === '0x') {
            $stat1 = self::hex2bin($app, substr($stat1, 2));
        }
        if (substr($stat2, 0, 2) === '0x') {
            $stat2 = self::hex2bin($app, substr($stat2, 2));
        }

        $sql = 'select bin(0b' . trim($stat1) . ' & 0b' . trim($stat2) . ') as result';

        $stmt = $conn->prepare($sql);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $stmt->closeCursor();

        if ($row) {
            $status = $row['result'];
        }

        return $status;
    }

    /**
     * compute ((0 M s1) M s2) where M is the "mask" operator
     * nb : s1,s2 are binary mask strings as "01x0xx1xx0x", no other format (hex) supported
     *
     * @param Application $app
     * @param $stat1 a binary mask "010x1xx0.." STRING
     * @param $stat2 a binary mask "x100x1..." STRING
     *
     * @return binary string
     */
    public static function operation_mask(Application $app, $stat1, $stat2)
    {
        $conn = $app['phraseanet.appbox']->get_connection();

        $status = '0';
        $stat1_or  = '0b' . trim(str_replace("x", "0", $stat1));
        $stat1_and = '0b' . trim(str_replace("x", "1", $stat1));
        $stat2_or  = '0b' . trim(str_replace("x", "0", $stat2));
        $stat2_and = '0b' . trim(str_replace("x", "1", $stat2));

        // $sql = "SELECT BIN(((((0 | :o1) & :a1)) | :o2) & :a2) AS result";
        // $stmt = $conn->prepare($sql);
        // $stmt->execute(array(':o1'=>$stat1_or, ':a1'=>$stat1_and, ':o2'=>$stat2_or, ':a2'=>$stat2_and));

        $sql = "SELECT BIN(((((0 | $stat1_or) & $stat1_and)) | $stat2_or) & $stat2_and) AS result";
        $stmt = $conn->prepare($sql);
        $stmt->execute();

        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        $stmt->closeCursor();

        if ($row) {
            $status = $row['result'];
        }

        return $status;
    }

    public static function operation_and_not(Application $app, $stat1, $stat2)
    {
        $conn = $app['phraseanet.appbox']->get_connection();

        $status = '0';

        if (substr($stat1, 0, 2) === '0x') {
            $stat1 = self::hex2bin($app, substr($stat1, 2));
        }
        if (substr($stat2, 0, 2) === '0x') {
            $stat2 = self::hex2bin($app, substr($stat2, 2));
        }

        $sql = 'select bin(0b' . trim($stat1) . ' & ~0b' . trim($stat2) . ') as result';

        $stmt = $conn->prepare($sql);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $stmt->closeCursor();

        if ($row) {
            $status = $row['result'];
        }

        return $status;
    }

    public static function operation_or(Application $app, $stat1, $stat2)
    {
        $conn = $app['phraseanet.appbox']->get_connection();

        $status = '0';

        if (substr($stat1, 0, 2) === '0x') {
            $stat1 = self::hex2bin($app, substr($stat1, 2));
        }
        if (substr($stat2, 0, 2) === '0x') {
            $stat2 = self::hex2bin($app, substr($stat2, 2));
        }

        $sql = 'select bin(0b' . trim($stat1) . ' | 0b' . trim($stat2) . ') as result';

        $stmt = $conn->prepare($sql);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $stmt->closeCursor();

        if ($row) {
            $status = $row['result'];
        }

        return $status;
    }

    public static function dec2bin(Application $app, $status)
    {
        $status = (string) $status;

        if ( ! ctype_digit($status)) {
            throw new \Exception(sprintf('`%s`is non-decimal value', $status));
        }

        $conn = $app['phraseanet.appbox']->get_connection();

        $sql = 'select bin(' . $status . ') as result';

        $stmt = $conn->prepare($sql);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $stmt->closeCursor();

        $status = '0';

        if ($row) {
            $status = $row['result'];
        }

        return $status;
    }

    public static function hex2bin(Application $app, $status)
    {
        $status = (string) $status;
        if (substr($status, 0, 2) === '0x') {
            $status = substr($status, 2);
        }

        if ( ! ctype_xdigit($status)) {
            throw new \Exception('Non-hexadecimal value');
        }

        $conn = $app['phraseanet.appbox']->get_connection();

        $sql = 'select BIN( CAST( 0x' . trim($status) . ' AS UNSIGNED ) ) as result';

        $stmt = $conn->prepare($sql);
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        $stmt->closeCursor();

        $status = '0';

        if ($row) {
            $status = $row['result'];
        }

        return $status;
    }

    public static function purge()
    {
        self::$_status = self::$_statuses = [];
    }
}
