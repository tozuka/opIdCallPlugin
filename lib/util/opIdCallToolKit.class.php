<?php

/**
 * This file is part of the OpenPNE package.
 * (c) OpenPNE Project (http://www.openpne.jp/)
 *
 * For the full copyright and license information, please view the LICENSE
 * file and the NOTICE file that were distributed with this source code.
 */

class opIdCallToolkit
{
  /**
   * opIdCall log massage
   *
   * @param $message log message
   * @param $priority sfLogger::EMERG ALERT CRIT ERR WARNING NOTICE INFO DEBUG
   */
  public static function log($message, $priority = sfLogger::INFO)
  {
    if (sfConfig::get('sf_logging_enabled'))
    {
      sfContext::getInstance()->getLogger()->log('{opIdCallPlugin} '.$message, $priority);
    }
  }
}
