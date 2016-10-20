<?php
/*
 * @version $Id$
 -------------------------------------------------------------------------
 GLPI - Gestionnaire Libre de Parc Informatique
 Copyright (C) 2015-2016 Teclib'.

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
* @since version 0.84
*/

function displayWarrantyValues($values_array) {
   foreach ($values_array as $name => $value) {
      $total                  = $value['warranted']+$value['not_warranted'];
      $percent_warranted      = NULL;
      $percent_not_warranted  = NULL;
      if ($total > 0) {
         $percent_warranted      = "(".number_format($value['warranted']/$value['total']*100, 2)."%)";
         $percent_not_warranted  = "(".number_format($value['not_warranted']/$value['total']*100, 2)."%)";
      }
      echo "<tr><td>$name</td>
                <td class='center'>".$value['total']."</td>
                <td class='center'>".$value['warranted']." $percent_warranted</td>
                <td class='center'>".$value['not_warranted']." $percent_not_warranted</td>
            </tr>";
   }
}

function displayAgeValues($values_array) {
   foreach ($values_array as $name => $values) {
      $total               = $values['total_inf_3']+$values['total_sup_3_inf_5']+$values['total_sup_5'];
      $percent_inf_3       = NULL;
      $percent_sup_3_inf_5 = NULL;
      $percent_sup_5       = NULL;
      if ($total > 0) {
         $percent_inf_3       = "(".number_format($values['total_inf_3']/$total*100, 2)."%)";
         $percent_sup_3_inf_5 = "(".number_format($values['total_sup_3_inf_5']/$total*100, 2)."%)";
         $percent_sup_5       = "(".number_format($values['total_sup_5']/$total*100, 2)."%)";
      }
      echo "<tr><td>$name</td>
            <td class='center'>$total</td>
            <td class='center'>".$values['total_inf_3']." $percent_inf_3</td>
            <td class='center'>".$values['total_sup_3_inf_5']." $percent_sup_3_inf_5</td>
            <td class='center'>".$values['total_sup_5']." $percent_sup_5</td></tr>";
   }
}

function displayStatusesValues($values_array) {
   foreach ($values_array as $name => $value) echo "<tr><td>$name</td><td class='center'>$value</td></tr>";
}

function prepareValues($datas, $object, $title, $values_array) {
   foreach ($values_array as $result) {
      $name = $result['name'];
      unset($result['name']);
      $datas[$object][$title][$name] = $result;
   }
   return $datas;
}

include ('../inc/includes.php');
global $DB;

Session::checkRight("reports", READ);

Html::header(Report::getTypeName(Session::getPluralNumber()), $_SERVER['PHP_SELF'], "tools", "report");

if (!isset($_GET["id"])) {
   $_GET["id"] = 0;
}

Report::title();

$datas = array();

