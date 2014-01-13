<?php

require_once('PHPUnit/Autoload.php');
require_once('../../documongo/Load.php');

use \documongo\MongoObject\DocumentType;
use \documongo\MongoObject\Document;

class DocumentTest extends PHPUnit_Framework_TestCase
{

    /**
     * @dataProvider providerCreate
     */
    public function testCreateDelete($mn, $prefix, $typeObject, $uuid)
    {

        $testDocument = Document::create($mn, $prefix, $typeObject, $uuid);

        $this->assertEquals($testDocument->uuid, $uuid);

        $ok = $testDocument->save();

        $this->assertEquals($ok, true);

        $this->assertEquals($testDocument->uuid, $uuid);


        $testDocument2 = Document::findByUuid($mn, $prefix, $uuid);

        $this->assertEquals($testDocument2->uuid, $uuid);

        $this->assertEquals($testDocument, $testDocument2);

        $deleted = $testDocument2->delete();

        $this->assertEquals($deleted, true);
    }

    public function providerCreate()
    {
        $mn = new \MongoClient();
        $prefix = "temp_test_";

        $typeObject = DocumentType::findByType($mn, $prefix, "faculty");

        $uuid = "test-uuid";

        return array(array($mn, $prefix, $typeObject, $uuid));
    }

}

