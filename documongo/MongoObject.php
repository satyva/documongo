<?php

namespace documongo;

class MongoObject {
    protected $mn;
    protected $prefix;

    protected $mongoId;
    protected $mongoObject;

    protected function __construct($mn, $prefix, $mongoObject) {
        $this->mn = $mn;
        $this->prefix = $prefix;
        $this->mongoObject = $mongoObject;

        if (isset($mongoObject["_id"])) {
            $this->mongoId = $mongoObject["_id"];
        }
    }

    static function create($mn, $prefix) {
        $obj = new static($mn, $prefix, array());

        return $obj;
    }

    function exists() {
        return !is_null($this->mongoId);
    }
}
