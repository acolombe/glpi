<?php

class LogSimple extends Log {

   function getTabNameForItem(CommonGLPI $item, $withtemplate=0) {
      $nb = countElementsInTable('glpi_logs',
                                 ['itemtype' => $item->getType(),
                                  'items_id' => $item->getID(),
                                  'id_search_option'=>31]);
      return self::createTabEntry(__('Simplified Logs'), $nb);
   }

}

?>