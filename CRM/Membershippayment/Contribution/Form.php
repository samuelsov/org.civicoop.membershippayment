<?php

class CRM_Membershippayment_Contribution_Form {

  private static $singleton;

  private $created_contribution_id;

  private function __construct() {

  }

  public function post($op, $objectName, $objectId, &$objectRef) {
    if ($op == 'create' && $objectName == 'Contribution') {
      $this->created_contribution_id = $objectId;
    }
  }

  public function postProcess($formName, &$form) {
    if ($formName != 'CRM_Contribute_Form_Contribution') {
      return;
    }
    if (!CRM_Core_Permission::check( 'edit memberships' )) {
      return;
    }

    $membership_payment_id = false;
    $contribution_id = $form->getVar('_id');

    if (!$contribution_id && $this->created_contribution_id) {
      $contribution_id = $this->created_contribution_id;
    }
    
    if ($contribution_id) {
      $membership_payment_id = CRM_Core_DAO::singleValueQuery("SELECT id FROM civicrm_membership_payment where contribution_id = %1", array(1 => array($contribution_id, 'Integer')));
    }

    $values = $form->controller->exportValues($form->getVar('_name'));

    $membership_id = false;
    if (!empty($form->_submitValues['membership_id'])) {
      $membership_id = $form->_submitValues['membership_id'];
    }

    // create soft contribution entry if contact ID and member contact are different
    if(!empty($form->_submitValues['member_contact']) && ($form->_submitValues['member_contact'] != $contactId)) {
      $result = civicrm_api3('ContributionSoft', 'create', array(
        'sequential' => 1,
        'contribution_id' => $contribution_id,
        'amount' => $form->_submitValues['total_amount'],
        'contact_id' => $form->_submitValues['member_contact'],
        'soft_credit_type_id' => $form->_submitValues['soft_credit_type_id'],
      ));
    }

    if (!$membership_payment_id && $membership_id) {
      $sql = "INSERT INTO `civicrm_membership_payment` (`contribution_id`, `membership_id`) VALUES (%1, %2)";
      $params[1] = array($contribution_id, 'Integer');
      $params[2] = array($membership_id, 'Integer');
      CRM_Core_DAO::executeQuery($sql, $params);
    } elseif ($membership_payment_id && $membership_id) {
      $sql = "UPDATE `civicrm_membership_payment` SET `membership_id` = %1 WHERE `id` = %2";
      $params[1] = array($membership_id, 'Integer');
      $params[2] = array($membership_payment_id, 'Integer');
      CRM_Core_DAO::executeQuery($sql, $params);
    } elseif ($membership_payment_id && !$membership_id) {
      $sql = "DELETE FROM `civicrm_membership_payment` WHERE `id` = %1";
      $params[1] = array($membership_payment_id, 'Integer');
      CRM_Core_DAO::executeQuery($sql, $params);
    }
  }

  public function buildForm($formName, &$form) {
    if ($formName != 'CRM_Contribute_Form_Contribution') {
      return;
    }
    if (!CRM_Core_Permission::check( 'edit memberships' )) {
      return;
    }

    $contact_id = $form->getVar('_contactID');
    if (!$contact_id) {
      return;
    }

    $memberships = array('' => ts('-- None --')) + $this->getMembershipsForContact($contact_id);

    $current_membership_id = false;
    $contribution_id = $form->getVar('_id');
    $contact_ids[] = $contact_id;
    if ($contribution_id) {
      $current_membership_id = CRM_Core_DAO::singleValueQuery("SELECT membership_id FROM civicrm_membership_payment where contribution_id = %1", array(1 => array($contribution_id, 'Integer')));

      $softContribution = civicrm_api3('ContributionSoft', 'get', array('contribution_id' => $contribution_id));
      foreach($softContribution['values'] as $softContribution) {
        if (!in_array($softContribution['contact_id'], $contact_ids)) {
          $memberships = $memberships + $this->getMembershipsForContact($softContribution['contact_id']);
          $contact_ids[] = $softContribution['contact_id'];
        }
      }
    }

    $snippet['template'] = 'CRM/Membershippayment/Contribution/Form.tpl';
    $snippet['contact_id'] = $contact_id;

    $form->addEntityRef('member_contact', ts('Member Contact'));
    $form->add('select', 'membership_id', ts('Membership'), $memberships);

    $softCreditTypes = array('' => ts('- Select Soft Credit Type -')) + CRM_Core_OptionGroup::values('soft_credit_type');
    $form->add('select', 'soft_credit_type_id', ts('Soft Credit'), $softCreditTypes);

    if ($current_membership_id) {
      $defaults['membership_id'] = $current_membership_id;
    }

    $form->assign('contact_id', $contact_id);

    $defaults['member_contact'] = $contact_id;
    $defaults['soft_credit_type_id'] = CRM_Utils_Array::value(ts('Gift'), array_flip($softCreditTypes));
    $form->setDefaults($defaults);

    CRM_Core_Region::instance('page-body')->add($snippet);
  }

  private function getMembershipsForContact($contact_id) {
    $dao = new CRM_Member_DAO_Membership();
    $dao->contact_id = $contact_id;
    $dao->whereAdd('is_test IS NULL OR is_test = 0');

    // order by start date to find most recent membership first, CRM-4545
    $dao->orderBy('start_date DESC');

    $dao->find(false);
    $memberships = array();
    $display_name = civicrm_api3('Contact', 'getvalue', array('id' => $contact_id, 'return' => 'display_name'));
    while ($dao->fetch()) {
      $startDate = new DateTime($dao->start_date);
      $endDate = new DateTime($dao->end_date);
      $memberships[$dao->id] = $display_name." - ".CRM_Member_PseudoConstant::membershipType($dao->membership_type_id) . ': '.CRM_Member_PseudoConstant::membershipStatus($dao->status_id, null, 'label').' ('.$startDate->format('d-m-Y').' - '.$endDate->format('d-m-Y').')';
    }
    return $memberships;
  }

  /**
   * Singleton
   *
   * @return \CRM_Membershippayment_Contribution_Form
   */
  public static function singleton() {
    if (!self::$singleton) {
      self::$singleton = new CRM_Membershippayment_Contribution_Form();
    }
    return self::$singleton;
  }

}