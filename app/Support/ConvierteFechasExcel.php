<?php

namespace App\Support;

use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

trait ConvierteFechasExcel
{
    protected function fechaExcel($valor): ?string
    {
        if (empty($valor)) {
            return null;
        }

        if ($valor instanceof \DateTimeInterface) {
            return $valor->format('Y-m-d');
        }

        if (is_numeric($valor)) {
            return ExcelDate::excelToDateTimeObject($valor)->format('Y-m-d');
        }

        return $valor;
    }

    protected function numeroExcel($valor): ?int
    {
        if ($valor === null || $valor === '' || $valor === '-') {
            return null;
        }

        if (! is_numeric($valor)) {
            return null;
        }

        return (int) $valor;
    }

    protected function decimalExcel($valor): ?float
    {
        if ($valor === null || $valor === '' || $valor === '-') {
            return null;
        }

        if ($valor instanceof \DateTimeInterface) {
            // La celda contiene una fecha por error, no un decimal — la descartamos
            return null;
        }

        if (! is_numeric($valor)) {
            return null;
        }

        $numero = (float) $valor;

        // Un promedio de tarjetas nunca sería tan alto — si lo es, es un dato corrupto (ej. fecha)
        if ($numero > 100) {
            return null;
        }

        return $numero;
    }
}