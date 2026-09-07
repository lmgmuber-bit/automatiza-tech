<?php
/** Revierte la 015. No borra las columnas de cobro: perder un precio cobrado es peor que
 *  dejar cinco columnas sin uso, y volver a agregarlas es inmediato. */
return static function (PDO $pdo): void {
    $pdo->exec('DROP TABLE IF EXISTS cc_party_contacts');
};
