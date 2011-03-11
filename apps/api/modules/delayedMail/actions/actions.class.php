<?php

/**
 * This file is part of the OpenPNE package.
 * (c) OpenPNE Project (http://www.openpne.jp/)
 *
 * For the full copyright and license information, please view the LICENSE
 * file and the NOTICE file that were distributed with this source code.
 */

/**
 * prefs actions.
 *
 * @package    opIdCallPlugin
 * @subpackage action
 * @author     tozuka <tozuka@tejimaya.net>
 */
class delayedMailActions extends sfActions
{
  /**
   * Executes send action
   * 
   * @param sfWebRequest $request A request object
   */
  public function executeSend(sfWebRequest $request)
  {
    if ('127.0.0.1' !== $request->getRemoteAddress()
      || !$request->hasParameter('class')
      || !$request->hasParameter('id')
      || !$request->hasParameter('delay')
      || !$request->hasParameter('culture'))
    {
      $this->forward404();
    }

    IdCallUtil::processAfterDelay(
      $request->getParameter('class'),
      (int)$request->getParameter('id'),
      (int)$request->getParameter('delay'),
      $request->getParameter('culture')
    );

    return sfView::NONE;
  }
}
