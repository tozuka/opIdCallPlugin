<?php

/**
 * This file is part of the OpenPNE package.
 * (c) OpenPNE Project (http://www.openpne.jp/)
 *
 * For the full copyright and license information, please view the LICENSE
 * file and the NOTICE file that were distributed with this source code.
 */

/**
 * MemberConfigIdCallForm.
 *
 * @package    opIdCallPlugin
 * @subpackage form
 * @author     Shinichi Urabe <urabe@tejimaya.com>
 */
class MemberConfigIdCallForm extends MemberConfigForm
{
  const ID_CALL_ACTIVITY_PUBLIC_FLAG = 'id_call_activity_public_flag';
  const ID_CALL_MAIL_POST_NAME_SUFFIX = 'id_call_mail_post_name_suffix';

  protected
    $category = 'idCall';

  public function configure()
  {
    $public_flags = Doctrine::getTable('ActivityData')->getPublicFlags();
    $this->widgetSchema[self::ID_CALL_ACTIVITY_PUBLIC_FLAG] = new sfWidgetFormChoice(array(
      'choices'  => $public_flags,
      'expanded' => true,
      'default'  => $this->member->getConfig(self::ID_CALL_ACTIVITY_PUBLIC_FLAG, ActivityDataTable::PUBLIC_FLAG_SNS),
      'label'    => 'Id call mail post activity public flag',
    ));
    $this->widgetSchema[self::ID_CALL_MAIL_POST_NAME_SUFFIX] = new sfWidgetFormInput(array(
      'default'  => $this->member->getConfig(self::ID_CALL_MAIL_POST_NAME_SUFFIX, '殿'),
      'label'    => 'Id call mail post nickname suffix',
    ));

    $this->validatorSchema[self::ID_CALL_ACTIVITY_PUBLIC_FLAG] = new sfValidatorChoice(array(
      'choices' => array_keys($public_flags),
    ));
    $this->validatorSchema[self::ID_CALL_MAIL_POST_NAME_SUFFIX] = new opValidatorString(array('trim' => true, 'required' => false));
  }
}
