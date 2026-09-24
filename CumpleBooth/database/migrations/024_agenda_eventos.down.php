<?php
/** Reversión 018: elimina únicamente la logística de agenda. Respaldarla antes en PROD. */
return static function (PDO $pdo): void { $pdo->exec('DROP TABLE IF EXISTS cc_agenda_eventos'); };
