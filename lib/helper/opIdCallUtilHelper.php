<?php

/**
 * This file is part of the OpenPNE package.
 * (c) OpenPNE Project (http://www.openpne.jp/)
 *
 * For the full copyright and license information, please view the LICENSE
 * file and the NOTICE file that were distributed with this source code.
 */

/**
 * opIdCallUtilHelper provides basic utility helper functions.
 *
 * @package    OpenPNE
 * @subpackage helper
 * @author     Shinichi Urabe <urabe@tejimaya.com>
 */

/**
 * Creates a mailaddress for mobile_mail_frontend
 *
 * @param $params array($route, array $params)
 * @param $params Member object
 * @return string
 */
function op_id_call_generate_mail(array $route_params, Member $targetMember = null)
{
  if (!$route_params)
  {
    return false;
  }
  $route_results = array_keys($route_params);
  $route = $route_results[0];
  $params = $route_params[$route];

  $configuration = sfContext::getInstance()->getConfiguration();
  $configPath = '/mobile_mail_frontend/config/routing.yml';
  $files = array_merge(array(sfConfig::get('sf_apps_dir').$configPath), $configuration->globEnablePlugin('/apps'.$configPath));

  if (sfConfig::get('op_is_mail_address_contain_hash') && $targetMember)
  {
    $params['hash'] = $targetMember->getMailAddressHash();
  }

  $routing = new opMailRouting(new sfEventDispatcher());
  $config = new sfRoutingConfigHandler();
  $routes = $config->evaluate($files);

  $routing->setRoutes(array_merge(sfContext::getInstance()->getRouting()->getRoutes(), $routes));

  return $routing->generate($route, $params);
}
