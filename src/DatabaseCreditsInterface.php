<?php

namespace Volyanytsky\Database;

interface DatabaseCreditsInterface
{
  public function getType();
  public function getHost();
  public function getName();
  public function getUser();
  public function getPass();
  public function getCharset();
  public function getOptions();
}



 ?>
