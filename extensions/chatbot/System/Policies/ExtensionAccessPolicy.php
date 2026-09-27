<?php
namespace App\Extensions\Chatbot\System\Policies;
class ExtensionAccessPolicy{public function use(mixed$user):bool{return $user!==null;}public function administer(mixed$user):bool{return (bool)($user->isAdmin()??false);}}
