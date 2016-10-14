<?php
/*
 * @version $Id$
 -------------------------------------------------------------------------
 GLPI - Gestionnaire Libre de Parc Informatique
 Copyright (C) 2015 Teclib'.

 http://glpi-project.org

 based on GLPI - Gestionnaire Libre de Parc Informatique
 Copyright (C) 2003-2014 by the INDEPNET Development Team.
 
 -------------------------------------------------------------------------

 LICENSE

 This file is part of GLPI.

 GLPI is free software; you can redistribute it and/or modify
 it under the terms of the GNU General Public License as published by
 the Free Software Foundation; either version 2 of the License, or
 (at your option) any later version.

 GLPI is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 GNU General Public License for more details.

 You should have received a copy of the GNU General Public License
 along with GLPI. If not, see <http://www.gnu.org/licenses/>.
 --------------------------------------------------------------------------
 */

/** @file
* @brief
*/

if (!defined('GLPI_ROOT')) {
   die("Sorry. You can't access directly to this file");
}

/**
 * Class which manages notification events
**/
class NotificationEvent extends CommonDBTM {

   static function getTypeName($nb=0) {
      return _n('Event', 'Events', $nb);
   }


   /**
    * @param $itemtype
    * @param $options   array to pass to showFromArray or $value
   **/
   static function dropdownEvents($itemtype, $options=array()) {

      $p['name']    = 'event';
      $p['display'] = true;
      $p['value']   = '';

      if (is_array($options) && count($options)) {
         foreach ($options as $key => $val) {
            $p[$key] = $val;
         }
      }

      $events = array();
      $target = NotificationTarget::getInstanceByType($itemtype);
      if ($target) {
         $events = $target->getAllEvents();
      }
      $events[''] = Dropdown::EMPTY_VALUE;
      return Dropdown::showFromArray($p['name'], $events, $p);
   }


   /**
    * retrieve the label for an event
    *
    * @since version 0.83
    *
    * @param itemtype string name of the type
    * @param $event   string name of the event
    *
    * @return string
   **/
   static function getEventName($itemtype, $event) {

      $events = array();
      $target = NotificationTarget::getInstanceByType($itemtype);
      if ($target) {
         $events = $target->getAllEvents();
         if (isset($events[$event])) {
            return $events[$event];
         }
      }
      return NOT_AVAILABLE;
   }


