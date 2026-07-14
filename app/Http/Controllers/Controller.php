<?php

namespace App\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

abstract class Controller
{
    // V2-H-014: habilita $this->authorize() en todos los controllers.
    // En Laravel 12 el Controller base NO trae este trait por defecto.
    use AuthorizesRequests;
}