$computers_by_status = $DB->query("SELECT s.name, COUNT(c.id) AS total
                                   FROM glpi_states s
                                   LEFT JOIN glpi_computers c ON c.states_id = s.id
                                   GROUP BY s.id");
$printers_by_status  = $DB->query("SELECT s.name, COUNT(p.id) AS total
                                   FROM glpi_states s
                                   LEFT JOIN glpi_printers p ON p.states_id = s.id
                                   GROUP BY s.id");

$nb_affected_computers     = $DB->fetch_assoc($DB->query("SELECT COUNT(id) as total FROM glpi_computers WHERE users_id != 0"));
$nb_non_affected_computers = $DB->fetch_assoc($DB->query("SELECT COUNT(id) as total FROM glpi_computers WHERE users_id  = 0"));

$affected_computers = $DB->query("
   SELECT ct.name, COUNT(c_3.id) AS total_inf_3, COUNT(c_35.id) AS total_sup_3_inf_5, COUNT(c_5.id) AS total_sup_5
   FROM glpi_computertypes ct
   LEFT JOIN glpi_computers c_3 ON c_3.computertypes_id = ct.id AND c_3.date_creation >= DATE_SUB(NOW(),INTERVAL 3 YEAR) AND c_3.users_id != 0
   LEFT JOIN glpi_computers c_35 ON c_35.computertypes_id = ct.id AND c_35.date_creation BETWEEN DATE_SUB(NOW(),INTERVAL 5 YEAR) AND DATE_SUB(NOW(),INTERVAL 3 YEAR) AND c_35.users_id != 0
   LEFT JOIN glpi_computers c_5 ON c_5.computertypes_id = ct.id AND c_5.date_creation <= DATE_SUB(NOW(),INTERVAL 5 YEAR) AND c_5.users_id != 0
   GROUP BY ct.id");

$non_affected_computers = $DB->query("
   SELECT ct.name, COUNT(c_3.id) AS total_inf_3, COUNT(c_35.id) AS total_sup_3_inf_5, COUNT(c_5.id) AS total_sup_5
   FROM glpi_computertypes ct
   LEFT JOIN glpi_computers c_3 ON c_3.computertypes_id = ct.id AND c_3.date_creation >= DATE_SUB(NOW(),INTERVAL 3 YEAR) AND c_3.users_id = 0
   LEFT JOIN glpi_computers c_35 ON c_35.computertypes_id = ct.id AND c_35.date_creation BETWEEN DATE_SUB(NOW(),INTERVAL 5 YEAR) AND DATE_SUB(NOW(),INTERVAL 3 YEAR) AND c_35.users_id = 0
   LEFT JOIN glpi_computers c_5 ON c_5.computertypes_id = ct.id AND c_5.date_creation <= DATE_SUB(NOW(),INTERVAL 5 YEAR) AND c_5.users_id = 0
   GROUP BY ct.id");

$spared_computers = $DB->query("
   SELECT ct.name, COUNT(c_3.id) AS total_inf_3, COUNT(c_35.id) AS total_sup_3_inf_5, COUNT(c_5.id) AS total_sup_5
   FROM glpi_computertypes ct
   LEFT JOIN glpi_states s ON s.name = 'Spare'
   LEFT JOIN glpi_computers c_3 ON c_3.computertypes_id = ct.id AND c_3.date_creation >= DATE_SUB(NOW(),INTERVAL 3 YEAR) AND c_3.states_id = s.id
   LEFT JOIN glpi_computers c_35 ON c_35.computertypes_id = ct.id AND c_35.date_creation BETWEEN DATE_SUB(NOW(),INTERVAL 5 YEAR) AND DATE_SUB(NOW(),INTERVAL 3 YEAR) AND c_35.states_id = s.id
   LEFT JOIN glpi_computers c_5 ON c_5.computertypes_id = ct.id AND c_5.date_creation <= DATE_SUB(NOW(),INTERVAL 5 YEAR) AND c_5.states_id = s.id
   GROUP BY ct.id");

$temporarily_affected_computers = $DB->query("
   SELECT ct.name, COUNT(c_3.id) AS total_inf_3, COUNT(c_35.id) AS total_sup_3_inf_5, COUNT(c_5.id) AS total_sup_5
   FROM glpi_computertypes ct
   LEFT JOIN glpi_states s ON s.name = 'Affectation provisoire'
   LEFT JOIN glpi_computers c_3 ON c_3.computertypes_id = ct.id AND c_3.date_creation >= DATE_SUB(NOW(),INTERVAL 3 YEAR) AND c_3.states_id = s.id
   LEFT JOIN glpi_computers c_35 ON c_35.computertypes_id = ct.id AND c_35.date_creation BETWEEN DATE_SUB(NOW(),INTERVAL 5 YEAR) AND DATE_SUB(NOW(),INTERVAL 3 YEAR) AND c_35.states_id = s.id
   LEFT JOIN glpi_computers c_5 ON c_5.computertypes_id = ct.id AND c_5.date_creation <= DATE_SUB(NOW(),INTERVAL 5 YEAR) AND c_5.states_id = s.id
   GROUP BY ct.id");

$waiting_trash_computers = $DB->query("
   SELECT ct.name, COUNT(c_3.id) AS total_inf_3, COUNT(c_35.id) AS total_sup_3_inf_5, COUNT(c_5.id) AS total_sup_5
   FROM glpi_computertypes ct
   LEFT JOIN glpi_states s ON s.name = 'En attente de mise au rebut'
   LEFT JOIN glpi_computers c_3 ON c_3.computertypes_id = ct.id AND c_3.date_creation >= DATE_SUB(NOW(),INTERVAL 3 YEAR) AND c_3.states_id = s.id
   LEFT JOIN glpi_computers c_35 ON c_35.computertypes_id = ct.id AND c_35.date_creation BETWEEN DATE_SUB(NOW(),INTERVAL 5 YEAR) AND DATE_SUB(NOW(),INTERVAL 3 YEAR) AND c_35.states_id = s.id
   LEFT JOIN glpi_computers c_5 ON c_5.computertypes_id = ct.id AND c_5.date_creation <= DATE_SUB(NOW(),INTERVAL 5 YEAR) AND c_5.states_id = s.id
   GROUP BY ct.id");

$affected_monitors = $DB->query("
   SELECT mt.name, COUNT(i_w.id)+COUNT(i_nw.id) AS total, COUNT(i_w.id) AS warranted, COUNT(i_nw.id) AS not_warranted
   FROM glpi_monitortypes mt
   LEFT JOIN glpi_monitors m ON  m.monitortypes_id = mt.id AND m.users_id != 0
   LEFT JOIN glpi_infocoms i_w ON i_w.itemtype = 'Monitor' AND i_w.items_id = m.id AND DATE_ADD(i_w.warranty_date, INTERVAL i_w.warranty_duration MONTH) >= NOW()
   LEFT JOIN glpi_infocoms i_nw ON i_nw.itemtype = 'Monitor' AND i_nw.items_id = m.id AND DATE_ADD(i_nw.warranty_date, INTERVAL i_nw.warranty_duration MONTH) < NOW()
   GROUP BY mt.id");

$affected_printers = $DB->query("
   SELECT pt.name, COUNT(i_w.id)+COUNT(i_nw.id) AS total, COUNT(i_w.id) AS warranted, COUNT(i_nw.id) AS not_warranted
   FROM glpi_printertypes pt
   LEFT JOIN glpi_printers p ON  p.printertypes_id = pt.id AND p.users_id != 0
   LEFT JOIN glpi_infocoms i_w ON i_w.itemtype = 'Printer' AND i_w.items_id = p.id AND DATE_ADD(i_w.warranty_date, INTERVAL i_w.warranty_duration MONTH) >= NOW()
   LEFT JOIN glpi_infocoms i_nw ON i_nw.itemtype = 'Printer' AND i_nw.items_id = p.id AND DATE_ADD(i_nw.warranty_date, INTERVAL i_nw.warranty_duration MONTH) < NOW()
   GROUP BY pt.id");

foreach ($computers_by_status as $status) $datas['computers']['status'][$status['name']] = $status['total'];
foreach ($printers_by_status as $status)  $datas['printers']['status'][$status['name']]  = $status['total'];
$total_affected            = $nb_affected_computers['total'];
$total_non_affected        = $nb_non_affected_computers['total'];

$datas = prepareValues($datas, 'computers',  'affected',             $affected_computers);
$datas = prepareValues($datas, 'computers',  'non_affected',         $non_affected_computers);
$datas = prepareValues($datas, 'computers',  'spared',               $spared_computers);
$datas = prepareValues($datas, 'computers',  'temporarily_affected', $temporarily_affected_computers);
$datas = prepareValues($datas, 'computers',  'waiting_trash',        $waiting_trash_computers);
$datas = prepareValues($datas, 'monitors',   'affected',             $affected_monitors);
$datas = prepareValues($datas, 'printers',   'affected',             $affected_printers);

echo "<table class='tab_cadre_fixe'>
         <tr><th colspan='2'>Ordinateurs (par statuts)</th></tr>
         <tr><th>Statut</th><th class='center'>Total</th>";
displayStatusesValues($datas['computers']['status']);
echo "</table>
      <table class='tab_cadre_fixe'>
         <tr><th class='center'>Ordinateurs affectés (par types)</th>
             <th class='center'>Total</th>
             <th class='center'>0-3 ans</th>
             <th class='center'>3-5 ans</th>
             <th class='center'>+ de 5 ans</th></tr>";
displayAgeValues($datas['computers']['affected']);
echo "   <tr><th class='center'>Ordinateurs en attente d'affectation (par types)</th>
             <th class='center'>Total</th>
             <th class='center'>0-3 ans</th>
             <th class='center'>3-5 ans</th>
             <th class='center'>+ de 5 ans</th></tr>";
displayAgeValues($datas['computers']['non_affected']);
echo "   <tr><th class='center'>Ordinateurs en spare</th>
             <th class='center'>Total</th>
             <th class='center'>0-3 ans</th>
             <th class='center'>3-5 ans</th>
             <th class='center'>+ de 5 ans</th></tr>";
displayAgeValues($datas['computers']['spared']);
echo "   <tr><th class='center'>Ordinateurs en affectation provisoire</th>
             <th class='center'>Total</th>
             <th class='center'>0-3 ans</th>
             <th class='center'>3-5 ans</th>
             <th class='center'>+ de 5 ans</th></tr>";
displayAgeValues($datas['computers']['temporarily_affected']);
echo "   <tr><th class='center'>Ordinateurs en attente de mise au rebut</th>
             <th class='center' colspan='4'>Total</th></tr>";
displayAgeValues($datas['computers']['waiting_trash']);
echo "</table>
      <table class='tab_cadre_fixe'>
         <tr><th class='center'>Moniteurs affectés (par types)</th>
             <th class='center'>Total</th>
             <th class='center'>Sous garantie</th>
             <th class='center'>Hors garantie</th></tr>";
displayWarrantyValues($datas['monitors']['affected']);
echo "</table>
      <table class='tab_cadre_fixe'>
         <tr><th colspan='2'>Imprimantes (par statuts)</th></tr>
         <tr><th>Statut</th><th class='center'>Total</th>";
displayStatusesValues($datas['printers']['status']);
echo "</table>
      <table class='tab_cadre_fixe'>
         <tr><th class='center'>Garanties des imprimantes</th>
             <th class='center'>Total</th>
             <th class='center'>Sous garantie</th>
             <th class='center'>Hors garantie</th></tr>";
displayWarrantyValues($datas['printers']['affected']);
echo "</table>";

Html::footer();
?>