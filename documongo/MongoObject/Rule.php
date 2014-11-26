<?php

namespace documongo\MongoObject;

class Rule extends \documongo\MongoObject {
function my_dump($arr){echo '<pre>';var_dump($arr); echo '</pre>';   }

    protected function __construct($mn, $prefix, $mongoObject) {
        parent::__construct($mn, $prefix, $mongoObject);

        $this->security = $mn->selectDB($prefix . "security");

        $this->resetArrayField("actors");
        $this->resetArrayField("actions");
        $this->resetArrayField("objects");
    }

    private function resetArrayField($fieldName) {
        if (!isset($this->mongoObject[$fieldName]) || !is_array($this->mongoObject[$fieldName])) {
            $this->mongoObject[$fieldName] = array();
        }
    }

    function addActor($actor) {
        if ($actor) {
            $this->mongoObject["actors"][] = $actor;
        }
    }
    function addActors($actors) {
        if (is_array($actors)) {
            foreach ($actors as $actor) {
                $this->addActor($actor);
            }
        }
    }

    function addAction($action) {
        if ($action) {
            $this->mongoObject["actions"][] = $action;
        }
    }
    function addActions($actions) {
        if (is_array($actions)) {
            foreach ($actions as $action) {
                $this->addAction($action);
            }
        }
    }

    function addObject($object) {
        if ($object) {
            $this->mongoObject["objects"][] = $object;
        }
    }
    function addObjects($objects) {
        if (is_array($objects)) {
            foreach ($objects as $object) {
                $this->addObject($object);
            }
        }
    }

    function save() {
        $status = $this->security->rules->update(array("_id" => $this->mongoId), $this->mongoObject, array("upsert" => true));

        $ok = $status === true || isset($status["ok"]);
        if ($ok && !$status["updatedExisting"]) {
            $this->mongoId = (string)$status["upserted"];
            $this->mongoObject = $this->security->rules->findOne(array("_id" => new \MongoId($this->mongoId)));
        }
        return $ok;
    }

    static function find($mn, $prefix, $actor, $object) {
        $rule_search = array();

        if (!empty($actor)) {
            $rule_search["actors"] = array('$in' => array($actor));
        }

        if (!empty($object)) {
            $rule_search["objects"] = array('$regex' => $object . "(/.*)?", '$options' => 'i');
        }

        $rulesArray = array();

        if (!empty($rule_search)) {
            $rules = $mn->selectDB($prefix . "security")->rules->find($rule_search);

            if ($rules->hasNext()) {
                foreach ($rules as $rule) {
                    $ruleObject = new static($mn, $prefix, $rule);
                    $rulesArray[] = $ruleObject;
                }
            }
        }

        return $rulesArray;
    }
}
