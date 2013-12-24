<?php

class Document {
    use permission;

    protected $documentObject;

    protected $id;
    protected $uuid;
    protected $type;

    protected $typeObject;

    protected $mn;
    protected $prefix;

    protected $metaData;
    protected $realData;
    protected $security;

    static function isUuid($uuid) {
        return preg_match('/^\{?[0-9a-f]{8}\-?[0-9a-f]{4}\-?[0-9a-f]{4}\-?'.
                        '[0-9a-f]{4}\-?[0-9a-f]{12}\}?$/i', $uuid) === 1;
    }




    private function __construct($mn, $prefix, $documentObject) {
        $this->mn = $mn;
        $this->prefix = $prefix;

        $this->metaData = $mn->selectDB($prefix . "model");
        $this->realData = $mn->selectDB($prefix . "data");
        $this->security = $mn->selectDB($prefix . "security");


        if (!is_null($documentObject)) {
            $this->documentObject = $documentObject;

            $this->uuid = isset($this->documentObject["uuid"]) ? $this->documentObject["uuid"] : null;
            $this->id = isset($this->documentObject["_id"]) ? $this->documentObject["_id"] : null;
            $this->type = !empty($this->documentObject["type"]) ? $this->documentObject["type"] : null;
            $this->typeObject = DocumentType::findByType($this->mn, $this->prefix, $this->type);
        }
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

    function exists() {
        return !is_null($this->id);
    }


    function __get($name) {
        // if (!$this->exists()) throw new Exception("Error Processing Request", 1);

        switch ($name) {
          case 'id':
            return $this->id;
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
            return $this->documentObject;

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
        if (isset($this->documentObject[$fieldName])) {
            if ($this->documentObject[$fieldName] == $fieldValue) {
                $changed = false;
            }
        }

        $this->documentObject[$fieldName] = $fieldValue;

        return $changed;
    }

    function save() {
        $status = $this->realData->documents->update(array("_id" => $this->id), $this->documentObject, array("upsert" => true));

        $ok = $status === true || isset($status["ok"]);
        if ($ok && !$status["updatedExisting"]) {
            $this->id = (string)$status["upserted"];
            $this->documentObject = $this->realData->documents->findOne(array("_id" => new MongoId($this->id)));
            $this->uuid = isset($this->documentObject["uuid"]) ? $this->documentObject["uuid"] : null;
        }
        return $ok;
    }

    function delete() {
        $status = $this->realData->documents->remove(array("_id" => $this->id));

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

        if (!is_null($this->documentObject) && is_array($this->documentObject)) {

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