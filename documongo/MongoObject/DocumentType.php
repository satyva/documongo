<?php

namespace documongo\MongoObject;

class DocumentType extends \documongo\MongoObject {

    use \documongo\permission;

    protected $uuid;

    protected $itemsIndexed;

    protected $metaData;
    protected $security;

    protected function __construct($mn, $prefix, $mongoObject) {
        parent::__construct($mn, $prefix, $mongoObject);

        $this->metaData = $mn->selectDB($prefix . "model");
        $this->security = $mn->selectDB($prefix . "security");

        $this->uuid = isset($this->mongoObject["name"]) ? $this->mongoObject["name"] : null;

        $this->indexItems();
    }

    function indexItems() {
        $this->itemsIndexed = isset($this->mongoObject["items"]) && is_array($this->mongoObject["items"])
                                ? array_reduce($this->mongoObject["items"], function($_all_items, $_item) {
                                        if (isset($_item["name"])) {
                                            $_all_items[$_item["name"]] = $_item;
                                        }
                                        return $_all_items;
                                    }, array())
                                : array();
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
          case 'itemsIndexed':
            return $this->itemsIndexed;
            break;

          default:
            if (isset($this->mongoObject[$name])) {
                return $this->mongoObject[$name];
            }
            break;
        }
    }

    function getItemProperties($itemName) {
        $item = null;

        if (isset($this->itemsIndexed[$itemName])) {
            $item = $this->itemsIndexed[$itemName];
        }

        return $item;
    }

    function getItemI18nName($itemName, $language) {
        $itemI18nName = null;

        if (isset($this->itemsIndexed[$itemName])) {
            $item = $this->itemsIndexed[$itemName];

            $itemNoI18n = isset($item["no_i18n"]) ? (boolean)$item["no_i18n"] : false;

            $itemI18nName = $itemNoI18n ? $itemName : ($itemName . "_" . $language);
        }

        return $itemI18nName;
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


    function getAvailabilityPeriods() {
        $availabilityPeriods = isset($this->mongoObject["available_periods"]) ? $this->mongoObject["available_periods"] : array();

        return $availabilityPeriods;
    }

    function isAvailable(&$availablePeriods = array(), &$futureAvailablePeriods = array(), &$pastAvailablePeriods = array()) {

        $currentDateTime = new \DateTime();

        $availabilityPeriods = $this->getAvailabilityPeriods();

        $isAvailable = null;

        foreach ($availabilityPeriods as $availabilityPeriod) {
            $availabilityPeriodSince = isset($availabilityPeriod["since"]) ? new \DateTime($availabilityPeriod["since"]) : null;
            $availabilityPeriodTill = isset($availabilityPeriod["till"]) ? new \DateTime($availabilityPeriod["till"]) : null;
            $availabilityPeriodAvailable = $availabilityPeriod["available"];
            $availabilityPeriodDescription = isset($availabilityPeriod["description"]) ? $availabilityPeriod["description"] : null;

            $isAvailableElement = null;

            $availablePeriod = array();
            $availablePeriod["since"] = $availabilityPeriodSince;
            $availablePeriod["till"] = $availabilityPeriodTill;
            $availablePeriod["description"] = $availabilityPeriodDescription;

            if (!is_null($availabilityPeriodSince) && !is_null($availabilityPeriodTill)) {
                if ($availabilityPeriodSince <= $currentDateTime && $currentDateTime < $availabilityPeriodTill) {
                    $isAvailableElement = $availabilityPeriodAvailable;
                } elseif ($availabilityPeriodTill <= $currentDateTime) {
                    $pastAvailablePeriods[] = $availablePeriod;
                } elseif ($availabilityPeriodSince > $currentDateTime) {
                    $futureAvailablePeriods[] = $availablePeriod;
                }
            } elseif (!is_null($availabilityPeriodSince)) {
                if ($availabilityPeriodSince <= $currentDateTime) {
                    $isAvailableElement = $availabilityPeriodAvailable;
                } elseif ($availabilityPeriodSince > $currentDateTime) {
                    $futureAvailablePeriods[] = $availablePeriod;
                }
            } elseif (!is_null($availabilityPeriodTill)) {
                if ($currentDateTime <= $availabilityPeriodTill) {
                    $isAvailableElement = $availabilityPeriodAvailable;
                } elseif ($availabilityPeriodTill < $currentDateTime) {
                    $pastAvailablePeriods[] = $availablePeriod;
                }
            }

            if (!is_null($isAvailableElement)) {
                if (is_null($isAvailable)) {
                    $isAvailable = $isAvailableElement;
                } else {
                    $isAvailable = $isAvailable && $isAvailableElement;
                }

                if ($isAvailable) {
                    $availablePeriods[] = $availablePeriod;

                    // break;
                } elseif ($isAvailable === false) {
                    // break;
                }

                if (is_null($isAvailable)) {
                    $isAvailable = false;
                }
            }
        }

        return $isAvailable;
    }


    const PERIOD_NOW = "now";
    const PERIOD_FUTURE = "future";
    const PERIOD_PAST = "past";

    function getAvailablePeriods($availablePeriodType = self::PERIOD_NOW) {
        $nowAvailablePeriods = array();
        $futureAvailablePeriods = array();
        $pastAvailablePeriods = array();

        $isAvailable = $this->isAvailable($nowAvailablePeriods, $futureAvailablePeriods, $pastAvailablePeriods);

        $availablePeriods = array();

        switch ($availablePeriodType) {
            case self::PERIOD_NOW:
                $availablePeriods = $nowAvailablePeriods;
                break;

            case self::PERIOD_FUTURE:
                $availablePeriods = $futureAvailablePeriods;
                break;

            case self::PERIOD_PAST:
                $availablePeriods = $pastAvailablePeriods;
                break;

            default:
                $availablePeriods = array_merge($pastAvailablePeriods, $nowAvailablePeriods, $futureAvailablePeriods);
                break;
        }


        return $availablePeriods;
    }

    function getAvailablePeriod($availablePeriodType = self::PERIOD_NOW) {
        $availablePeriods = $this->getAvailablePeriods($availablePeriodType);

        $availablePeriod = array_shift($availablePeriods);

        return $availablePeriod;
    }
}
