<?php

namespace documongo\MongoObject;

class DocumentType extends \documongo\MongoObject {

    use \documongo\permission;

    protected $uuid;

    protected $metaData;
    protected $security;

    protected function __construct($mn, $prefix, $mongoObject) {
        parent::__construct($mn, $prefix, $mongoObject);

        $this->metaData = $mn->selectDB($prefix . "model");
        $this->security = $mn->selectDB($prefix . "security");

        $this->uuid = isset($mongoObject["name"]) ? $mongoObject["name"] : null;
    }

    function getName($language = null) {
        if (!$this->exists()) throw new \Exception("Error Processing Request", 1);

        return isset($this->mongoObject["label_" . $language]) ? $this->mongoObject["label_" . $language] : ($this->mongoObject["name"] . " [$language]");
    }

    function isPermitted($userUuid, $action, $xpath = null, $lang = null) {

        if (!$this->exists()) throw new \Exception("Error Processing Request", 1);

        if (empty($xpath) || $xpath == "/") {
            $xpath = "";
        }



        $isPermitted = $this->hasPermission($userUuid, $action, $xpath, $lang);

        if (is_null($isPermitted)) {
            $isPermitted = false;
        }

        return $isPermitted;
    }

    function __get($name) {
        if (!$this->exists()) throw new \Exception("Error Processing Request", 1);

        switch ($name) {
          case 'type':
            return $this->uuid;
            break;
          case 'typeObject':
            return $this->mongoObject;
            break;
          case 'items':
            return $this->mongoObject["items"];
            break;

          default:
            if (isset($this->mongoObject[$name])) {
                return $this->mongoObject[$name];
            }
            break;
        }
    }

    static function find($mn, $prefix) {
        $elems = array();
        $entries = $mn->selectDB($prefix . "model")->types->find();
        foreach ($entries as $entry) {
            $elems[] = new self($mn, $prefix, $entry);
        }

        return $elems;
    }

    static function findByType($mn, $prefix, $type) {
        $entry = $mn->selectDB($prefix . "model")->types->findOne(array("name" => $type));
        if (!is_null($entry)) {
            return new self($mn, $prefix, $entry);
        }
    }

    function isAvailable() {

        $currentDateTime = new \DateTime();

        $availablePeriods = isset($this->mongoObject["available_periods"]) ? $this->mongoObject["available_periods"] : array();

        $isAvailable = true;

        foreach ($availablePeriods as $availablePeriod) {
            $availablePeriodSince = isset($availablePeriod["since"]) ? new \DateTime($availablePeriod["since"]) : null;
            $availablePeriodTill = isset($availablePeriod["till"]) ? new \DateTime($availablePeriod["till"]) : null;
            $availablePeriodAvailable = $availablePeriod["available"];

            if (!is_null($availablePeriodSince) && !is_null($availablePeriodTill) && $availablePeriodSince <= $currentDateTime && $currentDateTime <= $availablePeriodTill) {
                $isAvailable = $availablePeriodAvailable;
            } elseif (!is_null($availablePeriodSince) && $availablePeriodSince <= $currentDateTime) {
                $isAvailable = $availablePeriodAvailable;
            } elseif (!is_null($availablePeriodTill) && $currentDateTime <= $availablePeriodTill) {
                $isAvailable = $availablePeriodAvailable;
            }

            if (!$isAvailable) {
                break;
            }
        }

        return $isAvailable;
    }
}
