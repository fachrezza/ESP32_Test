<?php

namespace App\Enums;

enum StatusMesin: string
{
    case Running = 'RUNNING'; // mesin berproduksi normal
    case Mould   = 'MOULD';   // berhenti karena urusan mould / cetakan
    case Setter  = 'SETTER'; // sedang disetel oleh setter
    case Off     = 'OFF';     // mesin mati
}
