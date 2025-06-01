<?php

require_once __DIR__ . '/../src/ModelClass/Model.php';
use ModelClass\Model;

define("DB_HOST", "localhost");
define("DB_NAME", "test_db");
define("DB_USER", "root");
define("DB_PASS", "");


//lets clear our user, in case we have performed this test before
$m = new Model("users");
if($m->loadBy("email", "user@user.mail")){
    $m->delete();
}

//lets do a custom sql
//$m->query("DELETE FROM users WHERE email='user@user.mail'");

//load user by any other col/field
if(!$m->loadBy("email", "user2@user.mail")){
    echo "This user do not exists\n\n";
}

function selectAll(){
    $m = new Model("users");
    $r = $m->select();
    var_dump($r);
}

echo "First select: \n";
selectAll();

//could be a data from a post
$dataPost = array();
$dataPost["name"] = "User Name";
$dataPost["email"] = "user@user.mail";
$dataPost["password"] = "123456";
$dataPost["anyOtherFieldNotInDB"] = "This field do not exists in DB, modelclass will ignore";

$user = new Model("users");
$user->setAll($dataPost);
echo "The query: ". $user->persist(true). "\n";
$user->persist();
echo "Persisted id: ".$user->get("id"). "\n\n";

echo "Second select: \n";
selectAll();

//lets change the user
$user->set("password", "changedPassword");

//lets persist on DB
echo "The query: ". $user->persist(true). "\n";
$user->persist();
echo "Persisted id: ".$user->get("id"). "\n\n";
$previousId = $user->get("id");

//lets load a user by id
$user2 = new Model("users");
$user2->load($previousId);
var_dump($user2->getAll());


$u = new Model("users");
echo "A field: ".$u->getValueByIdCol("1", "name"). "\n";
$u->setValueByIdCol("1", "name", "Another name");
echo "A field: ".$u->getValueByIdCol("1", "name"). "\n";
echo "Set a field: ".$u->setValueByIdCol("1", "name", "John Doe"). "\n";


