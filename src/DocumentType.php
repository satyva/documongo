<?php

class DocumentType{
    use permission;

    protected $uuid;
    protected $typeObject;

    protected $mn;
    protected $prefix;

    protected $metaData;
    protected $security;

    private function __construct($mn, $prefix, $typeObject) {
        $this->mn = $mn;
        $this->prefix = $prefix;

        $this->metaData = $mn->selectDB($prefix . "model");
        $this->security = $mn->selectDB($prefix . "security");

        $this->typeObject = $typeObject;
        $this->uuid = isset($typeObject["name"]) ? $typeObject["name"] : null;
    }

    function getName($language = null) {
        if (!$this->exists()) throw new Exception("Error Processing Request", 1);

        return isset($this->typeObject["label_" . $language]) ? $this->typeObject["label_" . $language] : ($this->typeObject["name"] . " [$language]");
    }

    function isPermitted($userUuid, $action, $xpath = null, $lang = null) {

        if (!$this->exists()) throw new Exception("Error Processing Request", 1);

        if (empty($xpath) || $xpath == "/") {
            $xpath = "";
        }



        $isPermitted = $this->hasPermission($userUuid, $action, $xpath, $lang);

        if (is_null($isPermitted)) {
            $isPermitted = false;
        }

        return $isPermitted;
    }

    function exists() {
        return !is_null($this->typeObject);
    }


    function __get($name) {
        if (!$this->exists()) throw new Exception("Error Processing Request", 1);

        switch ($name) {
          case 'type':
            return $this->uuid;
            break;
          case 'typeObject':
            return $this->typeObject;
            break;
          case 'items':
            return $this->typeObject["items"];
            break;

          default:
            if (isset($this->typeObject[$name])) {
                return $this->typeObject[$name];
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

        $availablePeriods = isset($this->typeObject["available_periods"]) ? $this->typeObject["available_periods"] : array();

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
