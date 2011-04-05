<?php
class IdCallTask extends sfBaseTask
{
  protected function configure()
  {
    $this->namespace = 'opIdCall';
    $this->name = 'clear-cache';
    $this->briefDescription = 'clears opIdCall cache';
  }

  protected function execute($arguments = array(),$options = array())
  {
    if (!sfContext::hasInstance())
    {
      sfContext::createInstance($this->createConfiguration('pc_frontend', 'prod'), 'pc_frontend');
    }

    $conn = Doctrine::getTable('MemberConfig')->getConnection();
    $stmt = $conn->execute('DELETE FROM member_config WHERE name in (?, ?)',
      array('id_call_rev_mapping', 'id_call_nicknames'));

    return $stmt->execute();
  }
}
