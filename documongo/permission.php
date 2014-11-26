<?php

namespace documongo;

trait permission {

    protected $permissions;

function my_dump($arr){echo '<pre>';var_dump($arr); echo '</pre>';   }

    protected function fetchPermissions($userUuid = null, $action = null) {

        if (!isset($this->permissions[$action])) {
            $rule_search = array(
                "objects" => array('$regex' => $this->uuid . "(/.*)?", '$options' => 'i')
            );
// where is xpath and lang?
            if (!is_null($userUuid)) {
                $rule_search["actors"] = array('$in' => array($userUuid, '*'));
            }
            if (!is_null($action)) {
                $rule_search["actions"] = array('$in' => array($action, "+$action", "-$action"));
            }
 //  my_dump($rule_search);
            $rules = $this->security->rules->find($rule_search);
// my_dump($rules);
            if ($rules->hasNext()) {
                foreach ($rules as $rule) {
//           my_dump($rule);
                    $ruleObjects = $rule["objects"];
                    $ruleActions = $rule["actions"];

                    foreach ($ruleObjects as $ruleObject) {
                        $ruleObject = trim(trim($ruleObject), "/");

                        if (substr($ruleObject, 0, strlen($this->uuid)) === $this->uuid) {
                            foreach ($ruleActions as $ruleAction) {
                                $realAction = trim($ruleAction, "+-");

                                if ($ruleAction === $action || $ruleAction === "+$action") {
                                    $this->permissions[$realAction]["allow"][$ruleObject] = true;

                                } elseif ($ruleAction === "-$action") {

                                    $this->permissions[$realAction]["deny"][$ruleObject] = true;
                                }
                            }
                        }
                    }
                }
            }
//           echo "perms";
 //         my_dump($this->permissions);
        }
    }


    function hasPermission($userUuid, $action, $xpath, $lang = null) {
        $isPermitted = null;

        if (empty($xpath) || $xpath == "/") {
            $xpath = "";
        }

        $this->fetchPermissions($userUuid, $action);


        $objectPathComponents = explode("/", trim($xpath, "/"));

        // echo "\n\n-----> hasPermission({$this->uuid}, $action, $xpath)\n\n";

    //     my_dump($this->permissions);

        foreach (array("allow" => true, "deny" => false) as $permMode => $permValue) {
     //        echo "===> $permMode => $permValue \n";
            if ($isPermitted === false) break;

            $curPath = $this->uuid;

            //echo "(?) check perm permissions[$action][$permMode][$curPath]: $permValue\n";

            $foundRule = isset($this->permissions[$action][$permMode][$curPath])
                   || (!is_null($lang) && isset($this->permissions[$action][$permMode][$curPath . "_" . $lang]));
            if ($foundRule) {
                // echo "(!) got perm permissions[$action][$permMode][$curPath]: $permValue\n";
                $isPermitted = $permValue;
            }

            foreach ($objectPathComponents as $pathComp) {
                if ($isPermitted === false) break;

                $curPath .= "/" . $pathComp;

                // echo "(?) check perm permissions[$action][$permMode][$curPath]: $permValue\n";
                $foundRule = isset($this->permissions[$action][$permMode][$curPath])
                        || (!is_null($lang) && isset($this->permissions[$action][$permMode][$curPath . "_" . $lang]));

                if ($foundRule) {
                    // echo "(!) got perm permissions[$action][$permMode][$curPath]: $permValue\n";
                    $isPermitted = $permValue;
                }
            }
        }

         //echo "result({$this->uuid}): ";
  //      my_dump($userUuid, $action, $xpath, $isPermitted);



        return $isPermitted;
    }

    public function getPermissions() {
        $this->fetchPermissions();

        return $this->permissions;
    }
}
