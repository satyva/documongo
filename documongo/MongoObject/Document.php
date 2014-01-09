<?php

namespace documongo;

class Document extends \documongo\MongoObject {

    use \documongo\permission;

    protected $id;
    protected $uuid;
    protected $type;

    protected $typeObject;

    protected $metaData;
    protected $realData;
    protected $security;

    static function isUuid($uuid) {
        return preg_match('/^\{?[0-9a-f]{8}\-?[0-9a-f]{4}\-?[0-9a-f]{4}\-?'.
                        '[0-9a-f]{4}\-?[0-9a-f]{12}\}?$/i', $uuid) === 1;
    }




    private function __construct($mn, $prefix, $mongoObject) {
        parent::__construct($mn, $prefix, $mongoObject);

        if (!is_null($mongoObject)) {
            $this->uuid = isset($this->mongoObject["uuid"]) ? $this->mongoObject["uuid"] : null;
            $this->type = !empty($this->mongoObject["type"]) ? $this->mongoObject["type"] : null;
            $this->typeObject = DocumentType::findByType($this->mn, $this->prefix, $this->type);
        }

        $this->metaData = $mn->selectDB($prefix . "model");
        $this->realData = $mn->selectDB($prefix . "data");
        $this->security = $mn->selectDB($prefix . "security");
    }

    function isPermitted($userUuid, $action, $xpath = null, $lang = null) {
        if (empty($xpath) || $xpath == "/") {
            $xpath = "";
        }

        $isPermitted = $this->typeObject->hasPermission($userUuid, $action, $xpath, $lang);

        if ($this->exists()) {

            if ($isPermitted || is_null($isPermitted)) {
                $isPermittedByObject = $this->hasPermission($userUuid, $action, $xpath, $lang);

                if (is_null($isPermitted)) {
                    $isPermitted = $isPermittedByObject;
                } elseif (is_null($isPermittedByObject)) {
                    // keep value
                } else {
                    $isPermitted = $isPermitted && $isPermittedByObject;
                }
            }
        }


        // if (!$this->exists()) {
        //     // throw new Exception("Error Processing Request", 1);
        // }

        if (is_null($isPermitted)) {
            $isPermitted = false;
        }

        return $isPermitted;
    }


    function __get($name) {
        // if (!$this->exists()) throw new Exception("Error Processing Request", 1);

        switch ($name) {
          case 'id':
            return $this->mongoId;
            break;
          case 'uuid':
            return $this->uuid;
            break;
          case 'type':
            return $this->type;
            break;
          case 'typeObject':
            return $this->typeObject;
            break;

          case 'fields':
            return $this->mongoObject;

          default:
            # code...
            break;
        }
    }


    function __set($name, $value) {
        switch ($name) {
            case 'typeObject':
                if ($value instanceof DocumentType) {
                    $this->typeObject = $value;
                    $this->type = $this->typeObject->name;
                }
                break;

            default:
                # code...
                break;
        }
    }

    function setField($fieldName, $fieldValue) {
        $changed = true;
        if (isset($this->mongoObject[$fieldName])) {
            if ($this->mongoObject[$fieldName] == $fieldValue) {
                $changed = false;
            }
        }

        $this->mongoObject[$fieldName] = $fieldValue;

        return $changed;
    }

    function save() {
        $status = $this->realData->documents->update(array("_id" => $this->mongoId), $this->mongoObject, array("upsert" => true));

        $ok = $status === true || isset($status["ok"]);
        if ($ok && !$status["updatedExisting"]) {
            $this->mongoId = (string)$status["upserted"];
            $this->mongoObject = $this->realData->documents->findOne(array("_id" => new MongoId($this->mongoId)));
            $this->uuid = isset($this->mongoObject["uuid"]) ? $this->mongoObject["uuid"] : null;
        }
        return $ok;
    }

    function delete() {
        $status = $this->realData->documents->remove(array("_id" => $this->mongoId));

        $ok = $status === true || isset($status["ok"]);
        return $ok;
    }

    static function find($mn, $prefix, $type) {
        $elems = array();

        $entries = $mn->selectDB($prefix . "data")->documents->find(array("type" => $type));
        foreach ($entries as $entry) {
            $elems[] = new self($mn, $prefix, $entry);
        }

        return $elems;
    }

    static function findById($mn, $prefix, $id) {
        $entry = null;
        if (!is_null($id)) {
            try {
                $entry = $mn->selectDB($prefix . "data")->documents->findOne(array("_id" => new MongoId($id)));

                if (!is_null($entry)) {
                    return new self($mn, $prefix, $entry);
                }
            } catch (MongoException $e) {
            }
        }
    }

    static function findByUuid($mn, $prefix, $uuid) {
        $entry = null;
        if (!is_null($uuid)) {
            try {
                $entry = $mn->selectDB($prefix . "data")->documents->findOne(array("uuid" => $uuid));

                if (!is_null($entry)) {
                    return new self($mn, $prefix, $entry);
                }
            } catch (MongoException $e) {
            }
        }
    }

    static function findByIdOrUuid($mn, $prefix, $id, $uuid) {
        $obj = self::findById($mn, $prefix, $id);
        if (!is_null($obj)) {
            return $obj;
        }

        $obj = self::findByUuid($mn, $prefix, $uuid);
        if (!is_null($obj)) {
            return $obj;
        }
    }

    static function create($mn, $prefix, $typeObject, $uuid = null) {
        $item = array("type" => $typeObject->type);
        if ($uuid) {
            $item["uuid"] = $uuid;
        }
        $obj = new self($mn, $prefix, $item);
        $obj->typeObject = $typeObject;

        return $obj;
    }


    static function findOrCreate($mn, $prefix, $id, $typeObject) {
        $obj = self::findById($mn, $prefix, $id);

        if (is_null($obj)) {
            $obj = self::create($mn, $prefix, $typeObject);
        }

        return $obj;
    }


    function saveVersion($versionLabel, $versionDescription) {
        $versionId = null;

        if (!is_null($this->mongoObject) && is_array($this->mongoObject)) {

            if ($this->typeObject && isset($this->typeObject["items"]) && is_array($this->typeObject["items"])) {
                $versionItemsContent = array();

                foreach ($this->typeObject["items"] as $itemName => $itemProperties) {
                    if (isset($itemProperties["no_versioning"]) && $itemProperties["no_versioning"]) {
                        continue;
                    }
                }
            }
        }

        return $versionId;
    }

    function getAllVersions() {

        $versions = array();

        return $versions;
    }

    function getVersion($id) {
        $version = null;

        return $version;
    }

    function findVersions($dateStart, $dateFinish) {
        $versions = array();

        return $versions;
    }
}
