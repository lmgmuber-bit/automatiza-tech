<?php
/** Revierte la 016. No borra la columna: el monto del descuento ya quedó guardado en
 *  `discount_amount`, así que perder el porcentaje solo pierde el "por qué" del monto. */
return static function (PDO $pdo): void {
    // Sin operación a propósito: quitar una columna con datos de cobro es peor que dejarla.
};
