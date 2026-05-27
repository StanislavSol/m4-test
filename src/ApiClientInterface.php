<?php

namespace M4;

interface ApiClientInterface
{
    public function auth($login, $pass);
    public function getTasks($from);
    public function getTask($id);
    public function upload($file);
    public function attach($taskId, $files);
    public function comment($taskId, $text);
    public function logout();
}
