<?php

      class FollowupNotify extends CommonDBTM {

         static function setNotifyControl() {

            // Set default config => all = 0
            $ur = $gr = $uw = $gw = $ua = $ga = $sa = 0;

            switch ($_POST['_requester_control']) {
               case '_users'                    : $ur = 1;              break;
               case '_groups'                   : $gr = 1;              break;
               case '_users_groups'             : $ur = $gr = 1;        break;
            }
            switch ($_POST['_watcher_control']) {
               case '_users'                    : $uw = 1;              break;
               case '_groups'                   : $gw = 1;              break;
               case '_users_groups'             : $uw = $gw = 1;        break;
            }
            switch ($_POST['_assigned_control']) {
               case '_users'                    : $ua = 1;              break;
               case '_groups'                   : $ga = 1;              break;
               case '_suppliers'                : $sa = 1;              break;
               case '_users_groups'             : $ua = $ga = 1;        break;
               case '_users_suppliers'          : $ua = $sa = 1;        break;
               case '_groups_suppliers'         : $ga = $sa = 1;        break;
               case '_users_groups_suppliers'   : $ua = $ga = $sa = 1;  break;
            }

            // Define "notify_control" array
            $aNotify = array(
               '_users_id_requester'   => $users_requester,
               '_groups_id_requester'  => $groups_requester,
               '_users_id_observer'    => $users_watcher,
               '_groups_id_observer'   => $groups_watcher,
               '_users_id_assign'      => $users_assigned,
               '_groups_id_assign'     => $groups_assigned,
               '_suppliers_id_assign'  => $suppliers_assigned
            );

            return json_encode($aNotify);

         }

         static function getNotifyControl($config=null) {
            return json_decode($config);
         }

         static function getUsersNotifyControl() {
            $user = new User();
            $user->getFromDB(Session::getLoginUserID());
            return self::getNotifyControl($user->getField('notify_control'));
         }

         static function showForm($form_config=null, $form_type=null) {

            // Define lists options
            $options_requester = array(
               '_no'                      => __('No'),
               '_users'                   => __('User').'s',
               '_groups'                  => __('Group').'s',
               '_users_groups'            => __('User').'s '.__('and').' '.__('Group').'s'
            );
            $options_watcher = array(
               '_no'                      => __('No'),
               '_users'                   => __('User').'s',
               '_groups'                  => __('Group').'s',
               '_users_groups'            => __('User').'s '.__('and').' '.__('Group').'s'
            );
            $options_assigned = array(
               '_no'                      => __('No'),
               '_users'                   => __('User').'s',
               '_groups'                  => __('Group').'s',
               '_suppliers'               => __('Supplier').'s',
               '_users_groups'            => __('User').'s '.__('and').' '.__('Group').'s',
               '_users_suppliers'         => __('User').'s '.__('and').' '.__('Supplier').'s',
               '_groups_suppliers'        => __('Group').'s '.__('and').' '.__('Supplier').'s',
               '_users_groups_suppliers' 
                  => __('User').'s, '.__('Group').'s '.__('and').' '.__('Supplier').'s'
            );

            // If updating a followup  : get followup config
            // If in general settings  : get general config
            // If in user settings     : get user config (or general config if not set)
            // Default                 : get general config
            switch ($form_config) {
               case 'followup_update' :
                  $fup = new TicketFollowUp();
                  $fup->getFromDB($_POST['id']);
                  $notify_control = self::getNotifyControl($fup->getField('notify_control'));
                  break;
               case 'general_config' :
                  $default = Config::getConfigurationValues('core', array('notify_control'));
                  $notify_control = self::getNotifyControl($default['notify_control']);
                  break;
               case 'user_config' :
                  $user = new User();
                  $user->getFromDB(Session::getLoginUserID());
                  $notify_control = self::getNotifyControl($user->getField('notify_control'));
                  if (!isset($notify_control)) {
                     $default = Config::getConfigurationValues('core', array('notify_control'));
                     $notify_control = self::getNotifyControl($default['notify_control']);
                  }
                  break;
               default :
                  $default = Config::getConfigurationValues('core', array('notify_control'));
                  $notify_control = self::getNotifyControl($default['notify_control']);
                  break;
            }

            // Set default options selected to prevent exceptions => all = 0
            $r_value = $o_value = $a_value = '_no';

            // Define actors notification booleans
            $ua = $notify_control->_users_id_assign;
            $sa = $notify_control->_suppliers_id_assign;
            $ga = $notify_control->_groups_id_assign;
            $ur = $notify_control->_users_id_requester;
            $gr = $notify_control->_groups_id_requester;
            $uo = $notify_control->_users_id_observer;
            $go = $notify_control->_groups_id_observer;

            // REQUESTERS config
            if       ($ur == 1 && $gr == 1)              { $r_value = '_users_groups';             }
            else if  ($ur == 1)                          { $r_value = '_users';                    }
            else if  ($gr == 1)                          { $r_value = '_groups';                   }
            // OBSERVERS config
            if       ($uo == 1 && $go == 1)              { $o_value = '_users_groups';             }
            else if  ($uo == 1)                          { $o_value = '_users';                    }
            else if  ($go == 1)                          { $o_value = '_groups';                   }
            // ASSIGNED config
            if       ($ua == 1 && $ga == 1 && $sa == 1)  { $a_value = '_users_groups_suppliers';   }
            else if  ($ga == 1 && $sa == 1)              { $a_value = '_groups_suppliers';         }
            else if  ($ua == 1 && $sa == 1)              { $a_value = '_groups_suppliers';         }
            else if  ($ua == 1 && $ga == 1)              { $a_value = '_users_groups';             }
            else if  ($ua == 1)                          { $a_value = '_users';                    }
            else if  ($ga == 1)                          { $a_value = '_groups';                   }
            else if  ($sa == 1)                          { $a_value = '_suppliers';                }

            if ($form_type == 'followup') {
               // Display form in followup
               echo "<tr><th colspan='2'>".__('Notifications')."</th></tr><tr><td colspan='2'>
                     <table width='100%'><tr><td>".__('Assigned')."(s)</td><td>";
               Dropdown::showFromArray('_assigned_control', $opt_a, array('value'=>$a_value));
               echo "</td></tr><tr><td>".__('Requester')."(s)</td><td>";
               Dropdown::showFromArray('_requester_control', $opt_r, array('value'=>$r_value));
               echo "</td></tr><tr><td>".__('Watcher')."(s)</td><td>";
               Dropdown::showFromArray('_watcher_control', $opt_w, array('value'=>$o_value));
               echo "</td></tr></table>";
            }
            else {
               // Display form in configuration editor
               echo "<tr class='headerRow'><th colspan='4'>".__('Notifications')."</th></tr>
                     <tr><td colspan='4'><table class='tab_cadre_fixe'>
                     <tr class='center'><td>".__('Assigned')."(s)</td>
                     <td>".__('Requester')."(s)</td><td>".__('Watcher')."(s)</td>
                     <tr class='center'><td>";
               Dropdown::showFromArray('_assigned_control', $opt_a, array('value'=>$a_value));
               echo "</td><td>";
               Dropdown::showFromArray('_requester_control', $opt_r, array('value'=>$r_value));
               echo "</td><td>";
               Dropdown::showFromArray('_watcher_control', $opt_w, array('value'=>$o_value));
               echo "</td></tr></table>";
            }

         }

      }

?>