   /**
    * Raise a notification event event
    *
    * @param $event           the event raised for the itemtype
    * @param $item            the object which raised the event
    * @param $options array   of options used
    * @param $label           used for debugEvent() (default '')
   **/
   static function raiseEvent($event, $item, $options=array(), $label='') {
      global $CFG_GLPI;

      //If notifications are enabled in GLPI's configuration
      if ($CFG_GLPI["use_mailing"]) {
         $email_processed    = array();
         $email_notprocessed = array();
         //Get template's information
         $template           = new NotificationTemplate();

         $notificationtarget = NotificationTarget::getInstance($item,$event,$options);
         if (!$notificationtarget) {
            return false;
         }
         $entity             = $notificationtarget->getEntity();

         //Foreach notification
         foreach (Notification::getNotificationsByEventAndType($event, $item->getType(), $entity)
                  as $data) {
            $targets = getAllDatasFromTable('glpi_notificationtargets',
                                            'notifications_id = '.$data['id']);

            $notificationtarget->clearAddressesList();

            //Process more infos (for example for tickets)
            $notificationtarget->addAdditionnalInfosForTarget();

            $template->getFromDB($data['notificationtemplates_id']);
            $template->resetComputedTemplates();

            //Set notification's signature (the one which corresponds to the entity)
            $template->setSignature(Notification::getMailingSignature($entity));

            $notify_me = false;
            if (Session::isCron()) {
               // Cron notify me
               $notify_me = true;
            } else {
               // Not cron see my pref
               $notify_me = $_SESSION['glpinotification_to_myself'];
            }

            $notify_control = FollowupNotify::getNotifyControl($_POST['notify_control']);

            foreach ($targets as $target) {

              $id = $target['items_id'];
              $ag = Notification::ASSIGN_GROUP;
              $rg = Notification::REQUESTER_GROUP;
              $og = Notification::OBSERVER_GROUP;
              $su = Notification::SUPPLIER;

              // If this target is a group and this group is disabled, skip target
              if ($id == $ag && $notify_control->_groups_id_assign    == 0 ||
                  $id == $rg && $notify_control->_groups_id_requester == 0 ||
                  $id == $og && $notify_control->_groups_id_observer  == 0 ||
                  $id == $su && $notify_control->_suppliers_id_assign == 0) {
                continue;
              }

              $notificationtarget->getAddressesByTarget($target,$options);

              $mails = $notificationtarget->getTargets();
              $mails_backup = $mails;
              $restore = array();

              $tickets_users = new Ticket_User();
              $roles = $tickets_users->getActors($item->getID());

              foreach ($roles as $role) {
                foreach ($role as $actor) {
                 
                  $type   = $actor['type'];
                  $ua     = CommonITILActor::ASSIGN;
                  $ur     = CommonITILActor::REQUESTER;
                  $uo     = CommonITILActor::OBSERVER;
                  $actor_email = trim(Toolbox::strtolower(UserEmail::getDefaultForUser($actor['users_id'])));

                  // If current actor is blocked, switch $unset to "true"
                  if ($type == $ua && $notify_control->_users_id_assign     == 0 ||
                      $type == $ur && $notify_control->_users_id_requester  == 0 ||
                      $type == $uo && $notify_control->_users_id_observer   == 0) {
                     unset($mails[$actor_email]);
                  }

                  // If a user assumes two roles, recover its mail address if enabled
                  if ($type == $ua && $notify_control->_users_id_assign     == 1 ||
                      $type == $ur && $notify_control->_users_id_requester  == 1 ||
                      $type == $uo && $notify_control->_users_id_observer   == 1) {
                    if (isset($mails_backup[$actor_email])) {
                      $restore[$actor_email] = $mails_backup[$actor_email];
                    }
                  }

                }
              }
             
              $mails = array_merge($mails, $restore);
             
               foreach ($mails as $user_email => $users_infos) {

                  if ($label
                      || $notificationtarget->validateSendTo($event, $users_infos, $notify_me)) {
                     //If the user have not yet been notified
                     if (!isset($email_processed[$users_infos['language']][$users_infos['email']])) {
                        //If ther user's language is the same as the template's one
                        if (isset($email_notprocessed[$users_infos['language']]
                                                     [$users_infos['email']])) {
                           unset($email_notprocessed[$users_infos['language']]
                                                    [$users_infos['email']]);
                        }
                        $options['item'] = $item;
                        if ($tid = $template->getTemplateByLanguage($notificationtarget,
                                                                    $users_infos, $event,
                                                                    $options)) {
                           //Send notification to the user
                           if ($label == '') {
                              $datas = $template->getDataToSend($notificationtarget, $tid,
                                                                $users_infos, $options);
                              $datas['_notificationtemplates_id'] = $data['notificationtemplates_id'];
                              $datas['_itemtype']                 = $item->getType();
                              $datas['_items_id']                 = $item->getID();
                              $datas['_entities_id']              = $entity;

                              Notification::send($datas);
                           } else {
                              $notificationtarget->getFromDB($target['id']);
                              echo "<tr class='tab_bg_2'><td>".$label."</td>";
                              echo "<td>".$notificationtarget->getNameID()."</td>";
                              echo "<td>".sprintf(__('%1$s (%2$s)'), $template->getName(),
                                                  $users_infos['language'])."</td>";
                              echo "<td>".$users_infos['email']."</td>";
                              echo "</tr>";
                           }
                           $email_processed[$users_infos['language']][$users_infos['email']]
                                                                     = $users_infos;

                        } else {
                           $email_notprocessed[$users_infos['language']][$users_infos['email']]
                                                                        = $users_infos;
                        }
                     }
                  }
               }
            }
         }
      }
      unset($email_processed);
      unset($email_notprocessed);
      $template = null;
      return true;
   }


   /**
    * Display debug information for an object
    *
    * @param $item            the object
    * @param $options   array
   **/
   static function debugEvent($item, $options=array()) {

      echo "<div class='spaced'>";
      echo "<table class='tab_cadre_fixe'>";
      echo "<tr><th colspan='2'>"._n('Notification', 'Notifications', Session::getPluralNumber()).
            "</th><th colspan='2'><font color='blue'> (".$item->getTypeName(1).")</font></th></tr>";

      $events = array();
      if ($target = NotificationTarget::getInstanceByType(get_class($item))) {
         $events = $target->getAllEvents();

         if (count($events)>0) {
            echo "<tr><th>".self::getTypeName(Session::getPluralNumber()).'</th><th>'._n('Recipient', 'Recipients', Session::getPluralNumber())."</th>";
            echo "<th>"._n('Notification template', 'Notification templates', Session::getPluralNumber())."</th>".
                 "<th>"._n('Email', 'Emails', Session::getPluralNumber())."</th></tr>";

            foreach ($events as $event => $label) {
               self::raiseEvent($event, $item, $options, $label);
            }

         } else  {
            echo "<tr class='tab_bg_2 center'><td colspan='4'>".__('No item to display')."</td></tr>";
         }
      }
      echo "</table></div>";
   }

}
?>
